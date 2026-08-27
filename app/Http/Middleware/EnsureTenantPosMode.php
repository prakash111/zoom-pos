<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPosMode
{
    /**
     * Handle an incoming request and strictly isolate routes based on tenant operating mode.
     */
    public function handle(Request $request, Closure $next, string $expectedMode): Response
    {
        $company = auth('web')->user()?->company;

        if (! $company && app()->bound('tenant.company_id')) {
            $company = Company::find(app('tenant.company_id'));
        }

        if (! $company) {
            return $next($request);
        }

        if ($expectedMode === 'restaurant' && $company->isGeneralMode()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Restaurant mode is currently disabled for your store.',
                ], 403);
            }

            return redirect()->route('tenant.dashboard')
                ->with('error', '🍽️ Restaurant Mode is currently disabled. You can switch your Operating Mode to "Food & Restaurant" in Store Settings.');
        }

        if ($expectedMode === 'general' && $company->isRestaurantMode()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'General retail POS is disabled while Food & Restaurant mode is active.',
                ], 403);
            }

            return redirect()->route('tenant.restaurant.pos')
                ->with('info', 'Switched to active Food & Restaurant POS Mode.');
        }

        return $next($request);
    }
}
