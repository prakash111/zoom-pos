<?php

namespace App\Services\Modular;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;
use Illuminate\Support\Facades\File;

/**
 * Storefront metadata for package modules — the list the SuperAdmin → Modules
 * screen shows so an operator can buy / install a vertical.
 *
 * Sources, merged in order:
 *   1. modules bundled in this build   (module-packages/<slug>/module.json)
 *   2. the vendor catalog              (License Manager GET /api/v1/catalog)
 *   3. per-slug override               (platform_system `module_catalog`)
 *
 * There is no in-app checkout — buying links out to the vendor's hosted
 * `buy.php`.
 */
class ModuleCatalog
{
    /**
     * Everything sellable, whether or not it is installed here.
     *
     * @return list<array{slug:string,name:string,description:?string,price:float,currency:string,bundled:bool,store_link:?string}>
     */
    public static function available(): array
    {
        $currency = strtoupper((string) (PlatformSystem::get('platform_default_currency') ?: config('app.currency', 'USD')));
        $rows = [];

        // 1. bundled in this build
        $root = base_path('module-packages');
        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $dir) {
                $manifest = $dir.'/module.json';
                if (! File::exists($manifest)) {
                    continue;
                }
                $m = json_decode(File::get($manifest), true) ?: [];
                $slug = strtolower((string) ($m['key'] ?? basename($dir)));
                $rows[$slug] = [
                    'slug' => $slug,
                    'name' => (string) ($m['name'] ?? $slug),
                    'description' => $m['description'] ?? null,
                    'price' => isset($m['price']) ? (float) $m['price'] : 0.0,
                    'currency' => strtoupper((string) ($m['currency'] ?? $currency)),
                    'bundled' => true,
                ];
            }
        }

        // 2. vendor catalog (adds price / non-bundled products)
        foreach (app(LicenseService::class)->catalog() as $p) {
            $slug = strtolower((string) ($p['slug'] ?? ''));
            if ($slug === '' || $slug === 'core') {
                continue;
            }
            $rows[$slug] = array_merge($rows[$slug] ?? ['bundled' => false], [
                'slug' => $slug,
                'name' => $p['name'] ?: ($rows[$slug]['name'] ?? $slug),
                'description' => $p['description'] ?? ($rows[$slug]['description'] ?? null),
                'price' => (float) ($p['price'] ?? ($rows[$slug]['price'] ?? 0)),
                'currency' => strtoupper((string) ($p['currency'] ?? ($rows[$slug]['currency'] ?? $currency))),
            ]);
            $rows[$slug]['bundled'] = $rows[$slug]['bundled'] ?? false;
        }

        // 3. per-slug override
        $decoded = json_decode((string) PlatformSystem::get('module_catalog'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $slug => $ov) {
                $slug = strtolower((string) $slug);
                if (! isset($rows[$slug]) || ! is_array($ov)) {
                    continue;
                }
                if (isset($ov['price'])) {
                    $rows[$slug]['price'] = (float) $ov['price'];
                }
                if (isset($ov['currency'])) {
                    $rows[$slug]['currency'] = strtoupper((string) $ov['currency']);
                }
            }
        }

        return collect($rows)
            ->map(fn ($r) => $r + ['store_link' => self::storeLink($r['slug'])])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Price / currency for one installed module (manifest-cached), plus its
     * buy link. Used by the "Installed Modules" table.
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
