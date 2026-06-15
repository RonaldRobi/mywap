<?php

namespace App\Actions;

use App\Models\User;

class LoadUsrahForUser
{
    public function execute(User $user): ?array
    {
        $group = $user->usrahGroups()
            ->with(['members' => fn ($query) => $query->withoutGlobalScopes()->select('users.id', 'name')])
            ->first();

        if (! $group) {
            return null;
        }

        $myRole = $group->members->firstWhere('id', $user->id)?->pivot?->role;
        $isLeader = in_array($myRole, ['leader', 'sub_leader']);

        $leader = $group->members->first(fn ($member) => $member->pivot?->role === 'leader');

        return [
            'id' => $group->id,
            'name' => $group->name,
            'meeting_day' => $group->meeting_day,
            'meeting_time' => $group->meeting_time,
            'leader_name' => $leader?->name,
            'is_leader' => $isLeader,
            'members' => $group->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->pivot?->role ?? 'member',
            ])->values(),
        ];
    }
}
