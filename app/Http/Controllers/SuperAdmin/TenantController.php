<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\TenantSetting;
use App\Services\Modular\ModulePackageService;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantController extends Controller
{
    /**
     * Update tenant enabled / licensed modules from SuperAdmin.
     * POST/PUT /superadmin/tenants/{tenantId}/modules
     * POST/PUT /api/superadmin/tenants/{tenantId}/modules
     */
    public function updateModules(Request $request, string|int $tenantId): JsonResponse
    {
        $company = Company::find($tenantId);
        if (! $company) {
            return response()->json(['success' => false, 'error' => 'Tenant not found.'], 404);
        }

        $rawModules = $request->input('modules')
            ?? $request->input('licensed_modules')
            ?? $request->input('enabled_modules')
            ?? [];

        if (is_string($rawModules)) {
            $decoded = json_decode($rawModules, true);
            $rawModules = is_array($decoded) ? $decoded : explode(',', $rawModules);
        }

        $normalized = [];
        foreach ((array) $rawModules as $mod) {
            if (is_string($mod) && trim($mod) !== '') {
                $canonical = ModuleRegistry::canonicalKey(trim($mod));
                $normalized[] = $canonical;
            }
        }
        $normalized = array_values(array_unique($normalized));

        if (empty($normalized)) {
            $normalized = [ModuleRegistry::resolveActiveMode($company)];
        }

        app(ModulePackageService::class)->authorizeExtensionAssignment($company, $normalized);

        $company->licensed_modules = $normalized;
        $company->save();

        // Synchronize tenant_settings table
        TenantSetting::set($company->id, 'enabled_modules', $normalized);

        // Invalidate tenant caches
        $id = (string) $company->id;
        Cache::forget("tenant_{$id}_role_permissions");
        Cache::forget("tenant_{$id}_drawer_menu");
        Cache::forget("navigation_menu_{$id}");
        Cache::forget("tenant_{$id}_drawer");
        Cache::forget("drawer_menu_{$id}");
        Cache::forget("tenant_{$id}_menu");

        AuditLog::record('tenant.modules_updated', $company->id, auth('platform_web')->id() ?? auth()->id(), [
            'licensed_modules' => $normalized,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tenant modules updated successfully.',
            'tenant_id' => $id,
            'modules' => $normalized,
            'licensed_modules' => $normalized,
            'enabled_modules' => $normalized,
        ]);
    }
}
