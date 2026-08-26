<?php

namespace App\Services;

use App\Models\User;
use App\Models\UsrahAttendance;
use App\Models\UsrahGroup;

/**
 * UsrahService
 *
 * Logik tunggal untuk domain Usrah — dikongsi oleh UsrahController (Inertia)
 * dan Api\V1\UsrahController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §4.
 */
class UsrahService
{
    public function serializeGroup(UsrahGroup $group, int $authUserId): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'meeting_day' => $group->meeting_day,
            'meeting_time' => $group->meeting_time,
            'is_leader' => in_array($group->members->firstWhere('id', $authUserId)?->pivot?->role, ['leader', 'sub_leader']),
            'members' => $group->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->pivot->role ?? 'member',
            ])->values(),
        ];
    }

    /**
     * Payload untuk halaman "Usrah Saya" (myGroup web & API).
     */
    public function myGroup(User $user): array
    {
        $groups = $user->usrahGroups()
            ->with(['members' => fn ($q) => $q->withoutGlobalScopes()->select('users.id', 'name')])
            ->get();

        $groupsData = $groups->map(fn (UsrahGroup $group) => $this->serializeGroup($group, $user->id));

        $allAttendanceHistory = collect();
        if ($groups->isNotEmpty()) {
            $allAttendanceHistory = UsrahAttendance::query()
                ->whereIn('usrah_group_id', $groups->pluck('id'))
                ->where('user_id', $user->id)
                ->orderByDesc('session_date')
                ->take(20)
                ->get()
                ->map(fn ($a) => [
                    'date' => $a->session_date->format('Y-m-d'),
                    'status' => $a->status,
                    'notes' => $a->notes,
                ]);
        }

        return [
            'groups' => $groupsData,
            'attendanceHistory' => $allAttendanceHistory,
        ];
    }
}
