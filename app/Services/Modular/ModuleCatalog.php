<?php

namespace App\Services\Modular;

use App\Models\PlatformSystem;
use App\Models\SduiModule;

/**
 * Storefront metadata for a package module — price, currency, and the external
 * URL where an operator buys it from the vendor. Values come from the module
 * manifest (`features.catalog`, written at install time) and can be overridden
 * per-slug via the `platform_system` key `module_catalog`
 * (JSON: { "<slug>": { "price": …, "buy_url": …, "buy_enabled": false } }).
 *
 * There is no in-app checkout — `buy_url` is a link out to the vendor's store.
 */
class ModuleCatalog
{
    /**
     * @return array{price: float, currency: string, buy_url: ?string, buy_enabled: bool}
     */
    public static function for(string $slug): array
    {
        $defaults = [
            'price' => 0.0,
            'currency' => strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD'))),
            'buy_url' => null,
            'buy_enabled' => false,
        ];

        $module = SduiModule::query()->where('slug', $slug)->first();
        $manifest = $module ? (array) ($module->features['catalog'] ?? []) : [];

        $override = [];
        $raw = PlatformSystem::get('module_catalog');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($decoded) && isset($decoded[$slug]) && is_array($decoded[$slug])) {
            $override = $decoded[$slug];
        }

        $merged = array_merge($defaults, array_intersect_key($manifest, $defaults), array_intersect_key($override, $defaults));

        $merged['price'] = round((float) $merged['price'], 2);
        $merged['currency'] = strtoupper((string) $merged['currency']) ?: $defaults['currency'];

        // buy_url: explicit manifest/override value wins; otherwise fall back to
        // the vendor storefront with the slug as a query param.
        $buyUrl = $merged['buy_url'] !== null ? trim((string) $merged['buy_url']) : '';
        if ($buyUrl === '' && ($store = trim((string) config('services.license_server.store_url')))) {
            $buyUrl = $store.(str_contains($store, '?') ? '&' : '?').'module='.urlencode($slug);
        }
        $merged['buy_url'] = $buyUrl !== '' ? $buyUrl : null;

        // A price with no explicit opt-out is buyable; an explicit flag wins.
        $merged['buy_enabled'] = array_key_exists('buy_enabled', $override)
            ? (bool) $override['buy_enabled']
            : (array_key_exists('buy_enabled', $manifest) ? (bool) $manifest['buy_enabled'] : $merged['price'] > 0);

        return $merged;
    }
}
