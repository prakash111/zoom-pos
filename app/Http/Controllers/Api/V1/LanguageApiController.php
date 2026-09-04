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
 * The store-default-language slice of app/Livewire/Tenant/Languages/Index.php
 * (LocalizationService::getActiveLanguages + Company::default_locale). The
 * per-phrase translation override editor on that page is a desktop power-user
 * tool with hundreds of keys — intentionally out of scope for mobile.
 */
class LanguageApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $languages = $localization->getActiveLanguages()->map(fn ($l) => [
            'code' => $l->code,
            'name' => $l->name,
            'native_name' => $l->native_name,
            'flag' => $l->flag,
            'direction' => $l->direction ?: 'ltr',
        ]);

        return response()->json([
            'success' => true,
            'default_language' => $company->default_locale ?: ($company->language ?: 'en'),
            'languages' => $languages,
        ]);
    }

    /**
     * Full merged translation dictionary (base language JSON + this
     * tenant's phrase overrides) for one locale — lets the mobile app fetch
     * language packs on demand instead of bundling every locale's full
     * catalog in the APK. GET /api/v1/pos/languages/translations/{locale}
     */
    public function translations(Request $request, string $locale, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $clean = strtolower(trim($locale));
        if (! $localization->isValidLocale($clean)) {
            return response()->json(['success' => false, 'error' => 'Unsupported locale.'], 404);
        }

        $version = $localization->translationVersion($clean, $company->id);

        return response()->json([
            'success' => true,
            'locale' => $clean,
            'version' => $version,
            'translations' => $localization->getMergedTranslations($clean, $company->id),
        ])->header('ETag', '"'.$version.'"');
    }

    /**
     * Version-aware language pack endpoint used by architecture-agnostic
     * clients. GET /api/app/translations?lang=xx&version=sha256
     */
    public function appTranslations(Request $request, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $clean = strtolower(trim((string) $request->query('lang', $request->query('locale', ''))));
        if ($clean === '') {
            $clean = $company->default_locale ?: ($company->language ?: 'en');
        }
        if (! $localization->isValidLocale($clean)) {
            return response()->json(['success' => false, 'error' => 'Unsupported locale.'], 404);
        }

        $version = $localization->translationVersion($clean, $company->id);
        $clientVersion = trim((string) $request->query('version', ''));
        $response = [
            'success' => true,
            'locale' => $clean,
            'version' => $version,
            'etag' => $version,
            'not_modified' => $clientVersion !== '' && hash_equals($version, $clientVersion),
        ];
        if (! $response['not_modified']) {
            $response['translations'] = $localization->getMergedTranslations($clean, $company->id);
        }

        return response()->json($response)->header('ETag', '"'.$version.'"');
    }

    public function setDefault(Request $request, LocalizationService $localization): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), ['locale' => ['required', 'string', 'max:10']]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $locale = $request->input('locale');
        $company->update(['default_locale' => $locale, 'language' => $locale]);
        $localization->setLocale($locale);

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'default_language', 'locale' => $locale]);

        return response()->json(['success' => true, 'message' => 'Store default language updated.', 'default_language' => $locale]);
    }
}
