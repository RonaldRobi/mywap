<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    // ─── SUPERADMIN ──────────────────────────────────────────────────────────────

    /**
     * Superadmin: view & edit fee amounts per organisation.
     */
    public function feesConfig(): Response
    {
        $organizations = Organization::orderBy('min_age')->get()->map(fn (Organization $org) => [
            'id'         => $org->id,
            'name'       => $org->name,
            'slug'       => $org->slug,
            'color_theme'=> $org->color_theme,
            'min_age'    => $org->min_age,
            'max_age'    => $org->max_age,
            'fee_amount' => (float) $org->fee_amount,
            'member_count' => $org->members()->count(),
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
                ->where('current_organization_id', $request->org));
        }
        if ($request->filled('type')) {
            $query->where('payable_type', $request->type);
        }

        $payments = $query->paginate(25)->through(fn (Payment $p) => [
            'id'          => $p->id,
            'user_name'   => $p->user?->name,
            'user_email'  => $p->user?->email,
            'org_name'    => $p->user?->organization?->name,
            'amount'      => (float) $p->amount,
            'status'      => $p->status,
            'type'        => $p->payable_type,
            'description' => $p->description,
            'reference'   => $p->reference,
            'created_at'  => $p->created_at?->toDateTimeString(),
        ]);

        $organizations = Organization::orderBy('min_age')->get(['id', 'name']);
        $summary = [
            'total'      => Payment::withoutGlobalScopes()->sum('amount'),
            'successful' => Payment::withoutGlobalScopes()->where('status', 'successful')->sum('amount'),
            'pending'    => Payment::withoutGlobalScopes()->where('status', 'pending')->count(),
        ];

        return Inertia::render('Superadmin/Transactions', [
            'payments'      => $payments,
            'organizations' => $organizations,
            'summary'       => $summary,
            'filters'       => $request->only(['status', 'org', 'type']),
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

        $payments = $query->paginate(25)->through(fn (Payment $p) => [
            'id'          => $p->id,
            'user_name'   => $p->user?->name,
            'user_email'  => $p->user?->email,
            'amount'      => (float) $p->amount,
            'status'      => $p->status,
            'type'        => $p->payable_type,
            'description' => $p->description,
            'reference'   => $p->reference,
            'created_at'  => $p->created_at?->toDateTimeString(),
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
            'payments'     => $payments,
            'organization' => ['name' => $user->organization?->name],
            'summary'      => $summary,
            'filters'      => $request->only(['status']),
        ]);
    }

    // ─── MEMBER ──────────────────────────────────────────────────────────────────

    /**
     * Member: initiate a (dummy) membership fee payment.
     * In production this would redirect to a payment gateway.
     */
    public function payFee(Request $request): RedirectResponse
    {
        $user = $request->user()->load('organization');

        // Guard: only Members can pay fees
        if (! $user->hasRole('Member')) {
            abort(403);
        }

        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        // Check if already paid this year
        $alreadyPaid = Payment::where('user_id', $user->id)
            ->where('payable_type', 'membership_fee')
            ->where('status', 'successful')
            ->whereYear('created_at', now()->year)
            ->exists();

        if ($alreadyPaid) {
            return back()->with('error', 'Yuran keahlian anda untuk tahun ini sudah dibayar.');
        }

        // Dummy payment — simulate instant success
        Payment::create([
            'user_id'     => $user->id,
            'payable_type'=> 'membership_fee',
            'payable_id'  => null,
            'amount'      => $feeAmount,
            'status'      => 'successful',
            'reference'   => 'DUMMY-' . strtoupper(Str::random(8)),
            'description' => "Yuran keahlian {$user->organization?->name} " . now()->year,
        ]);

        return back()->with('success', "Pembayaran yuran RM {$feeAmount} berjaya! (Mod dummy — gateway sebenar akan disambung kemudian)");
    }
}
