<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UserOrganizationTransitioned
 *
 * Fired by the ProcessAgeTransitions command (and any manual override) whenever
 * a member's current_organization_id changes.  Listeners receive the full User
 * model plus both the old and new organization IDs so they can log, notify, and
 * update permissions in a single, decoupled pass.
 */
class UserOrganizationTransitioned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  User  $user  The member being transitioned.
     * @param  int|null  $fromOrganizationId  Previous NGO (null for first-time joins).
     * @param  int  $toOrganizationId  New NGO after the transition.
     * @param  string|null  $fromMemberNo  No. ahli lama, disimpan sebagai sejarah.
     * @param  string|null  $toMemberNo  No. ahli baharu dalam NGO sasaran.
     */
    public function __construct(
        public readonly User $user,
        public readonly ?int $fromOrganizationId,
        public readonly int $toOrganizationId,
        public readonly ?string $fromMemberNo = null,
        public readonly ?string $toMemberNo = null,
    ) {}

    /**
     * No real-time broadcast needed at this stage — omit channels.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
