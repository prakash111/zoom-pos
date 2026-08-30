<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TenantSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mirrors app/Livewire/Tenant/Devices/Index.php exactly: active web-login
 * sessions (table `sessions`, NOT the TenantApiKey/POS terminal tokens) —
 * lets a store owner see and sign out stray dashboard logins from their
 * phone. Not gated by a permission module — the web page isn't either;
 * ownership/privilege is checked inline, same as the web version.
 */
class DeviceApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $sessions = TenantSession::query()->active()
            ->where('company_id', $company->id)
            ->when($user && ! $user->isPrivilegedRole(), fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantSession $s) => [
                'token' => $s->token,
                'user_id' => $s->user_id ? (string) $s->user_id : null,
                'user_name' => $s->user?->name ?? 'Unknown',
                'ip' => $s->ip,
                'user_agent' => $s->user_agent,
                'is_impersonation' => $s->isImpersonation(),
                'is_current_user' => $user && $s->user_id === $user->id,
                'created_at' => $s->created_at?->toIso8601String(),
                'expires_at' => $s->expires_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    public function revoke(Request $request, string $token): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $session = TenantSession::query()->active()->where('token', $token)->first();
        if (! $session || $session->company_id !== $company->id) {
            return response()->json(['success' => false, 'error' => 'Device session not found.'], 404);
        }

        $canRevoke = $user && ($user->isPrivilegedRole() || $session->user_id === $user->id);
        if (! $canRevoke) {
            return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $session->update(['revoked' => true]);
        AuditLog::record('device.revoked', $company->id, $user?->id, ['session_user_id' => $session->user_id]);

        return response()->json(['success' => true, 'message' => 'Device signed out.']);
    }
}
