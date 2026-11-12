<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Category $category)
    {
        return true;
    }

    public function create(User $user)
    {
        return $user->hasRole(['Superadmin', 'Admin', 'org-admin']);
    }

    public function update(User $user, Category $category)
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        if (! $user->hasRole(['Admin', 'org-admin'])) {
            return false;
        }

        // Global categories (organization_id null) are managed by Superadmin
        // only; an org admin may only touch their own organisation's categories.
        return $category->organization_id !== null
            && (int) $category->organization_id === (int) $user->current_organization_id;
    }

    public function delete(User $user, Category $category)
    {
        return $this->update($user, $category);
    }
}
