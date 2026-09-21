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

        return $sanitized;
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
}
