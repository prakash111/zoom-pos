<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppPreferenceController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Update tenant app preferences (drawer text & icons color, surfaces, theme mode, etc.)
     *
     * POST /api/v1/tenant/settings/app-preferences
     * POST /api/tenant/preferences
     * POST /api/app-preferences
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable) {}

        $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();
        $tenantId = $company?->id ?? $user?->tenant_id ?? $user?->company_id;

        if (! $tenantId) {
            $tenantId = app()->bound('tenant.company_id')
                ? app('tenant.company_id')
                : (request()->header('X-Tenant-Id') ?: request()->header('X-Company-Id') ?: 1);
        }

        $data = $request->all();

        // Normalize color keys for Drawer text & icons
        $color = $data['drawer_text_icon_color']
            ?? $data['drawer_text_and_icons']
            ?? $data['drawer_text_color']
            ?? $data['drawer_icon_color']
            ?? $data['color']
            ?? null;

        if ($color !== null) {
            $data['drawer_text_icon_color'] = $color;
            $data['drawer_text_and_icons'] = $color;
        }

        // Retrieve existing preferences to merge cleanly
        $existing = DB::table('tenant_settings')
            ->where('tenant_id', (string) $tenantId)
            ->where('key', 'app_preferences')
            ->value('value');

        $existingData = [];
        if ($existing) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $existingData = $decoded;
            }
        }

        $merged = array_merge($existingData, $data);

        // Update or insert into tenant_settings table
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => (string) $tenantId, 'key' => 'app_preferences'],
            [
                'value'      => json_encode($merged),
                'updated_at' => now(),
            ]
        );

        // Invalidate all cached drawer and navigation menus so changes reflect immediately
        Cache::forget("tenant_{$tenantId}_drawer_menu");
        Cache::forget("navigation_menu_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_drawer");
        Cache::forget("drawer_menu_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_menu");
        Cache::forget("tenant_{$tenantId}_navigation");
        Cache::forget("tenant_nav_{$tenantId}");

        if ($company !== null) {
            $company->flushTenantCaches();
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Preferences updated.',
            'preferences' => $merged,
            'data'        => $merged,
        ]);
    }

    /**
     * Retrieve tenant app preferences.
     *
     * GET /api/v1/tenant/settings/app-preferences
     * GET /api/tenant/preferences
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable) {}

        $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();
        $tenantId = $company?->id ?? $user?->tenant_id ?? $user?->company_id;

        if (! $tenantId) {
            $tenantId = app()->bound('tenant.company_id')
                ? app('tenant.company_id')
                : (request()->header('X-Tenant-Id') ?: request()->header('X-Company-Id') ?: 1);
        }

        $existing = DB::table('tenant_settings')
            ->where('tenant_id', (string) $tenantId)
            ->where('key', 'app_preferences')
            ->value('value');

        $preferences = [];
        if ($existing) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $preferences = $decoded;
            }
        }

        return response()->json([
            'success'     => true,
            'preferences' => $preferences,
            'data'        => $preferences,
        ]);
    }
}
