<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Localization\LocalizationService;
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

        $navConfig = $company->nav_config ?? [];

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'translations' => $localization->getMergedTranslations($locale, $company->id),
            'nav' => [
                'hidden_tiles' => array_values($navConfig['hidden_tiles'] ?? []),
                'section_order' => array_values($navConfig['section_order'] ?? []),
            ],
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
     * Persists which nav destinations this tenant hides from the mobile
     * drawer/rail/bars and what order its section groups render in. Both
     * arrays are opaque tile/section keys owned by the mobile client (see
     * _FeatureTile.key / _NavSection.key in dashboard_screen.dart) — this
     * endpoint doesn't validate them against a fixed list so new client
     * versions can introduce keys without a server round-trip first.
     */
    public function updateNav(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'hidden_tiles' => ['nullable', 'array'],
            'hidden_tiles.*' => ['string', 'max:60'],
            'section_order' => ['nullable', 'array'],
            'section_order.*' => ['string', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $navConfig = [
            'hidden_tiles' => array_values(array_unique($request->input('hidden_tiles', []))),
            'section_order' => array_values(array_unique($request->input('section_order', []))),
        ];
        $company->update(['nav_config' => $navConfig]);

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'nav_config']);

        return response()->json(['success' => true, 'message' => 'Navigation menu updated.', 'nav' => $navConfig]);
    }
}
