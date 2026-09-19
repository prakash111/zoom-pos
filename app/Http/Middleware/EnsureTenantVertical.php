<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Modular\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a web tenant route to stores that operate a given business vertical.
 *
 * A store picks one primary vertical at registration (retail | restaurant |
 * pharmacy | service_booking | repair_technician); it is recorded in
 * companies.licensed_modules. The pharmacy / salon / repair web pages are only
 * meaningful for a tenant licensed for that vertical — every other tenant is
 * bounced back to its dashboard.
 *
 * Usage:  ->middleware('tenant.vertical:pharmacy')
 */
class EnsureTenantVertical
{
    public function handle(Request $request, Closure $next, string $vertical): Response
    {
        $company = auth('web')->user()?->company;

        if (! $company && app()->bound('tenant.company_id')) {
            $company = Company::find(app('tenant.company_id'));
        }

        if (! $company) {
            return $next($request);
        }

        $vertical = ModuleRegistry::canonicalKey($vertical);

        // Company::hasModule() normalises licensed_modules (falling back to
        // pos_mode) to canonical vertical keys — it does not depend on the
        // platform SduiModule row existing, so the gate holds even for a
        // self-hosted install where the package is bundled rather than
        // installed through Super Admin → Modules.
        if ($company->hasModule($vertical)) {
            return $next($request);
        }

        $label = ucwords(str_replace('_', ' ', $vertical));

        if ($request->wantsJson()) {
            return response()->json([
                'error' => "The {$label} module is not enabled for your store.",
            ], 403);
        }

        return redirect()->route('tenant.dashboard')
            ->with('error', "The {$label} workspace is not enabled for your store.");
    }
}
