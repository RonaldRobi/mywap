<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Payment;
use App\Services\FeeService;
use App\Services\PaymentGatewayManager;
use App\Services\ReceiptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinancialController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

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
            ->map(fn (Payment $payment) => $this->receipts->historyRow($payment));

        $feeStatus = $feeService->getStatus($user);

        return ApiResponse::success([
            'campaigns' => $campaigns,
            'fee_status' => $feeStatus,
            'payment_history' => $paymentHistory,
        ]);
    }

    /**
     * Pulangkan URL ditandatangani untuk memuat turun resit PDF satu bayaran.
     * Hanya pemilik bayaran (user_id = pengguna semasa) yang dibenarkan, dan
     * resit hanya wujud untuk bayaran yang berjaya.
     */
    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();

        if ($payment->user_id !== $user->id) {
            return ApiResponse::error('Resit ini bukan milik anda.', status: 403);
        }

        if ($payment->status !== 'successful') {
            return ApiResponse::error('Resit hanya tersedia untuk bayaran yang berjaya.', status: 422);
        }

        return ApiResponse::success(['url' => $this->receipts->signedUrl($payment)]);
    }

    /**
     * Mulakan bayaran yuran tahunan — logik sama dengan web
     * PaymentController::payFee, tetapi sebagai JSON (mobile membuka
     * payment_url dalam WebView). Berjaya "dummy" terus menanda yuran.
     */
    public function payFee(Request $request, FeeService $feeService, PaymentGatewayManager $gateways): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Member')) {
            return ApiResponse::error('Akaun ini tidak dibenarkan membayar yuran.', status: 403);
        }

        $year = now()->year;

        if ($feeService->isLifeMember($user)) {
            return ApiResponse::error('Anda adalah ahli seumur hidup — tidak perlu bayar yuran.', status: 422);
        }

        if ($feeService->isExempted($user)) {
            return ApiResponse::error('Yuran anda telah dikecualikan — tidak perlu bayar yuran.', status: 422);
        }

        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $status = $feeService->getStatus($user, $year);
        if ($status['status'] === 'active') {
            return ApiResponse::error('Yuran keahlian anda untuk tahun ini sudah dibayar.', status: 422);
        }

        $org = $user->organization;
        $useGateway = $gateways->isLive($org);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'membership_fee',
            'payable_id' => null,
            'amount' => $feeAmount,
            'status' => $useGateway ? 'pending' : 'successful',
            'reference' => $useGateway ? 'FEE-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Yuran keahlian {$org?->name} {$year}",
            'gateway' => $gateways->gatewayFor($org),
            'organization_id' => $org?->id,
        ]);

        if ($useGateway && $org) {
            $url = $gateways->createPaymentRedirect(
                $org,
                $payment,
                $user->name,
                $user->email,
                $user->phone ?? null,
                "Yuran keahlian {$org?->name} {$year}",
            );

            if ($url) {
                return ApiResponse::success(['status' => 'redirect', 'payment_url' => $url]);
            }

            $payment->update(['status' => 'failed']);

            return ApiResponse::error('Pembayaran gagal diproses. Sila cuba lagi.', status: 502);
        }

        $feeService->markAsPaid($user, $year, $feeAmount, $payment->id);

        return ApiResponse::success([
            'status' => 'success',
            'message' => "Pembayaran yuran RM {$feeAmount} berjaya!",
        ]);
    }
}
