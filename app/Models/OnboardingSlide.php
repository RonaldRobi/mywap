<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Model;

class OnboardingSlide extends Model
{
    use NormalizesStoragePath;

    protected $fillable = ['slide_order', 'title', 'body', 'button_label', 'button_url', 'background_start', 'background_end', 'text_color', 'overlay_start_color', 'overlay_end_color', 'overlay_start_opacity', 'overlay_end_opacity', 'overlay_start_position', 'overlay_end_position', 'media_path', 'media_type', 'is_active'];

    protected function casts(): array
    {
        return ['slide_order' => 'integer', 'overlay_start_opacity' => 'integer', 'overlay_end_opacity' => 'integer', 'overlay_start_position' => 'integer', 'overlay_end_position' => 'integer', 'is_active' => 'boolean'];
    }

    public function getMediaPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
