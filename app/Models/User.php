<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\OrganizationScope;
use App\Support\NormalizesStoragePath;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * User
 *
 * The central actor in the MyMarhalah ecosystem.
 * Multi-tenancy is enforced via OrganizationScope, which automatically restricts
 * all Eloquent queries to users sharing the same current_organization_id as the
 * authenticated user — unless they hold the 'Superadmin' role.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $dob Date of Birth — drives the Age Transition Engine
 * @property string|null $phone
 * @property int|null $current_organization_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    use NormalizesStoragePath;

    public const IC_VALIDATION_REGEX = '/^(?:\d{12}|(?=.*[A-Z])[A-Z0-9]{6,32})$/';

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
        'is_active',
        'membership_status',
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
        'position_id',
        'topics',
        'referred_by_user_id',
        'first_login_at',
        'membership_letter_sent_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'profile_completed_at' => 'datetime',
            'first_login_at' => 'datetime',
            'membership_letter_sent_at' => 'datetime',
            'is_public_in_directory' => 'boolean',
            'membership_status' => 'string',
            'key_in_date' => 'date',
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
        static::addGlobalScope(new OrganizationScope);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => self::formatName($value),
        );
    }

    protected function icNumber(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => self::normalizeIc($value),
        );
    }

    public static function normalizeIc(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = strtoupper(str_replace(['-', ' '], '', $raw));

        if (preg_match('/^(\d{12})\.0$/', $raw, $matches)) {
            return $matches[1];
        }

        $digits = (string) preg_replace('/\D/', '', $raw);

        return strlen($digits) === 12 ? $digits : $raw;
    }

    public static function normalizeLoginId(string $identifier): string
    {
        $raw = trim($identifier);

        if (str_contains($raw, '@')) {
            return strtoupper($raw);
        }

        return self::normalizeIc($raw) ?? strtoupper($raw);
    }

    public static function formatName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $name === '' ? $name : mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    public function isDeceased(): bool
    {
        return $this->membership_status === 'deceased';
    }

    public static function icExistsInOrganization(string $ic, ?int $organizationId, ?int $exceptUserId = null): bool
    {
        $normalized = self::normalizeIc($ic);
        if ($normalized === null) {
            return false;
        }

        return self::withoutGlobalScope(OrganizationScope::class)
            ->where('current_organization_id', $organizationId)
            ->where('ic_number', $normalized)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->exists();
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

    public function branchAdminAssignments(): HasMany
    {
        return $this->hasMany(BranchAdmin::class);
    }

    public function managedBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_admins')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function organizationPosition(): BelongsTo
    {
        return $this->belongsTo(OrganizationPosition::class, 'position_id');
    }

    /**
     * Jawatan yang layak dipaparkan sebagai badge pada profil dan kad digital.
     *
     * Badge hanya keluar jika jawatan aktif, ditanda `show_badge`, dan milik
     * organisasi semasa ahli. Jawatan biasa seperti "Ahli Biasa" tidak
     * dipaparkan.
     */
    public function badgePosition(): ?OrganizationPosition
    {
        if (! $this->position_id) {
            return null;
        }

        $position = $this->relationLoaded('organizationPosition')
            ? $this->organizationPosition
            : $this->organizationPosition()->first();

        if (! $position || ! $position->is_active || ! $position->show_badge) {
            return null;
        }

        if ((int) $position->organization_id !== (int) $this->current_organization_id) {
            return null;
        }

        return $position;
    }

    public function positionBadgePayload(): ?array
    {
        if ($this->isDeceased()) {
            return [
                'id' => null,
                'name' => 'Meninggal Dunia',
                'category' => 'Status',
                'color' => '#6b7280',
            ];
        }

        $position = $this->badgePosition();

        if (! $position) {
            return null;
        }

        return [
            'id' => $position->id,
            'name' => $position->name,
            'category' => $position->category,
            'color' => $position->color,
        ];
    }

    public function branchChangeRequests(): HasMany
    {
        return $this->hasMany(BranchChangeRequest::class);
    }

    public function branchTransitionHistory(): HasMany
    {
        return $this->hasMany(BranchTransitionHistory::class)->latest();
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
            ->withPivot(['role', 'is_naqib', 'joined_at'])
            ->withTimestamps();
    }

    public function ledUsrahGroups(): BelongsToMany
    {
        return $this->belongsToMany(UsrahGroup::class, 'usrah_group_user')
            ->withPivot(['role', 'is_naqib', 'joined_at'])
            ->wherePivotIn('role', ['leader', 'sub_leader'])
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

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Abilities untuk token Sanctum mobile.
     *
     * Semua token diberi ability asas 'member'; token bagi akaun dengan peranan
     * pentadbir diberi ability tambahan 'admin' supaya route /admin/* boleh
     * disekat di lapisan routing, bukan sahaja di dalam controller.
     *
     * NOTA: abilities ditentukan semasa token dikeluarkan. Jika peranan berubah
     * selepas itu, pengguna perlu log masuk semula untuk token baharu.
     */
    public function apiTokenAbilities(): array
    {
        $abilities = ['member'];

        if ($this->isAdminPanelUser()) {
            $abilities[] = 'admin';
        }

        return $abilities;
    }

    /**
     * Adakah pengguna ini Superadmin?
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('Superadmin');
    }

    /**
     * Admin organisasi (`Admin`), termasuk alias lama `org-admin`.
     */
    public function isOrgAdmin(): bool
    {
        return $this->hasRole(['Admin', 'org-admin']);
    }

    /**
     * Admin cawangan: role `Admin Cawangan` tanpa peranan admin organisasi.
     */
    public function isBranchAdmin(): bool
    {
        return $this->hasRole('Admin Cawangan')
            && ! $this->hasRole(['Superadmin', 'Admin', 'org-admin']);
    }

    /**
     * Cawangan yang diuruskan oleh admin cawangan.
     */
    public function managedBranchId(): ?int
    {
        if (! $this->hasRole('Admin Cawangan')) {
            return null;
        }

        $assignment = $this->relationLoaded('branchAdminAssignments')
            ? $this->branchAdminAssignments->firstWhere('role', BranchAdmin::ROLE_MANAGER)
            : $this->branchAdminAssignments()
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->first();

        return $assignment?->branch_id ?? $this->branch_id;
    }

    /**
     * Sesiapa yang boleh masuk panel admin (Superadmin, Admin organisasi atau Admin cawangan).
     */
    public function isAdminPanelUser(): bool
    {
        return $this->isSuperadmin() || $this->isOrgAdmin() || $this->isBranchAdmin();
    }

    public function canManageBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return false;
        }

        if ($this->isSuperadmin()) {
            return true;
        }

        if ($this->isBranchAdmin()) {
            return (int) $this->managedBranchId() === (int) $branchId;
        }

        return $this->isOrgAdmin()
            && Branch::query()
                ->whereKey($branchId)
                ->where('organization_id', $this->current_organization_id)
                ->exists();
    }

    /**
     * Akaun staf mengurus katalog/pesanan dan tidak membeli melalui mall.
     * Mereka boleh log keluar atau guna akaun ahli untuk membeli.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole([
            'Superadmin',
            'Admin',
            'org-admin',
            'Admin Cawangan',
        ]);
    }

    /**
     * Bolehkah pengguna ini membuat pesanan mall sendiri?
     */
    public function canPurchase(): bool
    {
        return ! $this->isStaff();
    }

    /**
     * Superadmin boleh urus semua organisasi; Admin hanya organisasi sendiri.
     */
    public function canManageOrganization(?int $organizationId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->isOrgAdmin()
            && $organizationId !== null
            && (int) $this->current_organization_id === (int) $organizationId;
    }

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
     * Emel placeholder yang dijana semasa import ahli lama (cth.
     * "900101011234@mywap.my") bukan peti emel sebenar. OTP / pautan reset yang
     * dihantar ke alamat ini tidak akan sampai kepada ahli, jadi ia perlu
     * dilayan sebagai "tiada emel" supaya ahli diminta memasukkan emel sebenar.
     *
     * Pengesanan: bahagian tempatan (sebelum @) sama dengan no. IC ahli (tanpa
     * pedulikan huruf besar/kecil), dan domain ialah @mywap.my.
     */
    public function emailIsPlaceholder(): bool
    {
        $email = $this->email;

        if (! $email || ! $this->ic_number || ! str_ends_with(strtolower($email), '@mywap.my')) {
            return false;
        }

        $local = explode('@', $email, 2)[0] ?? '';

        return strtoupper(trim($local)) === strtoupper(trim($this->ic_number));
    }

    /**
     * Parse DOB from Malaysian IC number (YYMMDD-PB-XXXX).
     * Returns YYYY-MM-DD string or null.
     */
    public static function parseDobFromIc(?string $ic): ?string
    {
        if (! $ic) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $ic);
        if (strlen($digits) < 6) {
            return null;
        }

        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);

        if (! checkdate($mm, $dd, 2000)) {
            return null;
        }

        // Two-digit year is a sliding 100-year window ending at the current
        // year: "00" => 2000, and in 2026 "26" => 2026 while "27" => 1927.
        // Deriving the cutoff from the current date avoids a stale hardcoded
        // pivot that misparses the newest birth years every January.
        $yyyy = 2000 + $yy;
        if ($yyyy > (int) date('Y')) {
            $yyyy -= 100;
        }

        return sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd);
    }

    /**
     * Guess gender from Malaysian IC number.
     * Last digit of 12-digit IC: odd = lelaki, even = perempuan.
     */
    public static function guessGenderFromIc(?string $ic): ?string
    {
        if (! $ic) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $ic);
        if (strlen($digits) < 11) {
            return null;
        }

        $lastDigit = (int) substr($digits, -1);

        return $lastDigit % 2 === 1 ? 'lelaki' : 'perempuan';
    }
}
