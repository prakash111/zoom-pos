<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class StoreProfileController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/v1/tenant/store-profile or /api/tenant/store-profile
     */
    public function show(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $drawerHeader = $company->getDrawerHeaderPayload();

        return response()->json([
            'success' => true,
            'store_name' => $company->display_name,
            'business_name' => $company->display_name,
            'tenant_name' => $company->display_name,
            'title' => $company->display_name,
            'trading_name' => $company->getEffectiveTradeName(),
            'trade_name' => $company->getEffectiveTradeName(),
            'display_name' => $company->display_name,
            'header' => $drawerHeader,
            'drawer_header' => $drawerHeader,
            'tenant' => [
                'id' => (string) $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'tenant_name' => $company->display_name,
                'title' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'display_name' => $company->display_name,
                'store_name' => $company->display_name,
                'header' => $drawerHeader,
                'drawer_header' => $drawerHeader,
            ],
            'company' => $company,
        ]);
    }

    /**
     * POST /api/v1/tenant/store-profile or /api/tenant/store-profile
     *
     * Saves business_name and trading_name (and any optional contact/branding fields),
     * immediately flushes all tenant cache keys, and returns the real-time store display payload.
     */
    public function update(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        // Normalize aliases: business_name -> name, trading_name -> trade_name
        if ($request->filled('business_name') && ! $request->filled('name')) {
            $request->merge(['name' => $request->input('business_name')]);
        }
        if ($request->filled('trading_name') && ! $request->filled('trade_name')) {
            $request->merge(['trade_name' => $request->input('trading_name')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'trading_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:2'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $businessName = trim((string) ($request->input('business_name') ?: $request->input('name') ?: ''));
        if ($businessName !== '') {
            $data['name'] = $businessName;
        }

        $incomingTrade = trim((string) ($request->input('trading_name') ?: $request->input('trade_name') ?: ''));
        $existingTrade = trim((string) ($company->trade_name ?? ''));

        if (
            $incomingTrade === '' ||
            Company::isDemoPlaceholderName($incomingTrade) ||
            Company::isDemoPlaceholderName($existingTrade)
        ) {
            if ($businessName !== '') {
                $data['trade_name'] = $businessName;
            }
        } else {
            $data['trade_name'] = $incomingTrade;
        }

        if (! empty($data['name'])) {
            $legal = trim((string) ($company->legal_name ?? ''));
            if ($legal === '' || Company::isDemoPlaceholderName(str_replace([' Pvt. Ltd.', ' Ltd.', ' Inc.'], '', $legal))) {
                $data['legal_name'] = $data['name'].' Pvt. Ltd.';
            }
        }

        unset($data['business_name'], $data['trading_name']);

        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        $company->update($data);
        $freshCompany = $company->fresh();

        // Flush all tenant bootstrap, navigation, profile, and settings caches immediately
        $tenantId = (string) $company->id;
        Cache::forget("tenant_{$tenantId}_bootstrap");
        Cache::forget("tenant_{$tenantId}_navigation");
        Cache::forget("tenant_{$tenantId}_profile");
        Cache::forget("tenant_{$tenantId}_settings");
        Cache::forget("tenant_nav_{$tenantId}");
        Cache::forget("company_{$tenantId}");
        Cache::forget("tenant_executive_kpis_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_drawer");
        Cache::forget("tenant_{$tenantId}_drawer_menu");
        Cache::forget("navigation_menu_{$tenantId}");
        Cache::forget("store_profile_{$tenantId}");
        Cache::forget("tenant_store_profile_{$tenantId}");
        Cache::forget("drawer_menu_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_menu");
        $freshCompany->flushTenantCaches();

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'store-profile']);

        $drawerHeader = $freshCompany->getDrawerHeaderPayload();

        return response()->json([
            'success' => true,
            'message' => 'Store profile updated successfully.',
            'store_name' => $freshCompany->display_name,
            'business_name' => $freshCompany->display_name,
            'tenant_name' => $freshCompany->display_name,
            'title' => $freshCompany->display_name,
            'trading_name' => $freshCompany->getEffectiveTradeName(),
            'trade_name' => $freshCompany->getEffectiveTradeName(),
            'display_name' => $freshCompany->display_name,
            'header' => $drawerHeader,
            'drawer_header' => $drawerHeader,
            'company' => $freshCompany,
            'tenant' => [
                'id' => (string) $freshCompany->id,
                'name' => $freshCompany->display_name,
                'business_name' => $freshCompany->display_name,
                'tenant_name' => $freshCompany->display_name,
                'title' => $freshCompany->display_name,
                'trade_name' => $freshCompany->getEffectiveTradeName(),
                'trading_name' => $freshCompany->getEffectiveTradeName(),
                'display_name' => $freshCompany->display_name,
                'store_name' => $freshCompany->display_name,
                'header' => $drawerHeader,
                'drawer_header' => $drawerHeader,
            ],
        ]);
    }
}
