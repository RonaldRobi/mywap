<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Payment;
use App\Services\FeeService;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {}

    // ─── SUPERADMIN ──────────────────────────────────────────────────────────────

    /**
     * Superadmin: view & edit fee amounts per organisation.
     */
    public function feesConfig(): Response
    {
        $organizations = Organization::withCount('members')->orderBy('min_age')->get()->map(fn (Organization $org) => [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'color_theme' => $org->color_theme,
            'min_age' => $org->min_age,
            'max_age' => $org->max_age,
            'fee_amount' => (float) $org->fee_amount,
            'member_count' => $org->members_count,
        ]);

        return Inertia::render('Superadmin/Fees', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Superadmin: update fee amount for a specific organisation.
     */
    public function updateFee(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $organization->update(['fee_amount' => $data['fee_amount']]);

        return back()->with('success', "Fee for {$organization->name} updated to RM {$data['fee_amount']}.");
    }

    /**
     * Superadmin: all transactions across every org, with filters.
     */
    public function allTransactions(Request $request): Response
    {
        $query = Payment::with(['user.organization'])
            ->withoutGlobalScopes()
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('org')) {
            $query->whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                ->where('current_organization_id', (int) $request->org));
        }
        if ($request->filled('type')) {
            $query->where('payable_type', $request->type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('gateway_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->withoutGlobalScopes()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(25)->withQueryString()->through(fn (Payment $p) => [
            'id' => $p->id,
            'user_name' => $p->user?->name,
            'user_email' => $p->user?->email,
            'org_name' => $p->user?->organization?->name,
            'amount' => (float) $p->amount,
            'status' => $p->status,
            'type' => $p->payable_type,
            'description' => $p->description,
            'reference' => $p->reference,
            'gateway' => $p->gateway,
            'created_at' => $p->created_at?->toDateTimeString(),
        ]);

        $organizations = Organization::orderBy('min_age')->get(['id', 'name']);
        $summary = [
            'total' => Payment::withoutGlobalScopes()->sum('amount'),
            'successful' => Payment::withoutGlobalScopes()->where('status', 'successful')->sum('amount'),
            'pending' => Payment::withoutGlobalScopes()->where('status', 'pending')->count(),
        ];

        return Inertia::render('Superadmin/Transactions', [
            'payments' => $payments,
            'organizations' => $organizations,
            'summary' => $summary,
            'filters' => $request->only(['status', 'org', 'type', 'search', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Superadmin: manually update a transaction status.
     */
    public function updateTransactionStatus(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,successful,failed,refunded'],
        ]);

        $payment->update(['status' => $data['status']]);

        return back()->with('success', "Transaction #{$payment->id} marked as {$data['status']}.");
    }

    // ─── ADMIN ───────────────────────────────────────────────────────────────────

    /**
     * Admin: read-only transaction list scoped to their own organisation.
     */
    public function orgTransactions(Request $request): Response
    {
        $user = $request->user()->load('organization');

        $query = Payment::with('user')
            ->whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id))
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('gateway_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->withoutGlobalScopes()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(25)->withQueryString()->through(fn (Payment $p) => [
            'id' => $p->id,
            'user_name' => $p->user?->name,
            'user_email' => $p->user?->email,
            'amount' => (float) $p->amount,
            'status' => $p->status,
            'type' => $p->payable_type,
            'description' => $p->description,
            'reference' => $p->reference,
            'gateway' => $p->gateway,
            'created_at' => $p->created_at?->toDateTimeString(),
        ]);

        $summary = [
            'total_collected' => Payment::whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id))
                ->where('status', 'successful')
                ->sum('amount'),
            'pending_count' => Payment::whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id))
                ->where('status', 'pending')
                ->count(),
        ];

        return Inertia::render('Admin/Transactions', [
            'payments' => $payments,
            'organization' => ['name' => $user->organization?->name],
            'summary' => $summary,
            'filters' => $request->only(['status', 'search', 'date_from', 'date_to']),
        ]);
    }

    // ─── MEMBER ──────────────────────────────────────────────────────────────────

    /**
     * Member: initiate a membership fee payment via BayarCash or dummy fallback.
     */
    public function payFee(Request $request, FeeService $feeService): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user()->load('organization');

        if (! $user->hasRole('Member')) {
            abort(403);
        }

        $year = now()->year;

        if ($feeService->isLifeMember($user)) {
            return back()->with('error', 'Anda adalah ahli seumur hidup — tidak perlu bayar yuran.');
        }

        if ($feeService->isExempted($user)) {
            return back()->with('error', 'Yuran anda telah dikecualikan — tidak perlu bayar yuran.');
        }

        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $status = $feeService->getStatus($user, $year);
        if ($status['status'] === 'active') {
            return back()->with('error', 'Yuran keahlian anda untuk tahun ini sudah dibayar.');
        }

        $org = $user->organization;
        $useGateway = $this->gateways->isLive($org);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'membership_fee',
            'payable_id' => null,
            'amount' => $feeAmount,
            'status' => $useGateway ? 'pending' : 'successful',
            'reference' => $useGateway ? 'FEE-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Yuran keahlian {$org?->name} {$year}",
            'gateway' => $this->gateways->gatewayFor($org),
            'organization_id' => $org?->id,
        ]);

        if ($useGateway && $org) {
            $url = $this->gateways->createPaymentRedirect(
                $org,
                $payment,
                $user->name,
                $user->email,
                $user->phone ?? null,
                "Yuran keahlian {$org?->name} {$year}",
            );

            if ($url) {
                return Inertia::location($url);
            }

            $payment->update(['status' => 'failed']);

            return back()->with('error', 'Pembayaran gagal diproses. Sila cuba lagi.');
        }

        $feeService->markAsPaid($user, $year, $feeAmount, $payment->id);

        return back()->with('success', "Pembayaran yuran RM {$feeAmount} berjaya! (Mod dummy — gateway sebenar akan disambung kemudian)");
    }

    // ─── EXPORT ─────────────────────────────────────────────────────────────────

    public function exportCsv(Request $request): StreamedResponse
    {
        $isSuperadmin = $request->user()->hasRole('Superadmin');
        $user = $request->user()->load('organization');

        $query = Payment::with('user');

        if ($isSuperadmin) {
            $query->withoutGlobalScopes();

            if ($request->filled('org')) {
                $query->whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                    ->where('current_organization_id', (int) $request->org));
            }
        } else {
            $query->whereHas('user', fn ($q) => $q->withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('payable_type', $request->type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->withoutGlobalScopes()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->limit(5000)->get();

        $filename = 'transaksi-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($payments, $isSuperadmin) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['#', 'Nama', 'Emel', 'Pertubuhan', 'Jenis', 'Penerangan', 'Amaun (RM)', 'Status', 'Rujukan', 'Gateway', 'Tarikh']);

            foreach ($payments as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->user?->name ?? '—',
                    $p->user?->email ?? '—',
                    $isSuperadmin ? ($p->user?->organization?->name ?? '—') : '',
                    $this->typeLabel($p->payable_type),
                    $p->description ?? '—',
                    number_format((float) $p->amount, 2, '.', ''),
                    $this->statusLabel($p->status),
                    $p->reference ?? '—',
                    $p->gateway ?? '—',
                    $p->created_at?->toDateTimeString() ?? '—',
                ]);
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'membership_fee' => 'Yuran Keahlian',
            'infaq_donation' => 'Infaq / Sumbangan',
            'order' => 'Pesanan / Produk',
            default => $type ?? '—',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'successful' => 'Berjaya',
            'pending' => 'Menunggu',
            'failed' => 'Gagal',
            'refunded' => 'Dipulangkan',
            default => $status,
        };
    }
}
