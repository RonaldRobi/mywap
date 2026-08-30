<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected static function booted(): void
    {
        static::saved(fn () => cache()->forget('app_settings'));
        static::deleted(fn () => cache()->forget('app_settings'));
    }

    protected $fillable = [
        'app_name',
        'og_image_path',
        'login_image_path',
        'mobile_login_title',
        'mobile_login_subtitle',
        'mobile_login_background_start',
        'mobile_login_background_end',
        'mobile_login_accent',
        'system_logo_path',
        'splash_image_path',
        'splash_background_color',
        'splash_title',
        'splash_duration_ms',
        'splash_enabled',
        'chatbot_logo_path',
        'admin_contact_email',
        'admin_contact_phone',
        'resend_api_key',
        'gemini_api_key',
        'mail_from_address',
        'mail_from_name',
        'mail_mailer',
        'mail_smtp_host',
        'mail_smtp_port',
        'mail_smtp_username',
        'mail_smtp_password',
        'mail_smtp_encryption',
        'age_transition_enabled',
        'loading_screen_gif_path',
        'loading_screen_background_start',
        'loading_screen_background_end',
        'loading_screen_duration_ms',
        'loading_screen_enabled',
    ];

    protected function casts(): array
    {
        return [
            'splash_duration_ms' => 'integer',
            'splash_enabled' => 'boolean',
            'age_transition_enabled' => 'boolean',
            'loading_screen_duration_ms' => 'integer',
            'loading_screen_enabled' => 'boolean',
            'resend_api_key' => 'encrypted',
            'mail_smtp_password' => 'encrypted',
        ];
    }

    public static function singleton(): self
    {
        $existing = static::query()->first();

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'app_name' => 'myWAP',
            'system_logo_path' => null,
            'splash_image_path' => null,
            'splash_background_color' => '#0f172a',
            'splash_title' => 'myWAP',
            'splash_duration_ms' => 1800,
            'splash_enabled' => true,
        ]);
    }

    public function getSystemLogoPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    public function getOgImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    public function getSplashImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    public function getChatbotLogoPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    public function getLoadingScreenGifPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
