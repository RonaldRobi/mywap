<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryItem extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'file_path',
        'cover_image_path',
        'category',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getFilePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    public function getCoverImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
