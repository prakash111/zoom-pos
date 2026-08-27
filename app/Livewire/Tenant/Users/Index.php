<?php

namespace App\Livewire\Tenant\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Access Control & Users'])]
class Index extends Component
{
    public bool $showInviteForm = false;

    public string $inviteName = '';

    public string $inviteEmail = '';

    public string $invitePhone = '';

    public string $inviteRole = User::ROLE_CASHIER;

    public float $inviteCommissionRate = 0.0;

    public string $inviteCommissionType = 'percentage';

    public bool $sendViaEmail = true;

    // Edit Commission Modal State
    public bool $showCommissionModal = false;

    public ?string $editingUserId = null;

    public ?string $editingUserName = null;

    public float $editingCommissionRate = 0.0;

    public string $editingCommissionType = 'percentage';

    // Success / Share modal state
    public ?User $justInvitedUser = null;

    public ?string $justInvitedCode = null;

    public ?string $justInvitedLink = null;

    public ?string $justInvitedWhatsAppUrl = null;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()->isPrivilegedRole(), 403);
    }

    public function newInvite(): void
    {
        $this->reset(['inviteName', 'inviteEmail', 'invitePhone', 'justInvitedCode', 'justInvitedUser', 'justInvitedLink', 'justInvitedWhatsAppUrl']);
        $this->inviteRole = User::ROLE_CASHIER;
        $this->inviteCommissionRate = (float) (auth('web')->user()->company?->default_commission_rate ?? 0);
        $this->inviteCommissionType = (string) (auth('web')->user()->company?->default_commission_type ?: 'percentage');
        $this->sendViaEmail = true;
        $this->showInviteForm = true;
    }

    public function openCommissionModal(string $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->editingUserName = $user->name;
        $this->editingCommissionRate = (float) ($user->commission_rate ?? 0);
        $this->editingCommissionType = (string) ($user->commission_type ?: 'percentage');
        $this->showCommissionModal = true;
    }

    public function updateCommission(): void
    {
        $this->validate([
            'editingCommissionRate' => ['required', 'numeric', 'min:0'],
            'editingCommissionType' => ['required', 'in:percentage,fixed,profit_percentage,profit'],
        ]);

        if (! $this->editingUserId) {
            return;
        }

        $user = User::findOrFail($this->editingUserId);
        $user->update([
            'commission_rate' => $this->editingCommissionRate,
            'commission_type' => $this->editingCommissionType,
        ]);

        AuditLog::record('user.commission_updated', $user->company_id, auth('web')->id(), [
            'user_id' => $user->id,
            'commission_rate' => $this->editingCommissionRate,
            'commission_type' => $this->editingCommissionType,
        ]);

        $this->showCommissionModal = false;
        session()->flash('status', "Commission updated for {$user->name}.");
    }

    public function invite(): void
    {
        $allowedRoles = array_merge(array_keys(User::ROLES), ['operador']);

        $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email'],
            'invitePhone' => ['nullable', 'string', 'max:50'],
            'inviteRole' => ['required', 'in:'.implode(',', $allowedRoles)],
            'inviteCommissionRate' => ['numeric', 'min:0'],
            'inviteCommissionType' => ['required', 'in:percentage,fixed,profit_percentage,profit'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $plaintext = strtoupper(Str::random(8));

        $user = User::create([
            'company_id' => $companyId,
            'name' => $this->inviteName,
            'login' => Str::slug($this->inviteEmail).'-'.Str::lower(Str::random(4)),
            'email' => $this->inviteEmail,
            'password' => Hash::make(Str::random(32)),
            'role' => $this->inviteRole,
            'commission_rate' => $this->inviteCommissionRate,
            'commission_type' => $this->inviteCommissionType,
            'status' => 'convidado',
            'invitation_code_hash' => Hash::make($plaintext),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        AuditLog::record('user.invited', $user->company_id, auth('web')->id(), ['invited_user_id' => $user->id, 'role' => $this->inviteRole]);

        $deliveryService = app(InvoiceDeliveryService::class);
        $this->justInvitedUser = $user;
        $this->justInvitedCode = $plaintext;
        $this->justInvitedLink = route('accept-invite', ['email' => $user->email, 'code' => $plaintext]);
        $this->justInvitedWhatsAppUrl = $deliveryService->generateInvitationWhatsAppUrl($user, $plaintext, $this->invitePhone);

        // Attempt automated email dispatch if requested
        if ($this->sendViaEmail) {
            try {
                $deliveryService->sendInvitationEmail($user, $plaintext);
                session()->flash('status', "Invitation created and email sent successfully to {$user->email}!");
            } catch (\Throwable $e) {
                session()->flash('error', 'User created, but email could not be sent: '.$e->getMessage().'. You can share the invitation via WhatsApp or copy the link below.');
            }
        } else {
            session()->flash('status', "Team member {$user->name} created. Share the invitation link or code below.");
        }

        $this->showInviteForm = false;
    }

    public function sendEmailInvite(mixed $userId, string $code): void
    {
        $user = User::findOrFail($userId);
        try {
            app(InvoiceDeliveryService::class)->sendInvitationEmail($user, $code);
            session()->flash('status', "Invitation email successfully sent to {$user->email}!");
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send email: '.$e->getMessage());
        }
    }

    public function resendInvite(mixed $userId): void
    {
        $user = User::findOrFail($userId);
        if ($user->status !== 'convidado') {
            session()->flash('error', 'User has already accepted the invitation.');

            return;
        }

        $plaintext = strtoupper(Str::random(8));
        $user->update([
            'invitation_code_hash' => Hash::make($plaintext),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        $deliveryService = app(InvoiceDeliveryService::class);
        $this->justInvitedUser = $user;
        $this->justInvitedCode = $plaintext;
        $this->justInvitedLink = route('accept-invite', ['email' => $user->email, 'code' => $plaintext]);
        $this->justInvitedWhatsAppUrl = $deliveryService->generateInvitationWhatsAppUrl($user, $plaintext);

        session()->flash('status', "New invitation code generated for {$user->name}.");
    }

    public function updateUserRole(mixed $userId, string $newRole): void
    {
        $allowedRoles = array_keys(User::ROLES);
        if (! in_array($newRole, $allowedRoles, true)) {
            return;
        }

        $user = User::findOrFail($userId);
        $me = auth('web')->user();

        if ($user->id === $me->id && $newRole !== User::ROLE_ADMINISTRATOR) {
            session()->flash('error', 'You cannot change your own administrator role.');

            return;
        }

        $oldRole = $user->role;
        $user->update(['role' => $newRole]);

        AuditLog::record('user.role_changed', $user->company_id, $me->id, [
            'user_id' => $user->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);

        session()->flash('status', "Updated role for {$user->name} to ".(User::ROLES[$newRole] ?? $newRole).'.');
    }

    public function toggleUserStatus(mixed $userId): void
    {
        $user = User::findOrFail($userId);
        $me = auth('web')->user();

        if ($user->id === $me->id) {
            session()->flash('error', 'You cannot suspend your own account.');

            return;
        }

        $newStatus = $user->status === 'approved' ? 'suspended' : 'approved';
        $user->update(['status' => $newStatus]);

        AuditLog::record('user.status_changed', $user->company_id, $me->id, [
            'user_id' => $user->id,
            'new_status' => $newStatus,
        ]);

        session()->flash('status', "User {$user->name} is now {$newStatus}.");
    }

    public function switchToUser(mixed $userId)
    {
        $admin = Auth::guard('web')->user();
        abort_unless($admin->isPrivilegedRole(), 403);

        $targetUser = User::findOrFail($userId);
        abort_unless($targetUser->company_id === $admin->company_id, 403);
        abort_if($targetUser->id === $admin->id, 422);

        session(['impersonator_id' => $admin->id]);
        Auth::guard('web')->login($targetUser);

        AuditLog::record('user.impersonation_started', $admin->company_id, $admin->id, ['target_user_id' => $targetUser->id]);

        return redirect()->route('tenant.dashboard');
    }

    public function delete(string $id): void
    {
        $user = User::findOrFail($id);
        $me = auth('web')->user();

        if ($user->id === $me->id) {
            session()->flash('error', 'You cannot remove your own account.');

            return;
        }

        $remainingAdmins = User::whereIn('role', User::PRIVILEGED_ROLES)->where('id', '!=', $user->id)->count();
        if ($user->isPrivilegedRole() && $remainingAdmins === 0) {
            session()->flash('error', 'Cannot remove the last administrator.');

            return;
        }

        AuditLog::record('user.deleted', $user->company_id, $me->id, ['deleted_user_id' => $user->id]);
        $user->delete();
        session()->flash('status', 'User removed.');
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($sq) use ($term) {
                    $sq->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('role', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.tenant.users.index', [
            'users' => $users,
            'roles' => User::ROLES,
        ]);
    }
}
