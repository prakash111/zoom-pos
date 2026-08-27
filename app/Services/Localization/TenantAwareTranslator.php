<?php

namespace App\Services\Localization;

use Illuminate\Translation\Translator;

class TenantAwareTranslator extends Translator
{
    /**
     * The tenant ID for which translations are currently cached in $this->loaded.
     */
    protected ?string $loadedTenantId = null;

    /**
     * Resolve the active tenant identifier.
     */
    protected function resolveCurrentTenantId(): ?string
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
     * Ensure the translator's loaded lines correspond to the currently active tenant.
     */
    public function ensureTenantContext(): void
    {
        $currentTenantId = $this->resolveCurrentTenantId();
        if ($this->loadedTenantId !== $currentTenantId) {
            $this->loaded = [];
            $this->loadedTenantId = $currentTenantId;
        }
    }

    /**
     * Flush all loaded in-memory translations and tenant state.
     */
    public function flushLoaded(): void
    {
        $this->loaded = [];
        $this->loadedTenantId = null;
        TenantTranslationLoader::flushCache();
    }

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $this->ensureTenantContext();

        return parent::get($key, $replace, $locale, $fallback);
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        $this->ensureTenantContext();

        return parent::choice($key, $number, $replace, $locale);
    }

    public function hasForLocale($key, $locale = null)
    {
        $this->ensureTenantContext();

        return parent::hasForLocale($key, $locale);
    }

    public function has($key, $locale = null, $fallback = true)
    {
        $this->ensureTenantContext();

        return parent::has($key, $locale, $fallback);
    }
}
