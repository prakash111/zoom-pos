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
     * Return guaranteed non-empty navigation sections for a tenant or mode.
     *
     * @param  \App\Models\Company|string|null  $companyOrMode
     * @return list<array<string, mixed>>
     */
    public static function getEffectiveNavForTenant(mixed $companyOrMode): array
    {
        if ($companyOrMode instanceof Company) {
            $mode = ModuleRegistry::resolveActiveMode($companyOrMode);
        } elseif (is_string($companyOrMode) && trim($companyOrMode) !== '') {
            $mode = trim($companyOrMode);
        } else {
            $mode = 'retail';
        }

        $mode = strtolower(trim($mode));
        $mode = match ($mode) {
            'general', 'general_retail' => 'retail',
            'food_restaurant' => 'restaurant',
            default => $mode,
        };

        $sections = self::sectionsFor($mode);
        if (empty($sections)) {
            $sections = array_values(array_map([self::class, 'normalizeSection'], self::retailSections()));
        }

        return $sections;
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
            default => $mode,
        };
        $fallback = match ($mode) {
            'restaurant' => self::restaurantSections(),
            'pharmacy' => self::pharmacySections(),
            'service_booking' => self::serviceBookingSections(),
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

        return array_values(array_map([self::class, 'normalizeSection'], $sections));
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
        ]);
    }

    /**
     * Normalizes a nav item ensuring id/key, label/title parity and recursing into children.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalizeItem(array $item): array
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

        if (isset($item['children']) && is_array($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                if (is_array($child)) {
                    $children[] = self::normalizeItem($child);
                }
            }
            $normalized['children'] = $children;
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
            if ($key === '' || isset($seen[$key])) {
                return null;
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
                    ['key' => 'service_orders', 'label' => 'Service Orders', 'icon' => 'handyman', 'component' => 'service_orders', 'permission' => 'service_orders'],
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
                    ['key' => 'pos', 'label' => 'Pharmacy Counter POS', 'icon' => 'local_pharmacy', 'component' => 'pos', 'permission' => 'pos'],
                    ['key' => 'sales', 'label' => 'Dispensed Prescriptions', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'customers', 'label' => 'Patients & Doctors', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers'],
                ],
            ],
            [
                'key' => 'pharmacy_inventory',
                'label' => 'Medicines & Inventory',
                'color' => '#2563eb',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Drugs & Formulations', 'icon' => 'medication', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Therapeutic Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories'],
                    ['key' => 'suppliers', 'label' => 'Pharma Distributors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Patient Credit / Khata', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'payables', 'label' => 'Supplier Payables', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
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
                    ['key' => 'service_orders', 'label' => 'Appointments & Bookings', 'icon' => 'event_available', 'component' => 'service_orders', 'permission' => 'service_orders'],
                    ['key' => 'pos', 'label' => 'Service POS & Checkout', 'icon' => 'spa', 'component' => 'pos', 'permission' => 'pos'],
                    ['key' => 'customers', 'label' => 'Clients & Memberships', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers'],
                ],
            ],
            [
                'key' => 'products_staff',
                'label' => 'Supplies & Staff',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Products & Supplies', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'staff', 'label' => 'Specialists & Stylists', 'icon' => 'badge', 'component' => 'staff', 'permission' => 'users'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Client Due Receivables', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Service Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
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
                ['key' => 'devices', 'label' => 'Terminals & Devices', 'title' => 'Terminals & Devices', 'icon' => 'devices_other', 'component' => 'devices', 'type' => 'link', 'permission' => null],
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
                'label' => 'Store Profile & Branding',
                'title' => 'Store Profile & Branding',
                'icon' => 'storefront',
                'component' => 'settings_profile',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-profile',
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
