<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'system_logo_path',
        'splash_image_path',
        'splash_background_color',
        'splash_title',
        'splash_duration_ms',
        'splash_enabled',
    ];

    protected function casts(): array
    {
        return [
            'splash_duration_ms' => 'integer',
            'splash_enabled' => 'boolean',
        ];
    }

    public static function singleton(): self
    {
        $existing = static::query()->first();

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
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

    public function getSplashImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
