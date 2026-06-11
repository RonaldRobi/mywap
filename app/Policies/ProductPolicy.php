<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;

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
        if ($user->hasRole('Superadmin')) return true;
        if ($user->hasRole('Admin') && $product->organisasi_id == $user->organisasi_id) return true;
        return false;
    }

    public function delete(User $user, Product $product)
    {
        return $this->update($user, $product);
    }
}
