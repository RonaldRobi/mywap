<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\OrganizationScope;
use App\Support\NormalizesStoragePath;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * User
 *
 * The central actor in the MyMarhalah ecosystem.
 * Multi-tenancy is enforced via OrganizationScope, which automatically restricts
 * all Eloquent queries to users sharing the same current_organization_id as the
 * authenticated user — unless they hold the 'Superadmin' role.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string|null $dob       Date of Birth — drives the Age Transition Engine
 * @property string|null $phone
 * @property int|null    $current_organization_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    use NormalizesStoragePath;

    // ─── Mass Assignment ──────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'email',
        'ic_number',
        'password',
        'dob',
        'phone',
        'current_organization_id',
        'branch_id',
        'profile_completed_at',
        'education_level',
        'current_profession',
        'industry',
        'branch_name',
        'locality',
        'profile_photo_path',
        'expertise',
        'linkedin_url',
        'is_public_in_directory',
        'member_no',
        'member_no_sequence',
        'original_member_no',
        'wadah_state',
        'key_in_date',
        'registration_year',
        'birth_year',
        'address_1',
        'address_2',
        'postcode',
        'city',
        'state',
        'office_phone',
        'home_phone',
        'fax_number',
        'legacy_update_note',
        'notes',
        'gender',
        'marital_status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'position',
        'topics',
        'referred_by_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'dob'               => 'date',
            'profile_completed_at' => 'datetime',
            'is_public_in_directory' => 'boolean',
            'key_in_date'       => 'date',
        ];
    }

    // ─── Global Scopes ────────────────────────────────────────────────────────

    /**
     * Boot the model and register the OrganizationScope.
     *
     * Architectural decision: we attach the scope here (in booted) rather than
     * in a service provider so that the constraint travels with the model and is
     * impossible to forget when the model is used in new contexts (queues, CLI,
     * API resources, etc.).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The NGO tier this user currently belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referredMembers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    /**
     * Full chronological history of all NGO tier transitions.
     * Ordered ascending so the Profile Timeline renders oldest-first.
     */
    public function transitionHistory()
    {
        return $this->hasMany(UserTransitionHistory::class)
                    ->with(['fromOrganization', 'toOrganization'])
                    ->orderBy('transitioned_at', 'asc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function usrahGroups(): BelongsToMany
    {
        return $this->belongsToMany(UsrahGroup::class, 'usrah_group_user')
            ->withPivot(['is_naqib', 'joined_at'])
            ->withTimestamps();
    }

    public function ledUsrahGroups(): BelongsToMany
    {
        return $this->belongsToMany(UsrahGroup::class, 'usrah_group_user')
            ->withPivot(['is_naqib', 'joined_at'])
            ->wherePivot('is_naqib', true)
            ->withTimestamps();
    }

    public function facilityBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function membershipFees(): HasMany
    {
        return $this->hasMany(MembershipFee::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Compute age in full years from the stored DOB.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    public function getProfilePhotoPathAttribute($value): ?string
    {
        return $this->normalizeStoragePath($value);
    }

    /**
     * Parse DOB from Malaysian IC number (YYMMDD-PB-XXXX).
     * Returns YYYY-MM-DD string or null.
     */
    public static function parseDobFromIc(?string $ic): ?string
    {
        if (! $ic) return null;

        $digits = preg_replace('/[^0-9]/', '', $ic);
        if (strlen($digits) < 6) return null;

        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);

        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) return null;

        $yyyy = $yy > 25 ? 1900 + $yy : 2000 + $yy;

        return sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd);
    }

    /**
     * Guess gender from Malaysian IC number.
     * Last digit of 12-digit IC: odd = lelaki, even = perempuan.
     */
    public static function guessGenderFromIc(?string $ic): ?string
    {
        if (! $ic) return null;

        $digits = preg_replace('/[^0-9]/', '', $ic);
        if (strlen($digits) < 12) return null;

        $lastDigit = (int) substr($digits, -1);

        return $lastDigit % 2 === 1 ? 'lelaki' : 'perempuan';
    }
}
