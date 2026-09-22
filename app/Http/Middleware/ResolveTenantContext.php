<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Stores\StoreContext;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    /**
     * Binds the authenticated user's company_id into the container so every
     * BelongsToCompany-scoped model query is automatically tenant-isolated.
     * Runs after the guard has resolved the user (tenant_api already binds it
     * during token verification; this also covers the 'web' session guard).
     *
     * Always resolves fresh on every request rather than short-circuiting on
     * "something is already bound" — this container binding can otherwise
     * survive across requests within the same long-lived PHP process
     * (NativePHP's desktop app keeps one process alive for the whole app
     * session, and any Octane/Swoole/RoadRunner web deployment reuses worker
     * processes the same way). With the old "only bind if not already bound"
     * guard, logging out and a *different* tenant's user logging back in on
     * the same running process would keep every subsequent request scoped to
     * the FIRST account's company forever — the exact cross-tenant leak this
     * middleware exists to prevent. A request with no resolvable tenant
     * (guest/superadmin pages) must equally not inherit a stale binding left
     * by an earlier, unrelated request, so it's explicitly cleared instead.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Installation::isInstalled() || $request->is('install*')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user() ?? Auth::guard('tenant_api')->user();
        $companyId = $user?->company_id ?? $this->resolveCompanyIdFromHost($request);

        if ($companyId) {
            app()->instance('tenant.company_id', $companyId);
            app(StoreContext::class)->bind($request, $user, $companyId);
        } elseif (app()->bound('tenant.company_id')) {
            app()->forgetInstance('tenant.company_id');
            if (app()->bound('tenant.store_id')) {
                app()->forgetInstance('tenant.store_id');
            }
            if (app()->bound('tenant.store_is_primary')) {
                app()->forgetInstance('tenant.store_is_primary');
            }
        }

        return $next($request);
    }

    protected function resolveCompanyIdFromHost(Request $request): ?string
    {
        try {
            $host = strtolower($request->getHost());
            $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

            if ($baseHost && str_ends_with($host, '.'.$baseHost)) {
                $slug = substr($host, 0, -(strlen($baseHost) + 1));
                if ($slug && ! in_array($slug, Company::RESERVED_SLUGS, true)) {
                    return Company::withoutGlobalScopes()->where('slug', $slug)->value('id');
                }

                return null;
            }

            if ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1') {
                return Company::withoutGlobalScopes()->where('custom_domain', $host)->value('id');
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
