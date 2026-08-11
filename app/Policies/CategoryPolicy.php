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
        return $user->hasRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, Category $category)
    {
        return $user->hasRole(['Superadmin', 'Admin']);
    }

    public function delete(User $user, Category $category)
    {
        return $user->hasRole(['Superadmin', 'Admin']);
    }
}
