<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
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
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user);
    }
}
