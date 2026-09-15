<?php

namespace App\Models;

/**
 * Tenant model alias extending Company in multi-tenant contexts.
 *
 * Provides compatibility for tenant-scoped operations, tinker scripts,
 * and navigation menu customizations.
 */
class Tenant extends Company
{
    protected $table = 'companies';

    /**
     * Unified Store Display Name Accessor prioritizing:
     * Business Name (`business_name`) -> Trading Name (`trading_name`) -> Fallback ('Store').
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->business_name)) {
            return $this->business_name;
        }

        if (!empty($this->trading_name)) {
            return $this->trading_name;
        }

        return $this->name ?? 'Store';
    }
}
