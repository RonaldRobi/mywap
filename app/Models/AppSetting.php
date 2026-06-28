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
    ];

    protected function casts(): array
    {
        return [
            'splash_duration_ms' => 'integer',
            'splash_enabled' => 'boolean',
            'resend_api_key' => 'encrypted',
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
}
