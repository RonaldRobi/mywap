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

    private function configureMailFromSettings(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        try {
            $setting = AppSetting::singleton();

            if ($key = $setting->resend_api_key) {
                $mailConfig = [
                    'mail.default' => 'resend',
                    'mail.mailers.resend.key' => $key,
                    'services.resend.key' => $key,
                    'resend.api_key' => $key,
                ];

                if ($fromAddress = $setting->mail_from_address) {
                    $mailConfig['mail.from.address'] = $fromAddress;
                    $mailConfig['mail.from.name'] = $setting->mail_from_name ?: config('app.name');
                }

                config($mailConfig);
            }
        } catch (\Throwable) {
            // Silent fail — settings table may not be ready during early boot
        }
    }
}
