<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Navigation\TenantNavigationConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class NavigationMenuController extends Controller
{
    /**
     * POST /tenant/settings/navigation-menu
     */
    public function store(Request $request, TenantNavigationConfigService $navigation): JsonResponse
    {
        $validator = Validator::make($request->all(), TenantNavigationConfigService::validationRules());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('The navigation menu could not be saved.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth('web')->user();
        $company = $user?->company;
        abort_unless($user && $company, 403);

        $validated = $validator->validated();
        if (empty($validated['sections']) || (empty($validated['items']) && empty($validated['tree']))) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid menu configuration.'),
            ], 422);
        }

        $navConfig = $navigation->normalize($validated);
        $company->forceFill(['nav_config' => $navConfig])->saveOrFail();
        Cache::forget("tenant_{$company->id}_drawer_menu");
        $persistedNav = $company->fresh()->normalizedNavConfig();
        AuditLog::record('company.settings_updated', $company->id, $user->id, ['section' => 'nav_config']);

        return response()->json([
            'success' => true,
            'message' => __('Navigation menu updated.'),
            'nav' => $persistedNav,
        ]);
    }

    /**
     * Build and activate comprehensive default navigation for a store type.
     */
    public function populateDefaultNavigation(\App\Models\Company|\App\Models\Tenant $tenant, string $storeType): void
    {
        app(\App\Services\Navigation\MenuService::class)->populateDefaultNavigation($tenant, $storeType);
    }

    /**
     * GET /tenant/navigation/header or drawer header info.
     */
    public function drawerHeader(Request $request): JsonResponse
    {
        $user = auth('web')->user() ?? auth('tenant_api')->user();
        $tenant = $user?->tenant ?? $user?->company;
        abort_unless($tenant, 404);

        $drawerHeader = $tenant->getDrawerHeaderPayload();

        return response()->json([
            'success' => true,
            'header' => $drawerHeader,
            'drawer_header' => $drawerHeader,
        ]);
    }
}
