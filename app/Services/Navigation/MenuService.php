<?php

namespace App\Services\Navigation;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;

class MenuService
{
    /**
     * Build the tenant drawer navigation menu with domain isolation and deduplication.
     *
     * @param  Company|Tenant|string|null  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getDrawerMenu(mixed $tenant): array
    {
        return TenantNavRegistry::getEffectiveNavForTenant($tenant);
    }

    /**
     * Dynamic Drawer Navigation Tree.
     * Returns server-driven navigation tree for tenant and user.
     *
     * @param  Company|Tenant|mixed  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getDrawerTree(mixed $tenant, ?User $user = null): array
    {
        return self::buildDrawerTree($tenant, $user);
    }

    /**
     * Static helper to build the drawer navigation tree.
     *
     * @param  Company|Tenant|mixed  $tenant
     * @return list<array<string, mixed>>
     */
    public static function buildDrawerTree(mixed $tenant, ?User $user = null): array
    {
        return TenantNavRegistry::getEffectiveNavForTenant($tenant);
    }
}
