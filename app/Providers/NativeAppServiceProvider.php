<?php

namespace App\Providers;

use App\Jobs\RunDesktopSyncCycle;
use App\Services\Auth\DesktopSessionGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;

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
            ->backgroundColor('#0f172a');

        MenuBar::create()
            ->icon(base_path('launcher.png'))
            ->tooltip('Zoom POS Background Service')
            ->showDockIcon();

        RunDesktopSyncCycle::dispatch()->delay(now()->addSeconds(5));
    }

    protected function ensureApplicationInitialized(): void
    {
        $marker = storage_path('app/.zoom-pos-initialized-v1');

        try {
            $keyFile = storage_path('app/desktop-app-key');
            if (blank(config('app.key'))) {
                File::ensureDirectoryExists(dirname($keyFile));
                $key = File::exists($keyFile)
                    ? trim(File::get($keyFile))
                    : 'base64:'.base64_encode(random_bytes(32));
                File::put($keyFile, $key);
                config(['app.key' => $key]);
            }

            // NativePHP rewrites the default connection to its per-user SQLite file.
            Artisan::call('migrate', ['--force' => true]);

            // Runs on every cold start (not just first-ever boot) — see
            // DesktopSessionGuard for why "on boot" is the right moment, not
            // a shutdown hook. Must come after migrate — the sessions table
            // needs to exist.
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
