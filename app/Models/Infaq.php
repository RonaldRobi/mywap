<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Infaq extends Model
{
    use NormalizesStoragePath;

    protected $table = 'infaq';

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'description',
        'image_path',
        'type',
        'target_amount',
        'collected_amount',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'target_amount'    => 'float',
        'collected_amount' => 'float',
        'display_order'    => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(InfaqDonation::class);
    }

    /**
     * Progress percentage capped at 100 (for 'progress' type infaq).
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->type !== 'progress' || ! $this->target_amount || $this->target_amount <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->collected_amount / $this->target_amount) * 100));
    }

    public function getImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($infaq) {
            if (empty($infaq->slug)) {
                $slug = \Illuminate\Support\Str::slug($infaq->title);
                $original = $slug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $original . '-' . $counter++;
                }
                $infaq->slug = $slug;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function getYearAttribute()
    {
        return $this->created_at ? $this->created_at->format('Y') : date('Y');
    }

    public function getMonthAttribute()
    {
        return $this->created_at ? $this->created_at->format('m') : date('m');
    }

    public function getDayAttribute()
    {
        return $this->created_at ? $this->created_at->format('d') : date('d');
    }

    public function getPublicUrlAttribute()
    {
        return route('infaq.show', [
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'infaq' => $this->slug,
        ]);
    }

    protected $appends = ['year', 'month', 'day', 'public_url'];
}
