<?php

namespace App\Support;

use App\Models\AppSetting;

class MailConfig
{
    public static function build(AppSetting $setting): array
    {
        $config = [];
        $hasResend = filled($setting->resend_api_key);
        $hasSmtp = filled($setting->mail_smtp_host);

        if ($hasResend) {
            $config['mail.mailers.resend.key'] = $setting->resend_api_key;
            $config['services.resend.key'] = $setting->resend_api_key;
            $config['resend.api_key'] = $setting->resend_api_key;
        }

        self::configureSmtp($config, $setting);

        $config['mail.default'] = match ($setting->mail_mailer) {
            'resend' => $hasResend ? 'resend' : 'log',
            'log' => 'log',
            default => 'smtp',
        };

        if ($setting->mail_mailer === 'log') {
            $config['mail.mailers.transactional'] = ['transport' => 'log'];
        } elseif ($hasResend) {
            $config['mail.mailers.transactional'] = [
                'transport' => 'failover',
                'mailers' => $hasSmtp ? ['resend', 'smtp'] : ['resend'],
                'retry_after' => 60,
            ];
        } else {
            $config['mail.mailers.transactional'] = ['transport' => 'smtp'];
        }

        if ($setting->mail_from_address) {
            $config['mail.from.address'] = $setting->mail_from_address;
            $config['mail.from.name'] = $setting->mail_from_name ?: config('app.name');
        }

        return $config;
    }

    private static function configureSmtp(array &$config, AppSetting $setting): void
    {
        $host = $setting->mail_smtp_host ?: config('mail.mailers.smtp.host');
        $config['mail.mailers.smtp.host'] = $host;
        $config['mail.mailers.smtp.port'] = $setting->mail_smtp_port ?: config('mail.mailers.smtp.port');
        $config['mail.mailers.smtp.username'] = $setting->mail_smtp_username ?: config('mail.mailers.smtp.username');
        $config['mail.mailers.smtp.password'] = $setting->mail_smtp_password ?: config('mail.mailers.smtp.password');

        // Local mail server (Postfix/Dovecot on the same VPS) uses a self-signed
        // cert; disable peer verification so STARTTLS succeeds.
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $config['mail.mailers.smtp.verify_peer'] = false;
            $config['mail.mailers.smtp.scheme'] = null;
        }

        $encryption = $setting->mail_smtp_encryption ?: config('mail.mailers.smtp.encryption');
        if ($encryption && $encryption !== 'null') {
            $config['mail.mailers.smtp.encryption'] = $encryption;
        }
    }
}
