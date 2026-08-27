<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Privileged tenant roles bypass every permission check, or evaluate via PermissionChecker
        Gate::before(function ($user, $ability) {
            if ($user instanceof User) {
                if ($user->isPrivilegedRole()) {
                    return true;
                }

                $checker = app(PermissionChecker::class);
                $module = null;
                $action = 'view';

                if (str_contains($ability, '.')) {
                    [$mod, $act] = explode('.', $ability, 2);
                    $module = $mod;
                    $action = $act;
                } elseif (str_contains($ability, ',')) {
                    [$mod, $act] = explode(',', $ability, 2);
                    $module = $mod;
                    $action = $act;
                } elseif (str_contains($ability, '_')) {
                    $parts = explode('_', $ability, 2);
                    if (in_array(strtolower($parts[0]), ['view', 'create', 'edit', 'delete', 'export', 'send'], true)) {
                        $action = $parts[0];
                        $module = $parts[1];
                    } else {
                        $module = $parts[0];
                        $action = $parts[1];
                    }
                } else {
                    $module = $ability;
                }

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
                    'reconcile' => 'edit',
                    'finalize_invoice', 'finalize' => 'create',
                    'open' => 'create',
                    'sangria_suprimento', 'sangria', 'suprimento' => 'edit',
                    'close' => 'delete',
                    'view_history', 'history' => 'export',
                    default => strtolower((string) $action),
                };

                if (array_key_exists($module, PermissionChecker::MODULES)) {
                    return $checker->allows($user, $module, $action);
                }
            }

            return null;
        });
    }
}
