<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityMedia extends Model
{
    use NormalizesStoragePath;

    protected $fillable = [
        'facility_id',
        'path',
        'type',
        'caption',
        'order',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function getPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
