<?php

namespace App\Http\Controllers;

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
                'members_count' => $group->members->count(),
                'naqib_name' => optional($group->members->firstWhere('pivot.is_naqib', true))->name,
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_day' => ['nullable', 'string', 'max:20'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
        ]);

        UsrahGroup::create([
            'organization_id' => $user->current_organization_id,
            ...$data,
        ]);

        return back()->with('success', 'Kumpulan usrah berjaya dicipta.');
    }

    public function assignMembers(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $this->authorizeUsrahAccess($request->user(), $usrahGroup);

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.is_naqib' => ['nullable', 'boolean'],
        ]);

        $syncPayload = [];

        foreach ($data['members'] as $member) {
            $syncPayload[$member['user_id']] = [
                'is_naqib' => (bool) ($member['is_naqib'] ?? false),
                'joined_at' => now(),
            ];
        }

        $usrahGroup->members()->sync($syncPayload);

        return back()->with('success', 'Ahli usrah berjaya dikemaskini.');
    }

    public function myGroup(Request $request): Response
    {
        $user = $request->user();

        $group = $user->usrahGroups()
            ->with(['members' => fn ($q) => $q->withoutGlobalScopes()->select('users.id', 'name')])
            ->first();

        $isNaqib = (bool) optional($group?->members->firstWhere('id', $user->id))->pivot?->is_naqib;

        return Inertia::render('Usrah/MyGroup', [
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'meeting_day' => $group->meeting_day,
                'meeting_time' => $group->meeting_time,
                'members' => $group->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'is_naqib' => (bool) $member->pivot->is_naqib,
                ])->values(),
            ] : null,
            'isNaqib' => $isNaqib,
        ]);
    }

    public function logAttendance(Request $request, UsrahGroup $usrahGroup): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeUsrahAccess($user, $usrahGroup);

        $isNaqib = $usrahGroup->members()
            ->where('users.id', $user->id)
            ->wherePivot('is_naqib', true)
            ->exists();

        abort_unless($isNaqib, 403);

        // Stub for Phase 2: attendance persistence can be extended in next iteration.
        return back()->with('info', 'Log attendance stub berjaya dipanggil.');
    }

    private function authorizeUsrahAccess(User $user, UsrahGroup $group): void
    {
        if ($user->hasRole('Superadmin')) {
            return;
        }

        abort_if((int) $user->current_organization_id !== (int) $group->organization_id, 403);
    }
}
