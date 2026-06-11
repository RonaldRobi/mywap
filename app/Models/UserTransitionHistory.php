<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserTransitionHistory
 *
 * Immutable audit log of every NGO tier change for a member.
 * Records are written by the LogTransitionAndNotify listener and read by
 * the Profile Journey Timeline.
 *
 * This model deliberately has NO global scopes — it is a reporting table
 * and must be queryable by Superadmins across all organizations.
 *
 * @property int            $id
 * @property int            $user_id
 * @property int|null       $from_organization_id
 * @property int            $to_organization_id
 * @property \Carbon\Carbon $transitioned_at
 */
class UserTransitionHistory extends Model
{
    protected $fillable = [
        'user_id',
        'from_organization_id',
        'to_organization_id',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'transitioned_at' => 'datetime',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_organization_id');
    }

    public function toOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_organization_id');
    }
}
