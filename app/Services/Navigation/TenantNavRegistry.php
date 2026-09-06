<?php

namespace App\Services\Navigation;

use App\Models\Company;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * The nav tree for the tenant sidebar/drawer and mobile client,
 * supporting general retail, restaurant & cafe, pharmacy, service booking,
 * and future pluggable business modules.
 */
class TenantNavRegistry
{
    /**
     * Resolve all normalized active and licensed modes for a tenant.
     *
     * @return list<string>
     */
    public static function resolveTenantModes(mixed $tenant): array
    {
        if ($tenant instanceof Company) {
            $modes = (array) ($tenant->licensed_modules ?: []);
            if ($tenant->operating_mode) {
                $modes[] = $tenant->operating_mode;
            }
            if ($tenant->pos_mode) {
                $modes[] = $tenant->pos_mode;
            }
        } elseif (is_object($tenant)) {
            $modes = (array) ($tenant->licensed_modules ?? []);
            if (isset($tenant->operating_mode)) {
                $modes[] = $tenant->operating_mode;
            }
            if (isset($tenant->pos_mode)) {
                $modes[] = $tenant->pos_mode;
            }
        } elseif (is_string($tenant) && trim($tenant) !== '') {
            $modes = [trim($tenant)];
        } else {
            $modes = ['retail'];
        }

        $normalized = [];
        foreach ($modes as $item) {
            if (is_string($item)) {
                $norm = strtolower(trim($item));
                $norm = match ($norm) {
                    'general', 'general_retail' => 'retail',
                    'food_restaurant' => 'restaurant',
                    'repair', 'repairs', 'technician', 'repair_technician' => 'repair_technician',
                    default => $norm,
                };
                if ($norm !== '') {
                    $normalized[] = $norm;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Enforce strict domain boundaries across navigation menus.
     * Repair equipment tickets (#SO-...) must only exist in repair/technician modes.
     * Salon, spa, wellness, and general retail must never render repair service orders.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function filterDomainMismatches(array $sections, mixed $tenant): array
    {
        $modes = self::resolveTenantModes($tenant);
        $isRepair = false;
        foreach ($modes as $m) {
            if (in_array($m, ['repair', 'repairs', 'repair_technician', 'automotive', 'electronics_service'], true)) {
                $isRepair = true;
                break;
            }
        }

        $isSalon = false;
        foreach ($modes as $m) {
            if (in_array($m, ['salon', 'spa', 'wellness', 'service_booking', 'beauty'], true)) {
                $isSalon = true;
                break;
            }
        }

        $filterItems = function (array $items) use (&$filterItems, $isRepair, $isSalon): array {
            $filtered = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
                $component = strtolower(trim((string) ($item['component'] ?? '')));
                $target = strtolower(trim((string) ($item['target_endpoint'] ?? '')));
                $title = strtolower(trim((string) ($item['title'] ?? $item['label'] ?? '')));

                // Service orders (equipment/warranty repair tickets) are strictly gated to repair workbenches
                if (! $isRepair) {
                    if (in_array($key, ['service_orders', 'new_service_order', 'repair_orders'], true)
                        || in_array($component, ['service_orders'], true)
                        || ($target === '/api/v1/pos/service-orders')
                        || ($title === 'service orders' && ! str_contains($target, 'service-catalog'))
                        || $title === 'new service order') {
                        continue;
                    }
                }

                // In salon/spa modes, strip any repair tickets or equipment intake
                if ($isSalon) {
                    if (in_array($key, ['service_orders', 'repair_tickets', 'repair_create_ticket', 'repair_dashboard', 'repair_my_jobs', 'repair_detail'], true)
                        || in_array($component, ['service_orders', 'repair_tickets', 'repair_create_ticket', 'repair_dashboard'], true)
                        || in_array($title, ['service orders', 'repair ticket register', 'new intake ticket', 'repair workbench'], true)) {
                        continue;
                    }
                }

                if (! empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = $filterItems($item['children']);
                }

                $filtered[] = $item;
            }

            return array_values($filtered);
        };

        $result = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $secKey = strtolower(trim((string) ($section['key'] ?? $section['id'] ?? '')));
            if ($isSalon && in_array($secKey, ['repair_operations', 'repair_service', 'spare_parts_inventory'], true)) {
                continue;
            }
            if (isset($section['items']) && is_array($section['items'])) {
                $section['items'] = $filterItems($section['items']);
            }
            if (empty($section['items']) && ! in_array($secKey, ['administration', 'settings'], true)) {
                continue;
            }
            $result[] = $section;
        }

        return array_values($result);
    }

    /**
     * Return guaranteed non-empty navigation sections for a tenant or mode,
     * maintaining a clean modular hierarchy at first load with licensed verticals
     * grouped in strict sequence and Administration anchored at the bottom.
     *
     * @param  Company|string|null  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getEffectiveNavForTenant(mixed $tenant): array
    {
        $labels = [];
        if ($tenant instanceof Company && is_array($tenant->navigation_labels)) {
            $labels = $tenant->navigation_labels;
        } elseif (is_object($tenant) && isset($tenant->navigation_labels) && is_array($tenant->navigation_labels)) {
            $labels = $tenant->navigation_labels;
        }

        // If tenant already rearranged menus via drag-and-drop, serve their custom layout
        if ($tenant instanceof Company) {
            $custom = self::buildCustomNavTree($tenant);
            if (! empty($custom)) {
                $sections = array_values(array_map([self::class, 'normalizeSection'], $custom));
                $sections = self::filterDomainMismatches($sections, $tenant);
                return self::applyNavigationLabels($sections, $labels);
            }
        } elseif (is_object($tenant) && ! empty($tenant->navigation_menu_customization)) {
            $sections = array_values(array_map([self::class, 'normalizeSection'], (array) $tenant->navigation_menu_customization));
            $sections = self::filterDomainMismatches($sections, $tenant);
            return self::applyNavigationLabels($sections, $labels);
        }

        $sections = self::getBaseNavSectionsForTenant($tenant);
        $sections = self::filterDomainMismatches($sections, $tenant);
        return self::applyNavigationLabels($sections, $labels);
    }

    /**
     * Return guaranteed non-empty navigation sections for a tenant or mode,
     * maintaining a clean modular hierarchy at first load with licensed verticals
     * grouped in strict sequence and Administration anchored at the bottom.
     *
     * @param  Company|string|null  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getBaseNavSectionsForTenant(mixed $tenant): array
    {
        $sections = [];

        // Determine licensed modules
        if ($tenant instanceof Company) {
            $licensedRaw = $tenant->licensed_modules;
            if (empty($licensedRaw)) {
                $licensedRaw = [$tenant->operating_mode ?? $tenant->pos_mode ?? 'retail'];
            }
        } elseif (is_string($tenant) && trim($tenant) !== '') {
            $licensedRaw = [trim($tenant)];
        } else {
            $licensedRaw = ['retail'];
        }

        $licensed = [];
        foreach ((array) $licensedRaw as $item) {
            if (is_string($item)) {
                $norm = strtolower(trim($item));
                $norm = match ($norm) {
                    'general', 'general_retail' => 'retail',
                    'food_restaurant' => 'restaurant',
                    'repair', 'repairs', 'technician', 'repair_technician' => 'repair_technician',
                    default => $norm,
                };
                if ($norm !== '') {
                    $licensed[] = $norm;
                }
            }
        }
        if ($tenant instanceof Company && $tenant->restaurant_mode_locked) {
            $licensed = array_values(array_diff($licensed, ['restaurant']));
        }
        $licensed = array_values(array_unique($licensed));
        if (empty($licensed)) {
            $licensed = ['retail'];
        }

        // 1. Core Retail / POS Sections
        if (in_array('retail', $licensed, true)) {
            $sections[] = self::getRetailSalesSection();
            $sections[] = self::getInventorySection();
            $sections[] = self::getFinancialSection();
        }

        // 2. Restaurant Module Section
        if (in_array('restaurant', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'restaurant_operations',
                'key' => 'restaurant_operations',
                'title' => 'Restaurant Operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => self::getRestaurantMenuItems(),
            ]);
        }

        // 3. Pharmacy Module Section
        if (in_array('pharmacy', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'pharmacy_management',
                'key' => 'pharmacy_management',
                'title' => 'Pharmacy Management',
                'label' => 'Pharmacy Management',
                'color' => '#059669',
                'items' => self::getPharmacyMenuItems(),
            ]);
        }

        // 4. Salon / Service Booking Module Section
        if (in_array('service_booking', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'salon_bookings',
                'key' => 'salon_bookings',
                'title' => 'Salon & Bookings',
                'label' => 'Salon & Bookings',
                'color' => '#7c3aed',
                'items' => self::getSalonMenuItems(),
            ]);
        }

        // 5. Repair & Technician Module Section
        if (in_array('repair_technician', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'repair_service',
                'key' => 'repair_service',
                'title' => 'REPAIR OPERATIONS & SALES',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => self::getRepairMenuItems(),
            ]);
        }

        // 6. Future Dynamic Modules (Auto-registered via ModuleRegistry)
        foreach ($licensed as $mod) {
            if (! in_array($mod, ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician'], true)) {
                $generic = self::buildGenericModuleSection($mod);
                if ($generic !== null) {
                    $sections[] = self::normalizeSection($generic);
                }
            }
        }

        // 7. Active Package Modules (Perfex CRM pattern)
        if (\Illuminate\Support\Facades\Schema::hasTable('sdui_modules')) {
            try {
                $activePackageModules = \App\Models\SduiModule::query()
                    ->where('is_active', true)
                    ->where('source_type', 'package')
                    ->orderBy('sort_order')
                    ->get();

                foreach ($activePackageModules as $pkgModule) {
                    $pkgSlug = $pkgModule->slug;
                    $alreadyAdded = false;
                    foreach ($sections as $sec) {
                        $secKey = $sec['key'] ?? $sec['id'] ?? '';
                        if (str_starts_with($secKey, $pkgSlug) || $secKey === $pkgSlug) {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    if (! $alreadyAdded) {
                        $nav = $pkgModule->navigation;
                        if (is_array($nav) && ! empty($nav)) {
                            $validated = self::validatedCustomNavigation($nav);
                            if (! empty($validated)) {
                                foreach ($validated as $sec) {
                                    $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
                                    if ($secKey !== 'administration') {
                                        $sections[] = self::normalizeSection($sec);
                                    }
                                }
                                continue;
                            }
                        }

                        $generic = self::buildGenericModuleSection($pkgSlug);
                        if ($generic !== null) {
                            $sections[] = self::normalizeSection($generic);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Table might not exist or be accessible in early bootstrapping
            }
        }

        // Single-mode vertical stores (e.g. restaurant-only) without retail still receive
        // inventory and financial management sections.
        if (! in_array('retail', $licensed, true)) {
            if (! in_array('products_inventory', array_column($sections, 'key'), true)) {
                $sections[] = self::getInventorySection();
            }
            if (! in_array('financial_management', array_column($sections, 'key'), true)) {
                $sections[] = self::getFinancialSection();
            }
        }

        // 8. Administration & Settings (Strictly at the bottom)
        $sections[] = self::getAdministrationSection();

        return self::filterDomainMismatches(array_values($sections), $tenant);
    }

    /**
     * Builds and decorates custom navigation tree for tenant if customized.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function buildCustomNavTree(Company $company): ?array
    {
        $raw = $company->nav_config ?? $company->navigation_menu_customization;
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        $tree = null;
        if (! empty($raw['custom_tree']) && is_array($raw['custom_tree'])) {
            $tree = $raw['custom_tree'];
        } elseif (! empty($raw['tree']) && is_array($raw['tree'])) {
            $tree = $raw['tree'];
        } elseif (! empty($raw['items']) && is_array($raw['items'])) {
            $normalized = app(TenantNavigationConfigService::class)->normalize($raw);
            $tree = $normalized['tree'] ?? null;
        }

        if (empty($tree)) {
            return null;
        }

        $baseSections = self::getBaseNavSectionsForTenant($company);
        $sectionMeta = [];
        $catalogItems = [];

        $indexItems = function (array $items, string $sectionKey, ?string $parentId = null) use (&$indexItems, &$catalogItems): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $k = trim((string) ($item['key'] ?? $item['id'] ?? ''));
                if ($k === '') {
                    continue;
                }
                if (! isset($catalogItems[$k])) {
                    $catalogItems[$k] = $item;
                }
                if (! empty($item['children']) && is_array($item['children'])) {
                    $indexItems($item['children'], $sectionKey, $k);
                }
            }
        };

        foreach ($baseSections as $sec) {
            $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
            if ($secKey !== '') {
                $sectionMeta[$secKey] = $sec;
                $indexItems($sec['items'] ?? [], $secKey);
            }
        }

        $decorateNode = function (array $node, ?string $parentId = null) use (&$decorateNode, $catalogItems): ?array {
            $k = trim((string) ($node['key'] ?? $node['id'] ?? ''));
            if ($k === '') {
                return null;
            }
            if (isset($node['visible']) && $node['visible'] === false) {
                return null;
            }

            $meta = $catalogItems[$k] ?? [
                'key' => $k,
                'id' => $k,
                'title' => ucwords(str_replace('_', ' ', $k)),
                'label' => ucwords(str_replace('_', ' ', $k)),
                'icon' => 'widgets',
                'component' => $k,
                'target_endpoint' => '/api/tenant/views/'.str_replace('_', '-', $k),
                'permission' => null,
            ];

            $decorated = array_merge($meta, $node);
            $decorated['id'] = $k;
            $decorated['key'] = $k;
            $decorated['title'] = $meta['title'] ?? $meta['label'] ?? $k;
            $decorated['label'] = $meta['label'] ?? $meta['title'] ?? $k;
            $decorated['icon'] = $meta['icon'] ?? 'widgets';
            $decorated['component'] = $meta['component'] ?? $k;
            $decorated['target_endpoint'] = $meta['target_endpoint'] ?? ('/api/tenant/views/'.str_replace('_', '-', $k));
            $decorated['permission'] = $meta['permission'] ?? null;

            if ($parentId !== null) {
                $decorated['parent'] = $parentId;
                $decorated['parent_id'] = $parentId;
                $decorated['type'] = 'link';
            }

            $children = [];
            foreach ($node['children'] ?? [] as $child) {
                if (is_array($child)) {
                    $decChild = $decorateNode($child, $k);
                    if ($decChild !== null) {
                        $children[] = $decChild;
                    }
                }
            }

            $decorated['children'] = $children;
            if (! empty($children)) {
                $decorated['type'] = 'accordion';
                $decorated = array_merge($decorated, self::collapsedFlags());
            } else {
                $decorated['type'] = 'link';
            }

            return $decorated;
        };

        $customSections = [];
        foreach ($tree as $treeSection) {
            if (! is_array($treeSection)) {
                continue;
            }
            $secKey = trim((string) ($treeSection['key'] ?? $treeSection['id'] ?? ''));
            if ($secKey === '') {
                continue;
            }
            $meta = $sectionMeta[$secKey] ?? [
                'id' => $secKey,
                'key' => $secKey,
                'title' => ucwords(str_replace('_', ' ', $secKey)),
                'label' => ucwords(str_replace('_', ' ', $secKey)),
                'color' => '#475569',
            ];

            $decoratedItems = [];
            foreach ($treeSection['items'] ?? [] as $itemNode) {
                if (is_array($itemNode)) {
                    $dec = $decorateNode($itemNode, null);
                    if ($dec !== null) {
                        $decoratedItems[] = $dec;
                    }
                }
            }

            if (! empty($decoratedItems)) {
                $customSections[] = array_merge($meta, [
                    'id' => $secKey,
                    'key' => $secKey,
                    'items' => $decoratedItems,
                ]);
            }
        }

        // Anchor administration if not present in custom sections
        if (! in_array('administration', array_column($customSections, 'key'), true)) {
            $customSections[] = self::getAdministrationSection();
        }

        return empty($customSections) ? null : array_values($customSections);
    }

    /**
     * Cashier & Sales section for core retail operations.
     *
     * @return array<string, mixed>
     */
    public static function getRetailSalesSection(): array
    {
        return self::normalizeSection([
            'id' => 'cashier_sales',
            'key' => 'cashier_sales',
            'label' => 'Cashier & Sales',
            'title' => 'Cashier & Sales',
            'color' => '#1d4ed8',
            'items' => [
                ['key' => 'pos', 'label' => 'Point of Sale', 'title' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                ['key' => 'sales', 'label' => 'Sales & Invoices', 'title' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'title' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                ['key' => 'consignments', 'label' => 'Consignments', 'title' => 'Consignments', 'icon' => 'local_shipping', 'component' => 'consignments', 'permission' => 'consignments', 'target_endpoint' => '/api/tenant/views/consignments'],
                ['key' => 'customers', 'label' => 'Customers & CRM', 'title' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
            ],
        ]);
    }

    /**
     * Products & Inventory section for catalog management.
     *
     * @return array<string, mixed>
     */
    public static function getInventorySection(): array
    {
        return self::normalizeSection([
            'id' => 'products_inventory',
            'key' => 'products_inventory',
            'label' => 'Products & Inventory',
            'title' => 'Products & Inventory',
            'color' => '#b45309',
            'items' => [
                ['key' => 'inventory', 'label' => 'Product Catalog', 'title' => 'Product Catalog', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products', 'target_endpoint' => '/api/tenant/views/inventory'],
                ['key' => 'categories', 'label' => 'Categories', 'title' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/categories'],
                ['key' => 'brands', 'label' => 'Brands & Manufacturers', 'title' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/brands'],
                ['key' => 'units', 'label' => 'Units of Measure', 'title' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units', 'target_endpoint' => '/api/tenant/views/units'],
                ['key' => 'suppliers', 'label' => 'Suppliers & Vendors', 'title' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers', 'target_endpoint' => '/api/tenant/views/suppliers'],
                ['key' => 'taxes', 'label' => 'Taxes & Compliance', 'title' => 'Taxes & Compliance', 'icon' => 'percent', 'component' => 'taxes', 'permission' => 'settings', 'target_endpoint' => '/api/tenant/views/settings-taxes'],
                ['key' => 'catalog', 'label' => 'Online Digital Catalog', 'title' => 'Online Digital Catalog', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog', 'target_endpoint' => '/api/tenant/views/catalog'],
            ],
        ]);
    }

    /**
     * Financial Management section.
     *
     * @return array<string, mixed>
     */
    public static function getFinancialSection(): array
    {
        return self::normalizeSection([
            'id' => 'financial_management',
            'key' => 'financial_management',
            'label' => 'Financial Management',
            'title' => 'Financial Management',
            'color' => '#0f766e',
            'items' => [
                ['key' => 'cash_register', 'label' => 'Cash Register', 'title' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'title' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                ['key' => 'payables', 'label' => 'Accounts Payable', 'title' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/payables'],
                ['key' => 'sales_targets', 'label' => 'Sales Targets', 'title' => 'Sales Targets', 'icon' => 'flag', 'component' => 'sales_targets', 'permission' => 'targets', 'target_endpoint' => '/api/tenant/views/sales-targets'],
                ['key' => 'reports', 'label' => 'Reports', 'title' => 'Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ['key' => 'analytics', 'label' => 'Analytics', 'title' => 'Analytics', 'icon' => 'bar_chart', 'component' => 'analytics', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/analytics'],
            ],
        ]);
    }

    /**
     * Sub-menu items for Restaurant Operations.
     *
     * @return list<array<string, mixed>>
     */
    public static function getRestaurantMenuItems(): array
    {
        return [
            [
                'key' => 'restaurant_pos',
                'label' => 'Restaurant POS Terminal',
                'title' => 'Restaurant POS Terminal',
                'icon' => 'restaurant',
                'component' => 'restaurant_pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-pos',
            ],
            [
                'key' => 'dining_history',
                'label' => 'KOT Register & Live Orders',
                'title' => 'KOT Register & Live Orders',
                'icon' => 'receipt_long',
                'component' => 'dining_history',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/dining-history',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices History',
                'title' => 'Sales & Invoices History',
                'icon' => 'receipt',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Party Orders',
                'title' => 'Quotations & Party Orders',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Customers & CRM',
                'title' => 'Customers & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'key' => 'cash_register',
                'label' => 'Cash Register',
                'title' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
            [
                'key' => 'floor_plan',
                'label' => 'Dining Tables & Floor Plan',
                'title' => 'Dining Tables & Floor Plan',
                'icon' => 'table_restaurant',
                'component' => 'floor_plan',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-tables',
            ],
            [
                'key' => 'kitchen_display',
                'label' => 'Kitchen Display (KDS)',
                'title' => 'Kitchen Display (KDS)',
                'icon' => 'soup_kitchen',
                'component' => 'kitchen_display',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-kds',
            ],
        ];
    }

    /**
     * Sub-menu items for Pharmacy Management.
     *
     * @return list<array<string, mixed>>
     */
    public static function getPharmacyMenuItems(): array
    {
        return [
            [
                'key' => 'pharmacy_pos',
                'label' => 'Pharmacy Counter POS',
                'title' => 'Pharmacy Counter POS',
                'icon' => 'local_pharmacy',
                'component' => 'pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/pos',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices History',
                'title' => 'Sales & Invoices History',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Estimates',
                'title' => 'Quotations & Estimates',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Patients & Customers',
                'title' => 'Patients & Customers',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'key' => 'cash_register',
                'label' => 'Cash Register',
                'title' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
            [
                'key' => 'pharmacy_batches',
                'label' => 'Batch & Expiry Manager',
                'title' => 'Batch & Expiry Manager',
                'icon' => 'medication',
                'component' => 'pharmacy_batches',
                'permission' => 'products',
                'target_endpoint' => '/api/tenant/views/pharmacy-batches',
            ],
            [
                'key' => 'pharmacy_prescriptions',
                'label' => 'Prescriptions Queue',
                'title' => 'Prescriptions Queue',
                'icon' => 'receipt_long',
                'component' => 'pharmacy_prescriptions',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/pharmacy-prescriptions',
            ],
        ];
    }

    /**
     * Sub-menu items for Repair & Technician Operations.
     *
     * @return list<array<string, mixed>>
     */
    public static function getRepairMenuItems(): array
    {
        return [
            [
                'key' => 'pos',
                'label' => 'Point of Sale',
                'title' => 'Point of Sale',
                'icon' => 'point_of_sale',
                'component' => 'pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/pos',
            ],
            [
                'key' => 'repair_dashboard',
                'label' => 'Repair Workbench',
                'title' => 'Repair Workbench',
                'icon' => 'handyman',
                'component' => 'repair_dashboard',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-dashboard',
            ],
            [
                'key' => 'repair_create_ticket',
                'label' => 'New Intake Ticket',
                'title' => 'New Intake Ticket',
                'icon' => 'add_task',
                'component' => 'repair_create_ticket',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-create-ticket',
            ],
            [
                'key' => 'repair_tickets',
                'label' => 'Repair Ticket Register',
                'title' => 'Repair Ticket Register',
                'icon' => 'receipt_long',
                'component' => 'repair_tickets',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-tickets',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices',
                'title' => 'Sales & Invoices',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Proposals',
                'title' => 'Quotations & Proposals',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Customers & CRM',
                'title' => 'Customers & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
        ];
    }

    /**
     * Sub-menu items for Salon & Bookings.
     *
     * @return list<array<string, mixed>>
     */
    public static function getSalonMenuItems(): array
    {
        return [
            [
                'key' => 'salon_pos',
                'label' => 'Salon POS & Checkout',
                'title' => 'Salon POS & Checkout',
                'icon' => 'spa',
                'component' => 'pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/pos',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices History',
                'title' => 'Sales & Invoices History',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Estimates',
                'title' => 'Quotations & Estimates',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Clients & CRM',
                'title' => 'Clients & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'key' => 'cash_register',
                'label' => 'Cash Register',
                'title' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
            [
                'key' => 'service_calendar',
                'label' => 'Service Booking Calendar',
                'title' => 'Service Booking Calendar',
                'icon' => 'event_available',
                'component' => 'service_calendar',
                'permission' => 'service_orders',
                'target_endpoint' => '/api/tenant/views/service-calendar',
            ],
            [
                'key' => 'service_stylists',
                'label' => 'Stylists & Staff Assignments',
                'title' => 'Stylists & Staff Assignments',
                'icon' => 'badge',
                'component' => 'staff',
                'permission' => 'users',
                'target_endpoint' => '/api/tenant/views/service-stylists',
            ],
            [
                'key' => 'service_catalog',
                'label' => 'Service Catalog & Rates',
                'title' => 'Service Catalog & Rates',
                'icon' => 'format_list_bulleted',
                'component' => 'service_catalog',
                'permission' => 'service_orders',
                'target_endpoint' => '/api/tenant/views/service-catalog',
            ],
            [
                'key' => 'service_create',
                'label' => 'Add New Service',
                'title' => 'Add New Service',
                'icon' => 'add_circle_outline',
                'component' => 'service_create',
                'permission' => 'service_orders',
                'target_endpoint' => '/api/tenant/views/service-create',
            ],
        ];
    }

    /**
     * Builds generic standalone section for dynamic server modules.
     *
     * @return array<string, mixed>|null
     */
    public static function buildGenericModuleSection(string $mod): ?array
    {
        $normalizedMod = strtolower(trim($mod));
        $dbModule = ModuleRegistry::find($normalizedMod);
        $navigation = $dbModule['navigation'] ?? null;
        if (is_array($navigation) && $navigation !== []) {
            $validated = self::validatedCustomNavigation($navigation);
            if (! empty($validated)) {
                foreach ($validated as $sec) {
                    $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
                    if ($secKey !== 'administration') {
                        return self::normalizeSection($sec);
                    }
                }
            }
        }

        $title = ucwords(str_replace(['_', '-'], ' ', $normalizedMod));

        return self::normalizeSection([
            'id' => $normalizedMod.'_operations',
            'key' => $normalizedMod.'_operations',
            'label' => $title.' Operations',
            'title' => $title.' Operations',
            'color' => '#6366f1',
            'items' => [
                [
                    'key' => $normalizedMod.'_pos',
                    'label' => $title.' POS',
                    'title' => $title.' POS',
                    'icon' => 'widgets',
                    'component' => 'pos',
                    'permission' => 'pos',
                    'target_endpoint' => '/api/tenant/views/'.$normalizedMod.'-pos',
                ],
            ],
        ]);
    }

    /**
     * Return enriched navigation section for an individual licensed module.
     *
     * @return array<string, mixed>|null
     */
    public static function menuStructureForModule(string $module): ?array
    {
        $mod = strtolower(trim($module));
        $mod = match ($mod) {
            'general', 'general_retail' => 'retail',
            'food_restaurant' => 'restaurant',
            default => $mod,
        };

        return match ($mod) {
            'restaurant' => self::normalizeSection([
                'id' => 'restaurant_operations',
                'key' => 'restaurant_operations',
                'title' => 'Restaurant Operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => self::getRestaurantMenuItems(),
            ]),
            'pharmacy' => self::normalizeSection([
                'id' => 'pharmacy_management',
                'key' => 'pharmacy_management',
                'title' => 'Pharmacy Management',
                'label' => 'Pharmacy Management',
                'color' => '#059669',
                'items' => self::getPharmacyMenuItems(),
            ]),
            'service_booking' => self::normalizeSection([
                'id' => 'salon_bookings',
                'key' => 'salon_bookings',
                'title' => 'Salon & Bookings',
                'label' => 'Salon & Bookings',
                'color' => '#7c3aed',
                'items' => self::getSalonMenuItems(),
            ]),
            'repair_technician' => self::normalizeSection([
                'id' => 'repair_service',
                'key' => 'repair_service',
                'title' => 'REPAIR OPERATIONS & SALES',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => self::getRepairMenuItems(),
            ]),
            'retail' => self::getRetailSalesSection(),
            default => self::buildGenericModuleSection($mod),
        };
    }

    /**
     * Return normalized common administration section.
     *
     * @return array<string, mixed>
     */
    public static function getAdministrationSection(): array
    {
        return self::normalizeSection(self::administrationSection());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function sectionsFor(bool|string $isRestaurantOrMode): array
    {
        if (is_bool($isRestaurantOrMode)) {
            $raw = $isRestaurantOrMode ? self::restaurantSections() : self::retailSections();

            return array_values(array_map([self::class, 'normalizeSection'], $raw));
        }

        $mode = strtolower(trim((string) $isRestaurantOrMode));
        $mode = match ($mode) {
            'general', 'general_retail' => 'retail',
            'food_restaurant' => 'restaurant',
            'repair', 'repairs', 'technician', 'repair_technician' => 'repair_technician',
            default => $mode,
        };
        $fallback = match ($mode) {
            'restaurant' => self::restaurantSections(),
            'pharmacy' => self::pharmacySections(),
            'service_booking' => self::serviceBookingSections(),
            'repair_technician' => self::repairTechnicianSections(),
            default => self::retailSections(),
        };

        $sections = $fallback;
        $module = ModuleRegistry::find($mode);
        $navigation = $module['navigation'] ?? null;
        if (is_array($navigation) && $navigation !== []) {
            $validated = self::validatedCustomNavigation($navigation);
            if ($validated !== null && $validated !== []) {
                $sections = $validated;
            } else {
                Log::warning('Invalid database SDUI navigation; using core menu fallback.', [
                    'mode' => $mode,
                ]);
            }
        } elseif (($module['source'] ?? null) === 'database') {
            Log::warning('Empty database SDUI navigation; using core menu fallback.', [
                'mode' => $mode,
            ]);
        }

        if (empty($sections)) {
            $sections = self::retailSections();
        }

        $normalized = array_values(array_map([self::class, 'normalizeSection'], $sections));

        return self::filterDomainMismatches($normalized, $isRestaurantOrMode);
    }

    /**
     * Return enriched menu structure for a given mode.
     *
     * @return list<array<string, mixed>>
     */
    public static function menuStructureForMode(string $mode): array
    {
        return self::getEffectiveNavForTenant($mode);
    }

    /**
     * Overrides section and item display titles if tenant customized them in navigation_labels.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, string>  $labels
     * @return list<array<string, mixed>>
     */
    public static function applyNavigationLabels(array $sections, array $labels): array
    {
        if (empty($labels)) {
            return $sections;
        }

        $resolveLabel = function (string ...$candidates) use ($labels): ?string {
            foreach ($candidates as $cand) {
                if ($cand !== '') {
                    if (! empty($labels[$cand])) {
                        return (string) $labels[$cand];
                    }
                    $k1 = str_replace('-', '_', $cand);
                    if (! empty($labels[$k1])) {
                        return (string) $labels[$k1];
                    }
                    $k2 = str_replace('_', '-', $cand);
                    if (! empty($labels[$k2])) {
                        return (string) $labels[$k2];
                    }
                }
            }

            return null;
        };

        $applyToItem = function (array $item) use (&$applyToItem, $resolveLabel): array {
            $key = (string) ($item['key'] ?? $item['id'] ?? '');
            $component = (string) ($item['component'] ?? '');
            $labelSlug = \Illuminate\Support\Str::snake(strtolower($item['label'] ?? ''));
            $candidates = [$key, $component, $labelSlug];
            if ($key === 'repair_dashboard' || $key === 'repair_workbench') {
                $candidates[] = 'repair_workbench';
                $candidates[] = 'repair_dashboard';
            }
            $custom = $resolveLabel(...$candidates);
            if ($custom !== null) {
                $item['title'] = $custom;
                $item['label'] = $custom;
            }
            if (! empty($item['children']) && is_array($item['children'])) {
                $item['children'] = array_map($applyToItem, $item['children']);
            }

            return $item;
        };

        return array_values(array_map(function ($section) use ($resolveLabel, $applyToItem) {
            if (! is_array($section)) {
                return $section;
            }
            $key = (string) ($section['key'] ?? $section['id'] ?? '');
            $custom = $resolveLabel($key);
            if ($custom !== null) {
                $section['title'] = $custom;
                $section['label'] = $custom;
            }
            if (! empty($section['items']) && is_array($section['items'])) {
                $section['items'] = array_map($applyToItem, $section['items']);
            }

            return $section;
        }, $sections));
    }

    /**
     * Normalizes a nav section ensuring id/key, label/title parity.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public static function normalizeSection(array $section): array
    {
        $key = trim((string) ($section['key'] ?? $section['id'] ?? ''));
        $title = trim((string) ($section['title'] ?? $section['label'] ?? $key));
        $color = $section['color'] ?? $section['header_color'] ?? '#475569';

        $items = [];
        foreach ($section['items'] ?? $section['children'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = self::normalizeItem($item);
            }
        }

        return array_merge($section, [
            'id' => $key,
            'key' => $key,
            'title' => $title,
            'label' => $title,
            'color' => $color,
            'items' => $items,
        ], self::collapsedFlags());
    }

    /**
     * Every parent/accordion node in the drawer must render closed on mount and
     * only open when the user taps it. Emit every flag name the various client
     * releases have looked at so none of them can fall back to "expanded".
     *
     * @return array<string, bool>
     */
    public static function collapsedFlags(): array
    {
        return [
            'initially_expanded' => false,
            'initiallyExpanded' => false,
            'expanded' => false,
            'is_expanded' => false,
            'isExpanded' => false,
            'default_open' => false,
            'defaultOpen' => false,
            'auto_expand' => false,
        ];
    }

    /**
     * Normalizes a nav item ensuring id/key, label/title parity and recursing into children.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalizeItem(array $item, ?string $parentId = null): array
    {
        $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
        $title = trim((string) ($item['title'] ?? $item['label'] ?? $key));
        $icon = (string) ($item['icon'] ?? 'widgets');
        $component = (string) ($item['component'] ?? $key);

        $normalized = array_merge($item, [
            'id' => $key,
            'key' => $key,
            'title' => $title,
            'label' => $title,
            'icon' => $icon,
            'component' => $component,
        ]);

        if ($parentId !== null) {
            $normalized['parent'] = $parentId;
            $normalized['parent_id'] = $parentId;
            $normalized['type'] = $normalized['type'] ?? 'link';
        }

        if (isset($item['children']) && is_array($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                if (is_array($child)) {
                    $children[] = self::normalizeItem($child, $key);
                }
            }
            $normalized['children'] = $children;
        }

        $hasChildren = ! empty($normalized['children']);
        if ($hasChildren || ($normalized['type'] ?? null) === 'accordion') {
            $normalized['type'] = 'accordion';
            $normalized = array_merge($normalized, self::collapsedFlags());
        } else {
            $normalized['type'] = $normalized['type'] ?? 'link';
        }

        if (! $hasChildren && empty($normalized['target_endpoint'])) {
            $routeKey = str_replace('_', '-', $key);
            $normalized['target_endpoint'] = '/api/tenant/views/'.$routeKey;
        }

        return $normalized;
    }

    /**
     * Validate database-authored navigation before it can replace the core
     * tree. One corrupted section must never turn the bootstrap menu into an
     * empty or partially unusable payload.
     *
     * @param  list<mixed>  $navigation
     * @return list<array<string, mixed>>|null
     */
    private static function validatedCustomNavigation(array $navigation): ?array
    {
        $sections = [];
        $seen = [];

        foreach ($navigation as $section) {
            if (! is_array($section)) {
                return null;
            }

            $key = trim((string) ($section['key'] ?? $section['id'] ?? ''));
            $items = $section['items'] ?? $section['children'] ?? null;
            if ($key === '' || isset($seen[$key]) || ! is_array($items) || $items === []) {
                return null;
            }

            $validatedItems = self::validatedCustomItems($items);
            if ($validatedItems === null || $validatedItems === []) {
                return null;
            }

            $title = trim((string) ($section['title'] ?? $section['label'] ?? $key));
            $color = $section['color'] ?? $section['header_color'] ?? '#475569';

            $seen[$key] = true;
            $section['id'] = $key;
            $section['key'] = $key;
            $section['title'] = $title;
            $section['label'] = $title;
            $section['color'] = $color;
            $section['items'] = $validatedItems;
            $sections[] = $section;
        }

        return $sections === [] ? null : $sections;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>|null
     */
    private static function validatedCustomItems(array $items): ?array
    {
        $validated = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                return null;
            }

            $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
            if ($key === '') {
                if (! empty($item['title'])) {
                    $key = \Illuminate\Support\Str::slug($item['title'], '_');
                } elseif (! empty($item['label'])) {
                    $key = \Illuminate\Support\Str::slug($item['label'], '_');
                } elseif (! empty($item['route'])) {
                    $key = \Illuminate\Support\Str::slug(basename($item['route']), '_');
                }
            }
            if ($key === '' || isset($seen[$key])) {
                return null;
            }

            if (! empty($item['route']) && empty($item['target_endpoint'])) {
                $item['target_endpoint'] = $item['route'];
            }

            $children = $item['children'] ?? [];
            if ($children !== null && ! is_array($children)) {
                return null;
            }

            $validatedChildren = is_array($children) && $children !== []
                ? self::validatedCustomItems($children)
                : [];
            if ($validatedChildren === null) {
                return null;
            }

            $title = trim((string) ($item['title'] ?? $item['label'] ?? $key));
            $seen[$key] = true;
            $item['id'] = $key;
            $item['key'] = $key;
            $item['title'] = $title;
            $item['label'] = $title;
            if (array_key_exists('children', $item) || $validatedChildren !== []) {
                $item['children'] = $validatedChildren;
            }
            $validated[] = $item;
        }

        return $validated;
    }

    private static function retailSections(): array
    {
        return [
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'color' => '#1d4ed8',
                'items' => [
                    ['key' => 'pos', 'label' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes'],
                    ['key' => 'consignments', 'label' => 'Consignments', 'icon' => 'local_shipping', 'component' => 'consignments', 'permission' => 'consignments'],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance'],
                    ['key' => 'sales_targets', 'label' => 'Sales Targets', 'icon' => 'flag', 'component' => 'sales_targets', 'permission' => 'targets'],
                    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar_chart', 'component' => 'analytics', 'permission' => 'reports'],
                ],
            ],
            [
                'key' => 'products_inventory',
                'label' => 'Products & Inventory',
                'color' => '#b45309',
                'items' => [
                    ['key' => 'inventory', 'label' => 'All Products', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories'],
                    ['key' => 'brands', 'label' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories'],
                    ['key' => 'units', 'label' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units'],
                    ['key' => 'suppliers', 'label' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                    ['key' => 'taxes', 'label' => 'Taxes & Compliance', 'icon' => 'percent', 'component' => 'taxes', 'permission' => 'settings'],
                    ['key' => 'catalog', 'label' => 'Online Digital Catalog', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function restaurantSections(): array
    {
        return [
            [
                'key' => 'restaurant_operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => [
                    ['key' => 'restaurant_pos', 'label' => 'Restaurant POS Terminal', 'icon' => 'restaurant', 'component' => 'restaurant_pos', 'permission' => 'pos'],
                    ['key' => 'floor_plan', 'label' => 'Floor Plan & Tables', 'icon' => 'table_restaurant', 'component' => 'floor_plan', 'permission' => 'pos'],
                    ['key' => 'kitchen_display', 'label' => 'Kitchen Display (KDS)', 'icon' => 'soup_kitchen', 'component' => 'kitchen_display', 'permission' => 'pos'],
                ],
            ],
            [
                'key' => 'orders_cash',
                'label' => 'Orders & Cash',
                'color' => '#0284c7',
                'items' => [
                    ['key' => 'dining_history', 'label' => 'Dining & Sales History', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                ],
            ],
            [
                'key' => 'kitchen_menu_catalog',
                'label' => 'Kitchen Menu & Catalog',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Menu Dishes & Stock', 'icon' => 'restaurant_menu', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories'],
                    ['key' => 'brands', 'label' => 'Brands & Modifiers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories'],
                    ['key' => 'units', 'label' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units'],
                    ['key' => 'suppliers', 'label' => 'Food Suppliers', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                    ['key' => 'catalog', 'label' => 'Online QR Menu', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function pharmacySections(): array
    {
        return [
            [
                'key' => 'pharmacy_dispensary',
                'label' => 'Dispensary & Counter',
                'color' => '#059669',
                'items' => [
                    ['key' => 'pharmacy_pos', 'label' => 'Pharmacy Counter POS', 'icon' => 'local_pharmacy', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                    ['key' => 'sales', 'label' => 'Dispensed Prescriptions', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Estimates', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'label' => 'Patients & Doctors', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                ],
            ],
            [
                'key' => 'pharmacy_inventory',
                'label' => 'Medicines & Inventory',
                'color' => '#2563eb',
                'items' => [
                    ['key' => 'pharmacy_batches', 'label' => 'Batch & Expiry Manager', 'icon' => 'medication', 'component' => 'pharmacy_batches', 'permission' => 'products', 'target_endpoint' => '/api/tenant/views/pharmacy-batches'],
                    ['key' => 'pharmacy_prescriptions', 'label' => 'Prescriptions Queue', 'icon' => 'receipt_long', 'component' => 'pharmacy_prescriptions', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/pharmacy-prescriptions'],
                    ['key' => 'inventory', 'label' => 'Drugs & Formulations', 'icon' => 'medication', 'component' => 'inventory', 'permission' => 'products', 'target_endpoint' => '/api/tenant/views/inventory'],
                    ['key' => 'categories', 'label' => 'Therapeutic Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/categories'],
                    ['key' => 'suppliers', 'label' => 'Pharma Distributors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers', 'target_endpoint' => '/api/tenant/views/suppliers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                    ['key' => 'due_receivables', 'label' => 'Patient Credit / Khata', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                    ['key' => 'payables', 'label' => 'Supplier Payables', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/payables'],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function serviceBookingSections(): array
    {
        return [
            [
                'key' => 'service_operations',
                'label' => 'Appointments & Service',
                'color' => '#7c3aed',
                'items' => [
                    ['key' => 'salon_pos', 'label' => 'Service POS & Checkout', 'icon' => 'spa', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices History', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Estimates', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'label' => 'Clients & Memberships', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                    ['key' => 'service_calendar', 'label' => 'Appointments & Bookings', 'icon' => 'event_available', 'component' => 'service_calendar', 'permission' => 'service_orders', 'target_endpoint' => '/api/tenant/views/service-calendar'],
                ],
            ],
            [
                'key' => 'products_staff',
                'label' => 'Supplies & Staff',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Products & Supplies', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products', 'target_endpoint' => '/api/tenant/views/inventory'],
                    ['key' => 'service_catalog', 'label' => 'Service Catalog & Rates', 'icon' => 'format_list_bulleted', 'component' => 'service_catalog', 'permission' => 'service_orders', 'target_endpoint' => '/api/tenant/views/service-catalog'],
                    ['key' => 'service_create', 'label' => 'Add New Service', 'icon' => 'add_circle_outline', 'component' => 'service_create', 'permission' => 'service_orders', 'target_endpoint' => '/api/tenant/views/service-create'],
                    ['key' => 'staff', 'label' => 'Specialists & Stylists', 'icon' => 'badge', 'component' => 'staff', 'permission' => 'users', 'target_endpoint' => '/api/tenant/views/service-stylists'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                    ['key' => 'due_receivables', 'label' => 'Client Due Receivables', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                    ['key' => 'reports', 'label' => 'Service Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function repairTechnicianSections(): array
    {
        return [
            [
                'key' => 'repair_operations',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => [
                    ['key' => 'pos', 'label' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                    ['key' => 'repair_dashboard', 'label' => 'Repair Workbench', 'icon' => 'handyman', 'component' => 'repair_dashboard', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-dashboard'],
                    ['key' => 'repair_create_ticket', 'label' => 'New Intake Ticket', 'icon' => 'add_task', 'component' => 'repair_create_ticket', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-create-ticket'],
                    ['key' => 'repair_tickets', 'label' => 'Repair Ticket Register', 'icon' => 'receipt_long', 'component' => 'repair_tickets', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-tickets'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
                ],
            ],
            [
                'key' => 'spare_parts_inventory',
                'label' => 'Spare Parts & Inventory',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Parts & Consumables', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'category', 'component' => 'categories', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/categories'],
                    ['key' => 'suppliers', 'label' => 'Parts Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Repair Invoices Due', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Workshop Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function administrationSection(): array
    {
        $tabs = self::settingsTabItems();

        return [
            'key' => 'administration',
            'label' => 'Administration & Settings',
            'title' => 'Administration & Settings',
            'color' => '#475569',
            'items' => [
                ['key' => 'subscription', 'label' => 'Subscription & Billing', 'title' => 'Subscription & Billing', 'icon' => 'workspace_premium', 'component' => 'subscription', 'type' => 'link', 'permission' => null],
                [
                    'key' => 'settings',
                    'label' => 'Store Settings',
                    'title' => 'Store Settings',
                    'icon' => 'settings',
                    'component' => 'settings',
                    'type' => 'accordion',
                    'permission' => 'settings',
                    'children' => $tabs,
                ],
                // Retain the flat rows for the existing navigation editor and
                // older clients. Current SDUI clients de-duplicate by key and
                // use the canonical children tree above for drawer rendering.
                ...$tabs,
                ['key' => 'languages', 'label' => 'Languages & Translations', 'title' => 'Languages & Translations', 'icon' => 'translate', 'component' => 'languages', 'type' => 'link', 'permission' => 'settings'],
                ['key' => 'staff', 'label' => 'Users & Permissions', 'title' => 'Users & Permissions', 'icon' => 'badge', 'component' => 'staff', 'type' => 'link', 'permission' => 'users'],
                ['key' => 'roles', 'label' => 'Roles & Access Levels', 'title' => 'Roles & Access Levels', 'icon' => 'admin_panel_settings', 'component' => 'roles', 'type' => 'link', 'permission' => 'users', 'target_endpoint' => '/api/tenant/views/roles'],
                ['key' => 'devices', 'label' => 'Terminals & Devices', 'title' => 'Terminals & Devices', 'icon' => 'devices_other', 'component' => 'devices', 'type' => 'link', 'permission' => null],
                ['key' => 'change_password', 'label' => 'Change Password', 'title' => 'Change Password', 'icon' => 'lock_reset', 'component' => 'change_password', 'type' => 'link', 'permission' => null, 'target_endpoint' => '/api/tenant/views/change-password'],
            ],
        ];
    }

    /**
     * Store Settings' seven tenant-owned tabs with declarative SDUI target endpoints.
     */
    public static function settingsTabItems(): array
    {
        return [
            [
                'key' => 'settings_mode',
                'label' => 'Store Operating Mode',
                'title' => 'Store Operating Mode',
                'icon' => 'flash',
                'component' => 'settings_mode',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-mode',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_profile',
                'label' => 'Store Profile',
                'title' => 'Store Profile',
                'icon' => 'storefront',
                'component' => 'settings_profile',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-profile',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_branding',
                'label' => 'Branding & Colors',
                'title' => 'Store Branding & Colors',
                'icon' => 'palette',
                'component' => 'settings_branding',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-branding',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_receipts',
                'label' => 'Receipt Prefixes & Bank Terms',
                'title' => 'Receipt Prefixes & Bank Terms',
                'icon' => 'receipt',
                'component' => 'settings_receipts',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-receipts',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_financial',
                'label' => 'Financial & Currency',
                'title' => 'Financial & Currency',
                'icon' => 'monetization_on',
                'component' => 'settings_financial',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-financial',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_taxes',
                'label' => 'Taxes & Compliance',
                'title' => 'Taxes & Compliance',
                'icon' => 'percent',
                'component' => 'settings_taxes',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-taxes',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_api',
                'label' => 'API & Integrations',
                'title' => 'API & Integrations',
                'icon' => 'api',
                'component' => 'settings_api',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-api',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_navigation',
                'label' => 'Navigation Menu',
                'title' => 'Navigation Menu',
                'icon' => 'menu_open',
                'component' => 'settings_navigation',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-navigation',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
        ];
    }
}
