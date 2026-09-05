<?php

namespace App\Providers;

use App\Models\SduiModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Boots active ZIP-packaged modules: loads each one's routes.php.
 *
 * Navigation for an active module is already surfaced automatically by
 * ModuleRegistry/TenantNavRegistry once its sdui_modules row is active, and
 * its migrations are run explicitly during activation
 * (ModulePackageService::activate) rather than on every boot. This provider
 * only wires up routes.
 *
 * The `sdui_modules.is_active` column is the sole source of truth for
 * whether a module's routes load — an orphaned `modules/{key}/` directory
 * on disk (e.g. left over from a failed uninstall) never grants routes on
 * its own.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped autoloader for module Controllers referenced from a
        // module's routes.php. There is no Composer step available to a
        // SuperAdmin at runtime, so `Modules\{Key}\...` classes are mapped
        // directly onto `modules/{key}/...` files. Migrations don't need
        // this — Artisan's migrator requires migration files directly.
        spl_autoload_register(function (string $class): void {
            if (! str_starts_with($class, 'Modules\\')) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen('Modules\\'))).'.php';
            $path = base_path('modules/'.$relative);

            if (is_file($path)) {
                require $path;
            }
        });
    }

    public function boot(): void
    {
        if (! Schema::hasTable('sdui_modules')) {
            return;
        }

        try {
            $activeModules = SduiModule::query()
                ->where('source_type', 'package')
                ->where('is_active', true)
                ->whereNotNull('package_path')
                ->get();
        } catch (Throwable $e) {
            Log::warning('ModuleServiceProvider: could not query sdui_modules.', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($activeModules as $module) {
            $routesFile = base_path('modules/'.$module->package_path.'/routes.php');
            if (! is_file($routesFile)) {
                continue;
            }

            try {
                $this->loadRoutesFrom($routesFile);
            } catch (Throwable $e) {
                Log::warning("ModuleServiceProvider: failed loading routes for module '{$module->slug}'.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
