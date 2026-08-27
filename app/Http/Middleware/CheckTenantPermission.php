<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantPermission
{
    public function __construct(protected PermissionChecker $permissions) {}

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = auth('web')->user();

        $module = match (strtolower((string) $module)) {
            'quotation', 'quotations', 'quote' => 'quotes',
            'product' => 'products',
            'category' => 'categories',
            'unit' => 'units',
            'supplier' => 'suppliers',
            'customer' => 'customers',
            'sale' => 'sales',
            'setting' => 'settings',
            'user' => 'users',
            'report' => 'reports',
            'financial', 'financials' => 'finance',
            'consignment', 'consignments' => 'consignments',
            'service_order', 'service_orders', 'service-order', 'service-orders', 'repair', 'repairs' => 'service_orders',
            'target', 'targets', 'sales_targets', 'sales-targets' => 'targets',
            'register', 'cash_register', 'cash-register' => 'cash_register',
            default => strtolower((string) $module),
        };

        $action = match (strtolower((string) $action)) {
            'send' => 'export',
            'read', 'show', 'index' => 'view',
            'add', 'new' => 'create',
            'update' => 'edit',
            'remove', 'destroy' => 'delete',
            'manage' => 'edit',
            'open' => 'create',
            'sangria_suprimento', 'sangria', 'suprimento' => 'edit',
            'close' => 'delete',
            'view_history', 'history' => 'export',
            'convert' => 'convert_to_sale',
            'payment', 'pay' => 'process_payment',
            'discount' => 'apply_discount',
            default => strtolower((string) $action),
        };

        $moduleLabel = PermissionChecker::MODULES[$module] ?? ucfirst($module);
        $actionLabel = PermissionChecker::getActionDescription($module, $action);

        abort_unless(
            $user && $this->permissions->allows($user, $module, $action),
            403,
            "Access Denied: Your account does not have permission to perform [{$actionLabel}] in {$moduleLabel}."
        );

        return $next($request);
    }
}
