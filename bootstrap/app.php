<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckTenantApiUserPermission;
use App\Http\Middleware\CheckTenantPermission;
use App\Http\Middleware\EnsureAppIsInstalled;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\EnsureTenantEmailIsVerified;
use App\Http\Middleware\EnsureTenantPosMode;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Human-facing Blade/Livewire panels (session-based, CSRF-protected).
            Route::middleware(['web', EnsureAppIsInstalled::class])
                ->group(base_path('routes/installer.php'));

            // Maintenance-mode is applied inside these two files, scoped to the
            // authenticated route groups only — the login routes themselves
            // must stay reachable, otherwise a Super Admin could never sign in
            // to turn maintenance mode back off.
            Route::middleware(['web', EnsureAppIsInstalled::class])
                ->group(base_path('routes/superadmin.php'));

            Route::middleware(['web', EnsureAppIsInstalled::class])
                ->group(base_path('routes/tenant.php'));

            // Legacy desktop/browser client compatibility layer: literal legacy
            // URL paths, bearer-token auth, no CSRF/session (stateless).
            Route::middleware([EnsureAppIsInstalled::class, CheckMaintenanceMode::class])
                ->group(base_path('routes/sync.php'));

            // RESTful E-Invoicing & External Fiscal Tax Engine API (stateless, token/key auth).
            // SetLocale here honors the mobile app's `Accept-Language` header
            // (see LocalizationService::getActiveLocale) so validation errors
            // and any translated strings in API responses match the client's
            // chosen locale.
            Route::middleware([EnsureAppIsInstalled::class, CheckMaintenanceMode::class, SetLocale::class])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveTenantContext::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        // The public landing page is served from a whole-response cache shared
        // across visitors, so its embedded CSRF token is not per-session. The
        // marketing contact form is instead protected by an origin-locked
        // rate limit (throttle:6,1) and a honeypot field.
        $middleware->validateCsrfTokens(except: ['contact']);

        $middleware->alias([
            'installed' => EnsureAppIsInstalled::class,
            'not_installed' => EnsureNotInstalled::class,
            'maintenance_check' => CheckMaintenanceMode::class,
            'security_headers' => SecurityHeaders::class,
            'tenant_context' => ResolveTenantContext::class,
            'tenant.permission' => CheckTenantPermission::class,
            'tenant.api.permission' => CheckTenantApiUserPermission::class,
            'tenant.pos_mode' => EnsureTenantPosMode::class,
            'tenant.subscription' => EnsureTenantSubscriptionActive::class,
            'tenant.verified' => EnsureTenantEmailIsVerified::class,
        ]);

        // There is no single named "login" route — two separate guards each
        // have their own. Route an unauthenticated hit to the right one by
        // which panel the URL belongs to.
        //
        // API and JSON clients must NEVER be bounced with a 302 to an HTML
        // login page: the mobile app's Dio client throws on any 3xx, so an
        // unauthenticated (or header-less) API call has to come back as a
        // 401 JSON body instead. Returning null here makes the framework's
        // Authenticate middleware do exactly that.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return $request->is('superadmin*')
                ? route('superadmin.login')
                : route('tenant.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
