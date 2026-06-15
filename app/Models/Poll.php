<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'type',
        'target_type',
        'ends_at',
        'show_results',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'show_results' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PollQuestion::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    public function targetMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'poll_target_members');
    }

    public function targetUsrahGroups(): BelongsToMany
    {
        return $this->belongsToMany(UsrahGroup::class, 'poll_target_usrah_groups');
    }

    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->is_active && !$this->isExpired();
    }
}
