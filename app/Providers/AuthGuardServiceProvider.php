<?php

namespace App\Providers;

use App\Models\AdminSession;
use App\Models\TenantSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the two bearer-token guards used by the legacy desktop/browser
 * client via the sync-compat routes. Not Sanctum: Sanctum's token table/format
 * doesn't match the "{company_id}.{64 hex}" shape the shipped client already
 * persists and replays (see TenantAuthService::mintToken()).
 */
class AuthGuardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::viaRequest('tenant_api', function (Request $request) {
            $token = $this->bearerToken($request);
            if (! $token || ! str_contains($token, '.')) {
                return null;
            }

            $session = TenantSession::query()->active()->find($token);
            if (! $session) {
                return null;
            }

            // Resolving the token is what ESTABLISHES the tenant context —
            // it must never depend on one already being bound (e.g. left over,
            // possibly stale, from a prior request under Octane/long-lived
            // workers). Bypass the BelongsToCompany scope here and verify
            // company_id explicitly instead of going through $session->user.
            $user = User::query()->withoutGlobalScope('company')->find($session->user_id);
            if (! $user || $user->company_id !== $session->company_id) {
                return null;
            }

            // Now bind the resolved tenant context for the rest of the request.
            app()->instance('tenant.company_id', $session->company_id);
            app()->instance('tenant.session', $session);

            return $user;
        });

        Auth::viaRequest('platform_api', function (Request $request) {
            $token = $this->bearerToken($request);
            if (! $token) {
                return null;
            }

            $session = AdminSession::query()->active()->find($token);

            return $session?->admin;
        });
    }

    protected function bearerToken(Request $request): ?string
    {
        return $request->bearerToken()
            ?? $request->input('token')
            ?? $request->header('X-Auth-Token');
    }
}
