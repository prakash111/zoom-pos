<?php

namespace App\Services\Navigation;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantNavigationSetting;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    /**
     * Populate and activate all parent and child navigation settings and feature flags
     * for a tenant based on their selected store operating mode.
     */
    public function populateDefaultNavigation(Company|Tenant $tenant, ?string $storeType = null): void
    {
        if ($tenant instanceof Company) {
            $tenant->refresh();
        }

        $tenantModes = TenantNavRegistry::resolveTenantModes($tenant);
        if ($storeType && ! in_array($storeType, $tenantModes, true)) {
            $tenantModes[] = ModuleRegistry::canonicalKey($storeType);
        }
        $tenantModes = array_values(array_unique($tenantModes));

        $allModules = [];
        foreach ($tenantModes as $mode) {
            foreach (ModuleRegistry::getModulesForStoreType($mode) as $mod) {
                $allModules[$mod['key']] = $mod;
            }
        }
        if (empty($allModules)) {
            $allModules = ModuleRegistry::getModulesForStoreType($storeType ?: 'retail');
        }

        $order = 0;
        foreach ($allModules as $module) {
            TenantNavigationSetting::updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->id,
                    'module_key' => $module['key'],
                ],
                [
                    'group'      => $module['group'] ?? null,
                    'label'      => $module['label'] ?? null,
                    'icon'       => $module['icon'] ?? null,
                    'route'      => $module['route'] ?? null,
                    'is_enabled' => true,
                    'is_visible' => true,
                    'sort_order' => $order++,
                ]
            );

            TenantFeature::updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->id,
                    'feature_key' => $module['key'],
                ],
                [
                    'is_enabled' => true,
                    'metadata' => [
                        'group' => $module['group'] ?? null,
                        'label' => $module['label'] ?? null,
                    ],
                ]
            );
        }

        // Build the canonical nav_config tree so the company model and SDUI
        // immediately reflect full menu visibility without visiting Navigation settings.
        $canonicalSections = TenantNavRegistry::getBaseNavSectionsForTenant($tenant);
        $tree = [];
        $flatItems = [];
        foreach ($canonicalSections as $sIndex => $sec) {
            $secKey = $sec['key'] ?? $sec['id'] ?? 'section_'.$sIndex;
            $secItems = [];
            foreach ($sec['items'] ?? [] as $iIndex => $item) {
                $itemKey = $item['key'] ?? $item['id'] ?? 'item_'.$iIndex;
                $itemNode = [
                    'key' => $itemKey,
                    'label' => $item['label'] ?? $item['title'] ?? $itemKey,
                    'visible' => true,
                    'order' => $iIndex,
                    'parent_id' => null,
                    'level' => 0,
                    'children' => [],
                ];
                $secItems[] = $itemNode;
                $flatItems[] = [
                    'key' => $itemKey,
                    'section' => $secKey,
                    'parent' => null,
                    'parent_id' => null,
                    'level' => 0,
                    'order' => $iIndex,
                    'visible' => true,
                ];
            }
            $tree[] = [
                'key' => $secKey,
                'order' => $sIndex,
                'items' => $secItems,
            ];
        }

        $navConfig = [
            'sections' => array_map(fn ($s, $idx) => ['key' => $s['key'], 'order' => $idx], $tree, array_keys($tree)),
            'items' => $flatItems,
            'tree' => $tree,
            'custom_tree' => $tree,
        ];

        $tenant->update(['nav_config' => $navConfig]);

        Cache::forget("tenant_nav_{$tenant->id}");
    }

    /**
     * Get cached or fresh navigation tree for a tenant.
     */
    public function getNavigationForTenant(Company|Tenant $tenant): array
    {
        return Cache::remember("tenant_nav_{$tenant->id}", 3600, function () use ($tenant) {
            return TenantNavRegistry::getEffectiveNavForTenant($tenant);
        });
    }
}
