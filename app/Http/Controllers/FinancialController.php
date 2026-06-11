<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function adminCampaigns(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $campaigns = Campaign::query()
            ->where('organization_id', $organizationId)
            ->latest()
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'status' => $campaign->status,
                'target_amount' => (float) $campaign->target_amount,
                'current_amount' => (float) $campaign->current_amount,
                'progress_percent' => $campaign->target_amount > 0
                    ? min(100, round(($campaign->current_amount / $campaign->target_amount) * 100))
                    : 0,
                'created_at' => $campaign->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $campaigns]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'status' => ['required', 'in:draft,active,closed'],
        ]);

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $suffix = 1;

        while (Campaign::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        Campaign::create([
            'organization_id' => $request->user()->current_organization_id,
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'target_amount' => $validated['target_amount'],
            'current_amount' => 0,
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Kempen infaq berjaya dicipta.');
    }

    public function memberOverview(Request $request): Response
    {
        $user = $request->user();

        $campaigns = Campaign::query()
            ->where('organization_id', $user->current_organization_id)
            ->where('status', 'active')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'target_amount' => (float) $campaign->target_amount,
                'current_amount' => (float) $campaign->current_amount,
                'progress_percent' => $campaign->target_amount > 0
                    ? min(100, round(($campaign->current_amount / $campaign->target_amount) * 100))
                    : 0,
            ]);

        $paymentHistory = Payment::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'payable_type' => $payment->payable_type,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toISOString(),
            ]);

        $latestFeePayment = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'successful')
            ->where('payable_type', 'membership_fee')
            ->latest('created_at')
            ->first();

        $feeStatusValue = $latestFeePayment && $latestFeePayment->created_at->gte(now()->subYear())
            ? 'active'
            : 'due';

        return Inertia::render('Member/Financial/Overview', [
            'campaigns' => $campaigns,
            'feeStatus' => [
                'status' => $feeStatusValue,
                'amount_due' => $feeStatusValue === 'active' ? 0 : 120,
                'last_paid_at' => $latestFeePayment?->created_at?->toISOString(),
            ],
            'paymentHistory' => $paymentHistory,
        ]);
    }
}
