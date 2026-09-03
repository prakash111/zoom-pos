<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PushNotificationSetting;
use App\Services\Localization\LocalizationService;
use App\Services\Navigation\TenantNavigationConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Single mobile-app cold-start call: the active locale's merged translation
 * dictionary, this tenant's nav customization (hidden destinations/section
 * order), and the handful of company config values the app otherwise had to
 * fetch from several endpoints separately. Nothing here is cached
 * server-side beyond normal Eloquent/query caching — the mobile client is
 * responsible for its own on-disk cache (see TranslationsCache/BootstrapCache)
 * so the app stays usable offline between calls.
 */
class AppBootstrapController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/v1/pos/app/bootstrap?locale=xx
     */
    public function bootstrap(Request $request, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $locale = strtolower(trim((string) $request->query('locale', '')));
        if ($locale === '' || ! $localization->isValidLocale($locale)) {
            $locale = $company->default_locale ?: ($company->language ?: 'en');
        }

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'translations' => $localization->getMergedTranslations($locale, $company->id),
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
                'tax_id' => $company->tax_id ?? '',
                'logo_url' => $company->getLogoUrl(),
                'favicon_url' => $company->getFaviconUrl(),
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
            ],
            // Reserved for tenant-wide status/announcement banners — no
            // authoring surface exists yet, so this is always empty today.
            'messages' => [],
        ]);
    }

    /**
     * POST /api/v1/pos/settings/nav-config
     *
     * Persists this tenant's nav customization: which section groups exist
     * and in what order, and — for every item — which section it's placed
     * in (letting an item move to a different section than it defaults
     * to), its order within that section, and whether it's hidden. `key`/
     * `section` are opaque tile/section keys owned by the client (see
     * _FeatureTile.key / _NavSection.key in dashboard_screen.dart and
     * ALL_DOCK_ITEMS on web) — this endpoint doesn't validate them against
     * a fixed list so new client versions can introduce keys without a
     * server round-trip first. Shared by both the mobile app and the web
     * tenant Settings > Navigation Menu tab.
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
}
