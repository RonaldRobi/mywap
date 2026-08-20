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
}
