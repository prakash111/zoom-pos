<?php

namespace App\Http\Middleware;

use App\Models\Company;
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
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('tenant.company_id')) {
            $user = Auth::guard('web')->user() ?? Auth::guard('tenant_api')->user();
            if ($user) {
                app()->instance('tenant.company_id', $user->company_id);
            } else {
                // Check if host matches custom domain or subdomain
                $host = strtolower($request->getHost());
                $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

                if ($baseHost && str_ends_with($host, '.'.$baseHost)) {
                    $slug = substr($host, 0, -(strlen($baseHost) + 1));
                    if ($slug && ! in_array($slug, Company::RESERVED_SLUGS, true)) {
                        $company = Company::withoutGlobalScopes()->where('slug', $slug)->first();
                        if ($company) {
                            app()->instance('tenant.company_id', $company->id);
                        }
                    }
                } elseif ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1') {
                    $company = Company::withoutGlobalScopes()->where('custom_domain', $host)->first();
                    if ($company) {
                        app()->instance('tenant.company_id', $company->id);
                    }
                }
            }
        }

        return $next($request);
    }
}
