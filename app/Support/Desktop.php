<?php

namespace App\Support;

/**
 * Single source of truth for "is this request running inside the NativePHP
 * desktop app" — checks NativePHP environment variables, runtime flags,
 * and falls back to probing NativePHP App facade.
 */
class Desktop
{
    public static function isRunning(): bool
    {
        if (config('nativephp-internal.running') || env('NATIVEPHP_RUNNING') || ! empty(env('NATIVEPHP_STORAGE_PATH'))) {
            return true;
        }

        if (! class_exists(\Native\Desktop\Facades\App::class)) {
            return false;
        }

        try {
            \Native\Desktop\Facades\App::isHidden();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A packaged desktop build ships with no real APP_KEY (baking the
     * server's production key into a binary every user can extract would let
     * anyone decrypt every device's stored sync token). This is the single
     * source of truth for the per-device replacement: read the key already
     * persisted on this device, or generate a fresh random one and persist
     * it — so encryption stays USABLE (every login on this device reuses the
     * same key) without ever sharing one key across every install.
     *
     * Must not throw: this runs during ServiceProvider::register(), before
     * NativeAppServiceProvider has had a chance to guarantee storage
     * directories exist, so directory creation here is best-effort.
     */
    public static function resolveOrCreatePersistentAppKey(): string
    {
        $keyFile = storage_path('app/desktop-app-key');

        $existing = @is_file($keyFile) ? trim((string) @file_get_contents($keyFile)) : '';
        if ($existing !== '') {
            return $existing;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        try {
            if (! is_dir(dirname($keyFile))) {
                @mkdir(dirname($keyFile), 0775, true);
            }
            @file_put_contents($keyFile, $key);
        } catch (\Throwable) {
            // Best effort — if it can't be persisted, this process still has
            // a usable key; a later cold start would just mint another one.
        }

        return $key;
    }

    /**
     * A desktop install is meant to be single-tenant, but the local SQLite
     * database has no such constraint enforced — if this device has ever
     * been logged into more than one cloud account (switching accounts,
     * shared/demo hardware, or just testing), it accumulates more than one
     * Company row. RunDesktopSyncCycle runs in a separate queue-worker
     * process with no HTTP request/session of its own to read "who's logged
     * in right now" from, so without this it fell back to `Company::first()`
     * — whichever company happened to be inserted first — and kept syncing
     * that one forever, regardless of which account the user actually signs
     * into afterwards. That starves the real logged-in tenant's data of
     * background refreshes and lets a stale/foreign company's rows pile up
     * locally. This is the explicit record of "whichever company most
     * recently completed a real login on this device", written at the one
     * point both the fresh-bootstrap and returning-login paths funnel
     * through (TenantLogin::login()), and consulted by the background job
     * instead of guessing.
     */
    public static function rememberActiveCompany(string|int $companyId): void
    {
        $file = storage_path('app/desktop-active-company');

        try {
            if (! is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            @file_put_contents($file, (string) $companyId);
        } catch (\Throwable) {
            // Best effort — worst case the background job falls back to
            // Company::first() for this cycle only.
        }
    }

    public static function activeCompanyId(): ?string
    {
        $file = storage_path('app/desktop-active-company');
        $value = @is_file($file) ? trim((string) @file_get_contents($file)) : '';

        return $value !== '' ? $value : null;
    }
}
