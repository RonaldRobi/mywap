<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsrahGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'meeting_day',
        'meeting_time',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usrah_group_user')
            ->withPivot(['role', 'is_naqib', 'joined_at'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(UsrahAttendance::class);
    }

    public function leaders(): BelongsToMany
    {
        return $this->members()->wherePivotIn('role', ['leader', 'sub_leader']);
    }

    public function naqib(): ?User
    {
        return $this->members()->wherePivot('role', 'leader')->first();
    }
}
