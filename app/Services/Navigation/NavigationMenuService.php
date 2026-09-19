<?php

namespace App\Services\Navigation;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NavigationMenuService
{
    /**
     * Root commerce keys that must always be emitted with indent: 0, level: 0,
     * parent: null, and children: []. Never emit children: null or child flags.
     *
     * @var list<string>
     */
    public const ROOT_COMMERCE_KEYS = [
        'pos',
        'pharmacy_pos',
        'salon_pos',
        'restaurant_pos',
        'consignments',
    ];

    /**
     * All core commerce transaction keys.
     *
     * @var list<string>
     */
    public const ALL_COMMERCE_KEYS = [
        'pos',
        'pharmacy_pos',
        'salon_pos',
        'restaurant_pos',
        'sales',
        'quotations',
        'consignments',
        'customers',
        'cash_register',
    ];

    /**
     * Specialized business vertical identifiers.
     *
     * @var list<string>
     */
    public const SPECIALIZED_VERTICALS = [
        'pharmacy',
        'salon',
        'spa',
        'wellness',
        'service_booking',
        'beauty',
        'restaurant',
        'dining_tables',
        'repair',
        'repairs',
        'technician',
        'repair_technician',
    ];

    /**
     * Vertical primary section keys.
     *
     * @var list<string>
     */
    public const VERTICAL_SECTION_KEYS = [
        'pharmacy_management',
        'pharmacy_dispensary',
        'salon_bookings',
        'service_operations',
        'restaurant_operations',
        'repair_service',
        'repair_operations',
    ];

    /**
     * Check whether a tenant operates in a specialized business vertical.
     */
    public static function isSpecializedTenant(mixed $tenant): bool
    {
        if ($tenant instanceof Company) {
            $licensed = (array) ($tenant->licensed_modules ?? []);
            $posMode = strtolower(trim((string) ($tenant->pos_mode ?? '')));
            $opMode = strtolower(trim((string) ($tenant->operating_mode ?? '')));

            // If retail is explicitly the operating mode or pos mode, it's not a single-vertical specialized account
            if ($posMode === 'retail' || $posMode === 'general' || $opMode === 'retail') {
                return false;
            }

            // If licensed modules has retail and no specialized pos_mode, it's retail
            if (in_array('retail', $licensed, true) && ! in_array($posMode, self::SPECIALIZED_VERTICALS, true)) {
                return false;
            }

            // If pos_mode is a specialized vertical, it's specialized
            if (in_array($posMode, self::SPECIALIZED_VERTICALS, true)) {
                return true;
            }

            // If licensed modules has a specialized vertical and NOT retail
            $hasSpecialized = (bool) array_intersect($licensed, self::SPECIALIZED_VERTICALS);
            if ($hasSpecialized && ! in_array('retail', $licensed, true)) {
                return true;
            }
        }

        // Mode string check
        if (is_string($tenant)) {
            $norm = strtolower(trim($tenant));
            if ($norm === 'retail' || $norm === 'general') {
                return false;
            }
            if (in_array($norm, self::SPECIALIZED_VERTICALS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enforces that root commerce items (specifically Point of Sale and Consignments)
     * are emitted with indent: 0, level: 0, parent: null, parent_id: null, and children: [].
     * Never emit children: null or set child flags on root commerce items.
     * Consignments is strictly flat and cannot be nested under Point of Sale.
     *
     * @param  array<string, mixed>  $item
     */
    public static function enforceRootCommerceItem(array &$item): void
    {
        $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
        if (! in_array($key, self::ROOT_COMMERCE_KEYS, true)) {
            return;
        }

        $item['level'] = 0;
        $item['indent'] = 0;
        $item['parent'] = null;
        $item['parent_id'] = null;

        // Consignments is strictly a flat sibling and must NEVER have children or be nested
        if ($key === 'consignments') {
            $item['children'] = [];
            $item['type'] = 'link';
            unset(
                $item['has_children'],
                $item['hasChildren'],
                $item['is_child'],
                $item['isChild'],
                $item['initially_expanded'],
                $item['initiallyExpanded'],
                $item['expanded'],
                $item['is_expanded'],
                $item['isExpanded'],
                $item['default_open'],
                $item['defaultOpen'],
                $item['auto_expand'],
                $item['accordion']
            );

            return;
        }

        // For POS items (pos, pharmacy_pos, salon_pos, restaurant_pos),
        // filter out any accidentally nested consignments or root commerce keys
        if (isset($item['children']) && is_array($item['children'])) {
            $item['children'] = array_values(array_filter($item['children'], function ($ch) {
                if (! is_array($ch)) {
                    return false;
                }
                $k = strtolower(trim((string) ($ch['key'] ?? $ch['id'] ?? '')));

                return $k !== 'consignments';
            }));
        } else {
            $item['children'] = [];
        }

        if (empty($item['children'])) {
            $item['children'] = [];
            $item['type'] = 'link';
            unset(
                $item['has_children'],
                $item['hasChildren'],
                $item['is_child'],
                $item['isChild'],
                $item['initially_expanded'],
                $item['initiallyExpanded'],
                $item['expanded'],
                $item['is_expanded'],
                $item['isExpanded'],
                $item['default_open'],
                $item['defaultOpen'],
                $item['auto_expand'],
                $item['accordion']
            );
        }
    }

    /**
     * Cleanly places an extension module like Consignments under Products & Inventory
     * as a flat sibling (level: 0, indent: 0, children: []).
     *
     * @param  array<string, mixed>  $inventorySection
     * @param  array<string, mixed>|null  $existingItem
     * @return array<string, mixed>
     */
    public static function attachConsignmentsToInventory(array $inventorySection, ?array $existingItem = null): array
    {
        $items = is_array($inventorySection['items'] ?? null) ? $inventorySection['items'] : [];
        $hasConsignments = false;

        foreach ($items as $it) {
            $k = strtolower(trim((string) ($it['key'] ?? $it['id'] ?? '')));
            if ($k === 'consignments') {
                $hasConsignments = true;
                break;
            }
        }

        if (! $hasConsignments) {
            $consignmentsNode = $existingItem ?? [
                'id' => 'consignments',
                'key' => 'consignments',
                'label' => 'Consignments',
                'title' => 'Consignments',
                'icon' => 'local_shipping',
                'component' => 'consignments',
                'permission' => 'consignments',
                'target_endpoint' => '/api/tenant/views/consignments',
                'route' => '/api/tenant/views/consignments',
            ];

            self::enforceRootCommerceItem($consignmentsNode);
            $consignmentsNode['visible'] = true;
            $items[] = $consignmentsNode;
            $inventorySection['items'] = $items;
        }

        return $inventorySection;
    }

    /**
     * Vertical Module Deduplication:
     * When a tenant's business vertical is specialized (pharmacy, salon, restaurant, repair),
     * suppress the trailing generic "Retail & Cashier" section if its core capabilities
     * already exist in the vertical's primary menu block.
     *
     * If an extension module like consignments is enabled for a pharmacy or salon tenant,
     * place it cleanly as a flat sibling under "Product Catalog" / "Products & Inventory",
     * rather than generating a duplicate trailing "Retail & Cashier" section.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function deduplicateVerticalSections(array $sections, mixed $tenant): array
    {
        if (! self::isSpecializedTenant($tenant)) {
            return $sections;
        }

        // Check whether a specialized vertical section is present
        $verticalSectionIndex = null;
        $cashierSectionIndex = null;
        $inventorySectionIndex = null;

        foreach ($sections as $i => $sec) {
            if (! is_array($sec)) {
                continue;
            }
            $key = strtolower(trim((string) ($sec['key'] ?? $sec['id'] ?? '')));
            if (in_array($key, self::VERTICAL_SECTION_KEYS, true)) {
                $verticalSectionIndex = $i;
            } elseif ($key === 'cashier_sales') {
                $cashierSectionIndex = $i;
            } elseif ($key === 'products_inventory') {
                $inventorySectionIndex = $i;
            }
        }

        // Extract consignments node if present anywhere in cashier_sales or enabled
        $consignmentsNode = null;
        if ($cashierSectionIndex !== null) {
            $cashierItems = $sections[$cashierSectionIndex]['items'] ?? [];
            foreach ($cashierItems as $ci) {
                if (strtolower(trim((string) ($ci['key'] ?? $ci['id'] ?? ''))) === 'consignments') {
                    $consignmentsNode = $ci;
                    break;
                }
            }
        }

        $consignmentsEnabled = false;
        if ($tenant instanceof Company) {
            $consignmentsEnabled = $tenant->hasModule('consignments')
                || in_array('consignments', (array) ($tenant->licensed_modules ?? []), true);
        }

        // Place consignments under products_inventory if enabled or found in cashier_sales
        if (($consignmentsNode !== null || $consignmentsEnabled) && $inventorySectionIndex !== null) {
            $sections[$inventorySectionIndex] = self::attachConsignmentsToInventory(
                $sections[$inventorySectionIndex],
                $consignmentsNode
            );
        }

        // Suppress cashier_sales for specialized verticals if vertical primary section exists
        // or if tenant is single-vertical account
        if ($cashierSectionIndex !== null && ($verticalSectionIndex !== null || self::isSpecializedTenant($tenant))) {
            unset($sections[$cashierSectionIndex]);
            $sections = array_values($sections);
        }

        return $sections;
    }

    /**
     * Complete sanitization of navigation sections:
     * 1. Runs vertical module deduplication.
     * 2. Enforces indent: 0, children: [] on Point of Sale and Consignments.
     * 3. Ensures no null children arrays exist anywhere.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function sanitizeSections(array $sections, mixed $tenant = null): array
    {
        if ($tenant !== null) {
            $sections = self::deduplicateVerticalSections($sections, $tenant);
        }

        $sanitizeItem = function (array &$item) use (&$sanitizeItem): void {
            self::enforceRootCommerceItem($item);

            if (isset($item['children'])) {
                if (! is_array($item['children'])) {
                    $item['children'] = [];
                } else {
                    foreach ($item['children'] as &$child) {
                        if (is_array($child)) {
                            $sanitizeItem($child);
                        }
                    }
                    unset($child);
                }
            } else {
                $item['children'] = [];
            }
        };

        foreach ($sections as &$sec) {
            if (! is_array($sec) || ! isset($sec['items']) || ! is_array($sec['items'])) {
                continue;
            }
            $sec['items'] = array_values($sec['items']);
            foreach ($sec['items'] as &$it) {
                if (is_array($it)) {
                    $sanitizeItem($it);
                }
            }
            unset($it);
        }
        unset($sec);

        return NavigationSanitizerService::sanitizeSections($sections);
    }

    /**
     * Sanitizes SDUI menu components (as returned by formatCustomMenuComponents).
     *
     * @param  list<array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    public static function sanitizeComponents(array $components): array
    {
        $sanitizeItem = function (array &$item) use (&$sanitizeItem): void {
            $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
            if (in_array($key, self::ROOT_COMMERCE_KEYS, true)) {
                $item['level'] = 0;
                $item['indent'] = 0;
                $item['parent'] = null;
                $item['parent_id'] = null;
                $item['children'] = [];
                unset(
                    $item['has_children'],
                    $item['hasChildren'],
                    $item['is_child'],
                    $item['isChild'],
                    $item['initially_expanded'],
                    $item['initiallyExpanded'],
                    $item['expanded'],
                    $item['is_expanded'],
                    $item['isExpanded'],
                    $item['default_open'],
                    $item['defaultOpen'],
                    $item['auto_expand'],
                    $item['accordion']
                );
            }

            if (isset($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as &$child) {
                    if (is_array($child)) {
                        $sanitizeItem($child);
                    }
                }
                unset($child);
            } else {
                $item['children'] = [];
            }
        };

        foreach ($components as &$comp) {
            if (is_array($comp) && ($comp['type'] ?? '') === 'list_tile') {
                $sanitizeItem($comp);
            }
        }
        unset($comp);

        return $components;
    }

    /**
     * Clears stale or corrupt cached JSON trees from companies.nav_config /
     * settings['navigation_menu'] / tenant_settings for a tenant so they
     * re-sync with the normalized server schema.
     */
    public static function cleanTenantNavigation(string|Company $tenantOrEmail): bool
    {
        $company = null;
        if ($tenantOrEmail instanceof Company) {
            $company = $tenantOrEmail;
        } else {
            $company = Company::where('email', $tenantOrEmail)
                ->orWhere('id', $tenantOrEmail)
                ->orWhere('slug', $tenantOrEmail)
                ->first();
        }

        if (! $company) {
            return false;
        }

        $cid = (string) $company->id;

        // 1. Clear database stored navigation customization
        $company->forceFill([
            'nav_config' => null,
            'navigation_menu_customization' => null,
        ])->save();

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')
                ->where('tenant_id', $cid)
                ->whereIn('key', ['navigation_menu_custom', 'navigation_menu'])
                ->delete();
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('company_id', $company->id)
                ->where('key', 'like', '%navigation%')
                ->delete();
        }

        // 2. Clear all cache keys
        Cache::forget("tenant_{$cid}_drawer_menu");
        Cache::forget("navigation_menu_{$cid}");
        Cache::forget("tenant_nav_{$cid}");

        // 3. Re-seed with normalized canonical vertical navigation
        $posMode = $company->pos_mode ?: 'pharmacy';
        app(MenuService::class)->populateDefaultNavigation($company, $posMode);

        return true;
    }
}
