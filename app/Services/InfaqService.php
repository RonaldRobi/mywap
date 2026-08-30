<?php

namespace App\Services;

use App\Models\Infaq;
use App\Models\InfaqDonation;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webimpian\BayarcashSdk\FpxDirectDebit;

/**
 * InfaqService
 *
 * Logik tunggal untuk domain Infaq — dikongsi oleh InfaqController (web/Inertia)
 * dan Api\V1\InfaqController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §4.
 */
class InfaqService
{
    public function __construct(
        protected BayarCashService $bayarCashService,
        protected DonorService $donorService,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Payload halaman/endpoint senarai infaq (web: Infaq/Index).
     */
    public function index(): array
    {
        $infaqs = Infaq::query()
            ->where('is_active', true)
            ->with('organization:id,name,slug')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'type' => $item->type,
                'target_amount' => (float) $item->target_amount,
                'collected_amount' => (float) $item->collected_amount,
                'progress_percent' => $item->progress_percent,
                'image_path' => $item->image_path,
                'public_url' => $item->public_url,
                'is_external' => $item->is_external,
                'external_url' => $item->external_url,
                'organization_id' => $item->organization_id,
                'organization_name' => $item->organization?->name,
                'organization_slug' => $item->organization?->slug,
                'days_running' => max(1, (int) abs(now()->diffInDays($item->created_at))),
            ]);

        // Only list organizations that actually have at least one active campaign,
        // so the filter never shows an empty tab.
        $orgIdsWithCampaigns = $infaqs->pluck('organization_id')->filter()->unique();

        $organizations = Organization::query()
            ->whereIn('id', $orgIdsWithCampaigns)
            ->orderBy('sort_order')
            ->orderBy('min_age')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
            ])
            ->values();

        return [
            'infaqs' => $infaqs,
            'organizations' => $organizations,
            'hasGlobal' => $infaqs->whereNull('organization_id')->isNotEmpty(),
        ];
    }

    /**
     * Payload penuh untuk halaman/endpoint show infaq.
     */
    public function showDetail(Infaq $infaq): array
    {
        $infaq->load('organization:id,name,slug,logo_path,color_theme');

        $recentDonations = InfaqDonation::query()
            ->where('infaq_id', $infaq->id)
            ->where('status', 'confirmed')
            ->with('user:id,name')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn (InfaqDonation $donation) => [
                'id' => $donation->id,
                'amount' => (float) $donation->amount,
                'created_at' => $donation->created_at->diffForHumans(),
                'donor_name' => $donation->is_anonymous ? 'Hamba Allah' : ($donation->donor_name ?? $donation->user?->name ?? 'Hamba Allah'),
                'prayer_message' => $donation->prayer_message,
            ]);

        $totalDonors = InfaqDonation::query()
            ->where('infaq_id', $infaq->id)
            ->where('status', 'confirmed')
            ->count();

        $daysRunning = max(1, (int) abs(now()->diffInDays($infaq->created_at)));

        $related = Infaq::query()
            ->where('is_active', true)
            ->where('id', '!=', $infaq->id)
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'image_path' => $item->image_path,
                'progress_percent' => $item->progress_percent,
                'collected_amount' => $item->collected_amount,
                'target_amount' => $item->target_amount,
                'public_url' => $item->public_url,
            ]);

        return [
            'infaq' => [
                'id' => $infaq->id,
                'slug' => $infaq->slug,
                'title' => $infaq->title,
                'description' => $infaq->description,
                'image_path' => $infaq->image_path,
                'type' => $infaq->type,
                'allow_recurring' => $infaq->allow_recurring,
                'target_amount' => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'is_external' => $infaq->is_external,
                'external_url' => $infaq->external_url,
                'organization_name' => $infaq->organization?->name ?? 'Pengurusan myWAP',
                'organization_slug' => $infaq->organization?->slug,
                'organization_logo' => $infaq->organization?->logo_path,
                'organization_color' => $infaq->organization?->color_theme,
                'total_donors' => $totalDonors,
                'days_running' => $daysRunning,
                'public_url' => $infaq->public_url,
                'year' => $infaq->year,
                'month' => $infaq->month,
                'day' => $infaq->day,
            ],
            'recentDonations' => $recentDonations,
            'relatedInfaqs' => $related,
        ];
    }

    /**
     * Proses donasi. Kembalikan array hasil:
     *   ['status' => 'redirect', 'payment_url' => $url]   — gateway / external
     *   ['status' => 'success',  'donation' => [...]]     — tanpa gateway
     *   ['status' => 'error',    'message' => $msg]       — kegagalan proses
     */
    public function donate(Request $request, Infaq $infaq, ?User $user): array
    {
        // External campaigns are handled entirely on the external (DOKU) page.
        if ($infaq->is_external) {
            return ['status' => 'redirect', 'payment_url' => $infaq->external_url];
        }

        $isRecurring = $request->boolean('is_recurring') && $infaq->allow_recurring;

        $rules = [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999'],
            'donor_name' => ['required', 'string', 'max:255'],
            'donor_phone' => ['required', 'string', 'max:50'],
            'donor_email' => ['required', 'email', 'max:255'],
            'prayer_message' => ['nullable', 'string', 'max:400'],
            'is_anonymous' => ['boolean'],
            'wants_updates' => ['boolean'],
        ];

        if ($isRecurring) {
            $rules['frequency'] = ['required', 'in:monthly,weekly,yearly'];
        }

        $data = $request->validate($rules);

        $org = $infaq->organization_id ? Organization::find($infaq->organization_id) : null;
        $useGateway = $this->gateways->isLive($org);

        // Recurring / Direct Debit is only supported by BayarCash FPX Direct Debit.
        if ($isRecurring && ! ($org && $org->supportsRecurring())) {
            return ['status' => 'error', 'message' => 'Sumbangan berkala hanya tersedia untuk gateway BayarCash. Sila pilih sumbangan sekali sahaja.'];
        }

        $frequencyMap = [
            'monthly' => FpxDirectDebit::MODE_MONTHLY,
            'weekly' => FpxDirectDebit::MODE_WEEKLY,
            'yearly' => FpxDirectDebit::MODE_YEARLY,
        ];

        $gatewayName = $this->gateways->gatewayFor($org);

        $donation = DB::transaction(function () use ($infaq, $user, $data, $org, $useGateway, $gatewayName, $isRecurring, $frequencyMap) {
            $ref = 'INFQ-'.strtoupper(Str::random(10));

            $nextBilling = null;
            if ($isRecurring) {
                $nextBilling = match ($data['frequency']) {
                    'monthly' => now()->addMonth()->toDateString(),
                    'weekly' => now()->addWeek()->toDateString(),
                    'yearly' => now()->addYear()->toDateString(),
                };
            }

            $donation = InfaqDonation::create([
                'infaq_id' => $infaq->id,
                'user_id' => $user?->id,
                'amount' => $data['amount'],
                'reference' => $ref,
                'status' => $useGateway ? 'pending' : 'confirmed',
                'donor_name' => $data['donor_name'],
                'donor_phone' => $data['donor_phone'],
                'donor_email' => $data['donor_email'],
                'prayer_message' => $data['prayer_message'] ?? null,
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'wants_updates' => $data['wants_updates'] ?? false,
                'is_recurring' => $isRecurring,
                'frequency' => $isRecurring ? $frequencyMap[$data['frequency']] : null,
                'next_billing_date' => $nextBilling,
                'recurring_status' => $isRecurring ? 'pending' : null,
            ]);

            // Deduplicate donor
            $donor = $this->donorService->findOrCreate($donation);
            $donation->update(['donor_id' => $donor->id]);
            if (! $useGateway && $donation->status === 'confirmed') {
                $this->donorService->incrementDonor($donor, (float) $data['amount']);
            }

            $paymentRef = ($isRecurring ? 'DDR-' : 'INFQ-').strtoupper(Str::random(8));

            $payment = Payment::create([
                'user_id' => $user?->id,
                'payable_type' => 'infaq_donation',
                'payable_id' => $donation->id,
                'amount' => $data['amount'],
                'status' => $useGateway ? 'pending' : 'successful',
                'reference' => $paymentRef,
                'description' => $isRecurring
                    ? "Donasi berkala ({$data['frequency']}): {$infaq->title}"
                    : "Donasi: {$infaq->title}",
                'gateway' => $gatewayName,
                'organization_id' => $org?->id,
            ]);

            if (! $useGateway) {
                $infaq->increment('collected_amount', $data['amount']);
            }

            return $donation;
        });

        if ($useGateway && $org) {
            $payment = Payment::where('payable_type', 'infaq_donation')
                ->where('payable_id', $donation->id)
                ->first();

            if ($isRecurring) {
                // Recurring is BayarCash-only (guarded above via supportsRecurring()).
                $url = $this->bayarCashService->createDirectDebitEnrollment(
                    $org,
                    $donation,
                    $payment,
                    $data['donor_name'],
                    $data['donor_email'],
                    $data['donor_phone'],
                    $frequencyMap[$data['frequency']],
                );
            } else {
                // One-time donation: route through whichever gateway the org uses.
                $url = $this->gateways->createPaymentRedirect(
                    $org,
                    $payment,
                    $data['donor_name'],
                    $data['donor_email'],
                    $data['donor_phone'],
                    "Donasi: {$infaq->title}",
                );
            }

            if ($url) {
                return ['status' => 'redirect', 'payment_url' => $url];
            }

            $payment->update(['status' => 'failed']);

            return ['status' => 'error', 'message' => 'Pembayaran gagal diproses. Sila cuba lagi.'];
        }

        return ['status' => 'success', 'donation' => $this->serializeDonation($donation)];
    }

    /**
     * Serialize satu InfaqDonation kepada bentuk untuk API/response success.
     */
    public function serializeDonation(InfaqDonation $donation): array
    {
        $donation->loadMissing('infaq', 'user:id,name');

        return [
            'id' => $donation->id,
            'reference' => $donation->reference,
            'amount' => (float) $donation->amount,
            'status' => $donation->status,
            'donor_name' => $donation->is_anonymous ? 'Hamba Allah' : ($donation->donor_name ?? $donation->user?->name ?? 'Hamba Allah'),
            'is_anonymous' => (bool) $donation->is_anonymous,
            'is_recurring' => (bool) $donation->is_recurring,
            'frequency' => $donation->frequency,
            'prayer_message' => $donation->prayer_message,
            'created_at' => $donation->created_at?->toISOString(),
            'infaq' => [
                'id' => $donation->infaq->id,
                'title' => $donation->infaq->title,
                'slug' => $donation->infaq->slug,
                'public_url' => $donation->infaq->public_url,
            ],
        ];
    }
}
