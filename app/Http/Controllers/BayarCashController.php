<?php

namespace App\Http\Controllers;

use App\Models\InfaqDonation;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BayarCashService;
use App\Services\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BayarCashController extends Controller
{
    public function __construct(
        protected BayarCashService $bayarCashService,
        protected FeeService $feeService,
    ) {}

    public function callback(Request $request): JsonResponse
    {
        $callbackData = $request->all();

        Log::info('BayarCash callback received', $callbackData);

        $payment = Payment::query()
            ->where('reference', $callbackData['order_number'] ?? '')
            ->where('gateway', 'bayarcash')
            ->first();

        if (!$payment || !$payment->organization) {
            Log::warning('BayarCash callback: payment not found', ['order_number' => $callbackData['order_number'] ?? null]);
            return response()->json(['status' => 'not_found']);
        }

        $org = $payment->organization;

        if (!$org->hasBayarCashConfig()) {
            Log::warning('BayarCash callback: org missing gateway config', ['org_id' => $org->id]);
            return response()->json(['status' => 'misconfigured']);
        }

        $valid = $this->bayarCashService->verifyCallback($callbackData, $org);

        if (!$valid) {
            Log::warning('BayarCash callback: invalid checksum', ['order_number' => $callbackData['order_number'] ?? null]);
            return response()->json(['status' => 'invalid_checksum']);
        }

        $isSuccess = $this->bayarCashService->isPaymentSuccessful($callbackData);

        $payment->update([
            'status'      => $isSuccess ? 'successful' : 'failed',
            'gateway_ref' => $callbackData['transaction_id'] ?? $callbackData['exchange_transaction_id'] ?? $payment->gateway_ref,
        ]);

        if ($isSuccess) {
            $this->handleSuccessfulPayment($payment, $callbackData);
        }

        Log::info('BayarCash callback processed', [
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $callbackData = $request->all();

        $payment = Payment::query()
            ->where('reference', $callbackData['order_number'] ?? '')
            ->where('gateway', 'bayarcash')
            ->first();

        if (!$payment) {
            Log::warning('BayarCash redirect: payment not found', ['order_number' => $callbackData['order_number'] ?? null]);
            return redirect()->route('dashboard')->with('error', 'Rujukan pembayaran tidak dijumpai.');
        }

        if ($payment->status === 'successful') {
            $url = $this->resolveSuccessUrl($payment);
            return redirect()->away($url);
        }

        if ($payment->status === 'failed') {
            return redirect()->route('dashboard')->with('error', 'Pembayaran tidak berjaya. Sila cuba lagi.');
        }

        if (isset($callbackData['status'])) {
            $org = $payment->organization;
            if ($org && $org->hasBayarCashConfig()) {
                $valid = $this->bayarCashService->verifyCallback($callbackData, $org);
                if ($valid) {
                    $isSuccess = $this->bayarCashService->isPaymentSuccessful($callbackData);
                    $payment->update([
                        'status' => $isSuccess ? 'successful' : 'failed',
                    ]);
                    if ($isSuccess) {
                        $this->handleSuccessfulPayment($payment, $callbackData);
                    }
                }
            }
        }

        return redirect()->route('dashboard');
    }

    protected function resolveSuccessUrl(Payment $payment): string
    {
        $payableType = $payment->payable_type;
        $payableId = $payment->payable_id;

        if ($payableType === 'infaq_donation' && $payableId) {
            $donation = InfaqDonation::with('infaq')->find($payableId);
            if ($donation?->infaq) {
                $infaq = $donation->infaq;
                return route('infaq.success', [
                    'year'  => $infaq->year,
                    'month' => $infaq->month,
                    'day'   => $infaq->day,
                    'infaq' => $infaq->slug,
                ]);
            }
        }

        if ($payableType === 'membership_fee') {
            return route('member.financial.overview');
        }

        if ($payableType === 'order' && $payableId) {
            return route('orders.show', $payableId);
        }

        return route('dashboard');
    }

    protected function handleSuccessfulPayment(Payment $payment, array $callbackData): void
    {
        $payableType = $payment->payable_type;
        $payableId = $payment->payable_id;

        if ($payableType === 'infaq_donation' && $payableId) {
            DB::transaction(function () use ($payableId, $payment) {
                $donation = InfaqDonation::with('infaq')->find($payableId);
                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'confirmed']);
                    $donation->infaq?->increment('collected_amount', $donation->amount);
                }
            });
        } elseif ($payableType === 'membership_fee') {
            $user = $payment->user;
            if ($user) {
                $year = now()->year;
                $this->feeService->markAsPaid($user, $year, (float) $payment->amount, $payment->id);
            }
        } elseif ($payableType === 'order' && $payableId) {
            Order::where('id', $payableId)->update(['status' => 'paid']);
        }
    }
}
