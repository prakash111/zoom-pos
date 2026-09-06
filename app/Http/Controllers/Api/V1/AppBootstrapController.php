<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PushNotificationSetting;
use App\Services\Localization\LocalizationService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Server-Driven UI (SDUI) Bootstrap Controller.
 *
 * Hydrates client apps (mobile & web) on launch and tenant/mode switch:
 * store configuration, active module schemas, dynamic menus, translations,
 * UI schemas (payment options, status labels, tax rules), and push settings.
 */
class AppBootstrapController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/app/bootstrap?locale=xx or GET /api/v1/pos/app/bootstrap?locale=xx
     */
    public function bootstrap(Request $request, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company->is_seeding_complete) {
            app(\App\Services\Tenancy\TenantSampleDataService::class)->seed($company, $company->pos_mode ?: 'general');
            $company->refresh();
        }

        $locale = strtolower(trim((string) $request->query('locale', '')));
        if ($locale === '' || ! $localization->isValidLocale($locale)) {
            $locale = $company->default_locale ?: ($company->language ?: 'en');
        }

        $activeMode = strtolower(trim(ModuleRegistry::resolveActiveMode($company)));
        $allModules = ModuleRegistry::allModules();
        $availableModes = ModuleRegistry::availableModes($company);
        $activeModule = ModuleRegistry::getModule($activeMode);
        $menuStructure = TenantNavRegistry::getEffectiveNavForTenant($company);

        $translationVersion = $localization->translationVersion($locale, $company->id);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'tenant' => [
                'id' => (string) $company->id,
                'business_name' => $company->trade_name ?? $company->name,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'currency_decimals' => (int) ($company->currency_decimals ?? 2),
                'currency_symbol_position' => $company->currency_symbol_position ?? 'prefix',
                'timezone' => $company->resolveTimezone(),
                'language' => $company->language ?? 'en',
                'default_locale' => $company->default_locale ?: ($company->language ?: 'en'),
                'active_mode' => $activeMode,
                'available_modes' => $availableModes,
                'is_seeding_complete' => (bool) $company->is_seeding_complete,
                'navigation_labels' => $company->navigation_labels ?? new \stdClass(),
            ],
            'navigation_labels' => $company->navigation_labels ?? new \stdClass(),
            'modules' => $allModules,
            'active_module' => $activeModule,
            'menu_structure' => $menuStructure,
            'navigation' => $menuStructure,
            'theme' => $company->getThemeTokens(),
            'screens' => SchemaResponse::screenDirectory($company),
            'schema_contract' => SchemaResponse::contract(),
            'ui_schema' => [
                'payment_methods' => ModuleRegistry::paymentMethodsSchema($company),
                'status_labels' => ModuleRegistry::statusLabelsSchema(),
                'tax_configuration' => ModuleRegistry::taxConfigurationSchema($company),
                'action_pills' => ModuleRegistry::actionPillsSchema($company),
            ],
            'translations' => $localization->getMergedTranslations($locale, $company->id),
            'translations_version' => $translationVersion,
            'nav' => $company->normalizedNavConfig(),
            'push' => PushNotificationSetting::current()->publicConfig(),
            'config' => [
                'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'name' => $company->name,
                'trade_name' => $company->trade_name ?? $company->name,
                'country' => $company->country ?? 'US',
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'currency_decimals' => (int) ($company->currency_decimals ?? 2),
                'currency_symbol_position' => $company->currency_symbol_position ?? 'prefix',
                'timezone' => $company->resolveTimezone(),
                'language' => $company->language ?? 'en',
                'default_locale' => $company->default_locale ?: ($company->language ?: 'en'),
                'tax_id' => $company->tax_id ?? '',
                'tax_label' => $company->tax_label ?? ($company->country === 'IN' ? 'GST' : 'Tax'),
                'logo_url' => $company->getLogoUrl(),
                'favicon_url' => $company->getFaviconUrl(),
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
            ],
            // Reserved for tenant-wide status/announcement banners.
            'messages' => [],
        ])->header('ETag', '"'.$translationVersion.'"');
    }

    /**
     * POST /api/v1/pos/settings/nav-config
     */
    public function updateNav(Request $request, TenantNavigationConfigService $navigation): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), TenantNavigationConfigService::validationRules());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $navConfig = $navigation->normalize($validator->validated());
        $company->update(['nav_config' => $navConfig]);

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'nav_config']);

        return response()->json(['success' => true, 'message' => 'Navigation menu updated.', 'nav' => $navConfig]);
    }

    /**
     * POST /api/app/mode or POST /api/v1/pos/app/mode
     * Self-service mode switching is strictly locked for tenants.
     * Only SuperAdmin can modify operating modes.
     */
    public function switchMode(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Store operating mode is permanently locked. Self-service mode switching is disabled. Only a SuperAdmin can modify the store operating mode.',
        ], 403);
    }
}
