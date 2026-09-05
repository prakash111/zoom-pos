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
    ];

    public static function getActionsForModule(string $module): array
    {
        return self::MODULE_ACTIONS[$module] ?? self::ACTIONS;
    }

    public static function getActionDescription(string $module, string $action): string
    {
        if (isset(self::MODULE_ACTIONS[$module][$action])) {
            return self::MODULE_ACTIONS[$module][$action];
        }

        return self::ACTIONS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    public static function can(User $user, string $module, string $action = 'view'): bool
    {
        return app(self::class)->allows($user, $module, $action);
    }

    public function allows(User $user, string $module, string $action): bool
    {
        if ($user->isPrivilegedRole()) {
            return true;
        }

        $perm = Permission::query()
            ->where('user_id', $user->id)
            ->where('module', $module)
            ->where('action', $action)
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

        return in_array($action, $map[$module] ?? [], true);
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
