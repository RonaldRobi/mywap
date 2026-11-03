<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationPosition extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'category',
        'parent_id',
        'display_order',
        'is_active',
        'show_badge',
        'color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_badge' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function memberCount(): int
    {
        return User::where('current_organization_id', $this->organization_id)
            ->where('position_id', $this->id)
            ->count();
    }
}
