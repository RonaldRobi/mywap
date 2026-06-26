<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory, NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'author_id',
        'title',
        'content',
        'is_pinned',
        'cover_image_path',
        'target_criteria',
        'usrah_group_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function usrahGroup(): BelongsTo
    {
        return $this->belongsTo(UsrahGroup::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnnouncementImage::class)->orderBy('display_order');
    }

    public function coverImageUrl(): ?string
    {
        return $this->normalizeStoragePath($this->cover_image_path);
    }
}
