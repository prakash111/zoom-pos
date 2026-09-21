<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DynamicSetting;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Models\PushNotificationSetting;
use App\Services\Localization\LocalizationService;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\NavigationSanitizerService;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaResponse;
use App\Services\Tenancy\TenantSampleDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            // Same platform-wide "Auto-Seed Demo Data on Signup" toggle that
            // gates seeding at registration time (TenantProvisioningService)
            // — a tenant that skipped seeding there must not get silently
            // seeded the moment its app first calls bootstrap either.
            if (filter_var(PlatformSystem::get('auto_seed_demo_data_on_registration', true), FILTER_VALIDATE_BOOLEAN)) {
                app(TenantSampleDataService::class)->seed($company, $company->pos_mode ?: 'general');
                $company->refresh();
            } else {
                $company->update(['is_seeding_complete' => true]);
            }
        }

        $locale = strtolower(trim((string) $request->query('locale', '')));
        if ($locale === '' || ! $localization->isValidLocale($locale)) {
            $locale = $company->default_locale ?: ($company->language ?: 'en');
        }

        $activeMode = strtolower(trim(ModuleRegistry::resolveActiveMode($company)));
        $allModules = ModuleRegistry::modulesFor($company);
        $availableModes = ModuleRegistry::availableModes($company);
        $activeModule = ModuleRegistry::getModule($activeMode);
        $menuStructure = NavigationSanitizerService::sanitizeSections(
            TenantNavRegistry::getEffectiveNavForTenant($company)
        );
        $navigationConfigVersion = sha1((string) json_encode($menuStructure));

        $translationVersion = $localization->translationVersion($locale, $company->id);
        $effectiveTradeName = $company->getEffectiveTradeName();
        $drawerHeader = $company->getDrawerHeaderPayload();

        $businessType = strtoupper($company->pos_mode ?: 'RETAIL');
        $activeFeatures = ModuleRegistry::activeFeaturesFor($company);

        $pushConfig = ['enabled' => false];
        try {
            $pushConfig = PushNotificationSetting::current()->publicConfig($company->id);
        } catch (\Throwable $e) {
            Log::warning("Bootstrap push config failure: {$e->getMessage()}");
        }

        $defaultCountryIso = PlatformRegionalService::defaultCountryIso();
        $defaultDialCode = PlatformRegionalService::defaultDialCode();
        $defaultCurrency = PlatformRegionalService::defaultCurrency();
        $defaultLanguage = PlatformRegionalService::defaultLanguage();
        $defaultTimezone = PlatformRegionalService::defaultTimezone();

        $localizationPayload = [
            'default_currency' => $defaultCurrency,
            'default_language' => $defaultLanguage,
            'default_timezone' => $defaultTimezone,
            'default_country_code' => $defaultDialCode,
            'default_country_iso' => $defaultCountryIso,
        ];

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'localization' => $localizationPayload,
            'system_info' => $localizationPayload,
            'header' => $drawerHeader,
            'drawer_header' => $drawerHeader,
            'store_name' => $company->display_name,
            'business_name' => $company->display_name,
            'tenant_name' => $company->display_name,
            'title' => $company->display_name,
            'display_name' => $company->display_name,
            'business_type' => $businessType,
            'plan_features' => $activeFeatures,
            'features' => $activeFeatures,
            'tenant' => [
                'id' => (string) $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'tenant_name' => $company->display_name,
                'title' => $company->display_name,
                'trade_name' => $effectiveTradeName,
                'trading_name' => $effectiveTradeName,
                'display_name' => $company->display_name,
                'store_name' => $company->display_name,
                'slug' => (string) $company->slug,
                'subdomain' => (string) ($company->subdomain ?: $company->slug),
                'custom_domain' => $company->custom_domain,
                'storefront_url' => $company->getStorefrontUrl(),
                'country' => $company->country ?? $defaultCountryIso,
                'default_country_code' => $defaultDialCode,
                'default_country_iso' => $defaultCountryIso,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'currency_decimals' => (int) ($company->currency_decimals ?? 2),
                'currency_symbol_position' => $company->currency_symbol_position ?? 'prefix',
                'timezone' => $company->resolveTimezone(),
                'language' => $company->language ?? 'en',
                'default_locale' => $company->default_locale ?: ($company->language ?: 'en'),
                'active_mode' => $activeMode,
                'available_modes' => $availableModes,
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'is_seeding_complete' => (bool) $company->is_seeding_complete,
                'navigation_labels' => $company->navigation_labels ?? new \stdClass,
                'form_field_customizations' => $company->form_field_customizations ?? new \stdClass,
                'drawer_header' => $drawerHeader,
                'header' => $drawerHeader,
                'logo_url' => $company->getLogoUrl(),
                'logo' => $company->getLogoUrl(),
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
                'favicon_url' => $company->getFaviconUrl(),
            ],
            'company' => [
                'id' => (string) $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'tenant_name' => $company->display_name,
                'title' => $company->display_name,
                'trade_name' => $effectiveTradeName,
                'trading_name' => $effectiveTradeName,
                'display_name' => $company->display_name,
                'store_name' => $company->display_name,
                'slug' => (string) $company->slug,
                'subdomain' => (string) ($company->subdomain ?: $company->slug),
                'custom_domain' => $company->custom_domain,
                'storefront_url' => $company->getStorefrontUrl(),
                'country' => $company->country ?? $defaultCountryIso,
                'default_country_code' => $defaultDialCode,
                'default_country_iso' => $defaultCountryIso,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'currency_decimals' => (int) ($company->currency_decimals ?? 2),
                'currency_symbol_position' => $company->currency_symbol_position ?? 'prefix',
                'timezone' => $company->resolveTimezone(),
                'language' => $company->language ?? 'en',
                'default_locale' => $company->default_locale ?: ($company->language ?: 'en'),
                'active_mode' => $activeMode,
                'available_modes' => $availableModes,
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'is_seeding_complete' => (bool) $company->is_seeding_complete,
                'navigation_labels' => $company->navigation_labels ?? new \stdClass,
                'form_field_customizations' => $company->form_field_customizations ?? new \stdClass,
                'drawer_header' => $drawerHeader,
                'header' => $drawerHeader,
                'logo_url' => $company->getLogoUrl(),
                'logo' => $company->getLogoUrl(),
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
                'favicon_url' => $company->getFaviconUrl(),
            ],
            'slug' => (string) $company->slug,
            'subdomain' => (string) ($company->subdomain ?: $company->slug),
            'custom_domain' => $company->custom_domain,
            'storefront_url' => $company->getStorefrontUrl(),
            'navigation_labels' => $company->navigation_labels ?? new \stdClass,
            'form_field_customizations' => $company->form_field_customizations ?? new \stdClass,
            'modules' => $allModules,
            'active_module' => $activeModule,
            // Zero-touch feature/store-type contract for the mobile app: purely
            // derived from currently licensed + active modules.
            'active_features' => $activeFeatures,
            'store_types' => $availableModes,
            'menu_structure' => $menuStructure,
            'navigation' => $menuStructure,
            'navigation_schema_version' => 2,
            'navigation_config_version' => $navigationConfigVersion,
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
            'nav' => NavigationSanitizerService::normalizeNavPayload($company->normalizedNavConfig()),
            'push' => PushNotificationSetting::current()->publicConfig($company->id),
            'config' => [
                'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $effectiveTradeName,
                'trading_name' => $effectiveTradeName,
                'store_name' => $company->display_name,
                'display_name' => $company->display_name,
                'country' => $company->country ?? $defaultCountryIso,
                'default_country_code' => $defaultDialCode,
                'default_country_iso' => $defaultCountryIso,
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
                'drawer_header' => $drawerHeader,
                'support_phone' => PlatformBranding::current()->support_phone ?: '+918535075196',
                'support_whatsapp' => PlatformBranding::current()->support_phone ?: '+918535075196',
                'support_email' => PlatformBranding::current()->support_email ?: 'support@zoomnearby.com',
                'auth_banner_image_url' => DynamicSetting::get('auth_banner_image_url') ?: null,
                'show_auth_banner' => (bool) DynamicSetting::get('show_auth_banner', false),
            ],
            // Reserved for tenant-wide status/announcement banners.
            'messages' => [],
        ])->header('ETag', '"'.$translationVersion.'-'.$navigationConfigVersion.'"');
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

        $validated = $validator->validated();
        if (empty($validated['sections']) || (empty($validated['items']) && empty($validated['tree']))) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid menu configuration.',
            ], 422);
        }

        $navConfig = $navigation->normalize($validated);
        $company->forceFill([
            'nav_config' => $navConfig,
            'navigation_menu_customization' => $navConfig['tree'],
        ])->saveOrFail();

        $companyIdStr = (string) $company->id;
        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $companyIdStr, 'key' => 'navigation_menu_custom'],
                [
                    'value' => json_encode($navConfig['tree']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Cache::forget("tenant_{$companyIdStr}_drawer_menu");
        Cache::forget("navigation_menu_{$companyIdStr}");

        // Return the value read through the same cast/normalizer used by the
        // next bootstrap request. This makes a failed database round-trip
        // visible immediately instead of optimistically echoing the payload.
        $persistedNav = $company->fresh()->normalizedNavConfig();

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'nav_config']);

        return response()->json(['success' => true, 'message' => 'Navigation menu updated.', 'nav' => $persistedNav]);
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
