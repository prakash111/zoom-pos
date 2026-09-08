<?php

namespace App\Services\Modular;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;

/**
 * Storefront metadata for a package module — price, currency, and the external
 * URL where an operator buys it from the vendor's License Manager.
 *
 * Values come from the vendor catalog (License Manager `/api/v1/catalog`, when a
 * license server is configured), overlaid by the module manifest
 * (`features.catalog`) and finally a per-slug `platform_system` `module_catalog`
 * override. There is no in-app checkout — the link goes to the vendor's
 * hosted `buy.php`.
 */
class ModuleCatalog
{
    /**
     * @return array{price: float, currency: string, description: ?string, buy_enabled: bool}
     */
    public static function for(string $slug): array
    {
        $defaults = [
            'price' => 0.0,
            'currency' => strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD'))),
            'description' => null,
            'buy_enabled' => false,
        ];

        // 1. vendor catalog
        $remote = [];
        foreach (app(LicenseService::class)->catalog() as $p) {
            if (($p['slug'] ?? null) === $slug) {
                $remote = array_intersect_key($p, $defaults);
                break;
            }
        }

        // 2. manifest features.catalog
        $module = SduiModule::query()->where('slug', $slug)->first();
        $manifest = $module ? array_intersect_key((array) ($module->features['catalog'] ?? []), $defaults) : [];

        // 3. platform_system override
        $override = [];
        $raw = PlatformSystem::get('module_catalog');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($decoded) && is_array($decoded[$slug] ?? null)) {
            $override = array_intersect_key($decoded[$slug], $defaults);
        }

        $merged = array_merge($defaults, $remote, $manifest, $override);
        $merged['price'] = round((float) $merged['price'], 2);
        $merged['currency'] = strtoupper((string) $merged['currency']) ?: $defaults['currency'];

        $explicit = $override['buy_enabled'] ?? $manifest['buy_enabled'] ?? null;
        $merged['buy_enabled'] = $explicit !== null
            ? (bool) $explicit
            : ($merged['price'] > 0 && app(LicenseService::class)->storeUrl() !== '');

        return $merged;
    }

    /**
     * Full "Buy this module" link into the vendor's hosted checkout, or null
     * when no store URL is configured.
     */
    public static function storeLink(string $slug): ?string
    {
        $store = app(LicenseService::class)->storeUrl();
        if ($store === '') {
            return null;
        }

        return $store.'?'.http_build_query([
            'product' => $slug,
            'domain' => LicenseService::currentDomain(),
            'return' => route('superadmin.modules.index'),
        ]);
    }
}
