<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Tenant-authored custom roles on top of the built-in system roles. A role is
 * a { module: [action] } permission map (PermissionChecker vocabulary); once
 * created it is immediately assignable to staff and enforced by
 * PermissionChecker for any user carrying that role slug.
 */
class RoleApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/tenant/roles
     * Built-in system roles + this tenant's custom roles, plus the full
     * permission catalog for building the create/edit form.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $roles = Role::query()
            ->forTenant($company->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->present($role, $company->id))
            ->values();

        $modules = $this->permissionCatalog($company);

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'modules' => $modules,
            'permission_groups' => $modules,
        ]);
    }

    /**
     * POST /api/tenant/roles
     * Body: name (required), description, permissions {module: [action]}.
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $admin = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $name = trim($request->input('name'));
        $slug = $this->uniqueSlug($company->id, Role::makeSlug($name));

        $role = Role::create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
            'description' => $request->input('description'),
            'permissions' => $this->resolvePermissions($request),
            'is_demo' => false,
        ]);

        PermissionChecker::flushRoleCache();

        AuditLog::record('role.created', $company->id, $admin?->id, [
            'role_id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Role \"{$role->name}\" created.",
            'role' => $this->present($role, $company->id),
        ], 201);
    }

    /**
     * PUT/PATCH /api/tenant/roles/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $admin = $this->resolveUser($request, $company);

        $role = Role::query()->where('id', $id)->where('company_id', $company->id)->first();
        if (! $role || $role->is_system) {
            return response()->json(['success' => false, 'error' => 'Custom role not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        if ($request->filled('name')) {
            $role->name = trim($request->input('name'));
        }
        if ($request->has('description')) {
            $role->description = $request->input('description');
        }
        if ($request->has('permissions') || $this->hasFlatPermissionKeys($request)) {
            $role->permissions = $this->resolvePermissions($request);
        }
        $role->save();

        PermissionChecker::flushRoleCache();

        AuditLog::record('role.updated', $company->id, $admin?->id, [
            'role_id' => $role->id,
            'slug' => $role->slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Role \"{$role->name}\" updated.",
            'role' => $this->present($role->fresh(), $company->id),
        ]);
    }

    /**
     * DELETE /api/tenant/roles/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $admin = $this->resolveUser($request, $company);

        $role = Role::query()->where('id', $id)->where('company_id', $company->id)->first();
        if (! $role || $role->is_system) {
            return response()->json(['success' => false, 'error' => 'Custom role not found.'], 404);
        }

        $inUse = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('role', $role->slug)
            ->count();
        if ($inUse > 0) {
            return response()->json([
                'success' => false,
                'error' => "Reassign the {$inUse} staff member(s) on this role before deleting it.",
            ], 422);
        }

        $slug = $role->slug;
        $role->delete();
        PermissionChecker::flushRoleCache();

        AuditLog::record('role.deleted', $company->id, $admin?->id, ['slug' => $slug]);

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function present(Role $role, string $companyId): array
    {
        return [
            'id' => (string) $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'editable' => ! $role->is_system && (string) $role->company_id === (string) $companyId,
            'permissions' => Role::permissionMapFor($companyId, $role->slug) ?? (is_array($role->permissions) ? $role->permissions : []),
        ];
    }

    /**
     * GET /api/tenant/roles/schema
     */
    public function getPermissionsSchema(Request $request): JsonResponse
    {
        return app(\App\Http\Controllers\Api\RolePermissionController::class)->getPermissionsSchema($request);
    }

    /** @return list<array<string, mixed>> */
    private function permissionCatalog(?\App\Models\Company $company = null): array
    {
        $filtered = $company
            ? \App\Http\Controllers\Api\RolePermissionController::getFilteredModulesForTenant($company)
            : PermissionChecker::MODULES;

        $modules = [];
        foreach ($filtered as $slug => $label) {
            $actions = [];
            foreach (PermissionChecker::getActionsForModule($slug) as $actionSlug => $actionLabel) {
                $actions[] = [
                    'slug' => $actionSlug,
                    'label' => $actionLabel,
                    'field_key' => "perm__{$slug}__{$actionSlug}",
                ];
            }
            $modules[] = [
                'module' => $slug,
                'slug' => $slug,
                'label' => $label,
                'actions' => $actions,
            ];
        }

        return $modules;
    }

    /**
     * Accept either a nested `permissions` map ({module: [action]}) or the flat
     * `perm__<module>__<action> => bool` checkboxes emitted by the SDUI role
     * form, and return a sanitised nested map.
     *
     * @return array<string, list<string>>
     */
    private function resolvePermissions(Request $request): array
    {
        $raw = $request->input('permissions');
        if (is_array($raw) && $raw !== []) {
            return $this->sanitizePermissions($raw);
        }

        $nested = [];
        foreach ($request->all() as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'perm__')) {
                continue;
            }
            if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $parts = explode('__', substr($key, 6), 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$module, $action] = $parts;
            $nested[$module][] = $action;
        }

        return $this->sanitizePermissions($nested);
    }

    private function hasFlatPermissionKeys(Request $request): bool
    {
        foreach (array_keys($request->all()) as $key) {
            if (is_string($key) && str_starts_with($key, 'perm__')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only known module/action pairs.
     *
     * @param  mixed  $raw
     * @return array<string, list<string>>
     */
    private function sanitizePermissions($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $module => $actions) {
            if (! is_array($actions) || ! isset(PermissionChecker::MODULE_ACTIONS[$module])) {
                continue;
            }
            $valid = array_values(array_intersect(
                array_map('strval', $actions),
                array_keys(PermissionChecker::getActionsForModule($module))
            ));
            if ($valid !== []) {
                $clean[$module] = $valid;
            }
        }

        return $clean;
    }

    private function uniqueSlug(string $companyId, string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Role::query()->forTenant($companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i;
            $i++;
        }

        return $slug;
    }
}
