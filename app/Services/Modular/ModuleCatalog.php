<?php

namespace App\Services\Modular;

use App\Models\PlatformSystem;
use App\Models\SduiModule;

/**
 * Resolves the storefront metadata (price, currency, buy button) for a package
 * module. Values come from the module manifest (`features.catalog`, written at
 * install time) and can be overridden per-slug by a SuperAdmin via the
 * `platform_system` key `module_catalog` (JSON: { "<slug>": { ...overrides } }).
 */
class ModuleCatalog
{
    /**
     * @return array{price: float, currency: string, buy_item_id: ?string, buy_enabled: bool}
     */
    public static function for(string $slug): array
    {
        $defaults = [
            'price' => 0.0,
            'currency' => strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD'))),
            'buy_item_id' => null,
            'buy_enabled' => false,
        ];

        $module = SduiModule::query()->where('slug', $slug)->first();
        $manifest = $module ? (array) (($module->features['catalog'] ?? [])) : [];

        $override = [];
        $raw = PlatformSystem::get('module_catalog');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($decoded) && isset($decoded[$slug]) && is_array($decoded[$slug])) {
            $override = $decoded[$slug];
        }

        $merged = array_merge($defaults, array_intersect_key($manifest, $defaults), array_intersect_key($override, $defaults));

        $merged['price'] = round((float) $merged['price'], 2);
        $merged['currency'] = strtoupper((string) $merged['currency']) ?: $defaults['currency'];
        $merged['buy_item_id'] = $merged['buy_item_id'] !== null ? (string) $merged['buy_item_id'] : null;
        // A price with no explicit opt-out is buyable; an explicit flag wins.
        $merged['buy_enabled'] = array_key_exists('buy_enabled', $override)
            ? (bool) $override['buy_enabled']
            : (array_key_exists('buy_enabled', $manifest) ? (bool) $manifest['buy_enabled'] : $merged['price'] > 0);

        return $merged;
    }
}
