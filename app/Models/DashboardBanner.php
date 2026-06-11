<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardBanner extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'title',
        'image_path',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
