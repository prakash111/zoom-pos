<?php

namespace App\Services\Navigation;

class NavigationSanitizerService
{
    /**
     * Enforce strictly contiguous zero-indexed arrays for sections, items, and children.
     */
    public static function sanitizeSections(array $sections): array
    {
        $sections = array_values($sections);
        $first = $sections[0] ?? null;

        // Some legacy drawer endpoints pass a flat list of items through the
        // same boundary. Detect it after re-indexing so sparse numeric keys do
        // not make the first node disappear.
        $isFlatItemList = is_array($first)
            && ! array_key_exists('items', $first)
            && (array_key_exists('key', $first) || array_key_exists('id', $first));
        if ($isFlatItemList) {
            return self::sanitizeItems($sections);
        }

        $sanitized = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $section['items'] = self::sanitizeItems($section['items'] ?? []);

            if (array_key_exists('children', $section)) {
                $section['children'] = self::sanitizeItems($section['children']);
            }
            if (array_key_exists('sub_items', $section)) {
                $section['sub_items'] = self::sanitizeItems($section['sub_items']);
            }
            if (isset($section['first_item']) && is_array($section['first_item'])) {
                $section['first_item'] = self::sanitizeItem($section['first_item']);
            }

            $sanitized[] = $section;
        }

        return self::deduplicateStorefrontFromStoreSettings($sanitized);
    }

    /**
     * Re-index a collection of navigation items and all descendant children.
     *
     * @return list<array<string, mixed>>
     */
    public static function sanitizeItems(mixed $items, bool $ensureChildren = true): array
    {
        if (! is_array($items)) {
            return [];
        }

        $sanitized = [];
        foreach (array_values($items) as $item) {
            if (is_array($item)) {
                $sanitized[] = self::sanitizeItem($item, $ensureChildren);
            }
        }

        return $sanitized;
    }

    /**
     * Normalize one item without relying on its incoming PHP array keys.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function sanitizeItem(array $item, bool $ensureChildren = true): array
    {
        if ($ensureChildren || array_key_exists('children', $item)) {
            $item['children'] = self::sanitizeItems($item['children'] ?? []);
        }

        if (array_key_exists('is_external_url', $item)) {
            $item['is_external_url'] = (bool) $item['is_external_url'];
        }
        if (array_key_exists('url', $item) && $item['url'] !== null) {
            $item['url'] = (string) $item['url'];
        }
        if (array_key_exists('badge', $item) && $item['badge'] !== null) {
            $item['badge'] = (string) $item['badge'];
        }

        return $item;
    }

    /**
     * Backwards-compatible name for callers that sanitize child collections.
     *
     * @return list<array<string, mixed>>
     */
    public static function sanitizeChildren(mixed $children): array
    {
        return self::sanitizeItems($children);
    }

    /**
     * Normalize an entire navigation payload or nav config structure.
     */
    public static function normalizeNavPayload(array $payload): array
    {
        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $payload['sections'] = array_values($payload['sections']);
        }
        if (isset($payload['items']) && is_array($payload['items'])) {
            // `items` is the legacy flat index and intentionally omits
            // children; only re-index children when the field already exists.
            $payload['items'] = self::sanitizeItems($payload['items'], false);
        }
        if (isset($payload['tree']) && is_array($payload['tree'])) {
            $payload['tree'] = self::sanitizeSections($payload['tree']);
        }

        return $payload;
    }

    /**
     * Deduplicate storefront items from Store Settings, retaining them solely
     * inside the dedicated "sec_storefront" ("Storefront & Online Sales") section.
     * Also strips lingering legacy eCommerce storefront items from inventory.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function deduplicateStorefrontFromStoreSettings(array $sections): array
    {
        $hasStorefrontSection = false;
        $storefrontItemKeys = [
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
            'nav_storefront_domain',
            'nav_storefront_inquiries',
            'nav_view_live_store',
        ];
        $storefrontRoutes = [
            'tenant.settings.storefront',
            'tenant.settings.payments',
            'tenant.settings.coupons',
            'tenant.settings.faqs',
            'tenant.settings.reviews',
            '/settings/storefront/banner',
            '/settings/storefront/payments',
            '/settings/storefront/coupons',
            '/settings/storefront/faqs',
            '/settings/storefront/reviews',
            '/settings/storefront/domain',
            '/storefront/inquiries',
            '/api/tenant/views/settings-storefront',
            '/api/tenant/views/settings-payments',
            '/api/tenant/views/settings-coupons',
            '/api/tenant/views/settings-faqs',
            '/api/tenant/views/settings-reviews',
            '/api/tenant/views/settings-storefront-domain',
            '/api/tenant/views/storefront-inquiries',
        ];
        $storefrontComponents = [
            'settings_storefront',
            'settings_payments',
            'settings_coupons',
            'settings_faqs',
            'settings_reviews',
            'store_domain',
            'store_inquiries',
        ];

        // Gather all keys, routes, components from the active sec_storefront section
        foreach ($sections as $section) {
            $sId = strtolower((string) ($section['id'] ?? $section['key'] ?? ''));
            if ($sId === 'sec_storefront') {
                $hasStorefrontSection = true;
                foreach (($section['items'] ?? []) as $item) {
                    $k = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
                    if ($k !== '') {
                        $storefrontItemKeys[] = $k;
                    }
                    $comp = strtolower(trim((string) ($item['component'] ?? '')));
                    if ($comp !== '') {
                        $storefrontComponents[] = $comp;
                    }
                    $route = strtolower(rtrim((string) ($item['route'] ?? ''), '/'));
                    if ($route !== '') {
                        $storefrontRoutes[] = $route;
                    }
                    $endpoint = strtolower(rtrim((string) ($item['target_endpoint'] ?? ''), '/'));
                    if ($endpoint !== '') {
                        $storefrontRoutes[] = $endpoint;
                    }
                }
            }
        }

        $storefrontItemKeys = array_unique(array_filter($storefrontItemKeys));
        $storefrontRoutes = array_unique(array_filter($storefrontRoutes));
        $storefrontComponents = array_unique(array_filter($storefrontComponents));

        $isStorefrontItem = function (array $item) use ($storefrontItemKeys, $storefrontRoutes, $storefrontComponents): bool {
            $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
            if ($key !== '' && in_array($key, $storefrontItemKeys, true)) {
                return true;
            }
            $comp = strtolower(trim((string) ($item['component'] ?? '')));
            if ($comp !== '' && in_array($comp, $storefrontComponents, true)) {
                return true;
            }
            $route = strtolower(rtrim((string) ($item['route'] ?? ''), '/'));
            if ($route !== '' && in_array($route, $storefrontRoutes, true)) {
                return true;
            }
            $endpoint = strtolower(rtrim((string) ($item['target_endpoint'] ?? ''), '/'));
            if ($endpoint !== '' && in_array($endpoint, $storefrontRoutes, true)) {
                return true;
            }
            $label = strtolower(trim((string) ($item['label'] ?? $item['title'] ?? '')));
            if ($label === 'ecommerce storefront & website'
                || $label === 'storefront banner & auth'
                || $label === 'storefront payment gateways'
                || $label === 'store faqs & help center'
                || $label === 'product ratings & reviews'
                || $label === 'coupons & discounts') {
                return true;
            }

            return false;
        };

        foreach ($sections as &$section) {
            $sId = strtolower((string) ($section['id'] ?? $section['key'] ?? ''));
            if ($sId === 'sec_storefront') {
                continue; // Preserve everything inside the dedicated Storefront section
            }

            if (isset($section['items']) && is_array($section['items'])) {
                $filteredItems = [];
                foreach ($section['items'] as $item) {
                    $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
                    $label = strtolower(trim((string) ($item['label'] ?? $item['title'] ?? '')));

                    // Strip legacy catalog labeled 'eCommerce Storefront & Website'
                    if ($key === 'catalog' && str_contains($label, 'storefront')) {
                        continue;
                    }
                    if ($key === 'ecommerce_storefront_website') {
                        continue;
                    }

                    // Check if this item is Store Settings (key 'settings' or 'store_settings')
                    $isStoreSettings = in_array($key, ['settings', 'store_settings'], true) || $label === 'store settings';
                    if ($isStoreSettings) {
                        if (isset($item['children']) && is_array($item['children'])) {
                            $item['children'] = array_values(array_filter($item['children'], function ($child) use ($isStorefrontItem) {
                                return ! $isStorefrontItem($child);
                            }));
                        }
                        if (isset($item['sub_items']) && is_array($item['sub_items'])) {
                            $item['sub_items'] = array_values(array_filter($item['sub_items'], function ($child) use ($isStorefrontItem) {
                                return ! $isStorefrontItem($child);
                            }));
                        }
                    } elseif ($hasStorefrontSection && $isStorefrontItem($item)) {
                        // Flat duplicate row in administration or other sections
                        continue;
                    }

                    $filteredItems[] = $item;
                }
                $section['items'] = array_values($filteredItems);
            }
        }
        unset($section);

        return $sections;
    }
}
