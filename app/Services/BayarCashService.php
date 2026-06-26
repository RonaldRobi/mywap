<?php

namespace App\Services;

use App\Models\InfaqDonation;
use App\Models\Organization;
use App\Models\Payment;
use Webimpian\BayarcashSdk\Bayarcash;

class BayarCashService
{
    public function client(Organization $org): Bayarcash
    {
        $client = new Bayarcash($org->bayarcash_api_token);
        $client->setApiVersion('v3');

        if ($org->bayarcash_environment === 'sandbox') {
            $client->useSandbox();
        }

        return $client;
    }

    // ─── One-time payment intent ────────────────────────────────────────────

    public function createPaymentIntent(
        Organization $org,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        ?string $payerPhone = null,
        int $paymentChannel = Bayarcash::FPX
    ): ?string {
        $client = $this->client($org);

        $data = [
            'payment_channel'  => $paymentChannel,
            'portal_key'       => $org->bayarcash_portal_key,
            'order_number'     => $payment->reference,
            'amount'           => number_format((float) $payment->amount, 2, '.', ''),
            'payer_name'       => $payerName,
            'payer_email'      => $payerEmail,
            'callback_url'     => route('bayarcash.callback'),
            'return_url'       => route('bayarcash.redirect'),
        ];

        if ($payerPhone) {
            $data['payer_telephone_number'] = $payerPhone;
        }

        $checksum = $client->createPaymentIntentChecksumValue($org->bayarcash_secret_key, $data);
        $data['checksum'] = $checksum;

        $response = $client->createPaymentIntent($data);

        if ($response && $response->url) {
            $payment->update([
                'gateway'     => 'bayarcash',
                'gateway_ref' => $response->id,
                'gateway_url' => $response->url,
            ]);

            return $response->url;
        }

        return null;
    }

    // ─── Recurring / Direct Debit enrollment ────────────────────────────────

    public function createDirectDebitEnrollment(
        Organization $org,
        InfaqDonation $donation,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        string $payerPhone,
        string $frequency, // MT, WK, DL, YR
        ?string $effectiveDate = null,
        ?string $expiryDate = null,
    ): ?string {
        $client = $this->client($org);

        $data = [
            'portal_key'              => $org->bayarcash_portal_key,
            'order_number'            => $payment->reference,
            'amount'                  => number_format((float) $payment->amount, 2, '.', ''),
            'payer_name'              => $payerName,
            'payer_email'             => $payerEmail,
            'payer_telephone_number'  => $payerPhone,
            'payer_id_type'           => Bayarcash\FpxDirectDebit::NRIC,
            'payer_id'                => $payerPhone,
            'application_type'        => Bayarcash\FpxDirectDebit::ENROLMENT,
            'application_reason'      => 'Donasi berkala: ' . $donation->infaq->title,
            'frequency_mode'          => $frequency,
            'callback_url'            => route('bayarcash.direct-debit.callback'),
            'return_url'              => route('bayarcash.redirect'),
        ];

        if ($effectiveDate) {
            $data['effective_date'] = $effectiveDate;
        }
        if ($expiryDate) {
            $data['expiry_date'] = $expiryDate;
        }

        $checksum = $client->createFpxDIrectDebitEnrolmentChecksumValue($org->bayarcash_secret_key, $data);
        $data['checksum'] = $checksum;

        $response = $client->createFpxDirectDebitEnrollment($data);

        if ($response && $response->url) {
            $payment->update([
                'gateway'     => 'bayarcash',
                'gateway_ref' => $response->orderNumber,
                'gateway_url' => $response->url,
            ]);

            return $response->url;
        }

        return null;
    }

    // ─── Callback verification ──────────────────────────────────────────────

    public function verifyCallback(array $callbackData, Organization $org): bool
    {
        $client = $this->client($org);

        $method = isset($callbackData['record_type'])
            ? 'verifyTransactionCallbackData'
            : 'verifyReturnUrlCallbackData';

        return $client->{$method}($callbackData, $org->bayarcash_secret_key);
    }

    public function verifyDirectDebitCallback(array $callbackData, Organization $org): bool
    {
        $client = $this->client($org);
        $recordType = $callbackData['record_type'] ?? '';

        $method = match ($recordType) {
            'direct_debit_bank_approval' => 'verifyDirectDebitBankApprovalCallbackData',
            'direct_debit_authorization' => 'verifyDirectDebitAuthorizationCallbackData',
            'direct_debit_transaction'   => 'verifyDirectDebitTransactionCallbackData',
            default                      => null,
        };

        if (!$method || !method_exists($client, $method)) {
            return false;
        }

        return $client->{$method}($callbackData, $org->bayarcash_secret_key);
    }

    public function isPaymentSuccessful(array $callbackData): bool
    {
        return ($callbackData['status'] ?? '') === '3'
            || ($callbackData['status'] ?? '') === '1';
    }
}
