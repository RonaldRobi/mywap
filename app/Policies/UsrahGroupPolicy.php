<?php

namespace App\Policies;

use App\Models\UsrahGroup;
use App\Models\User;

class UsrahGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Superadmin', 'Admin']);
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
        return $user->hasRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole('Admin');
    }

    public function delete(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole('Admin');
    }

    public function assignMembers(User $user, UsrahGroup $usrahGroup): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return (int) $user->current_organization_id === (int) $usrahGroup->organization_id
            && $user->hasRole('Admin');
    }

    public function logAttendance(User $user, UsrahGroup $usrahGroup): bool
    {
        return $usrahGroup->members()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['leader', 'sub_leader'])
            ->exists();
    }
}
