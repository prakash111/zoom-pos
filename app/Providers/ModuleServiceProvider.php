<?php

namespace App\Providers;

use App\Models\SduiModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Boot layer for ZIP-packaged, self-contained modules (Perfex-style plugin
 * engine). For every module whose `sdui_modules` row is `source_type =
 * package` AND `is_active = true`, this provider wires its capabilities into
 * the running app *without the module ever touching a core file*:
 *
 *   modules/<key>/
 *     routes.php                      flat route file (legacy / simple modules)
 *     routes/api.php, routes/web.php  split route files (loaded if present)
 *     Providers/ModuleProvider.php    Modules\<key>\Providers\ModuleProvider
 *                                     — registered + booted like any provider
 *     Resources/views/               published under the "module-<key>::" ns
 *
 * `sdui_modules.is_active` is the sole source of truth — an orphaned
 * `modules/<key>/` directory left by a failed uninstall grants nothing on
 * its own. Migrations are NOT auto-run here; they run once, explicitly, in
 * ModulePackageService::activate().
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Runtime PSR-4-ish autoloader for `Modules\<key>\...` classes —
        // there is no `composer dump-autoload` step available to a SuperAdmin,
        // so the namespace maps straight onto `modules/<key>/...` on disk.
        spl_autoload_register(function (string $class): void {
            if (! str_starts_with($class, 'Modules\\')) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen('Modules\\'))).'.php';
            $path = base_path('modules/'.$relative);

            if (is_file($path)) {
                require $path;
            } elseif (is_file(base_path('module-packages/'.$relative))) {
                require base_path('module-packages/'.$relative);
            }
        });
    }

    public function boot(): void
    {
        try {
            // Schema::hasTable() itself opens a DB connection — on a fresh
            // install (no database configured/migrated yet, e.g. before the
            // /install wizard has run, or the DB is briefly unreachable) this
            // throws rather than returning false, which would otherwise crash
            // every single request app-wide (including /install itself) since
            // this provider boots on every bootstrap, HTTP or console.
            if (! Schema::hasTable('sdui_modules')) {
                return;
            }

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
            try {
                $this->bootModule((string) $module->package_path);
            } catch (Throwable $e) {
                Log::warning("ModuleServiceProvider: failed booting module '{$module->slug}'.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Wire one on-disk module directory into the app. Public + path-based so
     * it can be re-run after a runtime activate without rebooting the kernel.
     */
    public function bootModule(string $packagePath): void
    {
        $base = base_path('modules/'.$packagePath);
        if ($packagePath === '' || ! is_dir($base)) {
            $base = base_path('module-packages/'.$packagePath);
            if ($packagePath === '' || ! is_dir($base)) {
                return;
            }
        }

        $key = basename($packagePath);

        // 1. The module's own ServiceProvider, if it ships one.
        $providerClass = 'Modules\\'.$key.'\\Providers\\ModuleProvider';
        if (is_file($base.'/Providers/ModuleProvider.php') && class_exists($providerClass)) {
            $this->app->register($providerClass);
        }

        // 2. Routes — the flat file, then split api/web files.
        foreach (['routes.php', 'routes/api.php', 'routes/web.php'] as $rel) {
            $file = $base.'/'.$rel;
            if (is_file($file)) {
                $this->loadRoutesFrom($file);
            }
        }

        // 3. Views under a per-module namespace: view('module-<key>::foo').
        foreach (['Resources/views', 'resources/views'] as $rel) {
            $dir = $base.'/'.$rel;
            if (is_dir($dir)) {
                $this->loadViewsFrom($dir, 'module-'.$key);
                break;
            }
        }
    }
}
