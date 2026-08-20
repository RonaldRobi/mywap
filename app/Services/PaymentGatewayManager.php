<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use Webimpian\BayarcashSdk\Bayarcash;

/**
 * Resolves which payment gateway an organisation uses and delegates the
 * "create a one-time payment redirect" call to the correct service.
 *
 * This is the single place that knows how to branch between BayarCash, DOKU
 * and senangPay for one-time payments (orders, membership fees, one-time
 * infaq donations, event registrations).
 *
 * Recurring / Direct Debit is intentionally NOT handled here — only BayarCash
 * supports it, and InfaqController calls BayarCashService directly for that.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected BayarCashService $bayarCash,
        protected DokuService $doku,
        protected SenangPayService $senangPay,
    ) {}

    /**
     * The gateway that will actually be used for a given organisation, or
     * 'dummy' when the org has no gateway configured.
     */
    public function gatewayFor(?Organization $org): string
    {
        if (! $org) {
            return 'dummy';
        }

        return $org->activeGateway() ?? 'dummy';
    }

    public function isLive(?Organization $org): bool
    {
        return $this->gatewayFor($org) !== 'dummy';
    }

    /**
     * Maklumat branding untuk paparan pembayaran (nama, tagline, logo, kaedah).
     * Data diambil dari config/payment-gateways.php — tidak dihardcode di
     * frontend, supaya gateway masa hadapan cukup dengan menambah config.
     *
     * @return array{key: string, name: string, tagline: string, logo: string|null, methods: string}
     */
    public function branding(?Organization $org): array
    {
        $gateway = $this->gatewayFor($org);

        $config = config("payment-gateways.{$gateway}", []);

        return [
            'key' => $gateway,
            'name' => $config['name'] ?? ucfirst($gateway),
            'tagline' => $config['tagline'] ?? 'Pembayaran dalam talian yang selamat',
            'logo' => isset($config['logo']) ? url($config['logo']) : null,
            'methods' => $config['methods'] ?? 'Pembayaran dalam talian',
        ];
    }

    /**
     * Create a one-time payment for the given (already persisted) Payment row
     * and return the URL to redirect the payer to.
     *
     * The Payment's `gateway`, `gateway_ref` and `gateway_url` columns are set
     * by the underlying service. Returns null on failure (caller should mark
     * the payment failed and show an error).
     */
    public function createPaymentRedirect(
        Organization $org,
        Payment $payment,
        string $payerName,
        string $payerEmail,
        ?string $payerPhone = null,
        string $description = 'Payment',
        string $paymentMethod = 'fpx',
    ): ?string {
        return match ($this->gatewayFor($org)) {
            'senangpay' => $this->senangPay->createPaymentIntent(
                $org,
                $payment,
                $payerName,
                $payerEmail,
                $payerPhone,
                $description,
            ),
            'doku' => $this->doku->createCheckout(
                $org,
                $payment,
                $payerName,
                $payerEmail,
                $payerPhone,
                $description,
            ),
            'bayarcash' => $this->bayarCash->createPaymentIntent(
                $org,
                $payment,
                $payerName,
                $payerEmail,
                $payerPhone,
                $paymentMethod === 'duitnow_qr' ? Bayarcash::DUITNOW_QR : Bayarcash::FPX,
            ),
            default => null,
        };
    }
}
