<?php

namespace App\Providers;

use App\Events\UserOrganizationTransitioned;
use App\Listeners\LogTransitionAndNotify;
use App\Models\AppSetting;
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

        Vite::prefetch(concurrency: 3);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Event::listen(
            UserOrganizationTransitioned::class,
            LogTransitionAndNotify::class,
        );
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
            } elseif ($mailer === 'smtp') {
                $mailConfig = [
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $setting->mail_smtp_host ?: config('mail.mailers.smtp.host'),
                    'mail.mailers.smtp.port' => $setting->mail_smtp_port ?: config('mail.mailers.smtp.port'),
                    'mail.mailers.smtp.encryption' => $setting->mail_smtp_encryption ?: config('mail.mailers.smtp.encryption'),
                    'mail.mailers.smtp.username' => $setting->mail_smtp_username ?: config('mail.mailers.smtp.username'),
                    'mail.mailers.smtp.password' => $setting->mail_smtp_password ?: config('mail.mailers.smtp.password'),
                ];
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
}
