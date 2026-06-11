<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'location',
        'type',
        'price_per_unit',
        'capacity',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function getImagePathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
