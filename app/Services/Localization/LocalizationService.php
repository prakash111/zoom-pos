<?php

namespace App\Services\Localization;

use App\Models\Company;
use App\Models\Language;
use App\Models\TenantTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class LocalizationService
{
    /**
     * Default catalog of platform languages with flags and directions.
     */
    protected array $defaultLanguages = [
        ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸', 'direction' => 'ltr', 'is_default' => true],
        ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'flag' => '🇫🇷', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'flag' => '🇩🇪', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag' => '🇸🇦', 'direction' => 'rtl', 'is_default' => false],
        ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'flag' => '🇮🇳', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'flag' => '🇧🇷', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'flag' => '🇮🇹', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'flag' => '🇨🇳', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'flag' => '🇯🇵', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'flag' => '🇮🇩', 'direction' => 'ltr', 'is_default' => false],
        ['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'flag' => '🇹🇷', 'direction' => 'ltr', 'is_default' => false],
    ];

    /**
     * Ensure default language catalog is populated in database.
     */
    public function ensureDefaultLanguages(): void
    {
        try {
            if (Language::count() === 0) {
                foreach ($this->defaultLanguages as $lang) {
                    Language::create($lang);
                }
            }
        } catch (\Throwable) {
            // DB not ready or testing before migrate
        }
    }

    /**
     * Get all active languages.
     */
    public function getActiveLanguages(): Collection
    {
        $this->ensureDefaultLanguages();
        try {
            return Language::where('is_active', true)->orderBy('name')->get();
        } catch (\Throwable) {
            return collect([]);
        }
    }

    /**
     * Get all languages (active and inactive).
     */
    public function getAllLanguages(): Collection
    {
        $this->ensureDefaultLanguages();
        try {
            return Language::orderBy('name')->get();
        } catch (\Throwable) {
            return collect([]);
        }
    }

    /**
     * Determine active interface language following strict priority levels without cross-tenant bleed:
     *
     * Priority 1 (User Selection):
     *   - User's explicitly chosen locale from session (Session::get('locale'))
     *   - User's saved profile preference (users.locale)
     *
     * Priority 2 (Store Primary Language):
     *   - Tenant store's default primary language (companies.default_locale or companies.language)
     *
     * Priority 3 (System Global Fallback):
     *   - Application default fallback (config('app.fallback_locale') or config('app.locale'))
     */
    public function getActiveLocale(): string
    {
        // Priority 1a: User's explicitly chosen locale saved in session
        if (Session::has('locale')) {
            $sessionLocale = Session::get('locale');
            if (! empty($sessionLocale) && is_string($sessionLocale) && $this->isValidLocale($sessionLocale)) {
                return strtolower(trim($sessionLocale));
            }
        }

        // Priority 1b: User's explicitly chosen profile locale
        $user = auth('web')->user() ?? auth('platform_web')->user() ?? auth('tenant_api')->user();
        if ($user && ! empty($user->locale) && is_string($user->locale) && $this->isValidLocale($user->locale)) {
            return strtolower(trim($user->locale));
        }

        // Priority 2: Store primary language
        $company = $this->resolveActiveCompany($user);
        if ($company) {
            $storeLocale = $company->default_locale ?: $company->language;
            if (! empty($storeLocale) && is_string($storeLocale) && $this->isValidLocale($storeLocale)) {
                return strtolower(trim($storeLocale));
            }
        }

        // Priority 3: System global fallback
        $fallback = config('app.fallback_locale') ?: config('app.locale', 'en');

        return ! empty($fallback) ? (string) $fallback : 'en';
    }

    /**
     * Resolve currently active company/tenant model.
     */
    public function resolveActiveCompany($user = null): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $tenantId = app('tenant.company_id');
            if (! empty($tenantId)) {
                try {
                    return Company::withoutGlobalScopes()->find($tenantId);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        if ($user && isset($user->company_id) && ! empty($user->company_id)) {
            try {
                return $user->company ?? Company::withoutGlobalScopes()->find($user->company_id);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Validate if a locale code is known/supported.
     */
    public function isValidLocale(string $locale): bool
    {
        $clean = strtolower(trim($locale));
        if (empty($clean)) {
            return false;
        }

        $this->ensureDefaultLanguages();

        try {
            if (Language::where('code', $clean)->where('is_active', true)->exists()) {
                return true;
            }
        } catch (\Throwable) {
            // DB not ready
        }

        return in_array($clean, ['en', 'es', 'fr', 'de', 'ar', 'hi', 'pt', 'it', 'zh', 'ja', 'ru', 'id', 'tr', 'nl'], true);
    }

    /**
     * Get the active language model.
     */
    public function getActiveLanguage(): ?Language
    {
        $code = $this->getActiveLocale();
        $this->ensureDefaultLanguages();
        try {
            return Language::where('code', $code)->first() ?? Language::where('is_default', true)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check if active locale is RTL.
     */
    public function isRtl(): bool
    {
        $lang = $this->getActiveLanguage();

        return $lang ? $lang->isRtl() : false;
    }

    /**
     * Switch current user's locale (updates session and individual user profile).
     * Does NOT alter the store's primary language for other staff accounts.
     */
    public function setLocale(string $locale): void
    {
        $clean = strtolower(trim($locale));
        $this->ensureDefaultLanguages();

        $lang = Language::where('code', $clean)->where('is_active', true)->first();
        if (! $lang) {
            $clean = 'en';
        }

        Session::put('locale', $clean);
        App::setLocale($clean);

        // Update the authenticated user's profile locale if available
        $user = auth('web')->user() ?? auth('platform_web')->user();
        if ($user) {
            try {
                $user->update(['locale' => $clean]);
            } catch (\Throwable) {
                // Ignore DB update errors
            }
        }

        $this->flushTranslator();
    }

    /**
     * Flush all translation loader and translator in-memory caches.
     */
    public function flushTranslator(?string $tenantId = null): void
    {
        TenantTranslationLoader::flushCache($tenantId);

        $translator = app('translator');
        if ($translator instanceof TenantAwareTranslator) {
            $translator->flushLoaded();
        }
    }

    /**
     * Get file path for a system language JSON file.
     */
    public function getLanguageFilePath(string $locale): string
    {
        return base_path("lang/{$locale}.json");
    }

    /**
     * Read system translation dictionary from JSON file.
     */
    public function getLanguageFileContent(string $locale): array
    {
        $path = $this->getLanguageFilePath($locale);

        if (! File::exists($path)) {
            // If file doesn't exist, create an empty one or duplicate from English template
            $enPath = $this->getLanguageFilePath('en');
            if (File::exists($enPath)) {
                $enContent = json_decode(File::get($enPath), true) ?: [];
                $this->saveLanguageFileContent($locale, $enContent);

                return $enContent;
            }

            return [];
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Save system translation dictionary to JSON file (SuperAdmin only).
     */
    public function saveLanguageFileContent(string $locale, array $translations): bool
    {
        $path = $this->getLanguageFilePath($locale);
        $dir = dirname($path);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        ksort($translations, SORT_NATURAL | SORT_FLAG_CASE);

        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = File::put($path, $json) !== false;
        $this->flushTranslator();

        return $result;
    }

    /**
     * Add a new translation key to a locale or globally across all locales (SuperAdmin only).
     */
    public function addTranslationKey(string $key, ?string $value = null, ?string $locale = null): void
    {
        $cleanKey = trim($key);
        if (empty($cleanKey)) {
            throw new RuntimeException('Translation key cannot be empty.');
        }

        $locales = $locale ? [$locale] : Language::pluck('code')->toArray();

        foreach ($locales as $loc) {
            $content = $this->getLanguageFileContent($loc);
            if (! array_key_exists($cleanKey, $content)) {
                $content[$cleanKey] = $value ?? $cleanKey;
                $this->saveLanguageFileContent($loc, $content);
            }
        }
    }

    /**
     * Update a specific key-value pair in a system language file (SuperAdmin only).
     */
    public function updateTranslationKey(string $locale, string $key, string $value): void
    {
        $content = $this->getLanguageFileContent($locale);
        $content[$key] = $value;
        $this->saveLanguageFileContent($locale, $content);
    }

    /**
     * Delete a translation key from a system language file (SuperAdmin only).
     */
    public function deleteTranslationKey(string $locale, string $key): void
    {
        $content = $this->getLanguageFileContent($locale);
        unset($content[$key]);
        $this->saveLanguageFileContent($locale, $content);
    }

    /**
     * Synchronize missing keys from base language (usually 'en') into target language.
     */
    public function syncMissingKeys(string $targetLocale, string $sourceLocale = 'en'): int
    {
        $source = $this->getLanguageFileContent($sourceLocale);
        $target = $this->getLanguageFileContent($targetLocale);

        $addedCount = 0;
        foreach ($source as $key => $defaultVal) {
            if (! array_key_exists($key, $target)) {
                $target[$key] = $defaultVal;
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            $this->saveLanguageFileContent($targetLocale, $target);
        }

        return $addedCount;
    }

    /**
     * Create a new custom platform language (SuperAdmin only).
     */
    public function createLanguage(array $data): Language
    {
        $code = strtolower(trim($data['code']));

        if (Language::where('code', $code)->exists()) {
            throw new RuntimeException("Language with code '{$code}' already exists.");
        }

        $language = Language::create([
            'code' => $code,
            'name' => trim($data['name']),
            'native_name' => trim($data['native_name'] ?? $data['name']),
            'flag' => trim($data['flag'] ?? '🌐'),
            'direction' => $data['direction'] ?? 'ltr',
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_default' => false,
        ]);

        // Copy template keys from English
        $this->syncMissingKeys($code, 'en');

        return $language;
    }

    /**
     * Tenant Store Specific Overrides: Get tenant custom translations.
     */
    public function getTenantTranslations(string $tenantId, string $locale, string $group = '*'): array
    {
        try {
            return TenantTranslation::where('tenant_id', $tenantId)
                ->where('locale', $locale)
                ->where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Backward-compatible alias for getTenantTranslations.
     */
    public function getCompanyTranslations(string $companyId, string $locale, string $group = '*'): array
    {
        return $this->getTenantTranslations($companyId, $locale, $group);
    }

    /**
     * Tenant Store Specific Overrides: Save or update tenant translation.
     * Guaranteed to never modify root application translation files.
     */
    public function saveTenantTranslation(string $tenantId, string $locale, string $key, ?string $value, string $group = '*'): void
    {
        if ($value === null || $value === '') {
            TenantTranslation::where('tenant_id', $tenantId)
                ->where('locale', $locale)
                ->where('group', $group)
                ->where('key', $key)
                ->delete();

            if (Schema::hasTable('company_translations')) {
                DB::table('company_translations')
                    ->where('company_id', $tenantId)
                    ->where('locale', $locale)
                    ->where('key', $key)
                    ->delete();
            }
        } else {
            TenantTranslation::updateOrCreate(
                ['tenant_id' => $tenantId, 'locale' => $locale, 'group' => $group, 'key' => $key],
                ['value' => $value]
            );

            if (Schema::hasTable('company_translations')) {
                DB::table('company_translations')->updateOrInsert(
                    ['company_id' => $tenantId, 'locale' => $locale, 'key' => $key],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->flushTranslator($tenantId);
    }

    /**
     * Backward-compatible alias for saveTenantTranslation.
     */
    public function saveCompanyTranslation(string $companyId, string $locale, string $key, ?string $value, string $group = '*'): void
    {
        $this->saveTenantTranslation($companyId, $locale, $key, $value, $group);
    }

    /**
     * Delete a tenant translation override.
     */
    public function deleteTenantTranslation(string $tenantId, string $locale, string $key, string $group = '*'): void
    {
        $this->saveTenantTranslation($tenantId, $locale, $key, null, $group);
    }

    /**
     * Get combined translation dictionary for a tenant (Base JSON + Tenant Overrides).
     */
    public function getMergedTranslations(string $locale, ?string $tenantId = null, string $group = '*'): array
    {
        $base = $this->getLanguageFileContent($locale);

        if (! $tenantId) {
            return $base;
        }

        $overrides = $this->getTenantTranslations($tenantId, $locale, $group);

        return array_merge($base, $overrides);
    }
}
