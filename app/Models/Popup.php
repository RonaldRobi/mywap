<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Popup extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'title',
        'content',
        'image_path',
        'button_text',
        'button_url',
        'button_text_2',
        'button_url_2',
        'popup_size',
        'is_active',
        'display_order',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
