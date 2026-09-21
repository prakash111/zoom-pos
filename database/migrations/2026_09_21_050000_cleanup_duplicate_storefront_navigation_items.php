<?php

use App\Models\Company;
use App\Services\Navigation\NavigationSanitizerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront keys to strip from Store Settings and other non-storefront sections.
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
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clean companies.nav_config and companies.navigation_menu_customization
        Company::withoutGlobalScopes()->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $dirty = false;
                $updates = [];

                if (! empty($company->nav_config) && is_array($company->nav_config)) {
                    $cleaned = $this->cleanNavPayload($company->nav_config);
                    if ($cleaned !== $company->nav_config) {
                        $updates['nav_config'] = $cleaned;
                        $dirty = true;
                    }
                }

                if (! empty($company->navigation_menu_customization) && is_array($company->navigation_menu_customization)) {
                    $cleanedCustom = $this->cleanSectionsList($company->navigation_menu_customization);
                    if ($cleanedCustom !== $company->navigation_menu_customization) {
                        $updates['navigation_menu_customization'] = $cleanedCustom;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $company->update($updates);
                }

                Cache::forget("navigation_menu_{$company->id}");
            }
        });

        // 2. Clean tenant_settings navigation_menu_custom and navigation_menu
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

                $cleaned = is_array($decoded) && isset($decoded['sections'])
                    ? $this->cleanNavPayload($decoded)
                    : $this->cleanSectionsList($decoded);

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

        // 3. Clear application-wide navigation caches
        Cache::flush();
    }

    /**
     * Clean a structured nav config payload containing sections / tree / items.
     */
    protected function cleanNavPayload(array $payload): array
    {
        if (isset($payload['tree']) && is_array($payload['tree'])) {
            $payload['tree'] = $this->cleanSectionsList($payload['tree']);
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $payload['sections'] = $this->cleanSectionsList($payload['sections']);
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

    /**
     * Clean a list of section definitions.
     */
    protected function cleanSectionsList(array $sections): array
    {
        return NavigationSanitizerService::deduplicateStorefrontFromStoreSettings(
            NavigationSanitizerService::sanitizeSections($sections)
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data sanitation
    }
};
