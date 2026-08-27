<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\Auth\TenantAuthService;
use App\Services\Sync\SyncCompatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mirrors the legacy api/mysql.php action dispatch, at the same URL
 * (/api/mysql.php) and the same `action` parameter names, so the existing
 * compiled desktop/browser client needs zero changes. Actions not yet ported
 * return a clear "not yet implemented" error instead of a silent 404/500 —
 * see the milestone backlog in the M1 plan for the full list.
 */
class MysqlSyncController extends Controller
{
    public function dispatch(Request $request, SyncCompatService $sync)
    {
        $action = $request->input('action', 'status');

        return match ($action) {
            'health' => $this->health(),
            'test_connection', 'test' => $this->testConnection(),
            'save_connection', 'save' => response()->json([
                'success' => true,
                'message' => 'Single shared database mode — per-tenant connection selection was removed.',
            ]),
            'save_table', 'save_tables', 'save_all', 'salvar_tabela', 'salvar_banco' => $this->saveTable($request, $sync),
            'load_all', 'load_tables', 'get_all_data', 'carregar_banco', 'load' => $this->loadAll($sync),
            'count_admins' => $this->countAdmins($sync),
            'get_permissoes_usuario', 'get_permissions' => $this->getPermissions($request, $sync),
            'set_permissao_usuario', 'set_permission' => $this->setPermission($request, $sync),
            'criar_convite', 'send_invitation' => $this->createInvite($request, $sync),
            'ativar_convite', 'activate_invitation' => $this->activateInvite($request),
            default => response()->json([
                'success' => false,
                'error' => "Not yet implemented in Laravel rebuild: {$action}",
            ], 501),
        };
    }

    protected function health()
    {
        try {
            DB::select('select 1');
            $ok = true;
        } catch (\Throwable) {
            $ok = false;
        }

        return response()->json(['success' => $ok, 'status' => $ok ? 'ok' : 'error']);
    }

    protected function testConnection()
    {
        return $this->health();
    }

    protected function saveTable(Request $request, SyncCompatService $sync)
    {
        $company = $sync->resolveTenant($request);
        $table = $request->input('table');
        $data = (array) $request->input('data', []);

        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Missing "table" parameter.'], 422);
        }

        $result = $sync->saveTable($company, $table, $data);

        return response()->json($result);
    }

    protected function loadAll(SyncCompatService $sync)
    {
        $company = $sync->resolveTenant(request());

        return response()->json(['success' => true, 'data' => $sync->loadAll($company)]);
    }

    protected function countAdmins(SyncCompatService $sync)
    {
        $company = $sync->resolveTenant(request());

        $count = User::query()
            ->whereIn('role', User::PRIVILEGED_ROLES)
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    protected function getPermissions(Request $request, SyncCompatService $sync)
    {
        $sync->resolveTenant($request);

        $userId = $request->input('usuario_id') ?? $request->input('user_id');
        $target = User::find($userId);
        if (! $target) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $permissions = Permission::where('user_id', $target->id)->where('allowed', true)
            ->get(['module', 'action'])
            ->map(fn ($p) => ['module' => $p->module, 'action' => $p->action]);

        return response()->json(['success' => true, 'permissions' => $permissions]);
    }

    protected function setPermission(Request $request, SyncCompatService $sync)
    {
        $company = $sync->resolveTenant($request);
        $caller = auth('tenant_api')->user();
        abort_unless($caller->isPrivilegedRole(), 403);

        $userId = $request->input('usuario_id') ?? $request->input('user_id');
        $module = $request->input('module');
        // Named "permission_action" (not "action") — the top-level dispatch
        // parameter is already called "action" (set_permissao_usuario), and
        // a request body can't carry two different values under one key.
        $permissionAction = $request->input('permission_action') ?? $request->input('permissao');
        $allowed = filter_var($request->input('allowed', true), FILTER_VALIDATE_BOOLEAN);

        $target = User::find($userId);
        if (! $target || ! $module || ! $permissionAction) {
            return response()->json(['success' => false, 'error' => 'Missing or invalid parameters.'], 422);
        }

        if ($allowed) {
            Permission::firstOrCreate(
                ['user_id' => $target->id, 'module' => $module, 'action' => $permissionAction],
                ['company_id' => $company->id, 'allowed' => true]
            );
        } else {
            Permission::where('user_id', $target->id)->where('module', $module)->where('action', $permissionAction)->delete();
        }

        return response()->json(['success' => true]);
    }

    protected function createInvite(Request $request, SyncCompatService $sync)
    {
        $company = $sync->resolveTenant($request);
        $caller = auth('tenant_api')->user();
        abort_unless($caller->isPrivilegedRole(), 403);

        $name = $request->input('name');
        $email = $request->input('email');
        $role = $request->input('role', 'operador');

        if (! $name || ! $email) {
            return response()->json(['success' => false, 'error' => 'Missing name or email.'], 422);
        }

        $plaintext = strtoupper(Str::random(8));

        $user = User::create([
            'company_id' => $company->id,
            'name' => $name,
            'login' => Str::slug($email).'-'.Str::lower(Str::random(4)),
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'role' => $role,
            'status' => 'convidado',
            'invitation_code_hash' => Hash::make($plaintext),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        return response()->json(['success' => true, 'user_id' => $user->id, 'code' => $plaintext]);
    }

    protected function activateInvite(Request $request)
    {
        $code = $request->input('code');
        if (! $code) {
            return response()->json(['success' => false, 'error' => 'Missing code.'], 422);
        }

        $invite = User::withoutGlobalScope('company')
            ->where('status', 'convidado')
            ->where('invitation_expires_at', '>', now())
            ->get()
            ->first(fn (User $u) => $u->invitation_code_hash && Hash::check($code, $u->invitation_code_hash));

        if (! $invite) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired invite code.'], 422);
        }

        $password = $request->input('password');
        if (! $password || strlen($password) < 8) {
            return response()->json(['success' => false, 'error' => 'A password of at least 8 characters is required.'], 422);
        }

        $invite->update([
            'password' => Hash::make($password),
            'status' => 'approved',
            'invitation_code_hash' => null,
            'invitation_expires_at' => null,
            'email_verified_at' => now(),
        ]);

        $token = app(TenantAuthService::class)->mintToken($invite->company_id);
        TenantSession::create([
            'token' => $token,
            'user_id' => $invite->id,
            'company_id' => $invite->company_id,
            'expires_at' => now()->addHours(TenantAuthService::SESSION_TTL_HOURS),
        ]);

        return response()->json(['success' => true, 'token' => $token]);
    }
}
