<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/Users/Permissions.php's grid load/save
 * exactly, computed server-side from PermissionChecker so the module/action
 * vocabulary and role-default presets never drift from the web page.
 */
class PermissionApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function show(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $targetUser = User::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->first();

        if (! $targetUser) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $modules = array_keys(PermissionChecker::MODULES);
        $grid = [];
        foreach ($modules as $module) {
            foreach (array_keys(PermissionChecker::getActionsForModule($module)) as $action) {
                $grid[$module][$action] = false;
            }
        }

        if ($targetUser->isPrivilegedRole()) {
            foreach ($modules as $module) {
                foreach (array_keys(PermissionChecker::getActionsForModule($module)) as $action) {
                    $grid[$module][$action] = true;
                }
            }
        } else {
            $explicit = Permission::where('user_id', $targetUser->id)->get();
            if ($explicit->isNotEmpty()) {
                foreach ($explicit as $p) {
                    if (isset($grid[$p->module][$p->action])) {
                        $grid[$p->module][$p->action] = (bool) $p->allowed;
                    }
                }
            } else {
                $roleDefaults = PermissionChecker::getRoleDefaults($targetUser->role);
                foreach ($roleDefaults as $module => $allowedActions) {
                    foreach ($allowedActions as $action) {
                        if (isset($grid[$module][$action])) {
                            $grid[$module][$action] = true;
                        }
                    }
                }
            }
        }

        $roleDefaultsAll = [];
        foreach (array_keys(User::ROLES) as $role) {
            $roleDefaultsAll[$role] = PermissionChecker::getRoleDefaults($role);
        }

        $moduleList = [];
        foreach (PermissionChecker::MODULES as $slug => $label) {
            $actions = [];
            foreach (PermissionChecker::getActionsForModule($slug) as $actionSlug => $actionLabel) {
                $actions[] = ['slug' => $actionSlug, 'label' => $actionLabel];
            }
            $moduleList[] = ['slug' => $slug, 'label' => $label, 'actions' => $actions];
        }

        return response()->json([
            'success' => true,
            'user' => ['id' => (string) $targetUser->id, 'name' => $targetUser->name, 'role' => $targetUser->role, 'is_privileged' => $targetUser->isPrivilegedRole()],
            'modules' => $moduleList,
            'grid' => $grid,
            'role_defaults' => $roleDefaultsAll,
        ]);
    }

    /**
     * Body: `grid` — {module: {action: bool}}, same shape as the GET response.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $admin = $this->resolveUser($request, $company);
        $targetUser = User::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->first();

        if (! $targetUser) {
            return response()->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $validator = Validator::make($request->all(), ['grid' => ['required', 'array']]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $grid = $request->input('grid');
        $modules = array_keys(PermissionChecker::MODULES);

        foreach ($modules as $module) {
            foreach (array_keys(PermissionChecker::getActionsForModule($module)) as $action) {
                $allowed = (bool) ($grid[$module][$action] ?? false);
                $existing = Permission::where('user_id', $targetUser->id)->where('module', $module)->where('action', $action)->first();

                if ($allowed) {
                    if (! $existing) {
                        Permission::create(['user_id' => $targetUser->id, 'company_id' => $targetUser->company_id, 'module' => $module, 'action' => $action, 'allowed' => true]);
                    } elseif (! $existing->allowed) {
                        $existing->update(['allowed' => true]);
                    }
                } elseif ($existing) {
                    $existing->delete();
                }
            }
        }

        AuditLog::record('user.permissions_updated', $company->id, $admin?->id, ['target_user_id' => $targetUser->id]);

        return response()->json(['success' => true, 'message' => "Permissions updated for {$targetUser->name}."]);
    }
}
