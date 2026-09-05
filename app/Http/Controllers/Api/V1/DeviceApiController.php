<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TenantApiKey;
use App\Models\TenantSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller serving active tenant devices, terminals, and web sessions.
 * Queries browser cookie sessions (TenantSession / sessions table)
 * and mobile/desktop POS terminals (TenantApiKey table, personal_access_tokens).
 */
class DeviceApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $bearer = $request->bearerToken()
            ?? $request->header('X-API-Key')
            ?? $request->header('X-Auth-Token')
            ?? $request->input('api_key')
            ?? $request->input('token');

        $devices = collect();

        // 1. Web sessions from TenantSession (sessions table)
        $webSessions = TenantSession::query()->active()
            ->where('company_id', $company->id)
            ->when($user && ! $user->isPrivilegedRole(), fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        foreach ($webSessions as $s) {
            $ua = strtolower((string) $s->user_agent);
            $platform = 'Web Browser';
            if (str_contains($ua, 'android')) {
                $platform = 'Android Web';
            } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
                $platform = 'iOS Web';
            } elseif (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) {
                $platform = 'macOS Web';
            } elseif (str_contains($ua, 'windows')) {
                $platform = 'Windows Web';
            } elseif (str_contains($ua, 'linux')) {
                $platform = 'Linux Web';
            }

            $isCurrent = $bearer && ($s->token === $bearer);

            $devices->push([
                'id' => $s->token,
                'token' => $s->token,
                'device_name' => 'Web Session - '.($s->user?->name ?? 'User'),
                'platform' => $platform,
                'user_id' => $s->user_id ? (string) $s->user_id : null,
                'user_name' => $s->user?->name ?? 'Unknown',
                'ip' => $s->ip ?: '127.0.0.1',
                'user_agent' => $s->user_agent,
                'type' => 'web_session',
                'is_impersonation' => $s->isImpersonation(),
                'is_current_user' => $user && $s->user_id === $user->id,
                'is_current_device' => $isCurrent,
                'last_active_at' => $s->updated_at?->toIso8601String() ?? $s->created_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
                'expires_at' => $s->expires_at?->toIso8601String(),
            ]);
        }

        // 2. POS Terminals and Mobile API keys from TenantApiKey
        $terminalKeys = TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->when($user && ! $user->isPrivilegedRole(), fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->orderByDesc('last_used_at')
            ->get();

        foreach ($terminalKeys as $k) {
            $nameLower = strtolower($k->name ?? '');
            $platform = 'POS Terminal';
            if (str_contains($nameLower, 'android')) {
                $platform = 'Android POS';
            } elseif (str_contains($nameLower, 'ios') || str_contains($nameLower, 'iphone') || str_contains($nameLower, 'ipad')) {
                $platform = 'iOS POS';
            } elseif (str_contains($nameLower, 'desktop') || str_contains($nameLower, 'windows') || str_contains($nameLower, 'mac') || str_contains($nameLower, 'linux')) {
                $platform = 'Desktop POS';
            } elseif (str_contains($nameLower, 'mobile')) {
                $platform = 'Mobile POS';
            }

            $isCurrent = $bearer && ($k->token === $bearer || $k->id === $bearer);

            $devices->push([
                'id' => (string) $k->id,
                'token' => $k->token,
                'device_name' => $k->name ?: ($platform.' ('.($k->user?->name ?? 'Staff').')'),
                'platform' => $platform,
                'user_id' => $k->user_id ? (string) $k->user_id : null,
                'user_name' => $k->user?->name ?? 'Staff Terminal',
                'ip' => $isCurrent ? ($request->ip() ?: '127.0.0.1') : '127.0.0.1',
                'user_agent' => $k->name,
                'type' => 'pos_terminal',
                'is_impersonation' => false,
                'is_current_user' => $user && $k->user_id === $user->id,
                'is_current_device' => $isCurrent,
                'last_active_at' => $k->last_used_at?->toIso8601String() ?? $k->updated_at?->toIso8601String() ?? $k->created_at?->toIso8601String(),
                'created_at' => $k->created_at?->toIso8601String(),
                'expires_at' => null,
            ]);
        }

        // 3. Check personal_access_tokens table if available
        if (Schema::hasTable('personal_access_tokens')) {
            $tokens = DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', function ($q) use ($company) {
                    $q->select('id')->from('users')->where('company_id', $company->id);
                })
                ->when($user && ! $user->isPrivilegedRole(), fn ($q) => $q->where('tokenable_id', $user->id))
                ->orderByDesc('last_used_at')
                ->get();

            foreach ($tokens as $t) {
                $isCurrent = $bearer && str_contains($bearer, (string) $t->id);
                $devices->push([
                    'id' => (string) $t->id,
                    'token' => (string) $t->id,
                    'device_name' => $t->name ?: 'API Terminal',
                    'platform' => 'Mobile / POS Token',
                    'user_id' => (string) $t->tokenable_id,
                    'user_name' => 'API Client',
                    'ip' => $request->ip() ?: '127.0.0.1',
                    'user_agent' => $t->name,
                    'type' => 'personal_token',
                    'is_impersonation' => false,
                    'is_current_user' => $user && (int) $t->tokenable_id === $user->id,
                    'is_current_device' => $isCurrent,
                    'last_active_at' => $t->last_used_at ? date('c', strtotime($t->last_used_at)) : null,
                    'created_at' => date('c', strtotime($t->created_at)),
                    'expires_at' => $t->expires_at ? date('c', strtotime($t->expires_at)) : null,
                ]);
            }
        }

        // Sort: current device first, then newest activity
        $sorted = $devices->sortBy([
            ['is_current_device', 'desc'],
            ['last_active_at', 'desc'],
        ])->values();

        return response()->json([
            'success' => true,
            'sessions' => $sorted,
            'devices' => $sorted,
            'total_active' => $sorted->count(),
        ]);
    }

    public function revoke(Request $request, string $token): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        // 1. Check TenantSession
        $session = TenantSession::query()->active()->where('token', $token)->first();
        if ($session) {
            if ($session->company_id !== $company->id) {
                return response()->json(['success' => false, 'error' => 'Device session not found.'], 404);
            }

            $canRevoke = $user && ($user->isPrivilegedRole() || $session->user_id === $user->id);
            if (! $canRevoke) {
                return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
            }

            $session->update(['revoked' => true]);
            AuditLog::record('device.revoked', $company->id, $user?->id, ['session_user_id' => $session->user_id, 'token' => $token]);

            return response()->json(['success' => true, 'message' => 'Device session signed out successfully.']);
        }

        // 2. Check TenantApiKey
        $apiKey = TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($token) {
                $q->where('token', $token)->orWhere('id', $token);
            })
            ->first();

        if ($apiKey) {
            $canRevoke = $user && ($user->isPrivilegedRole() || $apiKey->user_id === $user->id);
            if (! $canRevoke) {
                return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
            }

            $apiKey->update(['active' => false]);
            AuditLog::record('device.revoked', $company->id, $user?->id, ['api_key_id' => $apiKey->id, 'device_name' => $apiKey->name]);

            return response()->json(['success' => true, 'message' => 'POS Terminal revoked and logged out.']);
        }

        // 3. Check personal_access_tokens
        if (Schema::hasTable('personal_access_tokens')) {
            $pat = DB::table('personal_access_tokens')->where('id', $token)->first();
            if ($pat) {
                DB::table('personal_access_tokens')->where('id', $token)->delete();
                AuditLog::record('device.revoked', $company->id, $user?->id, ['pat_id' => $token]);

                return response()->json(['success' => true, 'message' => 'Terminal token revoked.']);
            }
        }

        return response()->json(['success' => false, 'error' => 'Device terminal session not found.'], 404);
    }
}
