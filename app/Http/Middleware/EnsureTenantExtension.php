<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantExtension
{
    public function handle(Request $request, Closure $next, string $extension): Response
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : ($request->attributes->get('company_id') ?: $request->user()?->company_id);
        $company = $companyId ? Company::find($companyId) : null;

        abort_unless($company && $company->hasModule($extension), 403,
            'This extension is not activated for your store by Super Admin.');

        return $next($request);
    }
}
