<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Subscription\SubscriptionEntitlementService;
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

        $entitlementService = app(SubscriptionEntitlementService::class);
        $canUse = $company && $entitlementService->tenantCanUseExtension($company, $extension);

        if (! $canUse) {
            $msg = 'This extension is not activated for your store by Super Admin. Upgrade your subscription plan to access this feature.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Upgrade Required',
                    'message' => $msg,
                    'extension' => $extension,
                    'requires_upgrade' => true,
                ], 403);
            }

            abort(403, $msg);
        }

        return $next($request);
    }
}
