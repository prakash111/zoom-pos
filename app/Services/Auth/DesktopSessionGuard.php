<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "When the Windows application is completely closed... destroy the active
 * session... when it opens again, login is required." NativePHP exposes no
 * reliable "before quit" hook in this version (only a WindowClosed event,
 * which fires when the window is hidden to the tray, not when the app
 * actually exits — the tray icon keeps background sync running by design).
 *
 * So instead of reacting to a shutdown that might not fire cleanly (a crash
 * or force-kill wouldn't trigger it either), this establishes the security
 * state fresh on every cold start of the app process: NativeAppServiceProvider
 * calls clearStaleSessionsOnBoot() once, before the window opens. A window
 * close-to-tray never re-runs this (the same process keeps running), so
 * background sync continues uninterrupted — only an actual full app restart
 * forces a fresh login, which is exactly what "completely closed" means here.
 *
 * Driver-aware on purpose: this app's own `sessions` table is
 * TenantAuthService's custom bearer-token store (schema: token/user_id/
 * company_id/...), NOT Laravel's session storage — truncating it would look
 * plausible but do nothing, since Auth::guard('web') here runs on
 * config('session.driver') (file, in this repo's .env). Clearing the actual
 * active store is what makes the stale session cookie meaningless on restart.
 */
class DesktopSessionGuard
{
    public function clearStaleSessionsOnBoot(): void
    {
        match (config('session.driver')) {
            'file' => $this->clearFileSessions(),
            'database' => $this->clearDatabaseSessions(),
            default => Log::info('DesktopSessionGuard: no clearing implemented for session driver.', [
                'driver' => config('session.driver'),
            ]),
        };
    }

    protected function clearFileSessions(): void
    {
        $path = config('session.files');

        if (! $path || ! File::isDirectory($path)) {
            return;
        }

        foreach (File::files($path) as $file) {
            File::delete($file->getPathname());
        }
    }

    protected function clearDatabaseSessions(): void
    {
        $table = config('session.table', 'sessions');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'payload')) {
            DB::table($table)->delete();
        }
    }
}
