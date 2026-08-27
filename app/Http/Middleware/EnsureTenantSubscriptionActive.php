<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    /**
     * Routes exempt from subscription expiry lock.
     */
    protected array $exemptRoutes = [
        'tenant.billing.index',
        'tenant.billing.activate',
        'tenant.billing.invoices.pdf',
        'tenant.activate',
        'tenant.settings.index',
        'tenant.logout',
        'tenant.impersonate.stop',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return $next($request);
        }

        $currentRoute = $request->route()?->getName();
        if (in_array($currentRoute, $this->exemptRoutes, true)) {
            return $next($request);
        }

        // 1. Check suspended status
        if ($company->isSuspended()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Store account is suspended.'], 403);
            }
            session()->flash('error', '⚠️ Your store account is suspended. Please check your billing status or contact platform support.');

            return redirect()->route('tenant.billing.index');
        }

        // 2. Check subscription expiration
        if ($company->isSubscriptionExpired()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Subscription expired. Please renew.'], 403);
            }
            session()->flash('error', '⏳ Your subscription plan has expired. Please redeem an activation license key or select a plan to continue using your POS and dashboard.');

            return redirect()->route('tenant.billing.index');
        }

        return $next($request);
    }
}
