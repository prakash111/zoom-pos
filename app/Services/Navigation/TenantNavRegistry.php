<?php

namespace App\Services\Navigation;

/**
 * The compiled-in nav tree for the web tenant sidebar/drawer (layouts/
 * tenant.blade.php's "RESTAURANT MODE DRAWER ITEMS" / "GENERAL RETAIL
 * DRAWER ITEMS" / "Administration & Settings" sections), as plain data —
 * used by Settings > Navigation Menu to build its editable working state
 * and to validate what a saved nav_config actually references. Section and
 * item keys are the exact same ones the mobile app's DashboardScreen uses
 * (_NavSection.key / _FeatureTile.key) and the same ones the `item-key`/
 * `data-section-key` attributes in layouts/tenant.blade.php carry, so a
 * store's nav_config customization applies identically on both platforms.
 *
 * This is intentionally a second, hand-maintained copy of the tree rather
 * than something introspected from the blade file at runtime — the two are
 * kept in sync by hand (adding a drawer item means adding it here too),
 * the same tradeoff the mobile app's own DashboardScreen tree already makes
 * against the web sidebar.
 *
 * An item may carry a `parent` key naming another item's key in the same
 * section — it then renders nested under that item by default (Settings'
 * eight tabs are the only compiled-in example, nested under `settings`) and
 * an admin can drag it back out to the section root, or nest any other
 * item, from Settings > Navigation Menu. Only one level of nesting is
 * supported.
 */
class TenantNavRegistry
{
    /**
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, parent?: string}>}>
     */
    public static function sectionsFor(bool $isRestaurant): array
    {
        return $isRestaurant ? self::restaurantSections() : self::retailSections();
    }

    private static function retailSections(): array
    {
        return [
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'items' => [
                    ['key' => 'pos', 'label' => 'Cashier POS Terminal'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices'],
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals'],
                    ['key' => 'customers', 'label' => 'Customers & CRM'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register'],
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable'],
                    ['key' => 'payables', 'label' => 'Accounts Payable'],
                    ['key' => 'reports', 'label' => 'Reports & Analytics'],
                ],
            ],
            [
                'key' => 'products_inventory',
                'label' => 'Products & Inventory',
                'items' => [
                    ['key' => 'inventory', 'label' => 'All Products'],
                    ['key' => 'categories', 'label' => 'Categories'],
                    ['key' => 'brands', 'label' => 'Brands & Manufacturers'],
                    ['key' => 'units', 'label' => 'Units of Measure'],
                    ['key' => 'suppliers', 'label' => 'Suppliers & Vendors'],
                    ['key' => 'catalog', 'label' => 'Online Digital Catalog'],
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
                'items' => [
                    ['key' => 'restaurant_pos', 'label' => 'Restaurant POS Terminal'],
                    ['key' => 'floor_plan', 'label' => 'Floor Plan & Tables'],
                    ['key' => 'kitchen_display', 'label' => 'Kitchen Display (KDS)'],
                ],
            ],
            [
                'key' => 'orders_cash',
                'label' => 'Orders & Cash',
                'items' => [
                    ['key' => 'dining_history', 'label' => 'Dining & Sales History'],
                    ['key' => 'cash_register', 'label' => 'Cash Register'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'items' => [
                    ['key' => 'accounts_receivable', 'label' => 'Accounts Receivable'],
                    ['key' => 'accounts_payable', 'label' => 'Accounts Payable'],
                    ['key' => 'reports_analytics', 'label' => 'Reports & Analytics'],
                ],
            ],
            [
                'key' => 'kitchen_menu_catalog',
                'label' => 'Kitchen Menu & Catalog',
                'items' => [
                    ['key' => 'menu_dishes', 'label' => 'Menu Dishes & Stock'],
                    ['key' => 'categories', 'label' => 'Categories'],
                    ['key' => 'brands', 'label' => 'Brands & Modifiers'],
                    ['key' => 'units', 'label' => 'Units of Measure'],
                    ['key' => 'suppliers', 'label' => 'Food Suppliers'],
                    ['key' => 'catalog', 'label' => 'Online QR Menu'],
                    ['key' => 'guest_directory', 'label' => 'Guest Directory'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function administrationSection(): array
    {
        return [
            'key' => 'administration',
            'label' => 'Administration & Settings',
            'items' => [
                ['key' => 'subscription', 'label' => 'Subscription & Billing'],
                ['key' => 'settings', 'label' => 'Store Settings'],
                ...self::settingsTabItems(),
                ['key' => 'languages', 'label' => 'Languages & Translations'],
                ['key' => 'staff', 'label' => 'Users & Permissions'],
                ['key' => 'devices', 'label' => 'Terminals & Devices'],
            ],
        ];
    }

    /**
     * Store Settings' eight tabs (resources/views/livewire/tenant/settings/
     * index.blade.php's `validTabs`/`#hash` routing), exposed as independent
     * nav items nested under `settings` by default so an admin can pin a
     * direct link to just one tab, reorder them, or un-nest one to the
     * section root — without changing the Settings page itself.
     */
    private static function settingsTabItems(): array
    {
        return [
            ['key' => 'settings_mode', 'label' => 'Store Operating Mode', 'parent' => 'settings'],
            ['key' => 'settings_profile', 'label' => 'Store Profile & Branding', 'parent' => 'settings'],
            ['key' => 'settings_receipts', 'label' => 'Receipt Prefixes & Bank Terms', 'parent' => 'settings'],
            ['key' => 'settings_financial', 'label' => 'Financial & Currency', 'parent' => 'settings'],
            ['key' => 'settings_taxes', 'label' => 'Taxes & Compliance', 'parent' => 'settings'],
            ['key' => 'settings_api', 'label' => 'API & Integrations', 'parent' => 'settings'],
            ['key' => 'settings_notifications', 'label' => 'Notification & Dispatch', 'parent' => 'settings'],
            ['key' => 'settings_navigation', 'label' => 'Navigation Menu', 'parent' => 'settings'],
        ];
    }
}
