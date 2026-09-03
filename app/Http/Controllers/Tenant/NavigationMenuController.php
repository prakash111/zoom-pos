<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Navigation\TenantNavigationConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $navConfig = $navigation->normalize($validator->validated());
        $company->update(['nav_config' => $navConfig]);
        AuditLog::record('company.settings_updated', $company->id, $user->id, ['section' => 'nav_config']);

        return response()->json([
            'success' => true,
            'message' => __('Navigation menu updated.'),
            'nav' => $navConfig,
        ]);
    }
}
