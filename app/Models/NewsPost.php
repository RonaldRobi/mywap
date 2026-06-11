<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsPost extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'author_id',
        'organization_id',
        'news_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image_path',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (NewsPost $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title) . '-' . Str::lower(Str::random(6));
            }

            if ($post->is_published && empty($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(NewsPostReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsPostComment::class)->latest();
    }

    public function getCoverImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
