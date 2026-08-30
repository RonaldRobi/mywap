<?php

namespace App\Providers;

use App\Events\UserOrganizationTransitioned;
use App\Listeners\LogTransitionAndNotify;
use App\Models\AppSetting;
use App\Models\EmailTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureAppName();
        $this->configureMailFromSettings();
        $this->configurePasswordResetMail();

        Vite::prefetch(concurrency: 3);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Event::listen(
            UserOrganizationTransitioned::class,
            LogTransitionAndNotify::class,
        );
    }

    private function configurePasswordResetMail(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $template = EmailTemplate::forKey('password_reset');
            $data = [
                'name' => $notifiable->name,
                'url' => $url,
            ];

            $subject = $template?->renderSubject($data) ?? 'Tetapkan Semula Kata Laluan - myWAP';
            $body = $template?->renderBody($data) ?? "Klik pautan di bawah untuk menetapkan semula kata laluan anda:\n\n{$url}";

            $logoUrl = $template?->headerImageUrl() ?? url(AppSetting::singleton()->system_logo_path ?? '/images/logomywaphorizontal.png');

            return (new MailMessage)
                ->subject($subject)
                ->view('emails.password-reset', [
                    'subject' => $subject,
                    'body' => $body,
                    'name' => $data['name'],
                    'url' => $url,
                    'logoUrl' => $logoUrl,
                    'appName' => config('app.name'),
                ]);
        });
    }

    private function configureAppName(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        try {
            $setting = AppSetting::singleton();

            if ($name = $setting->app_name) {
                config(['app.name' => $name]);
            }
        } catch (\Throwable) {
            // Silent fail
        }
    }

    private function configureMailFromSettings(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        try {
            $setting = AppSetting::singleton();
            $mailer = $setting->mail_mailer ?: 'log';
            $mailConfig = [];

            if ($mailer === 'resend' && $key = $setting->resend_api_key) {
                $mailConfig = [
                    'mail.default' => 'resend',
                    'mail.mailers.resend.key' => $key,
                    'services.resend.key' => $key,
                    'resend.api_key' => $key,
                ];

                $this->configureSmtpMailer($mailConfig, $setting);

                // Prefer failover (Resend → SMTP) when a backup SMTP is configured,
                // so essential mail still goes out when the Resend daily quota runs out.
                if ($setting->mail_smtp_host) {
                    $mailConfig['mail.default'] = 'failover';
                }
            } elseif ($mailer === 'smtp') {
                $mailConfig = ['mail.default' => 'smtp'];

                $this->configureSmtpMailer($mailConfig, $setting);
            } elseif ($mailer === 'log') {
                $mailConfig = ['mail.default' => 'log'];
            }

            if ($fromAddress = $setting->mail_from_address) {
                $mailConfig['mail.from.address'] = $fromAddress;
                $mailConfig['mail.from.name'] = $setting->mail_from_name ?: config('app.name');
            }

            config($mailConfig);
        } catch (\Throwable) {
            // Silent fail — settings table may not be ready during early boot
        }
    }

    private function configureSmtpMailer(array &$mailConfig, AppSetting $setting): void
    {
        $host = $setting->mail_smtp_host ?: config('mail.mailers.smtp.host');
        $mailConfig['mail.mailers.smtp.host'] = $host;
        $mailConfig['mail.mailers.smtp.port'] = $setting->mail_smtp_port ?: config('mail.mailers.smtp.port');
        $mailConfig['mail.mailers.smtp.username'] = $setting->mail_smtp_username ?: config('mail.mailers.smtp.username');
        $mailConfig['mail.mailers.smtp.password'] = $setting->mail_smtp_password ?: config('mail.mailers.smtp.password');

        // Local mail server (Postfix/Dovecot on the same VPS) uses a self-signed
        // cert; disable peer verification so STARTTLS succeeds.
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $mailConfig['mail.mailers.smtp.verify_peer'] = false;
            $mailConfig['mail.mailers.smtp.scheme'] = null;
        }

        $encryption = $setting->mail_smtp_encryption ?: config('mail.mailers.smtp.encryption');
        if ($encryption && $encryption !== 'null') {
            $mailConfig['mail.mailers.smtp.encryption'] = $encryption;
        }
    }
}
