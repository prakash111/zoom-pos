<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TenantSetting;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RolePermissionController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Resolve all normalized enabled module identifiers for a tenant.
     * Order of precedence:
     * 1. tenant_settings table ('enabled_modules')
     * 2. $company->licensed_modules
     * 3. $company->pos_mode / $company->operating_mode
     * 4. Fallback defaults
     *
     * @return list<string>
     */
    public static function resolveTenantEnabledModuleSlugs(mixed $company): array
    {
        if (! $company instanceof Company) {
            $companyId = $company ?: (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if (! $companyId && auth()->check()) {
                $companyId = auth()->user()->company_id ?? auth()->user()->tenant_id;
            }
            $company = $companyId ? Company::find($companyId) : null;
        }

        if (! $company) {
            return ['retail', 'pos', 'sales', 'finance', 'settings', 'users'];
        }

        // 1. tenant_settings table key = 'enabled_modules'
        $settingsModules = TenantSetting::get($company->id, 'enabled_modules', null);
        $raw = null;
        if (is_array($settingsModules) && ! empty($settingsModules)) {
            $raw = $settingsModules;
        } elseif (is_string($settingsModules) && trim($settingsModules) !== '') {
            $decoded = json_decode($settingsModules, true);
            if (is_array($decoded) && ! empty($decoded)) {
                $raw = $decoded;
            }
        }

        // 2. $company->licensed_modules
        if (empty($raw) && is_array($company->licensed_modules) && ! empty($company->licensed_modules)) {
            $raw = $company->licensed_modules;
        }

        // 3. $company->pos_mode / $company->operating_mode
        if (empty($raw)) {
            $raw = array_filter([$company->pos_mode ?: null, $company->operating_mode ?: null]);
        }

        // 4. Default fallback
        if (empty($raw)) {
            $raw = ['retail'];
        }

        // Canonical normalization
        $normalized = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $slug = strtolower(trim($item));
            $canonical = match ($slug) {
                'general', 'general_retail', 'retail' => 'retail',
                'food_restaurant', 'restaurant', 'restaurant_pos' => 'restaurant',
                'pharmacy', 'pharmacy_pos', 'chemist' => 'pharmacy',
                'salon', 'service_booking', 'beauty', 'spa', 'wellness' => 'salon',
                'repair', 'repairs', 'technician', 'repair_technician', 'repairtechnician', 'automotive', 'electronics_service' => 'repairs',
                'service_orders', 'service_order' => 'service_orders',
                'lead', 'leads', 'leadmanagement', 'lead_management' => 'leads',
                'products', 'inventory' => 'inventory',
                'quotes', 'quotations' => 'quotations',
                'consignments', 'consignment' => 'consignments',
                default => $slug,
            };
            $normalized[] = $canonical;
        }

        if ($company->restaurant_mode_locked) {
            $normalized = array_diff($normalized, ['restaurant']);
        }

        // Tenant-editable settings cannot grant access to an optional extension.
        $normalized = array_values(array_diff($normalized, ['leads']));
        if ($company->hasModule('leadmanagement')) {
            $normalized[] = 'leads';
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Helper to get tenant enabled module slugs by tenant ID.
     *
     * @return list<string>
     */
    public static function getTenantEnabledModuleSlugs(string|int $tenantId): array
    {
        $company = Company::find($tenantId);

        return self::resolveTenantEnabledModuleSlugs($company);
    }

    /**
     * Filter PermissionChecker::MODULES by tenant's active/enabled modules.
     *
     * @return array<string, string>
     */
    public static function getFilteredModulesForTenant(mixed $company): array
    {
        $enabled = self::resolveTenantEnabledModuleSlugs($company);

        $hasRetail = in_array('retail', $enabled, true);
        $hasRestaurant = in_array('restaurant', $enabled, true);
        $hasPharmacy = in_array('pharmacy', $enabled, true);
        $hasSalon = in_array('salon', $enabled, true);
        $hasRepair = in_array('repairs', $enabled, true) || in_array('repair', $enabled, true);
        $hasLeads = in_array('leads', $enabled, true);
        $hasConsignments = in_array('consignments', $enabled, true) || $hasRetail;
        $hasQuotes = in_array('quotations', $enabled, true) || in_array('quotes', $enabled, true) || $hasRetail;
        $hasInventory = in_array('inventory', $enabled, true) || in_array('products', $enabled, true) || $hasRetail;

        $filtered = [];
        foreach (PermissionChecker::MODULES as $slug => $label) {
            // Core management & store modules are always visible for every tenant
            if (in_array($slug, ['sales', 'finance', 'settings', 'users', 'customers', 'cash_register', 'reports', 'targets'], true)) {
                $filtered[$slug] = $label;

                continue;
            }

            // POS terminal is visible for retail, restaurant, pharmacy, salon, repair, or explicit pos
            if ($slug === 'pos') {
                if ($hasRetail || $hasRestaurant || $hasPharmacy || $hasSalon || $hasRepair || in_array('pos', $enabled, true)) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Products / Inventory & related cataloging
            if (in_array($slug, ['products', 'categories', 'units', 'suppliers'], true)) {
                if ($hasRetail || $hasInventory || $hasPharmacy || $hasSalon || $hasRepair || $hasRestaurant) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Digital catalog
            if ($slug === 'catalog') {
                if ($hasRetail || in_array('catalog', $enabled, true)) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Consignments
            if ($slug === 'consignments') {
                if ($hasConsignments) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Quotes & Proposals
            if ($slug === 'quotes') {
                if ($hasQuotes) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Repair & Service Orders
            if (in_array($slug, ['repair', 'service_orders'], true)) {
                if ($hasRepair) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Leads
            if ($slug === 'leads') {
                if ($hasLeads) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Restaurant
            if ($slug === 'restaurant') {
                if ($hasRestaurant) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Pharmacy
            if ($slug === 'pharmacy') {
                if ($hasPharmacy) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Salon
            if ($slug === 'salon') {
                if ($hasSalon) {
                    $filtered[$slug] = $label;
                }

                continue;
            }

            // Any other custom modules if explicitly enabled
            if (in_array($slug, $enabled, true)) {
                $filtered[$slug] = $label;
            }
        }

        return $filtered;
    }

    /**
     * GET /api/v1/tenant/roles/schema
     * GET /api/tenant/roles/schema
     * GET /api/roles/schema
     * GET /api/v1/tenant/permissions
     * GET /api/tenant/permissions
     * GET /api/permissions
     */
    public function getPermissionsSchema(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $tenantId = (string) $company->id;
        $cacheKey = "tenant_{$tenantId}_role_permissions";

        if ($request->boolean('nocache') || $request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 3600, function () use ($company) {
            $filteredModules = self::getFilteredModulesForTenant($company);
            $permissionGroups = [];

            foreach ($filteredModules as $slug => $label) {
                $actions = [];
                foreach (PermissionChecker::getActionsForModule($slug) as $actionSlug => $actionLabel) {
                    $actions[] = [
                        'slug' => $actionSlug,
                        'label' => $actionLabel,
                        'field_key' => "perm__{$slug}__{$actionSlug}",
                    ];
                }
                $permissionGroups[] = [
                    'module' => $slug,
                    'slug' => $slug,
                    'label' => $label,
                    'actions' => $actions,
                ];
            }

            return [
                'success' => true,
                'tenant_id' => (string) $company->id,
                'company_name' => $company->name,
                'enabled_modules' => self::resolveTenantEnabledModuleSlugs($company),
                'permission_groups' => $permissionGroups,
                'modules' => $permissionGroups,
            ];
        });

        return response()->json($data);
    }
}
