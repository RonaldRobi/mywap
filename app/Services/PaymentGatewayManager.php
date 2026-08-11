<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use Webimpian\BayarcashSdk\Bayarcash;

/**
 * Resolves which payment gateway an organisation uses and delegates the
 * "create a one-time payment redirect" call to the correct service.
 *
 * This is the single place that knows how to branch between BayarCash and DOKU
 * for one-time payments (orders, membership fees, one-time infaq donations).
 *
 * Recurring / Direct Debit is intentionally NOT handled here — only BayarCash
 * supports it, and InfaqController calls BayarCashService directly for that.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected BayarCashService $bayarCash,
        protected DokuService $doku,
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
    ): ?string {
        return match ($this->gatewayFor($org)) {
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
                Bayarcash::FPX,
            ),
            default => null,
        };
    }
}
