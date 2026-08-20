<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * senangPay — Hosted Payment Page integration.
 *
 * Docs: https://developer.senangpay.my/integration-api/manual-integration-api-open-api
 *
 * Flow: we POST (auto-submit form) to `{base}/payment/{merchantId}` with the
 * payment fields + an HMAC-SHA256 hash over `secretKey + detail + amount +
 * orderId`. The hosted page lets the payer choose FPX / QR Pay / card, then
 * returns to our Return URL (GET) and pings our Callback URL (POST).
 *
 * Return/Callback verification hash:
 *   HMAC-SHA256(secretKey + status_id + order_id + transaction_id + msg)
 *
 * Environments:
 *   production -> https://app.senangpay.my
 *   sandbox    -> https://dev2.senangpay.my
 */
class SenangPayService
{
    public function baseUrl(Organization $org): string
    {
        return $org->senangpay_environment === 'production'
            ? 'https://app.senangpay.my'
            : 'https://dev2.senangpay.my';
    }

    /**
     * The `detail` field only allows A-Z, a-z, 0-9, dot, comma, dash, underscore
     * (underscores are shown as spaces). Sanitise so the hash matches exactly
     * what we POST.
     */
    public function sanitizeDetail(string $detail): string
    {
        $detail = str_replace(' ', '_', trim($detail));
        $detail = (string) preg_replace('/[^A-Za-z0-9.,\-_]/', '', $detail);

        return mb_substr($detail, 0, 500);
    }

    /**
     * Generate the create-payment hash sent to senangPay:
     * HMAC-SHA256(secretKey + detail + amount + orderId).
     */
    public function createHash(Organization $org, string $detail, string $amount, string $orderId): string
    {
        $str = $org->senangpay_secret_key.$detail.$amount.$orderId;

        return hash_hmac('sha256', $str, $org->senangpay_secret_key);
    }

    /**
     * Build the POST endpoint + fields for the hosted payment page.
     *
     * @return array{url: string, fields: array<string, string>}|null
     */
    public function redirectPayload(
        Organization $org,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        ?string $payerPhone = null,
        string $description = 'Payment',
    ): ?array {
        if (blank($org->senangpay_merchant_id) || blank($org->senangpay_secret_key)) {
            Log::warning('senangPay redirectPayload: missing credentials', ['org_id' => $org->id]);

            return null;
        }

        $detail = $this->sanitizeDetail($description);
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $orderId = $payment->reference;
        $hash = $this->createHash($org, $detail, $amount, $orderId);

        $fields = array_filter([
            'detail' => $detail,
            'amount' => $amount,
            'order_id' => $orderId,
            'hash' => $hash,
            'name' => $payerName,
            'email' => $payerEmail,
            'phone' => $payerPhone,
            'timeout' => '900',
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'url' => $this->baseUrl($org).'/payment/'.$org->senangpay_merchant_id,
            'fields' => $fields,
        ];
    }

    /**
     * Prepare the payment and return a signed URL to our local auto-submit page
     * (senangPay requires a POST, so we cannot use a plain `redirect()->away()`).
     */
    public function createPaymentIntent(
        Organization $org,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        ?string $payerPhone = null,
        string $description = 'Payment',
    ): ?string {
        $payload = $this->redirectPayload($org, $payment, $payerName, $payerEmail, $payerPhone, $description);

        if (! $payload) {
            return null;
        }

        $payment->update([
            'gateway' => 'senangpay',
            'gateway_ref' => $payment->reference,
            'gateway_url' => $payload['url'],
        ]);

        // Halaman auto-POST ke senangPay. Tiada signed route — ia menyebabkan
        // 403/blank di belakang proxy (scheme http/https tak konsisten).
        return route('senangpay.pay', ['payment' => $payment->id]);
    }

    /**
     * Verify the return/callback hash senangPay sent us:
     * HMAC-SHA256(secretKey + status_id + order_id + transaction_id + msg).
     */
    public function verifyCallback(array $data, Organization $org): bool
    {
        if (blank($org->senangpay_secret_key) || blank($data['hash'] ?? null)) {
            return false;
        }

        $str = $org->senangpay_secret_key
            .($data['status_id'] ?? '')
            .($data['order_id'] ?? '')
            .($data['transaction_id'] ?? '')
            .($data['msg'] ?? '');

        $expected = hash_hmac('sha256', $str, $org->senangpay_secret_key);

        return hash_equals($expected, (string) $data['hash']);
    }

    public function isSuccessful(array $data): bool
    {
        return (string) ($data['status_id'] ?? '') === '1';
    }

    public function isFailed(array $data): bool
    {
        return (string) ($data['status_id'] ?? '') === '0';
    }
}
