<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mirrors app/Livewire/Tenant/Users/Index.php's staff-management actions
 * (invite/resend/role/status/commission/delete) exactly, including its
 * self-protection guards. Impersonation ("switch to user") is intentionally
 * not exposed here — desktop/admin-only, out of scope for the mobile app.
 */
class UserApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $search = $request->query('search');

        $query = User::withoutGlobalScope('company')->where('company_id', $company->id);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $t = "%{$search}%";
                $q->where('name', 'like', $t)->orWhere('email', 'like', $t)->orWhere('role', 'like', $t);
            });
        }

        $users = $query->orderBy('name')->get()->map(fn (User $u) => $this->present($u));

        return response()->json([
            'success' => true,
            'roles' => $this->assignableRoleMap($company),
            'users' => $users,
        ]);
    }

    /**
     * Built-in roles + this tenant's custom roles, as slug => display name.
     * Drives the staff invite / change-role dropdowns.
     *
     * @return array<string, string>
     */
    private function assignableRoleMap(Company $company): array
    {
        $map = User::ROLES;

        try {
            Role::query()
                ->where('company_id', $company->id)
                ->where('is_system', false)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->each(function (Role $role) use (&$map) {
                    $map[$role->slug] = $role->name;
                });
        } catch (\Throwable $e) {
            // roles table not migrated yet — fall back to built-ins only.
        }

        return $map;
    }

    public function invite(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $admin = $this->resolveUser($request, $company);

        $allowedRoles = array_merge(array_keys($this->assignableRoleMap($company)), ['operador']);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:'.implode(',', $allowedRoles)],
            'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'commission_type' => ['required', 'in:percentage,fixed,profit_percentage,profit'],
            'send_via_email' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $plaintext = strtoupper(Str::random(8));

        $user = User::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'login' => Str::slug($data['email']).'-'.Str::lower(Str::random(4)),
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'role' => $data['role'],
            'commission_rate' => $data['commission_rate'] ?? 0,
            'commission_type' => $data['commission_type'],
            'status' => 'convidado',
            'invitation_code_hash' => Hash::make($plaintext),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        AuditLog::record('user.invited', $company->id, $admin?->id, ['invited_user_id' => $user->id, 'role' => $data['role']]);

        $delivery = app(InvoiceDeliveryService::class);
        $inviteLink = route('accept-invite', ['email' => $user->email, 'code' => $plaintext]);
        $whatsappUrl = $delivery->generateInvitationWhatsAppUrl($user, $plaintext, $data['phone'] ?? null);

        $emailSent = false;
        $emailError = null;
        if ($request->boolean('send_via_email', true)) {
            try {
                $delivery->sendInvitationEmail($user, $plaintext);
                $emailSent = true;
            } catch (\Throwable $e) {
                $emailError = $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent ? "Invitation email sent to {$user->email}." : 'Team member created.',
            'user' => $this->present($user),
            'invitation_code' => $plaintext,
            'invitation_link' => $inviteLink,
            'whatsapp_url' => $whatsappUrl,
            'email_sent' => $emailSent,
            'email_error' => $emailError,
        ], 201);
    }

    public function resendInvite(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->findUser($company, $id);

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }
        if ($user->status !== 'convidado') {
            return response()->json(['success' => false, 'error' => 'User has already accepted the invitation.'], 422);
        }

        $plaintext = strtoupper(Str::random(8));
        $user->update(['invitation_code_hash' => Hash::make($plaintext), 'invitation_expires_at' => now()->addDays(7)]);

        $delivery = app(InvoiceDeliveryService::class);
        $inviteLink = route('accept-invite', ['email' => $user->email, 'code' => $plaintext]);
        $whatsappUrl = $delivery->generateInvitationWhatsAppUrl($user, $plaintext);

        return response()->json([
            'success' => true,
            'message' => 'New invitation code generated.',
            'invitation_code' => $plaintext,
            'invitation_link' => $inviteLink,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $me = $this->resolveUser($request, $company);
        $user = $this->findUser($company, $id);

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:'.implode(',', array_keys($this->assignableRoleMap($company)))],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $newRole = $request->input('role');
        if ($me && $user->id === $me->id && $newRole !== User::ROLE_ADMINISTRATOR) {
            return response()->json(['success' => false, 'error' => 'You cannot change your own administrator role.'], 422);
        }

        $oldRole = $user->role;
        $user->update(['role' => $newRole]);
        AuditLog::record('user.role_changed', $company->id, $me?->id, ['user_id' => $user->id, 'old_role' => $oldRole, 'new_role' => $newRole]);

        return response()->json(['success' => true, 'message' => 'Role updated.', 'user' => $this->present($user->fresh())]);
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $me = $this->resolveUser($request, $company);
        $user = $this->findUser($company, $id);

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }
        if ($me && $user->id === $me->id) {
            return response()->json(['success' => false, 'error' => 'You cannot suspend your own account.'], 422);
        }

        $newStatus = $user->status === 'approved' ? 'suspended' : 'approved';
        $user->update(['status' => $newStatus]);
        AuditLog::record('user.status_changed', $company->id, $me?->id, ['user_id' => $user->id, 'new_status' => $newStatus]);

        return response()->json(['success' => true, 'message' => "User is now {$newStatus}.", 'user' => $this->present($user->fresh())]);
    }

    public function updateCommission(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $me = $this->resolveUser($request, $company);
        $user = $this->findUser($company, $id);

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'commission_rate' => ['required', 'numeric', 'min:0'],
            'commission_type' => ['required', 'in:percentage,fixed,profit_percentage,profit'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $user->update($validator->validated());
        AuditLog::record('user.commission_updated', $company->id, $me?->id, ['user_id' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Commission updated.', 'user' => $this->present($user->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $me = $this->resolveUser($request, $company);
        $user = $this->findUser($company, $id);

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }
        if ($me && $user->id === $me->id) {
            return response()->json(['success' => false, 'error' => 'You cannot remove your own account.'], 422);
        }

        $remainingAdmins = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereIn('role', User::PRIVILEGED_ROLES)
            ->where('id', '!=', $user->id)
            ->count();
        if ($user->isPrivilegedRole() && $remainingAdmins === 0) {
            return response()->json(['success' => false, 'error' => 'Cannot remove the last administrator.'], 422);
        }

        AuditLog::record('user.deleted', $company->id, $me?->id, ['deleted_user_id' => $user->id]);
        $user->delete();

        return response()->json(['success' => true, 'message' => 'User removed.']);
    }

    private function findUser(Company $company, string $id): ?User
    {
        return User::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->first();
    }

    /** @var array<string, string>|null memoised custom-role slug => name for this request */
    private ?array $customRoleLabels = null;

    private function customRoleLabel(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if ($this->customRoleLabels === null) {
            $this->customRoleLabels = [];
            try {
                $companyId = app()->bound('tenant.company_id') ? app('tenant.company_id') : null;
                if ($companyId !== null) {
                    Role::query()
                        ->where('company_id', $companyId)
                        ->where('is_system', false)
                        ->get(['slug', 'name'])
                        ->each(fn (Role $r) => $this->customRoleLabels[$r->slug] = $r->name);
                }
            } catch (\Throwable $e) {
                // roles table absent — leave empty.
            }
        }

        return $this->customRoleLabels[$slug] ?? null;
    }

    private function present(User $u): array
    {
        return [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'role_label' => User::ROLES[$u->role]
                ?? $this->customRoleLabel($u->role)
                ?? Str::headline((string) $u->role),
            'status' => $u->status,
            'commission_rate' => (float) ($u->commission_rate ?? 0),
            'commission_type' => $u->commission_type ?: 'percentage',
            'is_privileged' => $u->isPrivilegedRole(),
        ];
    }
}
