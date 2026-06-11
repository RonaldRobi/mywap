<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasRole(['Superadmin', 'Admin', 'Member']);
    }

    public function view(User $user, Order $order)
    {
        if ($user->hasRole('Superadmin')) return true;
        if ($user->hasRole('Admin') && $order->organisasi_id == $user->organisasi_id) return true;
        if ($order->user_id == $user->id) return true;
        return false;
    }

    public function create(User $user)
    {
        return $user->hasRole(['Superadmin', 'Admin', 'Member']);
    }

    public function update(User $user, Order $order)
    {
        if ($user->hasRole('Superadmin')) return true;
        if ($user->hasRole('Admin') && $order->organisasi_id == $user->organisasi_id) return true;
        return false;
    }
}
