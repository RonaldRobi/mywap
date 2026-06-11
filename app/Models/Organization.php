<?php

namespace App\Models;

use App\Support\NormalizesStoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organization
 *
 * Represents one of the three NGO tiers: PKPIM (< 20), ABIM (20-29), WADAH (30+).
 * The slug and color_theme columns power dynamic routing and UI accent theming.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string|null $color_theme
 * @property int    $min_age
 * @property int|null $max_age
 */
class Organization extends Model
{
    use HasFactory;
    use NormalizesStoragePath;

    protected $fillable = [
        'name',
        'slug',
        'color_theme',
        'logo_path',
        'sort_order',
        'min_age',
        'max_age',
        'fee_amount',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────────────────

    /**
     * All users whose current NGO is this organization.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'current_organization_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function libraryItems(): HasMany
    {
        return $this->hasMany(LibraryItem::class);
    }

    public function usrahGroups(): HasMany
    {
        return $this->hasMany(UsrahGroup::class);
    }

    public function broadcastMessages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Resolve the correct Organization for a given age.
     * Used by the Age Transition Engine when migrating a member.
     */
    public static function forAge(int $age): ?self
    {
        return static::where('min_age', '<=', $age)
            ->where(function ($q) use ($age) {
                $q->whereNull('max_age')->orWhere('max_age', '>=', $age);
            })
            ->first();
    }

    public function getLogoPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }
}
