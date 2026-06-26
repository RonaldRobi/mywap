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
    ) {
    }

    // ─── Direct Debit callbacks ────────────────────────────────────────────

    public function directDebitCallback(Request $request): JsonResponse
    {
        $callbackData = $request->all();

        Log::info('BayarCash direct debit callback received', $callbackData);

        $recordType = $callbackData['record_type'] ?? '';

        // Find the donation via order_number (payment reference)
        $payment = Payment::query()
            ->where('reference', $callbackData['order_number'] ?? '')
            ->where('gateway', 'bayarcash')
            ->first();

        if (!$payment || !$payment->organization) {
            Log::warning('BayarCash DD callback: payment not found', ['order_number' => $callbackData['order_number'] ?? null]);
            return response()->json(['status' => 'not_found']);
        }

        $org = $payment->organization;

        if (!$org->hasBayarCashConfig()) {
            Log::warning('BayarCash DD callback: org missing gateway config', ['org_id' => $org->id]);
            return response()->json(['status' => 'misconfigured']);
        }

        $valid = $this->bayarCashService->verifyDirectDebitCallback($callbackData, $org);

        if (!$valid) {
            Log::warning('BayarCash DD callback: invalid checksum', $callbackData);
            return response()->json(['status' => 'invalid_checksum']);
        }

        return match ($recordType) {
            'direct_debit_bank_approval' => $this->handleBankApproval($callbackData, $payment),
            'direct_debit_authorization' => $this->handleAuthorization($callbackData, $payment),
            'direct_debit_transaction'   => $this->handleRecurringTransaction($callbackData, $payment, $org),
            default => response()->json(['status' => 'unknown_record_type']),
        };
    }

    protected function handleBankApproval(array $data, Payment $payment): JsonResponse
    {
        $donation = InfaqDonation::find($payment->payable_id);

        if (!$donation) {
            return response()->json(['status' => 'donation_not_found']);
        }

        $approved = ($data['approval_status'] ?? '') === '1';
        $mandateId = $data['mandate_id'] ?? null;
        $mandateRef = $data['mandate_reference_number'] ?? null;

        $donation->update([
            'mandate_id'        => $mandateId,
            'mandate_ref'       => $mandateRef,
            'recurring_status'  => $approved ? 'active' : 'failed',
        ]);

        if (!$approved) {
            $payment->update(['status' => 'failed']);
        }

        Log::info('BayarCash DD bank approval processed', [
            'donation_id' => $donation->id,
            'approved' => $approved,
            'mandate_id' => $mandateId,
        ]);

        return response()->json(['status' => 'ok']);
    }

    protected function handleAuthorization(array $data, Payment $payment): JsonResponse
    {
        $isSuccess = ($data['status'] ?? '') === '3' || ($data['status'] ?? '') === '1';

        $payment->update([
            'status'      => $isSuccess ? 'successful' : 'failed',
            'gateway_ref' => $data['transaction_id'] ?? $payment->gateway_ref,
        ]);

        if ($isSuccess && $payment->payable_type === 'infaq_donation') {
            DB::transaction(function () use ($payment) {
                $donation = InfaqDonation::with('infaq')->find($payment->payable_id);
                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'confirmed']);
                    $donation->infaq?->increment('collected_amount', $donation->amount);
                }
            });
        }

        Log::info('BayarCash DD authorization processed', [
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);

        return response()->json(['status' => 'ok']);
    }

    protected function handleRecurringTransaction(array $data, Payment $originalPayment, Organization $org): JsonResponse
    {
        $isSuccess = ($data['status'] ?? '') === '3' || ($data['status'] ?? '') === '1';

        if (!$isSuccess) {
            Log::warning('BayarCash DD recurring transaction failed', $data);
            return response()->json(['status' => 'failed']);
        }

        $donation = InfaqDonation::with('infaq')->find($originalPayment->payable_id);

        if (!$donation || !$donation->infaq) {
            return response()->json(['status' => 'donation_not_found']);
        }

        DB::transaction(function () use ($data, $donation, $org) {
            $amount = (float) ($data['amount'] ?? $donation->amount);
            $ref = 'INFQ-' . strtoupper(Str::random(10));
            $paymentRef = 'DDR-' . strtoupper(Str::random(8));

            // Create follow-up donation record for this recurring cycle
            $childDonation = InfaqDonation::create([
                'infaq_id'         => $donation->infaq_id,
                'user_id'          => $donation->user_id,
                'amount'           => $amount,
                'reference'        => $ref,
                'status'           => 'confirmed',
                'donor_name'       => $donation->donor_name,
                'donor_phone'      => $donation->donor_phone,
                'donor_email'      => $donation->donor_email,
                'is_anonymous'     => $donation->is_anonymous,
                'wants_updates'    => $donation->wants_updates,
                'is_recurring'     => true,
                'frequency'        => $donation->frequency,
                'mandate_id'       => $donation->mandate_id,
                'mandate_ref'      => $donation->mandate_ref,
                'recurring_status' => 'active',
            ]);

            Payment::create([
                'user_id'         => $donation->user_id,
                'payable_type'    => 'infaq_donation',
                'payable_id'      => $childDonation->id,
                'amount'          => $amount,
                'status'          => 'successful',
                'reference'       => $paymentRef,
                'description'     => "Donasi berkala (kitaran): {$donation->infaq->title}",
                'gateway'         => 'bayarcash',
                'gateway_ref'     => $data['transaction_id'] ?? null,
                'organization_id' => $org->id,
            ]);

            $donation->infaq->increment('collected_amount', $amount);

            // Update next billing date on the parent donation
            $nextBilling = match ($donation->frequency) {
                'DL' => now()->addDay()->toDateString(),
                'WK' => now()->addWeek()->toDateString(),
                'MT' => now()->addMonth()->toDateString(),
                'YR' => now()->addYear()->toDateString(),
                default => null,
            };

            if ($nextBilling) {
                $donation->updateQuietly(['next_billing_date' => $nextBilling]);
            }
        });

        Log::info('BayarCash DD recurring transaction processed', [
            'parent_donation' => $donation->id,
            'amount' => $data['amount'] ?? $donation->amount,
        ]);

        return response()->json(['status' => 'ok']);
    }

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
