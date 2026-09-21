<?php

namespace App\Services\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Flat (user_id, module, action) -> allowed matrix, default-deny, with a
 * privileged-role bypass and role default presets.
 */
class PermissionChecker
{
    public const MODULES = [
        'pos' => 'Point of Sale (POS)',
        'sales' => 'Sales & Receipts',
        'consignments' => 'Consignments & Merchandise Dispatch',
        'quotes' => 'Quotes & Proposals',
        'service_orders' => 'Service Orders & Warranty Repairs',
        'products' => 'Products & Inventory',
        'categories' => 'Categories & Brands',
        'units' => 'Units of Measure',
        'suppliers' => 'Suppliers & Vendors',
        'customers' => 'Customers & CRM',
        'catalog' => 'Online Digital Catalog',
        'cash_register' => 'Cash Register & Drawer Management',
        'finance' => 'Finance & Expenses',
        'repair' => 'Repair & Service Workbench',
        'reports' => 'Reports & Analytics',
        'targets' => 'Sales Targets & Goals',
        'settings' => 'Store Settings & SMTP',
        'users' => 'Users & Permissions',
        'leads' => 'Lead Management System',
        'restaurant' => 'Restaurant POS Terminal',
        'pharmacy' => 'Pharmacy POS & Checkout',
        'salon' => 'Salon POS & Checkout',
        'coupons' => 'Coupons & Discounts',
        'faqs' => 'Store FAQs & Help Center',
        'reviews' => 'Product Ratings & Reviews',
        'storefront' => 'Storefront & Online Sales',
        'gateways' => 'Payment Gateways & Integrations',
    ];

    public const ACTIONS = [
        'view' => 'View / Read',
        'create' => 'Create / Add',
        'edit' => 'Edit / Update',
        'delete' => 'Delete / Remove',
        'export' => 'Export / Download',
    ];

    /**
     * Granular action definitions per module.
     * Monolithic modules are broken down into specific operational actions.
     */
    public const MODULE_ACTIONS = [
        'quotes' => [
            'view' => 'View quotation records',
            'create' => 'Draft new quotations',
            'edit' => 'Update line items and terms',
            'delete' => 'Cancel / delete quotations',
            'approve' => 'Approve formal proposal',
            'convert_to_sale' => 'Convert quotation into an active POS checkout / sale',
            'export' => 'Export PDF proposals / send',
        ],
        'sales' => [
            'view' => 'View sales records & history',
            'create' => 'Scan items and build cart',
            'process_payment' => 'Accept payment and close invoice',
            'apply_discount' => 'Modify unit price or add custom discounts',
            'void' => 'Void finalized sales transactions',
            'edit' => 'Update or refund sales',
            'delete' => 'Cancel / delete sales',
            'export' => 'Export sales ledgers',
        ],
        'consignments' => [
            'view' => 'View consignment records & status',
            'create' => 'Dispatch merchandise / create consignment',
            'reconcile' => 'Reconcile returned / sold quantities',
            'finalize_invoice' => 'Convert sold units to finalized invoice',
            'edit' => 'Update consignment details',
            'delete' => 'Void or cancel consignments',
            'export' => 'Download consignment ledger',
        ],
        'service_orders' => [
            'view' => 'View service orders & warranty status',
            'create' => 'Create service order intake',
            'edit' => 'Update technical diagnosis, parts & labor',
            'delete' => 'Cancel / delete service orders',
            'export' => 'Print service tickets & receipts',
        ],
        'repair' => [
            'view' => 'Access workbench and ticket lists',
            'create' => 'Create intake tickets and accept advance deposits',
            'diagnose' => 'Update inspection checklists, assign parts, and update labor fees',
            'assign' => 'Assign or reassign tickets to specific technicians',
            'checkout' => 'Settle tickets, apply payments, and mark as delivered',
            'delete' => 'Void or delete tickets',
        ],
        'pos' => [
            'view' => 'Access Point of Sale terminal',
            'create' => 'Process new orders & sales',
            'edit' => 'Modify cart & inline price overrides',
            'delete' => 'Void active orders / clear cart',
            'export' => 'Print / export receipts',
        ],
        'products' => [
            'view' => 'View product catalogue & stock',
            'create' => 'Add new products',
            'edit' => 'Edit product details & pricing',
            'delete' => 'Delete products',
            'export' => 'Export product listings',
        ],
        'categories' => [
            'view' => 'View categories & brands',
            'create' => 'Create categories & brands',
            'edit' => 'Edit categories & brands',
            'delete' => 'Delete categories & brands',
            'export' => 'Export categories & brands',
        ],
        'units' => [
            'view' => 'View units of measure',
            'create' => 'Create units of measure',
            'edit' => 'Edit units of measure',
            'delete' => 'Delete units of measure',
            'export' => 'Export units list',
        ],
        'suppliers' => [
            'view' => 'View supplier directory',
            'create' => 'Add new suppliers',
            'edit' => 'Edit supplier profiles',
            'delete' => 'Delete suppliers',
            'export' => 'Export supplier contacts',
        ],
        'customers' => [
            'view' => 'View customer directory & CRM',
            'create' => 'Add new customers',
            'edit' => 'Edit customer profiles',
            'delete' => 'Delete customers',
            'export' => 'Export customer lists',
        ],
        'catalog' => [
            'view' => 'View digital catalog',
            'create' => 'Create catalog entries',
            'edit' => 'Update catalog settings',
            'delete' => 'Delete catalog items',
            'export' => 'Export catalog data',
        ],
        'cash_register' => [
            'view' => 'View drawer balance & history',
            'create' => 'Open register shift / add float',
            'edit' => 'Sangria & Suprimento operations',
            'delete' => 'Close register & finalize shift',
            'export' => 'Export shift Z-reports',
        ],
        'finance' => [
            'view' => 'View income & expense records',
            'create' => 'Add financial entries',
            'edit' => 'Edit financial transactions',
            'delete' => 'Delete financial records',
            'export' => 'Export financial statements',
        ],
        'reports' => [
            'view' => 'View reports & dashboards',
            'create' => 'Generate custom reports',
            'edit' => 'Customize report filters',
            'delete' => 'Delete saved reports',
            'export' => 'Export reports to CSV/PDF',
        ],
        'targets' => [
            'view' => 'View store & staff targets',
            'create' => 'Set monthly targets and staff quotas',
            'edit' => 'Edit monthly targets and staff quotas',
            'delete' => 'Remove targets',
            'export' => 'Export target reports',
        ],
        'settings' => [
            'view' => 'View system settings',
            'create' => 'Configure integrations',
            'edit' => 'Update store settings',
            'delete' => 'Reset configuration',
            'export' => 'Export backup files',
        ],
        'users' => [
            'view' => 'View user accounts & team',
            'create' => 'Invite new team members',
            'edit' => 'Edit user roles & details',
            'delete' => 'Deactivate / remove users',
            'export' => 'Export user list',
        ],
        'leads' => [
            'view' => 'View assigned leads',
            'view_any' => 'View all organization leads',
            'create' => 'Capture new leads',
            'edit' => 'Update lead details & stage',
            'delete' => 'Delete leads',
            'assign' => 'Assign leads to sales representatives',
            'convert' => 'Convert leads to customers & invoices',
            'export' => 'Export leads data',
        ],
        'restaurant' => [
            'view' => 'Access floor plan, dining tables & active orders',
            'create' => 'Open dining tables & create KOT orders',
            'edit' => 'Modify table orders, transfer tables & split checks',
            'delete' => 'Void KOT items or cancel dining orders',
            'manage_tables' => 'Configure dining rooms, tables & floor plans',
            'manage_kot' => 'Dispatch to kitchen & manage kitchen display (KDS)',
            'settle' => 'Settle table bills & process checkout',
            'export' => 'Export restaurant sales & dining reports',
        ],
        'pharmacy' => [
            'view' => 'Access pharmacy checkout, patient queue & prescriptions',
            'create' => 'Intake prescriptions & dispense medications',
            'edit' => 'Update drug dosages, substitutes & dispensing notes',
            'delete' => 'Cancel prescriptions or void dispensed items',
            'manage_batches' => 'Manage drug batches, expiry dates & lot numbers',
            'verify_rx' => 'Doctor & pharmacist prescription verification',
            'export' => 'Export controlled substance logs & dispensing reports',
        ],
        'salon' => [
            'view' => 'Access appointment calendar & staff roster',
            'create' => 'Book client appointments & service sessions',
            'edit' => 'Reschedule appointments & modify service packages',
            'delete' => 'Cancel appointments or remove booked services',
            'manage_stylists' => 'Assign stylists, specialists & chair schedules',
            'checkout' => 'Process salon checkout, tips & commission splits',
            'export' => 'Export appointments & stylist commission reports',
        ],
        'coupons' => [
            'view' => 'View coupon lists and usage statistics',
            'create' => 'Create new discount coupons and promo codes',
            'edit' => 'Update coupon discount rates, limits, and expiration',
            'delete' => 'Delete promotional coupons',
        ],
        'faqs' => [
            'view' => 'View store frequently asked questions',
            'create' => 'Add new storefront FAQs',
            'edit' => 'Update FAQ questions, answers, and sort order',
            'delete' => 'Delete store FAQs',
        ],
        'reviews' => [
            'view' => 'View product ratings and customer feedback',
            'create' => 'Submit reviews or write responses',
            'edit' => 'Approve or moderate customer reviews',
            'delete' => 'Delete customer reviews',
        ],
        'storefront' => [
            'view' => 'View storefront overview & live preview',
            'manage' => 'Manage storefront settings, domain & banner',
            'inquiries' => 'View and manage storefront inquiries',
            'inquiries.view' => 'View customer inquiries submitted on storefront',
            'inquiries.action' => 'Update inquiry status, reply or delete inquiries',
            'edit' => 'Edit storefront settings',
        ],
        'gateways' => [
            'view' => 'View active payment gateways',
            'manage' => 'Configure storefront payment gateways & credentials',
            'edit' => 'Update gateway settings',
        ],
    ];

    public static function canonicalModuleSlug(string $module): string
    {
        $slug = strtolower(trim($module));

        return match ($slug) {
            'food_restaurant', 'restaurant_pos' => 'restaurant',
            'pharmacy_pos', 'chemist' => 'pharmacy',
            'service_booking', 'salon_pos', 'beauty', 'spa', 'wellness' => 'salon',
            'repairs', 'repair_technician', 'repairtechnician', 'technician' => 'repair',
            'quotations', 'quote' => 'quotes',
            'inventory', 'product' => 'products',
            'lead', 'leadmanagement', 'lead_management' => 'leads',
            'service_order' => 'service_orders',
            default => $slug,
        };
    }

    public static function getActionsForModule(string $module): array
    {
        $canonical = self::canonicalModuleSlug($module);

        return self::MODULE_ACTIONS[$canonical] ?? self::MODULE_ACTIONS[$module] ?? self::ACTIONS;
    }

    public static function getActionDescription(string $module, string $action): string
    {
        $canonical = self::canonicalModuleSlug($module);

        if (isset(self::MODULE_ACTIONS[$canonical][$action])) {
            return self::MODULE_ACTIONS[$canonical][$action];
        }

        if (isset(self::MODULE_ACTIONS[$module][$action])) {
            return self::MODULE_ACTIONS[$module][$action];
        }

        return self::ACTIONS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    public static function can(User $user, string $module, string $action = 'view'): bool
    {
        return app(self::class)->allows($user, $module, $action);
    }

    public function allows(User $user, string $module, string $action = 'view'): bool
    {
        if (str_contains($module, '.') && ($action === 'view' || empty($action))) {
            [$mod, $act] = explode('.', $module, 2);
            $module = $mod;
            $action = $act;
        }

        $canonical = self::canonicalModuleSlug($module);
        // Tenant owners and explicit role grants cannot activate an extension.
        if ($canonical === 'leads' && ! $user->company?->hasModule('leadmanagement')) {
            return false;
        }

        if ($user->isPrivilegedRole()) {
            return true;
        }

        $perm = Permission::query()
            ->where('user_id', $user->id)
            ->whereIn('module', array_unique([$module, $canonical]))
            ->where(function ($q) use ($action) {
                $q->where('action', $action)
                    ->orWhere('action', 'manage')
                    ->orWhere('action', '*');
            })
            ->first();

        if ($perm !== null) {
            return (bool) $perm->allowed;
        }

        // If user has any explicit permissions configured, default to deny for unlisted actions
        $hasAnyExplicit = Permission::query()->where('user_id', $user->id)->exists();
        if ($hasAnyExplicit) {
            return false;
        }

        return $this->roleDefaultAllows($user->role, $module, $action, $user->company_id);
    }

    /** @var array<string, array<string, list<string>>|null> */
    private static array $roleMapCache = [];

    public function roleDefaultAllows(?string $role, string $module, string $action, ?string $companyId = null): bool
    {
        $role = strtolower($role ?? '');
        if (in_array($role, User::PRIVILEGED_ROLES, true)) {
            return true;
        }

        // A stored role row (built-in system role OR a tenant's custom role)
        // takes precedence; the hardcoded presets remain the fallback for a
        // fresh install whose roles table has not been seeded yet.
        $cacheKey = ($companyId ?? '-').'|'.$role;
        if (! array_key_exists($cacheKey, self::$roleMapCache)) {
            try {
                self::$roleMapCache[$cacheKey] = Role::permissionMapFor($companyId, $role);
            } catch (\Throwable $e) {
                self::$roleMapCache[$cacheKey] = null;
            }
        }

        $map = self::$roleMapCache[$cacheKey] ?? self::getRoleDefaults($role);
        $canonical = self::canonicalModuleSlug($module);
        $allowed = $map[$module] ?? $map[$canonical] ?? [];

        if (in_array($action, $allowed, true)) {
            return true;
        }

        if (in_array('manage', $allowed, true)) {
            return true;
        }

        if (str_starts_with($action, 'inquiries.') && in_array('inquiries', $allowed, true)) {
            return true;
        }

        return false;
    }

    /**
     * Forget the per-request custom-role cache (call after mutating roles).
     */
    public static function flushRoleCache(): void
    {
        self::$roleMapCache = [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function getRoleDefaults(string $role): array
    {
        $role = strtolower($role);

        switch ($role) {
            case User::ROLE_ADMINISTRATOR:
            case 'admin':
            case 'administrador':
            case 'owner':
            case 'tenant_admin':
            case 'tenant-admin':
            case 'superadmin':
                // Full access across all modules and all actions
                $map = [];
                foreach (self::MODULES as $mod => $label) {
                    $map[$mod] = array_keys(self::getActionsForModule($mod));
                }

                return $map;

            case User::ROLE_MANAGER:
                return [
                    'pos' => ['view', 'create', 'edit', 'delete', 'export'],
                    'sales' => ['view', 'create', 'process_payment', 'apply_discount', 'void', 'edit', 'export'],
                    'consignments' => ['view', 'create', 'reconcile', 'finalize_invoice', 'edit', 'delete', 'export'],
                    'quotes' => ['view', 'create', 'edit', 'delete', 'approve', 'convert_to_sale', 'export'],
                    'service_orders' => ['view', 'create', 'edit', 'delete', 'export'],
                    'repair' => ['view', 'create', 'diagnose', 'assign', 'checkout', 'delete'],
                    'products' => ['view', 'create', 'edit', 'delete', 'export'],
                    'categories' => ['view', 'create', 'edit', 'delete'],
                    'units' => ['view', 'create', 'edit', 'delete'],
                    'suppliers' => ['view', 'create', 'edit', 'delete', 'export'],
                    'customers' => ['view', 'create', 'edit', 'delete', 'export'],
                    'catalog' => ['view', 'create', 'edit', 'delete'],
                    'cash_register' => ['view', 'create', 'edit', 'delete', 'export'],
                    'finance' => ['view', 'create', 'edit', 'export'],
                    'reports' => ['view', 'export'],
                    'targets' => ['view', 'create', 'edit', 'delete', 'export'],
                    'settings' => ['view', 'edit'],
                    'users' => ['view'],
                    'leads' => ['view', 'view_any', 'create', 'edit', 'delete', 'assign', 'convert', 'export'],
                    'restaurant' => ['view', 'create', 'edit', 'delete', 'manage_tables', 'manage_kot', 'settle', 'export'],
                    'pharmacy' => ['view', 'create', 'edit', 'delete', 'manage_batches', 'verify_rx', 'export'],
                    'salon' => ['view', 'create', 'edit', 'delete', 'manage_stylists', 'checkout', 'export'],
                    'coupons' => ['view', 'create', 'edit', 'delete'],
                    'faqs' => ['view', 'create', 'edit', 'delete'],
                    'reviews' => ['view', 'create', 'edit', 'delete'],
                    'storefront' => ['view', 'manage', 'inquiries', 'inquiries.view', 'inquiries.action', 'edit'],
                    'gateways' => ['view', 'manage', 'edit'],
                ];

            case User::ROLE_SALESPERSON:
                return [
                    'pos' => ['view', 'create', 'edit'],
                    'sales' => ['view', 'create', 'process_payment', 'apply_discount'],
                    'consignments' => ['view', 'create', 'reconcile', 'edit'],
                    'quotes' => ['view', 'create', 'edit', 'convert_to_sale', 'export'],
                    'service_orders' => ['view', 'create', 'edit'],
                    'repair' => ['view', 'create'],
                    'products' => ['view'],
                    'categories' => ['view'],
                    'customers' => ['view', 'create', 'edit'],
                    'catalog' => ['view', 'create'],
                    'cash_register' => ['view'],
                    'targets' => ['view'],
                    'leads' => ['view', 'create', 'edit', 'convert'],
                    'restaurant' => ['view', 'create', 'edit', 'manage_kot', 'settle'],
                    'pharmacy' => ['view', 'create', 'edit'],
                    'salon' => ['view', 'create', 'edit', 'checkout'],
                    'storefront' => ['view', 'inquiries.view'],
                ];

            case User::ROLE_CASHIER:
            case 'operador':
                return [
                    'pos' => ['view', 'create'],
                    'sales' => ['view', 'create', 'process_payment'],
                    'consignments' => ['view'],
                    'quotes' => ['view', 'create'],
                    'service_orders' => ['view'],
                    'repair' => ['view', 'create', 'checkout'],
                    'customers' => ['view', 'create'],
                    'products' => ['view'],
                    'cash_register' => ['view', 'create', 'edit', 'delete'],
                    'targets' => ['view'],
                    'restaurant' => ['view', 'create', 'settle'],
                    'pharmacy' => ['view', 'create'],
                    'salon' => ['view', 'create', 'checkout'],
                ];

            case User::ROLE_TECHNICIAN:
            case 'technician':
                return [
                    'repair' => ['view', 'diagnose'],
                    'customers' => ['view'],
                    'pos' => ['view'],
                    'products' => ['view'],
                ];

            case User::ROLE_STOCK_CLERK:
                return [
                    'products' => ['view', 'create', 'edit', 'delete', 'export'],
                    'consignments' => ['view', 'create'],
                    'service_orders' => ['view', 'edit'],
                    'categories' => ['view', 'create', 'edit', 'delete'],
                    'units' => ['view', 'create', 'edit', 'delete'],
                    'suppliers' => ['view', 'create', 'edit', 'export'],
                    'catalog' => ['view'],
                ];

            case User::ROLE_FINANCE:
                return [
                    'sales' => ['view', 'process_payment', 'export'],
                    'consignments' => ['view', 'finalize_invoice', 'export'],
                    'quotes' => ['view', 'approve', 'export'],
                    'service_orders' => ['view', 'export'],
                    'cash_register' => ['view', 'create', 'edit', 'delete', 'export'],
                    'finance' => ['view', 'create', 'edit', 'export'],
                    'reports' => ['view', 'export'],
                    'targets' => ['view', 'export'],
                    'customers' => ['view'],
                    'suppliers' => ['view'],
                ];

            default:
                return [
                    'pos' => ['view', 'create'],
                    'sales' => ['view'],
                    'cash_register' => ['view', 'create'],
                ];
        }
    }
}
