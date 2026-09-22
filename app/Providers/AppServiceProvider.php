<?php

namespace App\Providers;

use App\Events\TenantRegistered;
use App\Http\Middleware\PreventDemoModifications;
use App\Listeners\TenantRegisteredListener;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use App\Models\Sale;
use App\Models\User;
use App\Observers\SaleObserver;
use App\Services\Auth\PermissionChecker;
use App\Support\Desktop;
use App\View\Composers\TenantNavigationComposer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (Desktop::isRunning() && blank(config('app.key'))) {
            config(['app.key' => Desktop::resolveOrCreatePersistentAppKey()]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningUnitTests()) {
            \App\Support\Installation::isInstalled();
        }

        Sale::observe(SaleObserver::class);
        Gate::policy(\App\Models\Invoice::class, \App\Policies\InvoicePolicy::class);
        Gate::policy(\App\Models\Sale::class, \App\Policies\SalePolicy::class);
        Event::listen(TenantRegistered::class, TenantRegisteredListener::class);

        // Re-run the demo read-only guard on Livewire follow-up requests
        // (`/livewire/update`) so a Super Admin settings save via a Livewire
        // action is blocked in DEMO_MODE, not just the initial page load.
        if (config('app.demo_mode')) {
            Livewire::addPersistentMiddleware([
                PreventDemoModifications::class,
            ]);
        }

        // Bump the marketing landing page's whole-response cache whenever any
        // content it renders changes (branding, plans, footer pages, menus).
        $bustLandingCache = static function (): void {
            Cache::increment('landing_page_cache_version')
                ?: Cache::forever('landing_page_cache_version', 2);
        };
        foreach ([PlatformBranding::class, SaaSPlan::class, Page::class, MenuItem::class] as $model) {
            $model::saved($bustLandingCache);
            $model::deleted($bustLandingCache);
        }

        $storageDirs = [
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            storage_path('framework/cache/data'),
            storage_path('logs'),
            storage_path('app'),
        ];
        foreach ($storageDirs as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        View::composer('layouts.tenant', TenantNavigationComposer::class);

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
