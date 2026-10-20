<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UsrahGroup;

class UsrahGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Superadmin', 'Admin', 'org-admin']);
    }

    public function view(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['Superadmin', 'Admin', 'org-admin']);
    }

    public function update(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole(['Admin', 'org-admin']);
    }

    public function delete(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole(['Admin', 'org-admin']);
    }

    public function assignMembers(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole(['Admin', 'org-admin']);
    }

    public function logAttendance(User $user, UsrahGroup $usrahGroup): bool
    {
        return $usrahGroup->members()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['leader', 'sub_leader'])
            ->exists();
    }
}
