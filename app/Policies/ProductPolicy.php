<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Product $product)
    {
        return true;
    }

    public function create(User $user)
    {
        return $user->hasRole(['Superadmin', 'Admin']);
    }

    public function update(User $user, Product $product)
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        if (! $user->hasRole('Admin')) {
            return false;
        }

        // The users table stores the org on `current_organization_id`; there is
        // no `organisasi_id` column on User. Comparing against the missing
        // column always yielded null, locking Admins out of every product.
        $userOrg = $user->current_organization_id;

        // Products created before orgs were tracked have a null organisasi_id;
        // let any Admin adopt those rather than orphaning them.
        if ($product->organisasi_id === null) {
            return true;
        }

        return $userOrg !== null && (int) $product->organisasi_id === (int) $userOrg;
    }

    public function delete(User $user, Product $product)
    {
        return $this->update($user, $product);
    }
}
