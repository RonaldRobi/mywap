<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\InfaqDonation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\DokuService;
use App\Services\FeeService;
use App\Services\RegistrationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles DOKU Malaysia Checkout API webhooks (payment notifications) and the
 * browser redirect back from the hosted checkout page.
 *
 * Security: the webhook signature is verified using the org's DOKU secret key
 * (HMAC-SHA256 over Client-Id / Request-Timestamp / Request-Target / Digest).
 * Business side-effects only happen inside the signed `callback` route.
 */
class DokuController extends Controller
{
    public function __construct(
        protected DokuService $doku,
        protected FeeService $feeService,
    ) {}

    /**
     * DOKU HTTP Notification (webhook). This is the source of truth for marking
     * a payment successful. Must be publicly reachable and idempotent.
     */
    public function callback(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $data = $request->json()->all();

        Log::info('DOKU notification received', ['body' => $data, 'headers' => [
            'client_id' => $request->header('Client-Id'),
            'request_timestamp' => $request->header('Request-Timestamp'),
            'signature' => $request->header('Signature'),
        ]]);

        // Locate our payment via the invoice_number we sent (= payment reference).
        $invoiceNumber = data_get($data, 'order.invoice_number');

        $payment = Payment::query()
            ->where('reference', $invoiceNumber)
            ->where('gateway', 'doku')
            ->first();

        if (! $payment || ! $payment->organization) {
            Log::warning('DOKU notification: payment not found', ['invoice_number' => $invoiceNumber]);

            return response()->json(['status' => 'not_found'], 404);
        }

        $org = $payment->organization;

        if (! $org->hasDokuConfig()) {
            Log::warning('DOKU notification: org missing gateway config', ['org_id' => $org->id]);

            return response()->json(['status' => 'misconfigured'], 400);
        }

        // Verify DOKU's signature over the exact raw body.
        $requestTarget = '/'.ltrim($request->path(), '/');
        $requestTarget = rtrim($requestTarget, '/') ?: '/';

        $valid = $this->doku->verifyNotificationSignature(
            $org,
            (string) $request->header('Client-Id'),
            (string) $request->header('Request-Timestamp'),
            $requestTarget,
            $rawBody,
            $request->header('Signature'),
        );

        if (! $valid) {
            Log::warning('DOKU notification: invalid signature', ['invoice_number' => $invoiceNumber]);

            return response()->json(['status' => 'invalid_signature'], 401);
        }

        // Idempotency: never re-process a terminal payment.
        if (in_array($payment->status, ['successful', 'failed', 'refunded'], true)) {
            Log::info('DOKU notification: payment already terminal', [
                'payment_id' => $payment->id,
                'current_status' => $payment->status,
            ]);

            return response()->json(['status' => 'ok', 'already_processed' => true]);
        }

        $status = data_get($data, 'payment.status');
        $isSuccess = $this->doku->isSuccessStatus($status);
        $isFailed = $this->doku->isFailedStatus($status);

        // PENDING / intermediate states: acknowledge but do nothing yet.
        if (! $isSuccess && ! $isFailed) {
            Log::info('DOKU notification: non-terminal status, ignoring', [
                'payment_id' => $payment->id,
                'status' => $status,
            ]);

            return response()->json(['status' => 'ok', 'ignored' => true]);
        }

        $payment->update([
            'status' => $isSuccess ? 'successful' : 'failed',
            'channel' => data_get($data, 'payment.channel') ?? $payment->channel,
        ]);

        if ($isSuccess) {
            $this->handleSuccessfulPayment($payment);
        }

        Log::info('DOKU notification processed', [
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Browser redirect back from DOKU's hosted checkout page. The webhook is the
     * source of truth; here we only route the user to the right result page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $invoiceNumber = $request->input('invoice_number')
            ?? $request->input('order.invoice_number')
            ?? $request->input('reference');

        $payment = null;

        if ($invoiceNumber) {
            $payment = Payment::query()
                ->where('reference', $invoiceNumber)
                ->where('gateway', 'doku')
                ->first();
        }

        if (! $payment) {
            return redirect()->route('dashboard');
        }

        // If the webhook hasn't landed yet, reconcile synchronously via Retrieve
        // so the user isn't shown a stale "pending" state.
        if ($payment->status === 'pending' && $payment->organization?->hasDokuConfig() && $payment->gateway_ref) {
            $status = $this->doku->retrieveStatus($payment->organization, $payment->gateway_ref);

            if ($this->doku->isSuccessStatus($status)) {
                $payment->update(['status' => 'successful']);
                $this->handleSuccessfulPayment($payment);
            } elseif ($this->doku->isFailedStatus($status)) {
                $payment->update(['status' => 'failed']);
            }
        }

        if ($payment->status === 'successful') {
            return redirect()->away($this->resolveSuccessUrl($payment));
        }

        if ($payment->status === 'failed') {
            return redirect()->route('dashboard')->with('error', 'Pembayaran tidak berjaya. Sila cuba lagi.');
        }

        // Still pending — send them to a sensible place; webhook will finalise.
        return redirect()->away($this->resolveSuccessUrl($payment))
            ->with('info', 'Pembayaran anda sedang diproses. Status akan dikemas kini sebentar lagi.');
    }

    // ─── Shared post-payment side effects (mirrors BayarCashController) ──────

    protected function handleSuccessfulPayment(Payment $payment): void
    {
        $payableType = $payment->payable_type;
        $payableId = $payment->payable_id;

        if ($payableType === 'infaq_donation' && $payableId) {
            DB::transaction(function () use ($payableId) {
                $donation = InfaqDonation::with('infaq')->find($payableId);
                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'confirmed']);
                    $donation->infaq?->increment('collected_amount', $donation->amount);

                    if ($donation->donor_id) {
                        $donor = Donor::find($donation->donor_id);
                        if ($donor) {
                            $donor->update([
                                'total_donated' => $donor->total_donated + $donation->amount,
                                'donation_count' => $donor->donation_count + 1,
                                'last_donated_at' => now(),
                            ]);
                        }
                    }
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
        } elseif ($payableType === 'registration') {
            app(RegistrationPaymentService::class)->confirmRegistration($payment);
        }
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
                    'year' => $infaq->year,
                    'month' => $infaq->month,
                    'day' => $infaq->day,
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

        if ($payableType === 'registration' && $payableId) {
            $registration = Registration::find($payableId);
            if ($registration) {
                return route('registrations.success', $registration);
            }
        }

        return route('dashboard');
    }
}
