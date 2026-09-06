<?php

namespace App\Services\Navigation;

use App\Models\Company;

class MenuService
{
    /**
     * Build the tenant drawer navigation menu with domain isolation and deduplication.
     *
     * @param  Company|string|null  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getDrawerMenu(mixed $tenant): array
    {
        return TenantNavRegistry::getEffectiveNavForTenant($tenant);
    }
}
