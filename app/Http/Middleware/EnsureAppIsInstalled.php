<?php

namespace App\Http\Middleware;

use App\Support\Desktop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Desktop::isRunning()) {
            return $next($request);
        }

        $installedFile = storage_path('installed');
        $legacyInstalledFile = storage_path('--installed');

        // Older deployments accidentally persisted the installation marker as
        // `storage/--installed`. Treat it as the same marker and repair the
        // canonical filename so API requests are not redirected to /install.
        if (! file_exists($installedFile) && file_exists($legacyInstalledFile)) {
            @copy($legacyInstalledFile, $installedFile);
        }

        if (! file_exists($installedFile) && ! $request->is('install*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
