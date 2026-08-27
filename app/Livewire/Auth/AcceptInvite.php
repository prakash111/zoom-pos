<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Direct port of the legacy ativar_convite semantics: the invitee doesn't
 * know their company, so the code is looked up by scanning every pending
 * invite (status = convidado) and password_verify-matching the plaintext
 * code — never by claimed identity.
 */
#[Layout('layouts.guest')]
class AcceptInvite extends Component
{
    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(): void
    {
        if (request()->has('code')) {
            $this->code = trim((string) request()->query('code'));
        }
    }

    public function accept()
    {
        $this->validate([
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invite = User::withoutGlobalScope('company')
            ->where('status', 'convidado')
            ->where('invitation_expires_at', '>', now())
            ->get()
            ->first(fn (User $u) => $u->invitation_code_hash && Hash::check($this->code, $u->invitation_code_hash));

        if (! $invite) {
            $this->error = 'Invalid or expired invite code.';

            return;
        }

        $invite->update([
            'password' => Hash::make($this->password),
            'status' => 'approved',
            'invitation_code_hash' => null,
            'invitation_expires_at' => null,
            'email_verified_at' => now(),
        ]);

        AuditLog::record('user.invite_accepted', $invite->company_id, $invite->id);

        Auth::guard('web')->login($invite);
        app()->instance('tenant.company_id', $invite->company_id);

        return redirect('/tenant');
    }

    public function render()
    {
        return view('livewire.auth.accept-invite');
    }
}
