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
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $color_theme
 * @property int $min_age
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
        'description',
        'logo_path',
        'sort_order',
        'min_age',
        'max_age',
        'fee_amount',
        'bayarcash_api_token',
        'bayarcash_portal_key',
        'bayarcash_secret_key',
        'bayarcash_environment',
        'payment_gateway',
        'doku_client_id',
        'doku_api_key',
        'doku_secret_key',
        'doku_environment',
        'senangpay_merchant_id',
        'senangpay_secret_key',
        'senangpay_environment',
        'website_url',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'tiktok_url',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            // DOKU credentials are sensitive: encrypt at rest. BayarCash keys
            // stay as-is to avoid breaking existing plaintext-stored values.
            'doku_api_key' => 'encrypted',
            'doku_secret_key' => 'encrypted',
            'senangpay_secret_key' => 'encrypted',
        ];
    }

    public function hasBayarCashConfig(): bool
    {
        return filled($this->bayarcash_api_token)
            && filled($this->bayarcash_portal_key)
            && filled($this->bayarcash_secret_key);
    }

    public function hasDokuConfig(): bool
    {
        return filled($this->doku_client_id)
            && filled($this->doku_api_key)
            && filled($this->doku_secret_key);
    }

    public function hasSenangPayConfig(): bool
    {
        return filled($this->senangpay_merchant_id)
            && filled($this->senangpay_secret_key);
    }

    /**
     * Resolve which gateway this organisation actively collects money through.
     *
     * Priority:
     *  1. Explicit `payment_gateway` selection (if that gateway is configured).
     *  2. Whichever gateway happens to be fully configured.
     *  3. null when nothing is configured (caller falls back to "dummy").
     */
    public function activeGateway(): ?string
    {
        $selected = $this->payment_gateway;

        if ($selected === 'senangpay' && $this->hasSenangPayConfig()) {
            return 'senangpay';
        }

        if ($selected === 'doku' && $this->hasDokuConfig()) {
            return 'doku';
        }

        if ($selected === 'bayarcash' && $this->hasBayarCashConfig()) {
            return 'bayarcash';
        }

        // No explicit (or misconfigured) selection: use whatever is ready.
        if ($this->hasSenangPayConfig()) {
            return 'senangpay';
        }

        if ($this->hasDokuConfig()) {
            return 'doku';
        }

        if ($this->hasBayarCashConfig()) {
            return 'bayarcash';
        }

        return null;
    }

    /**
     * Whether recurring / direct-debit donations are supported for this org.
     * Only BayarCash FPX Direct Debit supports recurring in this app.
     */
    public function supportsRecurring(): bool
    {
        return $this->activeGateway() === 'bayarcash';
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

    public function positions(): HasMany
    {
        return $this->hasMany(OrganizationPosition::class)->orderBy('display_order');
    }

    public function chartMembers(): HasMany
    {
        return $this->hasMany(OrganizationChartMember::class)->orderBy('display_order');
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
