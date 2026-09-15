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

        Auth::viaRequest('sanctum', function (Request $request) {
            $token = $request->bearerToken()
                ?? $request->header('X-API-Key')
                ?? $request->header('X-Auth-Token')
                ?? $request->input('api_key')
                ?? $request->input('token');

            if (! $token) {
                return null;
            }

            $apiKey = \App\Models\TenantApiKey::withoutGlobalScope('company')->where('token', $token)->where('active', true)->first();
            if ($apiKey) {
                $apiKey->update(['last_used_at' => now()]);
                app()->instance('tenant.company_id', $apiKey->company_id);
                app()->instance('tenant.api_key', $apiKey);
                $request->attributes->set('company_id', $apiKey->company_id);
                $request->attributes->set('api_key', $apiKey);

                if ($apiKey->user_id) {
                    $user = User::query()->withoutGlobalScope('company')->find($apiKey->user_id);
                    if ($user && $user->company_id === $apiKey->company_id && $user->status !== 'inactive') {
                        return $user;
                    }
                }

                return User::query()->withoutGlobalScope('company')->where('company_id', $apiKey->company_id)->first();
            }

            if (str_contains($token, '.')) {
                $session = TenantSession::query()->active()->find($token);
                if ($session) {
                    $user = User::query()->withoutGlobalScope('company')->find($session->user_id);
                    if ($user && $user->company_id === $session->company_id) {
                        app()->instance('tenant.company_id', $session->company_id);
                        app()->instance('tenant.session', $session);
                        $request->attributes->set('company_id', $session->company_id);

                        return $user;
                    }
                }
            }

            $company = \App\Models\Company::where('tax_api_key', $token)->orWhere('activation_key', $token)->first();
            if ($company) {
                app()->instance('tenant.company_id', $company->id);
                $request->attributes->set('company_id', $company->id);

                return User::query()->withoutGlobalScope('company')->where('company_id', $company->id)->first();
            }

            return null;
        });
    }

    protected function bearerToken(Request $request): ?string
    {
        return $request->bearerToken()
            ?? $request->input('token')
            ?? $request->header('X-Auth-Token');
    }
}
