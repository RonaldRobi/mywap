<?php

namespace App\Models;

use App\Support\Malaysia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'state',
        'address',
        'phone',
        'email',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Malaysia::titleCase($value),
            set: fn ($value) => Malaysia::titleCase($value),
        );
    }

    protected function state(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Malaysia::titleCase($value),
            set: fn ($value) => Malaysia::titleCase($value),
        );
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function branchAdmins(): HasMany
    {
        return $this->hasMany(BranchAdmin::class);
    }

    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_admins')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->adminUsers()->wherePivot('role', BranchAdmin::ROLE_MANAGER);
    }

    public function admins(): BelongsToMany
    {
        return $this->managers();
    }

    public function secretaries(): BelongsToMany
    {
        return $this->adminUsers()->wherePivot('role', BranchAdmin::ROLE_SETIAUSAHA);
    }
}
