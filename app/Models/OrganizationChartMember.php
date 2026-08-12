<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrganizationChartMember
 *
 * An entry in an organisation's "Carta Organisasi" (org chart).
 * Each entry carries a photo, name, position and a mailto email so the
 * front-end can render clickable contact cards scoped to the current org.
 */
class OrganizationChartMember extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'organization_id',
        'name',
        'position',
        'email',
        'image_path',
        'display_order',
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
