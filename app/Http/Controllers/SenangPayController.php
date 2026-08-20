<?php

namespace App\Http\Controllers;

use App\Models\InfaqDonation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\FeeService;
use App\Services\RegistrationPaymentService;
use App\Services\SenangPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * senangPay controller — hosted payment page (FPX / QR Pay / card).
 *
 * - `pay`      : local auto-submit page that POSTs to senangPay (signed URL).
 * - `callback` : senangPay POST notification — source of truth for success.
 * - `redirect` : browser GET return — routes the payer to the right page.
 */
class SenangPayController extends Controller
{
    public function __construct(
        protected SenangPayService $senangPay,
        protected RegistrationPaymentService $registrations,
    ) {}

    /**
     * Render the auto-submitting POST form to senangPay's hosted page.
     */
    public function pay(Request $request, Payment $payment)
    {
        $org = $payment->organization;
        abort_unless($org && $org->hasSenangPayConfig(), 404, 'Gateway tidak dikonfigurasi.');

        [$name, $email, $phone, $description] = $this->payerInfo($payment);

        $payload = $this->senangPay->redirectPayload($org, $payment, $name, $email, $phone, $description);

        abort_unless($payload, 500, 'Gagal menyediakan pembayaran.');

        return view('gateway.senangpay-pay', $payload);
    }

    /**
     * senangPay callback (POST). Verify hash, update the payment and run any
     * post-payment side effects (confirm registration + send email).
     */
    public function callback(Request $request): JsonResponse
    {
        $data = $request->all();

        Log::info('senangPay callback received', $data);

        $payment = Payment::query()
            ->where('reference', $data['order_id'] ?? '')
            ->where('gateway', 'senangpay')
            ->first();

        if (! $payment || ! $payment->organization) {
            Log::warning('senangPay callback: payment not found', ['order_id' => $data['order_id'] ?? null]);

            return response()->json(['status' => 'not_found'], 404);
        }

        $org = $payment->organization;

        if (! $org->hasSenangPayConfig()) {
            Log::warning('senangPay callback: org missing gateway config', ['org_id' => $org->id]);

            return response()->json(['status' => 'misconfigured'], 400);
        }

        if (! $this->senangPay->verifyCallback($data, $org)) {
            Log::warning('senangPay callback: invalid hash', $data);

            return response()->json(['status' => 'invalid_hash'], 401);
        }

        if (in_array($payment->status, ['successful', 'failed', 'refunded'], true)) {
            return response()->json(['status' => 'ok', 'already_processed' => true]);
        }

        $payment->update([
            'status' => $this->senangPay->isSuccessful($data) ? 'successful' : 'failed',
            'gateway_ref' => $data['transaction_id'] ?? $payment->gateway_ref,
        ]);

        if ($this->senangPay->isSuccessful($data)) {
            $this->handleSuccessfulPayment($payment);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Browser return from senangPay (GET). The callback is the source of truth;
     * here we just reconcile (if needed) and route the user.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $data = $request->all();

        $payment = Payment::query()
            ->where('reference', $data['order_id'] ?? '')
            ->where('gateway', 'senangpay')
            ->first();

        if (! $payment || ! $payment->organization) {
            return redirect()->route('dashboard')->with('error', 'Rujukan pembayaran tidak dijumpai.');
        }

        $org = $payment->organization;

        if ($org->hasSenangPayConfig() && $this->senangPay->verifyCallback($data, $org)) {
            if (in_array($payment->status, ['pending'], true) || $payment->status === '') {
                $payment->update([
                    'status' => $this->senangPay->isSuccessful($data) ? 'successful' : 'failed',
                    'gateway_ref' => $data['transaction_id'] ?? $payment->gateway_ref,
                ]);

                if ($this->senangPay->isSuccessful($data)) {
                    $this->handleSuccessfulPayment($payment);
                }
            }
        }

        if ($payment->status === 'successful') {
            return redirect()->away($this->resolveSuccessUrl($payment));
        }

        if ($payment->status === 'failed') {
            return redirect()->route('dashboard')->with('error', 'Pembayaran tidak berjaya. Sila cuba lagi.');
        }

        return redirect()->away($this->resolveSuccessUrl($payment))
            ->with('info', 'Pembayaran anda sedang diproses. Status akan dikemas kini sebentar lagi.');
    }

    // ─── Shared helpers ─────────────────────────────────────────────────────

    protected function payerInfo(Payment $payment): array
    {
        if ($payment->payable_type === Registration::class && $payment->payable) {
            $registration = $payment->payable;

            return [
                $registration->name,
                $registration->email ?: ($registration->name.'@mywap.my'),
                $registration->phone,
                'Pendaftaran: '.($registration->event?->title ?? $payment->reference),
            ];
        }

        return [
            $payment->user?->name ?? 'Pelanggan',
            $payment->user?->email ?? 'pelanggan@mywap.my',
            $payment->user?->phone,
            'Pembayaran: '.$payment->reference,
        ];
    }

    protected function handleSuccessfulPayment(Payment $payment): void
    {
        if ($payment->payable_type === Registration::class) {
            $this->registrations->confirmRegistration($payment);
        } elseif ($payment->payable_type === 'order' && $payment->payable_id) {
            Order::where('id', $payment->payable_id)->update(['status' => 'paid']);
        } elseif ($payment->payable_type === 'membership_fee') {
            $user = $payment->user;
            if ($user) {
                app(FeeService::class)->markAsPaid($user, now()->year, (float) $payment->amount, $payment->id);
            }
        } elseif ($payment->payable_type === 'infaq_donation' && $payment->payable_id) {
            $donation = InfaqDonation::find($payment->payable_id);
            if ($donation && $donation->status === 'pending') {
                $donation->update(['status' => 'confirmed']);
                $donation->infaq?->increment('collected_amount', $donation->amount);
            }
        }
    }

    protected function resolveSuccessUrl(Payment $payment): string
    {
        $type = $payment->payable_type;
        $id = $payment->payable_id;

        if ($type === Registration::class && $id) {
            $registration = Registration::find($id);
            if ($registration) {
                return route('registrations.success', $registration);
            }
        }

        if ($type === 'order' && $id) {
            return route('orders.show', $id);
        }

        if ($type === 'membership_fee') {
            return route('member.financial.overview');
        }

        if ($type === 'infaq_donation' && $id) {
            $donation = InfaqDonation::with('infaq')->find($id);
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

        return route('dashboard');
    }
}
