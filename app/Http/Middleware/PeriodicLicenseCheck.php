<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs `license:check-status` at most once per ~24h, triggered by SuperAdmin
 * activity, so licensing stays current even if the OS cron scheduler is not
 * configured. Executed in terminate() — no added request latency.
 */
class PeriodicLicenseCheck
{
    private const LOCK = 'license.autocheck.lock';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('services.license_server.url')) {
            return;
        }

        // Cache::add is atomic — only the first request in the window proceeds.
        if (! Cache::add(self::LOCK, true, now()->addHours(23))) {
            return;
        }

        try {
            Artisan::call('license:check-status', ['--sync' => true]);
        } catch (Throwable $e) {
            Cache::forget(self::LOCK);
        }
    }
}
