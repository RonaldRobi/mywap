<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Model;

class OnboardingSlide extends Model
{
    use NormalizesStoragePath;

    protected $fillable = ['slide_order', 'title', 'body', 'button_label', 'button_url', 'background_start', 'background_end', 'text_color', 'media_path', 'media_type', 'is_active'];

    protected function casts(): array
    {
        return ['slide_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function getMediaPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
