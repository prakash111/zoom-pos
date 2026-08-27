<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Services\Auth\TenantAuthService;
use Illuminate\Http\Request;

/**
 * Mirrors the legacy api/auth.php action dispatch at the same URL
 * (/api/auth.php). Only "login" and "logout" are implemented in Milestone 1
 * — enough to obtain a real bearer token for testing the sync-compat layer
 * end-to-end. Registration/OTP/recovery flows land in Milestone 3/4.
 */
class TenantAuthSyncController extends Controller
{
    public function dispatch(Request $request, TenantAuthService $auth)
    {
        $action = $request->input('action', 'login');

        return match ($action) {
            'login' => $this->login($request, $auth),
            'logout' => $this->logout($request, $auth),
            'session_info' => $this->sessionInfo($request),
            default => response()->json([
                'success' => false,
                'error' => "Not yet implemented in Laravel rebuild: {$action}",
            ], 501),
        };
    }

    protected function login(Request $request, TenantAuthService $auth)
    {
        $identifier = $request->input('login') ?? $request->input('email');
        $password = $request->input('senha') ?? $request->input('password');
        $uniqueId = $request->input('unique_id');

        if (! $identifier || ! $password) {
            return response()->json(['success' => false, 'error' => 'Missing credentials.'], 422);
        }

        try {
            $result = $auth->login($identifier, $password, $uniqueId, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 401);
        }

        return response()->json([
            'success' => true,
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
                'company_id' => $result['user']->company_id,
            ],
        ]);
    }

    protected function logout(Request $request, TenantAuthService $auth)
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token) {
            $auth->logout($token);
        }

        return response()->json(['success' => true]);
    }

    protected function sessionInfo(Request $request)
    {
        $user = auth('tenant_api')->user();

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
            ],
        ]);
    }
}
