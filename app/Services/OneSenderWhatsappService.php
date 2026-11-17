<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSenderWhatsappService
{
    public function sendOtp(string $phone, string $otp): bool
    {
        $settings = AppSetting::singleton();
        $url = $settings->onesender_url ?: config('services.onesender.url');
        $key = $settings->onesender_api_key ?: config('services.onesender.key');
        $contact = $settings->onesender_contact ?: config('services.onesender.contact', '+601157452119');

        if (! $url || ! $key) {
            Log::warning('OneSender OTP is not configured.');

            return false;
        }

        $message = "Kod OTP myWAP: *{$otp}*\n\n"
            ."Assalamualaikum. Kod ini untuk pengesahan log masuk myWAP dan sah selama 5 minit.\n\n"
            ."Jika anda tidak menerima kod atau menghadapi masalah, sila hubungi {$contact}.\n\n"
            ."Jangan kongsi kod ini dengan sesiapa.";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer '.$key])
                ->post($url, [
                    'recipient_type' => 'individual',
                    'to' => $this->normalizePhone($phone),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful() && (int) $response->json('code') === 200) {
                return true;
            }

            Log::warning('OneSender returned an unexpected response.', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('OneSender OTP request failed.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    public function sendResetLink(string $phone, string $url): bool
    {
        $settings = AppSetting::singleton();
        $apiUrl = $settings->onesender_url ?: config('services.onesender.url');
        $key = $settings->onesender_api_key ?: config('services.onesender.key');
        $contact = $settings->onesender_contact ?: config('services.onesender.contact', '+601157452119');

        if (! $apiUrl || ! $key) {
            Log::warning('OneSender password reset is not configured.');

            return false;
        }

        $message = "Assalamualaikum.\n\n"
            ."Pautan untuk menetapkan semula kata laluan myWAP anda:\n\n"
            ."{$url}\n\n"
            ."Pautan ini digunakan sekali sahaja dan mempunyai tempoh luput.\n\n"
            ."Jika anda tidak meminta reset kata laluan, abaikan mesej ini.\n\n"
            ."Jika tidak menerima pautan atau menghadapi masalah, sila hubungi {$contact}.";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer '.$key])
                ->post($apiUrl, [
                    'recipient_type' => 'individual',
                    'to' => $this->normalizePhone($phone),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful() && (int) $response->json('code') === 200;
        } catch (\Throwable $exception) {
            Log::error('OneSender password reset request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        return $digits;
    }
}
