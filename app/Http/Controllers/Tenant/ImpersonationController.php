<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Web-session "log in as" for the Blade/Livewire tenant panel. Distinct from
 * TenantAuthService::impersonate(), which mints a bearer token for the sync
 * API — this swaps the 'web' session guard's user directly, the standard
 * Laravel pattern, and is restricted to privileged users targeting a
 * same-company, non-privileged user.
 */
class ImpersonationController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        abort_unless($admin->isPrivilegedRole(), 403);
        abort_unless($user->company_id === $admin->company_id, 403);
        abort_if($user->id === $admin->id, 422);

        session(['impersonator_id' => $admin->id]);
        Auth::guard('web')->login($user);

        AuditLog::record('user.impersonation_started', $admin->company_id, $admin->id, ['target_user_id' => $user->id]);

        return redirect()->route('tenant.dashboard');
    }

    public function stop(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');
        abort_unless($impersonatorId, 403);

        $original = User::withoutGlobalScope('company')->findOrFail($impersonatorId);
        $currentUserId = Auth::guard('web')->id();

        session()->forget('impersonator_id');
        Auth::guard('web')->login($original);

        AuditLog::record('user.impersonation_ended', $original->company_id, $original->id, ['was_viewing_as' => $currentUserId]);

        return redirect()->route('tenant.dashboard');
    }
}
