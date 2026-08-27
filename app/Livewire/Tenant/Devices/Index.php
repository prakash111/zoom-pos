<?php

namespace App\Livewire\Tenant\Devices;

use App\Models\AuditLog;
use App\Models\TenantSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Devices'])]
class Index extends Component
{
    public function revoke(string $token): void
    {
        $user = auth('web')->user();
        $session = TenantSession::query()->active()->where('token', $token)->firstOrFail();

        // Admins may revoke any session in their own company; everyone else
        // may only revoke their own.
        $canRevoke = $session->company_id === $user->company_id
            && ($user->isPrivilegedRole() || $session->user_id === $user->id);
        abort_unless($canRevoke, 403);

        $session->update(['revoked' => true]);
        AuditLog::record('device.revoked', $session->company_id, $user->id, ['session_user_id' => $session->user_id]);
        session()->flash('status', 'Device signed out.');
    }

    public function render()
    {
        $user = auth('web')->user();

        // TenantSession is NOT a BelongsToCompany model (it also backs the
        // token-based sync guard, resolved before any tenant context
        // exists) — company_id must be filtered explicitly here.
        $sessions = TenantSession::query()->active()
            ->where('company_id', $user->company_id)
            ->when(! $user->isPrivilegedRole(), fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.tenant.devices.index', ['sessions' => $sessions]);
    }
}
