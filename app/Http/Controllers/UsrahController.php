<?php

namespace App\Http\Controllers;

use App\Actions\LoadUsrahForUser;
use App\Models\UsrahAttendance;
use App\Models\UsrahGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsrahController extends Controller
{
    public function adminIndex(Request $request): Response
    {
        $user = $request->user();

        $groups = UsrahGroup::query()
            ->with(['members' => fn ($q) => $q->withoutGlobalScopes()->select('users.id', 'name')])
            ->latest()
            ->get()
            ->map(fn (UsrahGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'meeting_day' => $group->meeting_day,
                'meeting_time' => $group->meeting_time,
                'is_active' => $group->is_active,
                'members_count' => $group->members->count(),
                'naqib_name' => optional($group->members->firstWhere('pivot.role', 'leader'))->name,
                'sub_leader_names' => $group->members
                    ->where('pivot.role', 'sub_leader')
                    ->pluck('name')
                    ->values(),
            ]);

        $members = User::withoutGlobalScopes()
            ->where('current_organization_id', $user->current_organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Usrah/AdminManage', [
            'groups' => $groups,
            'members' => $members,
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('create', UsrahGroup::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_day' => ['nullable', 'string', 'max:20'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        UsrahGroup::create([
            'organization_id' => $user->current_organization_id,
            ...$data,
        ]);

        return back()->with('success', 'Kumpulan usrah berjaya dicipta.');
    }

    public function updateGroup(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $this->authorize('update', $usrahGroup);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_day' => ['nullable', 'string', 'max:20'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $usrahGroup->update($data);

        return back()->with('success', 'Kumpulan usrah berjaya dikemaskini.');
    }

    public function deleteGroup(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $this->authorize('delete', $usrahGroup);

        $usrahGroup->delete();

        return back()->with('success', 'Kumpulan usrah berjaya dipadam.');
    }

    public function assignMembers(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $this->authorize('assignMembers', $usrahGroup);

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['nullable', 'string', 'in:leader,sub_leader,member'],
        ]);

        $syncPayload = [];

        foreach ($data['members'] as $member) {
            $role = $member['role'] ?? 'member';
            $syncPayload[$member['user_id']] = [
                'role' => $role,
                'is_naqib' => $role === 'leader',
                'joined_at' => now(),
            ];
        }

        $usrahGroup->members()->sync($syncPayload);

        return back()->with('success', 'Ahli usrah berjaya dikemaskini.');
    }

    public function myGroup(Request $request): Response
    {
        $user = $request->user();

        $groups = $user->usrahGroups()
            ->with(['members' => fn ($q) => $q->withoutGlobalScopes()->select('users.id', 'name')])
            ->get();

        $groupsData = $groups->map(fn (UsrahGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'meeting_day' => $group->meeting_day,
            'meeting_time' => $group->meeting_time,
            'is_leader' => in_array($group->members->firstWhere('id', $user->id)?->pivot?->role, ['leader', 'sub_leader']),
            'members' => $group->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->pivot->role ?? 'member',
            ])->values(),
        ]);

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

        return Inertia::render('Usrah/MyGroup', [
            'groups' => $groupsData,
            'attendanceHistory' => $allAttendanceHistory,
        ]);
    }

    public function logAttendance(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $this->authorize('logAttendance', $usrahGroup);

        $data = $request->validate([
            'session_date' => ['required', 'date'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendances.*.status' => ['required', 'string', 'in:hadir,tidak_hadir,uzur'],
            'attendances.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        foreach ($data['attendances'] as $attendance) {
            UsrahAttendance::updateOrCreate(
                [
                    'usrah_group_id' => $usrahGroup->id,
                    'user_id' => $attendance['user_id'],
                    'session_date' => $data['session_date'],
                ],
                [
                    'status' => $attendance['status'],
                    'notes' => $attendance['notes'] ?? null,
                    'created_by' => $user->id,
                ]
            );
        }

        return back()->with('success', 'Kehadiran berjaya direkodkan.');
    }

}
