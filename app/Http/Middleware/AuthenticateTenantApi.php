<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\TenantApiKey;
use App\Models\TenantSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateTenantApi
{
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null)
    {
        $token = $request->bearerToken()
            ?? $request->header('X-API-Key')
            ?? $request->header('X-Auth-Token')
            ?? $request->input('api_key')
            ?? $request->input('token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'message' => 'Missing Bearer token or X-API-Key header.',
            ], 401);
        }

        // 1. Check TenantApiKey (zk_live_...)
        $apiKey = TenantApiKey::where('token', $token)->where('active', true)->first();
        if ($apiKey) {
            if ($requiredPermission && ! $apiKey->hasPermission($requiredPermission)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => "This API key does not have the required '{$requiredPermission}' permission.",
                ], 403);
            }

            $apiKey->update(['last_used_at' => now()]);
            app()->instance('tenant.company_id', $apiKey->company_id);
            app()->instance('tenant.api_key', $apiKey);
            $request->attributes->set('company_id', $apiKey->company_id);
            $request->attributes->set('api_key', $apiKey);
            if ($apiKey->user_id) {
                $user = User::query()->withoutGlobalScope('company')->find($apiKey->user_id);
                if (! $user || $user->company_id !== $apiKey->company_id || $user->status === 'inactive') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized',
                        'message' => 'The user bound to this desktop session is no longer active.',
                    ], 401);
                }
                Auth::setUser($user);
            }

            return $next($request);
        }

        // 2. Check TenantSession (legacy session token)
        if (str_contains($token, '.')) {
            $session = TenantSession::query()->active()->find($token);
            if ($session) {
                $user = User::query()->withoutGlobalScope('company')->find($session->user_id);
                if ($user && $user->company_id === $session->company_id) {
                    app()->instance('tenant.company_id', $session->company_id);
                    app()->instance('tenant.session', $session);
                    $request->attributes->set('company_id', $session->company_id);
                    Auth::setUser($user);

                    return $next($request);
                }
            }
        }

        // 3. Fallback: check company tax_api_key
        $company = Company::where('tax_api_key', $token)->orWhere('activation_key', $token)->first();
        if ($company) {
            app()->instance('tenant.company_id', $company->id);
            $request->attributes->set('company_id', $company->id);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Invalid or expired API token.',
        ], 401);
    }
}
