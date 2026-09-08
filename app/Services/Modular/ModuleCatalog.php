<?php

namespace App\Services\Modular;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;

/**
 * Storefront metadata for package modules — the list the SuperAdmin → Modules
 * screen shows so an operator can buy / fetch a vertical.
 *
 * The catalog comes entirely from the vendor's License Manager
 * (`GET /api/v1/catalog`); the module source files also live only there and are
 * downloaded once a license key verifies. A per-slug `platform_system`
 * `module_catalog` override can adjust price / currency locally.
 */
class ModuleCatalog
{
    /**
     * Everything sellable, whether or not it is installed here.
     *
     * @return list<array{slug:string,name:string,description:?string,price:float,currency:string,store_link:?string}>
     */
    public static function available(): array
    {
        $currency = strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD')));

        $decoded = json_decode((string) PlatformSystem::get('module_catalog'), true);
        $override = is_array($decoded) ? $decoded : [];

        $rows = [];
        foreach (app(LicenseService::class)->catalog() as $p) {
            $slug = strtolower((string) ($p['slug'] ?? ''));
            if ($slug === '' || $slug === 'core') {
                continue;
            }

            $ov = is_array($override[$slug] ?? null) ? $override[$slug] : [];

            $rows[] = [
                'slug' => $slug,
                'name' => (string) ($p['name'] ?? $slug),
                'description' => $p['description'] ?? null,
                'price' => round((float) ($ov['price'] ?? $p['price'] ?? 0), 2),
                'currency' => strtoupper((string) ($ov['currency'] ?? $p['currency'] ?? $currency)),
                'store_link' => self::storeLink($slug),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Price / currency / description for one installed module. Used by the
     * "Installed Modules" table.
     *
     * @return array{price: float, currency: string, description: ?string, store_link: ?string}
     */
    public static function for(string $slug): array
    {
        $currency = strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD')));
        $module = SduiModule::query()->where('slug', $slug)->first();
        $c = $module ? (array) ($module->features['catalog'] ?? []) : [];

        foreach (self::available() as $row) {
            if ($row['slug'] === $slug) {
                $c += ['price' => $row['price'], 'currency' => $row['currency'], 'description' => $row['description']];
                break;
            }
        }

        return [
            'price' => round((float) ($c['price'] ?? 0), 2),
            'currency' => strtoupper((string) ($c['currency'] ?? $currency)),
            'description' => $c['description'] ?? null,
            'store_link' => self::storeLink($slug),
        ];
    }

    /**
     * Link into the vendor's hosted checkout, or null when no store URL is set.
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
