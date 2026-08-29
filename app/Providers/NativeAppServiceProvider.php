<?php

namespace App\Providers;

use App\Jobs\RunDesktopSyncCycle;
use App\Services\Auth\DesktopSessionGuard;
use App\Support\Desktop;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;
use Native\Desktop\NativeServiceProvider as VendorNativeServiceProvider;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $this->ensureApplicationInitialized();

        Window::open('main')
            ->title('Zoom POS')
            ->width(1366)
            ->height(850)
            ->minWidth(1024)
            ->minHeight(700)
            ->showDevTools(false)
            ->rememberState()
            ->url(url('/tenant/login'))
            ->backgroundColor('#0f172a');

        MenuBar::create()
            ->icon(base_path('launcher.png'))
            ->tooltip('Zoom POS Background Service')
            ->showDockIcon();

        $this->startBackgroundSyncCycle();
    }

    /**
     * Kicking off the self-rescheduling background sync job used to be a
     * single unguarded call here — and RunDesktopSyncCycle was missing the
     * Queueable trait that actually provides ->delay(), so this line threw
     * an uncaught fatal Error on literally every single app boot, right
     * after the window/tray were already created. That silently killed the
     * ENTIRE background sync mechanism forever (it never ran even once) and
     * risked taking down whatever process this boot sequence runs in with
     * it. Both are now fixed (see RunDesktopSyncCycle), but this stays
     * defensively wrapped for the same reason ensureApplicationInitialized()
     * is: a problem in a background nicety must never be able to break the
     * one thing the user actually needs — the app opening.
     */
    protected function startBackgroundSyncCycle(): void
    {
        try {
            RunDesktopSyncCycle::dispatch()->delay(now()->addSeconds(5));
        } catch (\Throwable $exception) {
            Log::error('Failed to schedule the desktop background sync cycle.', ['exception' => $exception]);
        }
    }

    protected function ensureApplicationInitialized(): void
    {
        $marker = storage_path('app/.zoom-pos-initialized-v1');

        try {
            $this->ensureNativeDatabaseConnectionIsActive();

            // Ensure all essential framework storage directories exist
            $storageDirs = [
                storage_path('framework/views'),
                storage_path('framework/sessions'),
                storage_path('framework/cache/data'),
                storage_path('logs'),
                storage_path('app/public'),
            ];
            foreach ($storageDirs as $dir) {
                File::ensureDirectoryExists($dir);
            }

            $installedFile = storage_path('installed');
            if (! File::exists($installedFile)) {
                File::ensureDirectoryExists(dirname($installedFile));
                File::put($installedFile, now()->toIso8601String());
            }

            // AppServiceProvider::register() already guarantees config('app.key')
            // is set to a persisted per-device key (App\Support\Desktop::
            // resolveOrCreatePersistentAppKey()) before any provider's boot()
            // runs — nothing left to do here.

            // Ensure database directory exists before running SQLite migrations
            $databasePath = config('nativephp-internal.database_path') ?: database_path('nativephp.sqlite');
            if (! empty($databasePath)) {
                File::ensureDirectoryExists(dirname($databasePath));
                if (! file_exists($databasePath)) {
                    touch($databasePath);
                }
            }

            $this->migrateIfAppVersionChanged();

            // Runs on every cold start (not just first-ever boot) — see
            // DesktopSessionGuard for why "on boot" is the right moment, not
            // a shutdown hook. Must come after migrate — the sessions table
            // needs to exist. Kept synchronous and before Window::open() on
            // purpose (not deferred): the window navigates straight to
            // /tenant/login, and a stale Laravel session file left in place
            // even briefly could let that request auto-redirect an
            // already-"logged-in" stale session to the dashboard before this
            // had a chance to clear it. It's a cheap filesystem sweep, not
            // the thing making cold boot slow — migrate was.
            app(DesktopSessionGuard::class)->clearStaleSessionsOnBoot();

            if (! File::exists($marker)) {
                File::ensureDirectoryExists(dirname($marker));
                File::put($marker, now()->toIso8601String());
            }
        } catch (\Throwable $exception) {
            Log::error('Desktop self-initialization failed.', ['exception' => $exception]);
        }
    }

    /**
     * `migrate` used to run unconditionally on every cold start, paying full
     * migration-repository/schema-diff overhead before the window opens even
     * when there's nothing to migrate. This device's SQLite schema only
     * actually changes when the installed app version changes, so this
     * tracks the last version successfully migrated on this device and skips
     * the call entirely on every other boot.
     */
    protected function migrateIfAppVersionChanged(): void
    {
        $versionFile = storage_path('app/.zoom-pos-migrated-version');
        $currentVersion = (string) config('nativephp.version');

        $lastMigratedVersion = @is_file($versionFile) ? trim((string) @file_get_contents($versionFile)) : '';

        if ($lastMigratedVersion !== '' && $lastMigratedVersion === $currentVersion) {
            return;
        }

        // NativePHP rewrites the default connection to its per-user SQLite file.
        Artisan::call('migrate', ['--force' => true]);

        try {
            File::ensureDirectoryExists(dirname($versionFile));
            File::put($versionFile, $currentVersion);
        } catch (\Throwable) {
            // Best effort — worst case migrate harmlessly re-runs next boot.
        }
    }

    /**
     * NativePHP's own database rewrite (Native\Desktop\NativeServiceProvider
     * ::bootingPackage() -> rewriteDatabase()) only fires when
     * config('nativephp-internal.running') is true, and that flag is
     * frozen the moment any config cache exists — a cached config always
     * wins over re-evaluating env('NATIVEPHP_RUNNING'). An installer built
     * before config/nativephp.php's `prebuild` step stopped running
     * `config:cache` ships with exactly that: a cache frozen at build time,
     * when NATIVEPHP_RUNNING wasn't set, baking 'running' => false in
     * permanently — so the rewrite never happens, and every local database
     * write instead targets whatever connection the *build machine's* own
     * .env had (here: this production server's own MySQL).
     *
     * Desktop::isRunning() doesn't have this problem — it checks env()
     * directly, which a cached config can't touch — so it's a reliable
     * signal to detect the mismatch and force the same rewrite NativePHP
     * would have done, healing this process immediately with no restart
     * required.
     */
    protected function ensureNativeDatabaseConnectionIsActive(): void
    {
        if (config('database.default') === 'nativephp' || ! Desktop::isRunning()) {
            return;
        }

        Log::warning('NativePHP database rewrite did not run (stale cached config?) — forcing it now.', [
            'database.default' => config('database.default'),
        ]);

        if (class_exists(VendorNativeServiceProvider::class)) {
            (new VendorNativeServiceProvider($this->app))->rewriteDatabase();
        }

        // Drop the stale cache so future cold starts read config fresh
        // instead of repeating this every single boot.
        if ($this->app->configurationIsCached()) {
            @unlink($this->app->getCachedConfigPath());
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'display_errors' => '0',
            'log_errors' => '1',
            'max_execution_time' => '0',
        ];
    }
}
