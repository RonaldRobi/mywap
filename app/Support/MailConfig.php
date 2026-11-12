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

        $isLog = $setting->mail_mailer === 'log';

        // Resend hanya untuk emel akaun (OTP, reset kata laluan, pendaftaran).
        // Semua emel lain — siaran, promosi, pesanan, borang — guna SMTP.
        $config['mail.default'] = $isLog ? 'log' : 'smtp';

        // Siaran (blast) emel mesti guna SMTP sendiri, bukan Resend. Kuota
        // Resend dikhaskan untuk OTP dan tetapan semula kata laluan.
        $config['mail.mailers.bulk'] = $isLog
            ? ['transport' => 'log']
            : $config['mail.mailers.smtp'];

        if ($isLog) {
            $config['mail.mailers.transactional'] = ['transport' => 'log'];
        } elseif ($hasResend && $setting->mail_mailer === 'resend') {
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
        $host = $setting->mail_smtp_host ?: env('MAIL_HOST', '127.0.0.1');

        $smtp = [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => $host,
            'port' => $setting->mail_smtp_port ?: env('MAIL_PORT', 2525),
            'username' => $setting->mail_smtp_username ?: env('MAIL_USERNAME'),
            'password' => $setting->mail_smtp_password ?: env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ];

        // Local mail server (Postfix/Dovecot on the same VPS) uses a self-signed
        // cert; disable peer verification so STARTTLS succeeds.
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $smtp['verify_peer'] = false;
            $smtp['scheme'] = null;
        }

        $encryption = $setting->mail_smtp_encryption ?: env('MAIL_ENCRYPTION');
        if ($encryption && $encryption !== 'null') {
            $smtp['encryption'] = $encryption;
        }

        $config['mail.mailers.smtp'] = $smtp;
    }
}
