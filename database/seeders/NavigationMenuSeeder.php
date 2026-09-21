<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Navigation\NavigationSanitizerService;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NavigationMenuSeeder extends Seeder
{
    /**
     * Duplicate storefront item keys to filter out from Store Settings.
     */
    protected array $storefrontKeys = [
        'storefront_banner_auth',
        'storefront_payment_gateways',
        'coupons_discounts',
        'store_faqs',
        'product_ratings_reviews',
        'ecommerce_storefront_website',
        'settings_storefront',
        'settings_payments',
        'settings_coupons',
        'settings_faqs',
        'settings_reviews',
        'nav_storefront_banner_auth',
        'nav_storefront_gateways',
        'nav_coupons_discounts',
        'nav_store_faqs',
        'nav_store_reviews',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Sanitize all existing company navigation menu customizations
        Company::withoutGlobalScopes()->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $dirty = false;
                $updates = [];

                if (! empty($company->nav_config) && is_array($company->nav_config)) {
                    $cleaned = $this->cleanPayload($company->nav_config);
                    if ($cleaned !== $company->nav_config) {
                        $updates['nav_config'] = $cleaned;
                        $dirty = true;
                    }
                }

                if (! empty($company->navigation_menu_customization) && is_array($company->navigation_menu_customization)) {
                    $cleanedSections = NavigationSanitizerService::deduplicateStorefrontFromStoreSettings(
                        NavigationSanitizerService::sanitizeSections($company->navigation_menu_customization)
                    );
                    if ($cleanedSections !== $company->navigation_menu_customization) {
                        $updates['navigation_menu_customization'] = $cleanedSections;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $company->update($updates);
                }

                Cache::forget("navigation_menu_{$company->id}");
            }
        });

        // 2. Sanitize tenant_settings entries
        if (Schema::hasTable('tenant_settings')) {
            $settings = DB::table('tenant_settings')
                ->whereIn('key', ['navigation_menu_custom', 'navigation_menu'])
                ->get();

            foreach ($settings as $setting) {
                if (empty($setting->value)) {
                    continue;
                }

                $decoded = json_decode($setting->value, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $cleaned = isset($decoded['sections']) || isset($decoded['tree'])
                    ? $this->cleanPayload($decoded)
                    : NavigationSanitizerService::deduplicateStorefrontFromStoreSettings(
                        NavigationSanitizerService::sanitizeSections($decoded)
                    );

                if ($cleaned !== $decoded) {
                    DB::table('tenant_settings')
                        ->where('id', $setting->id)
                        ->update([
                            'value' => json_encode($cleaned),
                            'updated_at' => now(),
                        ]);
                }

                Cache::forget("navigation_menu_{$setting->tenant_id}");
            }
        }

        // 3. Clear application-wide cache
        Cache::flush();
    }

    /**
     * Clean a nav config payload.
     */
    protected function cleanPayload(array $payload): array
    {
        if (isset($payload['tree']) && is_array($payload['tree'])) {
            $payload['tree'] = NavigationSanitizerService::deduplicateStorefrontFromStoreSettings(
                NavigationSanitizerService::sanitizeSections($payload['tree'])
            );
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $payload['sections'] = NavigationSanitizerService::deduplicateStorefrontFromStoreSettings(
                NavigationSanitizerService::sanitizeSections($payload['sections'])
            );
        }

        if (isset($payload['items']) && is_array($payload['items'])) {
            $payload['items'] = array_values(array_filter($payload['items'], function ($item) {
                if (! is_array($item)) {
                    return true;
                }
                $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
                $parent = strtolower(trim((string) ($item['parent'] ?? $item['parent_id'] ?? '')));
                if (in_array($parent, ['settings', 'store_settings'], true) && in_array($key, $this->storefrontKeys, true)) {
                    return false;
                }
                if ($key === 'catalog' && str_contains(strtolower((string) ($item['label'] ?? '')), 'storefront')) {
                    return false;
                }
                if ($key === 'ecommerce_storefront_website') {
                    return false;
                }
                return true;
            }));
        }

        return $payload;
    }
}
