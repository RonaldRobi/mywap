<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Payment;
use App\Services\FeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    /**
     * Gambaran kewangan ahli — status yuran, kempen infaq aktif, sejarah bayaran.
     * Sama logic dengan web FinancialController::memberOverview.
     */
    public function overview(Request $request, FeeService $feeService): JsonResponse
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

        $feeStatus = $feeService->getStatus($user);

        return ApiResponse::success([
            'campaigns' => $campaigns,
            'fee_status' => $feeStatus,
            'payment_history' => $paymentHistory,
        ]);
    }
}
