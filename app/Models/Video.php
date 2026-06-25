<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'youtube_url',
        'youtube_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return "https://img.youtube.com/vi/{$this->youtube_id}/hqdefault.jpg";
    }

    public function getEmbedUrlAttribute(): string
    {
        return "https://www.youtube.com/embed/{$this->youtube_id}";
    }
}
