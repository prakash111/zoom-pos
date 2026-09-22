<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Determine whether the user can view any sales.
     */
    public function viewAny(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isPrivilegedRole') && $user->isPrivilegedRole()) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('sales.view');
        }

        return true;
    }

    /**
     * Determine whether the user can view the sale.
     */
    public function view(?User $user, Sale $sale): bool
    {
        return $this->viewAny($user);
    }
}
