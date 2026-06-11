<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
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
     * @param  \App\Models\User  $user               The member being transitioned.
     * @param  int|null          $fromOrganizationId  Previous NGO (null for first-time joins).
     * @param  int               $toOrganizationId    New NGO after the transition.
     */
    public function __construct(
        public readonly \App\Models\User $user,
        public readonly ?int $fromOrganizationId,
        public readonly int $toOrganizationId,
    ) {}

    /**
     * No real-time broadcast needed at this stage — omit channels.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
