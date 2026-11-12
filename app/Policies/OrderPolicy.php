<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasRole(['Superadmin', 'Admin', 'Admin Cawangan', 'Member']);
    }

    public function view(User $user, Order $order)
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }
        if ($user->isBranchAdmin()) {
            return $order->items()
                ->whereHas('product', fn ($q) => $q->where('branch_id', $user->managedBranchId()))
                ->exists();
        }
        if ($user->hasRole(['Admin', 'org-admin']) && $order->organisasi_id == $user->current_organization_id) {
            return true;
        }
        if ($order->user_id == $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        // Staf (Admin/Superadmin dan admin organisasi/cawangan) mengurus
        // pesanan, bukan membelinya. Mereka boleh membeli sebagai tetamu.
        return $user->canPurchase();
    }

    public function update(User $user, Order $order)
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }
        if ($user->isBranchAdmin()) {
            $total = $order->items()->count();
            $own = $order->items()
                ->whereHas('product', fn ($q) => $q->where('branch_id', $user->managedBranchId()))
                ->count();

            return $total > 0 && $total === $own;
        }
        if ($user->hasRole(['Admin', 'org-admin']) && $order->organisasi_id == $user->current_organization_id) {
            return true;
        }

        return false;
    }
}
