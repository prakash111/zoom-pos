<?php

namespace App\Services\Localization;

use App\Models\TenantTranslation;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Translation\FileLoader;

class TenantTranslationLoader implements Loader
{
    /**
     * Request-level cache of tenant overrides: [tenantId:locale:group] => array
     */
    protected static array $tenantCache = [];

    public function __construct(
        protected FileLoader $fileLoader
    ) {}

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        // 1. Load base translations from file system (system files, /lang/{locale}.json, etc.)
        $lines = $this->fileLoader->load($locale, $group, $namespace);

        // 2. If this is a specific vendor namespace other than default, return base lines
        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        // 3. Resolve active tenant ID
        $tenantId = $this->resolveActiveTenantId();
        if (! $tenantId) {
            return $lines;
        }

        // 4. Retrieve tenant overrides for this tenant, locale, and group
        $overrides = $this->getTenantOverrides($tenantId, (string) $locale, (string) $group);

        if (! empty($overrides)) {
            $lines = array_replace($lines, $overrides);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->fileLoader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->fileLoader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->fileLoader->namespaces();
    }

    public function addPath($path)
    {
        $this->fileLoader->addPath($path);
    }

    public function paths()
    {
        return $this->fileLoader->paths();
    }

    public function jsonPaths()
    {
        return $this->fileLoader->jsonPaths();
    }

    public function getFileLoader(): FileLoader
    {
        return $this->fileLoader;
    }

    /**
     * Resolve the active tenant identifier.
     */
    public function resolveActiveTenantId(): ?string
    {
        if (app()->bound('tenant.company_id')) {
            $id = app('tenant.company_id');
            if (! empty($id)) {
                return (string) $id;
            }
        }

        $user = auth('web')->user();
        if ($user && ! empty($user->company_id)) {
            return (string) $user->company_id;
        }

        return null;
    }

    /**
     * Get tenant custom translations from database or request-level cache.
     */
    public function getTenantOverrides(string $tenantId, string $locale, string $group = '*'): array
    {
        $cacheKey = "{$tenantId}:{$locale}:{$group}";

        if (isset(self::$tenantCache[$cacheKey])) {
            return self::$tenantCache[$cacheKey];
        }

        try {
            $overrides = TenantTranslation::where('tenant_id', $tenantId)
                ->where('locale', $locale)
                ->where('group', $group)
                ->pluck('value', 'key')
                ->toArray();

            self::$tenantCache[$cacheKey] = $overrides;

            return $overrides;
        } catch (\Throwable) {
            // Database not ready or table missing
            return [];
        }
    }

    /**
     * Flush in-memory tenant cache.
     */
    public static function flushCache(?string $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$tenantCache = [];
        } else {
            foreach (array_keys(self::$tenantCache) as $key) {
                if (str_starts_with($key, "{$tenantId}:")) {
                    unset(self::$tenantCache[$key]);
                }
            }
        }
    }

    public function __call(string $method, array $arguments)
    {
        return $this->fileLoader->{$method}(...$arguments);
    }
}
