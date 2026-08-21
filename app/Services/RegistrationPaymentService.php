<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;

/**
 * Shared post-payment side effects for Event registrations, so every gateway
 * callback (BayarCash / DOKU / senangPay) confirms the registration and sends
 * the confirmation email exactly once.
 */
class RegistrationPaymentService
{
    public function confirmRegistration(Payment $payment): void
    {
        if ($payment->payable_type !== Registration::class) {
            return;
        }

        $registration = $payment->payable;

        if (! $registration) {
            return;
        }

        $registration->confirmAndNotify();
    }

    /**
     * Rekonsiliasi bayaran DOKU yang masih "pending" dengan status sebenar di
     * DOKU. Digunakan apabila halaman terima kasih / status pendaftaran dibuka,
     * supaya bayaran yang sudah berjaya di DOKU tidak kekal "Menunggu" jika
     * webhook tidak sampai.
     */
    public function reconcileDokuPayment(Payment $payment): void
    {
        if ($payment->status !== 'pending'
            || $payment->gateway !== 'doku'
            || ! $payment->gateway_ref
            || ! $payment->organization
            || ! $payment->organization->hasDokuConfig()) {
            return;
        }

        $doku = app(DokuService::class);
        $status = $doku->retrieveStatus($payment->organization, $payment->gateway_ref);

        if ($doku->isSuccessStatus($status)) {
            $payment->update(['status' => 'successful']);
            $this->confirmRegistration($payment);
        } elseif ($doku->isFailedStatus($status)) {
            $payment->update(['status' => 'failed']);
        }
    }
}
