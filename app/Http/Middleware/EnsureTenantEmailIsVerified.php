<?php

namespace App\Http\Middleware;

use App\Models\PlatformBranding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $branding = PlatformBranding::current();

        // If SMTP is not configured in SuperAdmin, OTP verification is not required
        if (! $branding->isSmtpConfigured()) {
            return $next($request);
        }

        $user = $request->user('web');

        // If user is authenticated and email is not verified, require OTP verification
        if ($user && is_null($user->email_verified_at)) {
            if ($request->routeIs('tenant.verify_otp', 'tenant.logout', 'tenant.login', 'tenant.register')) {
                return $next($request);
            }

            return redirect()->route('tenant.verify_otp');
        }

        return $next($request);
    }
}
