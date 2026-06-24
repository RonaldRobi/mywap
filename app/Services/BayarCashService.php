<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Http\Request;
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

    public function verifyCallback(array $callbackData, Organization $org): bool
    {
        $client = $this->client($org);

        $method = isset($callbackData['record_type'])
            ? 'verifyTransactionCallbackData'
            : 'verifyReturnUrlCallbackData';

        return $client->{$method}($callbackData, $org->bayarcash_secret_key);
    }

    public function isPaymentSuccessful(array $callbackData): bool
    {
        return ($callbackData['status'] ?? '') === '3'
            || ($callbackData['status'] ?? '') === '1';
    }
}
