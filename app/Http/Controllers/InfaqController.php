<?php

namespace App\Http\Controllers;

use App\Models\Infaq;
use App\Models\InfaqDonation;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\BayarCashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InfaqController extends Controller
{
    public function __construct(
        protected BayarCashService $bayarCashService,
    ) {}

    // ─── Superadmin: list all infaq ─────────────────────────────────────────

    public function manage(): Response
    {
        $items = Infaq::query()
            ->with('organization:id,name,slug')
            ->withCount('donations')
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Infaq $infaq) => [
                'id'               => $infaq->id,
                'title'            => $infaq->title,
                'slug'             => $infaq->slug,
                'description'      => $infaq->description,
                'image_path'       => $infaq->image_path,
                'type'             => $infaq->type,
                'allow_recurring'  => $infaq->allow_recurring,
                'target_amount'    => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'is_active'        => $infaq->is_active,
                'display_order'    => $infaq->display_order,
                'organization_id'  => $infaq->organization_id,
                'organization_name'=> $infaq->organization?->name ?? 'Global',
                'donations_count'  => $infaq->donations_count,
                'public_url'       => $infaq->public_url,
            ]);

        return Inertia::render('Superadmin/InfaqManage', [
            'organizations' => Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug']),
            'infaqItems'    => $items,
        ]);
    }

    // ─── Superadmin: create ──────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'type'            => ['required', 'in:one_off,progress'],
            'allow_recurring' => ['nullable', 'boolean'],
            'target_amount'   => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'is_active'       => ['nullable', 'boolean'],
            'display_order'   => ['nullable', 'integer', 'min:1', 'max:9999'],
            'infaq_image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('infaq_image')) {
            $storedPath = $request->file('infaq_image')->store('infaq', 'public');
            $imagePath  = '/storage/' . ltrim($storedPath, '/');
        }

        Infaq::create([
            'organization_id' => $data['organization_id'] ?? null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'image_path'      => $imagePath,
            'type'            => $data['type'],
            'allow_recurring' => (bool) ($data['allow_recurring'] ?? false),
            'target_amount'   => $data['type'] === 'progress' ? ($data['target_amount'] ?? null) : null,
            'collected_amount'=> 0,
            'is_active'       => (bool) ($data['is_active'] ?? true),
            'display_order'   => (int) ($data['display_order'] ?? 1),
        ]);

        return back()->with('success', 'Infaq berjaya dicipta.');
    }

    // ─── Superadmin: update ──────────────────────────────────────────────────

    public function update(Request $request, Infaq $infaq): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'type'            => ['required', 'in:one_off,progress'],
            'allow_recurring' => ['nullable', 'boolean'],
            'target_amount'   => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'is_active'       => ['nullable', 'boolean'],
            'display_order'   => ['nullable', 'integer', 'min:1', 'max:9999'],
            'infaq_image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ]);

        $imagePath = $infaq->image_path;

        if ($request->hasFile('infaq_image')) {
            // Delete old image
            if ($imagePath) {
                $oldRel = ltrim(str_replace('/storage/', '', parse_url($imagePath, PHP_URL_PATH) ?? ''), '/');
                if ($oldRel && Storage::disk('public')->exists($oldRel)) {
                    Storage::disk('public')->delete($oldRel);
                }
            }
            $newPath   = $request->file('infaq_image')->store('infaq', 'public');
            $imagePath = '/storage/' . ltrim($newPath, '/');
        }

        $infaq->update([
            'organization_id' => $data['organization_id'] ?? null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'image_path'      => $imagePath,
            'type'            => $data['type'],
            'allow_recurring' => (bool) ($data['allow_recurring'] ?? false),
            'target_amount'   => $data['type'] === 'progress' ? ($data['target_amount'] ?? null) : null,
            'is_active'       => (bool) ($data['is_active'] ?? false),
            'display_order'   => (int) ($data['display_order'] ?? 1),
        ]);

        return back()->with('success', 'Infaq berjaya dikemas kini.');
    }

    // ─── Superadmin: delete ──────────────────────────────────────────────────

    public function destroy(Infaq $infaq): RedirectResponse
    {
        if ($infaq->image_path) {
            $oldRel = ltrim(str_replace('/storage/', '', parse_url($infaq->image_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldRel && Storage::disk('public')->exists($oldRel)) {
                Storage::disk('public')->delete($oldRel);
            }
        }

        $infaq->delete();

        return back()->with('success', 'Infaq berjaya dipadam.');
    }

    // ─── Superadmin: seed demo data ──────────────────────────────────────────

    public function seedDemo(): RedirectResponse
    {
        $seeds = [
            [
                'title'         => 'Infaq Masjid Al-Iman',
                'description'   => 'Bantu kami membina kemudahan solat yang lebih selesa untuk komuniti.',
                'type'          => 'progress',
                'target_amount' => 50000,
                'collected_amount' => 23750,
                'display_order' => 1,
            ],
            [
                'title'         => 'Infaq Anak Yatim Ramadan',
                'description'   => 'Sumbangan untuk anak-anak yatim sempena bulan Ramadan yang mulia.',
                'type'          => 'one_off',
                'target_amount' => null,
                'collected_amount' => 8100,
                'display_order' => 2,
            ],
            [
                'title'         => 'Dana Pendidikan Islam',
                'description'   => 'Tajaan kelas Quran & fardhu ain untuk pelajar kurang berkemampuan.',
                'type'          => 'progress',
                'target_amount' => 15000,
                'collected_amount' => 9600,
                'display_order' => 3,
            ],
            [
                'title'         => 'Infaq Buku & Pustaka',
                'description'   => 'Sumbangkan untuk pengembangan koleksi buku perpustakaan komuniti.',
                'type'          => 'progress',
                'target_amount' => 8000,
                'collected_amount' => 4200,
                'display_order' => 4,
            ],
            [
                'title'         => 'Infaq Am — Derma Bebas',
                'description'   => 'Sumbangan am untuk kegunaan operasi pertubuhan.',
                'type'          => 'one_off',
                'target_amount' => null,
                'collected_amount' => 3300,
                'display_order' => 5,
            ],
        ];

        $palettes = [
            ['from' => '#059669', 'to' => '#065f46', 'text' => '#d1fae5'],
            ['from' => '#6366f1', 'to' => '#3730a3', 'text' => '#e0e7ff'],
            ['from' => '#f59e0b', 'to' => '#b45309', 'text' => '#fef3c7'],
            ['from' => '#0ea5e9', 'to' => '#0369a1', 'text' => '#e0f2fe'],
            ['from' => '#ec4899', 'to' => '#9d174d', 'text' => '#fce7f3'],
        ];

        foreach ($seeds as $i => $seed) {
            $palette = $palettes[$i % count($palettes)];
            $shortTitle = mb_substr($seed['title'], 0, 30);

            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$palette['from']};stop-opacity:1"/>
      <stop offset="100%" style="stop-color:{$palette['to']};stop-opacity:1"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#bg)"/>
  <circle cx="700" cy="80" r="180" fill="white" fill-opacity="0.06"/>
  <circle cx="120" cy="520" r="140" fill="white" fill-opacity="0.06"/>
  <text x="60" y="200" font-family="Arial, sans-serif" font-size="26" font-weight="bold" fill="{$palette['text']}" opacity="0.8">INFAQ</text>
  <text x="60" y="260" font-family="Arial, sans-serif" font-size="38" font-weight="900" fill="white">{$shortTitle}</text>
  <text x="60" y="320" font-family="Arial, sans-serif" font-size="20" fill="white" opacity="0.75">Derma &amp; Sumbangan</text>
  <rect x="60" y="380" width="120" height="4" rx="2" fill="white" fill-opacity="0.5"/>
</svg>
SVG;

            $filename  = 'infaq/demo_infaq_' . ($i + 1) . '.svg';
            Storage::disk('public')->put($filename, $svg);
            $imagePath = '/storage/' . ltrim($filename, '/');

            Infaq::updateOrCreate(
                ['title' => $seed['title']],
                array_merge($seed, [
                    'organization_id' => null,
                    'image_path'      => $imagePath,
                    'is_active'       => true,
                ])
            );
        }

        return back()->with('success', 'Demo infaq berjaya dijana (' . count($seeds) . ' item).');
    }

    // ─── Public: list page ────────────────────────────────────────────────
    
    public function index()
    {
        $infaqs = Infaq::query()
            ->where('is_active', true)
            ->with('organization:id,name')
            ->latest()
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'target_amount' => (float) $item->target_amount,
                'collected_amount' => (float) $item->collected_amount,
                'progress_percent' => $item->progress_percent,
                'image_path' => $item->image_path,
                'public_url' => $item->public_url,
                'organization_name' => $item->organization?->name,
                'days_running' => max(1, (int) abs(now()->diffInDays($item->created_at))),
            ]);

        return Inertia::render('Infaq/Index', [
            'infaqs' => $infaqs
        ]);
    }

    // ─── Member: detail page ────────────────────────────────────────────────

    public function show(Request $request, $year, $month, $day, Infaq $infaq): Response
    {
        $user = $request->user();
        if ($user) {
            $user->load('organization');
        }

        $isVisible = (bool) $infaq->is_active;

        abort_unless($isVisible, 404);

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

        $infaq->load('organization:id,name,slug,logo_path,color_theme');

        $related = Infaq::query()
            ->where('is_active', true)
            ->where('id', '!=', $infaq->id)
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'image_path' => $item->image_path,
                'progress_percent' => $item->progress_percent,
                'collected_amount' => $item->collected_amount,
                'target_amount' => $item->target_amount,
                'public_url' => $item->public_url,
            ]);

        return Inertia::render('Infaq/Show', [
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
                'organization_name' => $infaq->organization?->name ?? 'Pengurusan myWAP',
                'organization_slug' => $infaq->organization?->slug,
                'organization_logo' => $infaq->organization?->logo_path,
                'organization_color' => $infaq->organization?->color_theme,
                'total_donors' => $totalDonors,
                'days_running' => $daysRunning,
                'public_url' => $infaq->public_url,
                'year' => $year,
                'month' => $month,
                'day' => $day,
            ],
            'recentDonations' => $recentDonations,
            'relatedInfaqs' => $related,
        ]);
    }

    // ─── Member: submit a donation ───────────────────────────────────────────

    public function donateForm(Request $request, $year, $month, $day, Infaq $infaq): Response
    {
        abort_unless((bool) $infaq->is_active, 404);

        $infaq->load('organization:id,name');

        return Inertia::render('Infaq/Donate', [
            'infaq' => [
                'id' => $infaq->id,
                'title' => $infaq->title,
                'image_path' => $infaq->image_path,
                'allow_recurring' => $infaq->allow_recurring,
                'public_url' => $infaq->public_url,
            ]
        ]);
    }

    public function donate(Request $request, $year, $month, $day, Infaq $infaq): RedirectResponse
    {
        $isRecurring = $request->boolean('is_recurring') && $infaq->allow_recurring;

        $rules = [
            'amount'         => ['required', 'numeric', 'min:1', 'max:99999'],
            'donor_name'     => ['required', 'string', 'max:255'],
            'donor_phone'    => ['required', 'string', 'max:50'],
            'donor_email'    => ['required', 'email', 'max:255'],
            'prayer_message' => ['nullable', 'string', 'max:400'],
            'is_anonymous'   => ['boolean'],
            'wants_updates'  => ['boolean'],
        ];

        if ($isRecurring) {
            $rules['frequency'] = ['required', 'in:monthly,weekly,yearly'];
        }

        $data = $request->validate($rules);

        $user = $request->user();
        $org = $infaq->organization_id ? Organization::find($infaq->organization_id) : null;
        $useBayarCash = $org && $org->hasBayarCashConfig();

        if ($isRecurring && !$useBayarCash) {
            return back()->with('error', 'Pembayaran recurring memerlukan gateway BayarCash.');
        }

        $frequencyMap = [
            'monthly' => \Webimpian\BayarcashSdk\FpxDirectDebit::MODE_MONTHLY,
            'weekly'  => \Webimpian\BayarcashSdk\FpxDirectDebit::MODE_WEEKLY,
            'yearly'  => \Webimpian\BayarcashSdk\FpxDirectDebit::MODE_YEARLY,
        ];

        $donation = DB::transaction(function () use ($infaq, $user, $data, $org, $useBayarCash, $isRecurring, $frequencyMap) {
            $ref = 'INFQ-' . strtoupper(Str::random(10));

            $nextBilling = null;
            if ($isRecurring) {
                $nextBilling = match ($data['frequency']) {
                    'monthly' => now()->addMonth()->toDateString(),
                    'weekly'  => now()->addWeek()->toDateString(),
                    'yearly'  => now()->addYear()->toDateString(),
                };
            }

            $donation = InfaqDonation::create([
                'infaq_id'         => $infaq->id,
                'user_id'          => $user?->id,
                'amount'           => $data['amount'],
                'reference'        => $ref,
                'status'           => $useBayarCash ? 'pending' : 'confirmed',
                'donor_name'       => $data['donor_name'],
                'donor_phone'      => $data['donor_phone'],
                'donor_email'      => $data['donor_email'],
                'prayer_message'   => $data['prayer_message'] ?? null,
                'is_anonymous'     => $data['is_anonymous'] ?? false,
                'wants_updates'    => $data['wants_updates'] ?? false,
                'is_recurring'     => $isRecurring,
                'frequency'        => $isRecurring ? $frequencyMap[$data['frequency']] : null,
                'next_billing_date'=> $nextBilling,
                'recurring_status' => $isRecurring ? 'pending' : null,
            ]);

            $paymentRef = ($isRecurring ? 'DDR-' : 'INFQ-') . strtoupper(Str::random(8));

            $payment = Payment::create([
                'user_id'         => $user?->id,
                'payable_type'    => 'infaq_donation',
                'payable_id'      => $donation->id,
                'amount'          => $data['amount'],
                'status'          => $useBayarCash ? 'pending' : 'successful',
                'reference'       => $paymentRef,
                'description'     => $isRecurring
                    ? "Donasi berkala ({$data['frequency']}): {$infaq->title}"
                    : "Donasi: {$infaq->title}",
                'gateway'         => $useBayarCash ? 'bayarcash' : 'dummy',
                'organization_id' => $org?->id,
            ]);

            if (!$useBayarCash) {
                $infaq->increment('collected_amount', $data['amount']);
            }

            return $donation;
        });

        if ($useBayarCash && $org) {
            $payment = Payment::where('payable_type', 'infaq_donation')
                ->where('payable_id', $donation->id)
                ->first();

            if ($isRecurring) {
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
                $url = $this->bayarCashService->createPaymentIntent(
                    $org,
                    $payment,
                    $data['donor_name'],
                    $data['donor_email'],
                    $data['donor_phone'],
                );
            }

            if ($url) {
                return redirect()->away($url);
            }

            $payment->update(['status' => 'failed']);
            return back()->with('error', 'Pembayaran gagal diproses. Sila cuba lagi.');
        }

        return redirect()->route('infaq.success', [
            'year'  => $year,
            'month' => $month,
            'day'   => $day,
            'infaq' => $infaq->slug,
        ]);
    }

    public function qrCode(Infaq $infaq)
    {
        $shortUrl = route('infaq.short', ['infaq' => $infaq->slug]);

        return response(
            QrCode::format('png')->size(300)->margin(1)->generate($shortUrl),
            200,
            ['Content-Type' => 'image/png']
        );
    }

    public function donors(Infaq $infaq): Response
    {
        $donations = InfaqDonation::query()
            ->where('infaq_id', $infaq->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InfaqDonation $d) => [
                'id'                => $d->id,
                'donor_name'        => $d->is_anonymous ? 'Hamba Allah' : ($d->donor_name ?? $d->user?->name ?? 'Tanpa Nama'),
                'donor_email'       => $d->donor_email,
                'donor_phone'       => $d->donor_phone,
                'amount'            => (float) $d->amount,
                'status'            => $d->status,
                'reference'         => $d->reference,
                'is_anonymous'      => $d->is_anonymous,
                'is_recurring'      => $d->is_recurring,
                'frequency'         => $d->frequency,
                'recurring_status'  => $d->recurring_status,
                'prayer_message'    => $d->prayer_message,
                'wants_updates'     => $d->wants_updates,
                'created_at'        => $d->created_at->format('d M Y, h:i A'),
                'created_at_iso'    => $d->created_at->toISOString(),
            ]);

        return Inertia::render('Superadmin/InfaqDonors', [
            'infaq' => [
                'id'               => $infaq->id,
                'title'            => $infaq->title,
                'slug'             => $infaq->slug,
                'type'             => $infaq->type,
                'target_amount'    => (float) $infaq->target_amount,
                'collected_amount' => (float) $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'organization_name'=> $infaq->organization?->name ?? 'Global',
                'public_url'       => $infaq->public_url,
            ],
            'donations' => $donations,
        ]);
    }

    public function success(Request $request, $year, $month, $day, Infaq $infaq): Response
    {
        return Inertia::render('Infaq/Success', [
            'infaq' => [
                'id' => $infaq->id,
                'slug' => $infaq->slug,
                'title' => $infaq->title,
                'public_url' => $infaq->public_url,
                'year' => $year,
                'month' => $month,
                'day' => $day,
            ]
        ]);
    }
}
