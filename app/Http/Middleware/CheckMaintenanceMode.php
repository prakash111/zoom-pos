<?php

namespace App\Http\Middleware;

use App\Models\PlatformSystem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Fails open on any lookup error — a broken maintenance flag must never
        // itself take the whole platform down.
        try {
            $enabled = filter_var(PlatformSystem::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            $enabled = false;
        }

        if (! $enabled) {
            return $next($request);
        }

        // Super Admin (either transport) always bypasses maintenance mode.
        if (Auth::guard('platform_web')->check() || Auth::guard('platform_api')->check()) {
            return $next($request);
        }

        $message = PlatformSystem::get('maintenance_message', 'The platform is undergoing scheduled maintenance. Please try again shortly.');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'error' => $message], 503);
        }

        abort(503, $message);
    }
}
