<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DOKU Malaysia — Checkout API v3 integration.
 *
 * Docs: https://doku-developers.apidog.io
 *
 * Auth model (per DOKU "Authentication & Integrity" + "Signature" pages):
 *  - Authorization: Basic base64(API_KEY + ":")
 *  - Client-Id, Request-Timestamp (ISO8601 UTC "Z"), API-Version headers
 *  - Signature: HMACSHA256=base64(hmac_sha256(secret, stringToSign))
 *      stringToSign (POST) lines, "\n" separated, no trailing newline:
 *        Client-Id:{clientId}
 *        Request-Timestamp:{timestamp}
 *        Request-Target:{path}
 *        Digest:{base64(sha256(rawJsonBody))}
 *  - The SAME scheme is used to verify webhook notifications DOKU sends us,
 *    where Request-Target = the path of OUR notification URL.
 *
 * We use the Checkout API (/v3/checkouts) so the payer picks their channel
 * (FPX / TnG / GrabPay / ShopeePay) on DOKU's hosted page. This matches the
 * app's existing "redirect away to gateway" flow.
 */
class DokuService
{
    public const API_VERSION = 'arabica.2025-12-01';

    /** Channels enabled on the hosted checkout page. */
    public const CHANNELS = [
        'INTERNET_BANKING_FPX',
        'EWALLET_TNG',
        'EWALLET_GRABPAY',
        'EWALLET_SHOPEEPAY',
    ];

    public function baseUrl(Organization $org): string
    {
        return $org->doku_environment === 'production'
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';
    }

    // ─── Signature helpers ──────────────────────────────────────────────────

    public function digest(string $rawBody): string
    {
        return base64_encode(hash('sha256', $rawBody, true));
    }

    /**
     * Build the canonical string-to-sign for a POST request/notification.
     */
    public function stringToSign(string $clientId, string $timestamp, string $requestTarget, string $digest): string
    {
        return implode("\n", [
            'Client-Id:'.$clientId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$requestTarget,
            'Digest:'.$digest,
        ]);
    }

    /**
     * Produce the value for the `Signature` header, prefixed with HMACSHA256=.
     */
    public function generateSignature(string $secretKey, string $stringToSign): string
    {
        $hmac = base64_encode(hash_hmac('sha256', $stringToSign, $secretKey, true));

        return 'HMACSHA256='.$hmac;
    }

    /**
     * Verify a signature that DOKU sent us on a webhook notification.
     *
     * @param  string  $requestTarget  Path of our notification URL (e.g. /doku/callback)
     * @param  string  $rawBody  The exact raw request body bytes DOKU sent.
     */
    public function verifyNotificationSignature(
        Organization $org,
        string $clientId,
        string $timestamp,
        string $requestTarget,
        string $rawBody,
        ?string $providedSignature,
    ): bool {
        if (blank($providedSignature) || blank($org->doku_secret_key)) {
            return false;
        }

        $expected = $this->generateSignature(
            $org->doku_secret_key,
            $this->stringToSign($clientId, $timestamp, $requestTarget, $this->digest($rawBody)),
        );

        return hash_equals($expected, $providedSignature);
    }

    // ─── Create Checkout ────────────────────────────────────────────────────

    /**
     * Create a DOKU hosted checkout and return the redirect URL.
     *
     * @return string|null Checkout URL to redirect the payer to, or null on failure.
     */
    public function createCheckout(
        Organization $org,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        ?string $payerPhone = null,
        string $description = 'Payment',
    ): ?string {
        $clientId = $org->doku_client_id;
        $apiKey = $org->doku_api_key;
        $secret = $org->doku_secret_key;

        if (blank($clientId) || blank($apiKey) || blank($secret)) {
            Log::warning('DOKU createCheckout: missing credentials', ['org_id' => $org->id]);

            return null;
        }

        $requestTarget = '/v3/checkouts';
        $timestamp = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

        // Reference id for DOKU (unique per attempt); invoice_number = our ref.
        $referenceId = (string) Str::uuid();

        $body = [
            'id' => $referenceId,
            'order' => [
                'amount' => (float) number_format((float) $payment->amount, 2, '.', ''),
                'invoice_number' => $payment->reference,
                'currency' => 'MYR',
                'expired_at' => Carbon::now('UTC')->addHour()->format('Y-m-d\TH:i:s\Z'),
            ],
            'checkout_experience' => [
                'payment_channels' => self::CHANNELS,
                'language' => 'EN',
                'auto_redirect' => true,
                'retry_payment' => ['enabled' => true],
                'callback_url' => route('doku.redirect'),
                'callback_url_result' => route('doku.redirect'),
                'callback_url_cancel' => route('doku.redirect'),
            ],
            'payment' => ['type' => 'SALE'],
            'customer' => array_filter([
                'id' => 'ORG'.$org->id.'-U'.($payment->user_id ?? 'guest'),
                'name' => $payerName,
                'email' => $payerEmail,
                'phone' => $this->normalizePhone($payerPhone),
                'country' => 'MY',
            ]),
            'metadata' => [
                'payment_reference' => $payment->reference,
                'organization_id' => (string) $org->id,
            ],
        ];

        // DOKU requires the digest to be computed on the EXACT bytes sent, so we
        // serialise once and reuse the same string for the request and digest.
        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = $this->generateSignature(
            $secret,
            $this->stringToSign($clientId, $timestamp, $requestTarget, $this->digest($rawBody)),
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.base64_encode($apiKey.':'),
                'Client-Id' => $clientId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'API-Version' => self::API_VERSION,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->withBody($rawBody, 'application/json')
                ->timeout(30)
                ->post($this->baseUrl($org).$requestTarget);
        } catch (\Throwable $e) {
            Log::error('DOKU createCheckout: request failed', [
                'org_id' => $org->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('DOKU createCheckout: non-2xx response', [
                'org_id' => $org->id,
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $checkoutUrl = $data['payment']['checkout_url'] ?? null;

        if (! $checkoutUrl) {
            Log::warning('DOKU createCheckout: no checkout_url in response', [
                'payment_id' => $payment->id,
                'body' => $response->body(),
            ]);

            return null;
        }

        $payment->update([
            'gateway' => 'doku',
            'gateway_ref' => $referenceId,
            'gateway_url' => $checkoutUrl,
        ]);

        return $checkoutUrl;
    }

    // ─── Retrieve Payment Status (server-to-server reconciliation) ───────────

    /**
     * Retrieve a checkout/payment status directly from DOKU using the reference
     * id we sent as `id` on create. Returns the DOKU `status` (SUCCESS/PENDING/
     * FAILED/EXPIRED) or null on error.
     */
    public function retrieveStatus(Organization $org, string $referenceId): ?string
    {
        $clientId = $org->doku_client_id;
        $apiKey = $org->doku_api_key;
        $secret = $org->doku_secret_key;

        if (blank($clientId) || blank($apiKey) || blank($secret)) {
            return null;
        }

        $requestTarget = '/v3/checkouts/'.$referenceId;
        $timestamp = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

        // GET has no body -> empty digest per DOKU (digest applies to POST/PATCH).
        $signature = $this->generateSignature(
            $secret,
            implode("\n", [
                'Client-Id:'.$clientId,
                'Request-Timestamp:'.$timestamp,
                'Request-Target:'.$requestTarget,
            ]),
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.base64_encode($apiKey.':'),
                'Client-Id' => $clientId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'API-Version' => self::API_VERSION,
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->get($this->baseUrl($org).$requestTarget);
        } catch (\Throwable $e) {
            Log::error('DOKU retrieveStatus: request failed', [
                'org_id' => $org->id,
                'reference' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('payment.status');
    }

    // ─── Status helpers ─────────────────────────────────────────────────────

    public function isSuccessStatus(?string $status): bool
    {
        return strtoupper((string) $status) === 'SUCCESS';
    }

    public function isFailedStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['FAILED', 'EXPIRED'], true);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);

        if ($digits === '') {
            return null;
        }

        // Malaysian numbers: convert leading 0 to +60.
        if (str_starts_with($digits, '0')) {
            return '+60'.ltrim(substr($digits, 1), '0');
        }

        if (str_starts_with($digits, '60')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
