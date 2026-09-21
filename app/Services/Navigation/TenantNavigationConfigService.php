<?php

namespace App\Services\Navigation;

/**
 * Normalizes tenant navigation payloads from both clients.
 *
 * Older clients send a flat `items` list with `parent`; the WordPress-style
 * editors send a recursive `tree` with `parent_id`, `level`, sibling `order`,
 * and `children`. We persist both a canonical flat index (cheap for existing
 * renderers) and the canonical recursive tree (lossless for tree editors).
 */
class TenantNavigationConfigService
{
    public const MAX_LEVEL = 2;

    /**
     * Platform items whose parent is fixed by the registry and must never be
     * re-homed by a stored override or a drag-happy tree editor. Every "Store
     * Settings" tab is always a direct child of the `settings` accordion —
     * see TenantNavRegistry::settingsTabItems(). Keyed child => required parent.
     *
     * @var array<string, string>
     */
    public const FORCED_PARENTS = [
        'settings_mode' => 'settings',
        'settings_profile' => 'settings',
        'settings_branding' => 'settings',
        'settings_receipts' => 'settings',
        'settings_financial' => 'settings',
        'settings_taxes' => 'settings',
        'settings_api' => 'settings',
        'settings_navigation' => 'settings',
        'nav_view_live_store' => 'nav_storefront_group',
        'nav_storefront_domain' => 'nav_storefront_group',
        'nav_storefront_menus' => 'nav_storefront_group',
        'nav_storefront_inquiries' => 'nav_storefront_group',
        'nav_storefront_banner_auth' => 'nav_storefront_group',
        'nav_storefront_gateways' => 'nav_storefront_group',
        'nav_coupons_discounts' => 'nav_storefront_group',
        'nav_store_faqs' => 'nav_storefront_group',
        'nav_store_reviews' => 'nav_storefront_group',
    ];

    /**
     * Items that must stay at the top of their section (Main Menu). `settings`
     * is an accordion container: if it is nested under another row, its own
     * tabs land at level 3 and get clamped. Keep it a first-class parent.
     *
     * @var list<string>
     */
    public const FORCED_ROOT = [
        'settings',
        // Point of Sale and Consignments are always independent root commerce links.
        'pos',
        'pharmacy_pos',
        'salon_pos',
        'restaurant_pos',
        'consignments',
        'nav_storefront_group',
        'group_storefront',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function validationRules(): array
    {
        $rules = [
            'sections' => ['nullable', 'array'],
            'sections.*.key' => ['required', 'string', 'max:60'],
            'sections.*.order' => ['nullable', 'integer', 'min:0'],
            'sections.*.custom_title' => ['nullable', 'string', 'max:120'],
            'items' => ['nullable', 'array'],
            'items.*.key' => ['required', 'string', 'max:60'],
            'items.*.section' => ['nullable', 'string', 'max:60'],
            'items.*.parent' => ['nullable', 'string', 'max:60'],
            'items.*.parent_id' => ['nullable', 'string', 'max:60'],
            'items.*.level' => ['nullable', 'integer', 'between:0,2'],
            'items.*.order' => ['nullable', 'integer', 'min:0'],
            'items.*.visible' => ['required_with:items', 'boolean'],
            'tree' => ['nullable', 'array'],
            'tree.*.key' => ['required', 'string', 'max:60'],
            'tree.*.order' => ['nullable', 'integer', 'min:0'],
            'tree.*.custom_title' => ['nullable', 'string', 'max:120'],
            'tree.*.items' => ['required', 'array'],
        ];

        foreach (['tree.*.items.*', 'tree.*.items.*.children.*', 'tree.*.items.*.children.*.children.*'] as $path) {
            $rules[$path.'.key'] = ['required', 'string', 'max:60'];
            $rules[$path.'.title'] = ['nullable', 'string', 'max:120'];
            $rules[$path.'.label'] = ['nullable', 'string', 'max:120'];
            $rules[$path.'.parent_id'] = ['nullable', 'string', 'max:60'];
            $rules[$path.'.level'] = ['nullable', 'integer', 'between:0,2'];
            $rules[$path.'.order'] = ['nullable', 'integer', 'min:0'];
            $rules[$path.'.visible'] = ['required', 'boolean'];
            $rules[$path.'.children'] = ['nullable', 'array'];
        }
        $rules['tree.*.items.*.children.*.children.*.children'] = ['nullable', 'array', 'max:0'];

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sections: list<array{key: string, order: int}>, items: list<array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>, tree: list<array{key: string, order: int, items: list<array<string, mixed>>}>}
     */
    public function normalize(array $payload): array
    {
        $tree = $this->treeFrom($payload);
        $sections = $this->normalizeSections($payload, $tree);
        $items = $tree !== []
            ? $this->flattenTree($tree)
            : $this->normalizeFlatItems(is_array($payload['items'] ?? null) ? $payload['items'] : []);

        $items = $this->repairHierarchy($items);
        $sections = $this->appendMissingSections($sections, $items);
        $canonicalTree = $this->buildTree($sections, $items);
        $canonicalItems = $this->flattenTree($canonicalTree);

        // Legacy hidden-only overrides have no section, so they cannot be
        // represented inside the section tree. Keep them in the flat index
        // after the canonical pre-order rows instead of dropping them.
        foreach ($items as $key => $item) {
            if ($item['section'] === null && ! isset($canonicalItems[$key])) {
                $canonicalItems[$key] = $item;
            }
        }

        return [
            'sections' => $sections,
            'items' => array_values($canonicalItems),
            'tree' => $canonicalTree,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function treeFrom(array $payload): array
    {
        if (is_array($payload['tree'] ?? null) && $payload['tree'] !== []) {
            return array_values(array_filter($payload['tree'], 'is_array'));
        }

        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        return array_values(array_filter(
            $sections,
            fn ($section) => is_array($section) && array_key_exists('items', $section)
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $tree
     * @return list<array{key: string, order: int, custom_title?: string}>
     */
    private function normalizeSections(array $payload, array $tree): array
    {
        $source = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        if ($source === [] && $tree !== []) {
            $source = $tree;
        }

        $treeByKey = [];
        foreach ($tree as $treeSection) {
            if (! is_array($treeSection)) {
                continue;
            }
            $treeKey = trim((string) ($treeSection['key'] ?? ''));
            if ($treeKey !== '') {
                $treeByKey[$treeKey] = $treeSection;
            }
        }

        $sections = [];
        foreach ($source as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || isset($sections[$key])) {
                continue;
            }

            $treeSection = $treeByKey[$key] ?? null;
            $customTitle = trim((string) ($row['custom_title'] ?? ''));
            if ($customTitle === '') {
                $customTitle = trim((string) ($treeSection['custom_title'] ?? ''));
            }
            if ($customTitle === '' && ! empty($treeSection['items'])) {
                $first = $treeSection['items'][0];
                $customTitle = trim((string) ($first['title'] ?? $first['label'] ?? ''));
            }

            $sections[$key] = ['key' => $key, 'order' => max(0, (int) ($row['order'] ?? $index))];
            if ($customTitle !== '') {
                $sections[$key]['custom_title'] = $customTitle;
            }
        }

        uasort($sections, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($sections);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>
     */
    private function flattenTree(array $tree): array
    {
        $items = [];
        foreach ($tree as $section) {
            $sectionKey = trim((string) ($section['key'] ?? ''));
            if ($sectionKey === '') {
                continue;
            }
            $nodes = is_array($section['items'] ?? null) ? $section['items'] : [];
            $this->flattenNodes($nodes, $sectionKey, null, 0, $items);
        }

        return $items;
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>  $items
     */
    private function flattenNodes(array $nodes, string $section, ?string $parent, int $level, array &$items, ?string $clampAnchor = null): void
    {
        foreach (array_values($nodes) as $index => $node) {
            if (! is_array($node)) {
                continue;
            }
            $key = trim((string) ($node['key'] ?? ''));
            if ($key === '' || isset($items[$key])) {
                continue;
            }

            if ($level <= self::MAX_LEVEL) {
                $safeLevel = max(0, $level);
                $safeParent = $parent;
            } else {
                // A chain deeper than Main -> Sub -> Sub-Sub (a drag-happy
                // editor can nest items arbitrarily deep). Pin the overflow to
                // the nearest ancestor that still fits, as a Sub-Sub-Menu —
                // never eject it to the top level, which is what silently
                // moved "API & Integrations" out of Store Settings.
                $safeLevel = self::MAX_LEVEL;
                $safeParent = $clampAnchor;
            }

            $items[$key] = [
                'key' => $key,
                'section' => $section,
                'parent' => $safeParent,
                'parent_id' => $safeParent,
                'level' => $safeLevel,
                'order' => max(0, (int) ($node['order'] ?? $index)),
                'visible' => (bool) ($node['visible'] ?? true),
            ];

            // The deepest still-valid ancestor a clamped descendant may attach to.
            $childAnchor = $safeLevel <= self::MAX_LEVEL - 1 ? $key : $clampAnchor;

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $this->flattenNodes($children, $section, $key, $safeLevel + 1, $items, $childAnchor);
        }
    }

    /**
     * @param  list<mixed>  $source
     * @return array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>
     */
    private function normalizeFlatItems(array $source): array
    {
        $items = [];
        foreach ($source as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || isset($items[$key])) {
                continue;
            }
            $section = isset($row['section']) && trim((string) $row['section']) !== '' ? trim((string) $row['section']) : null;
            $parentValue = array_key_exists('parent_id', $row) ? $row['parent_id'] : ($row['parent'] ?? null);
            $parent = $parentValue !== null && trim((string) $parentValue) !== '' ? trim((string) $parentValue) : null;
            $items[$key] = [
                'key' => $key,
                'section' => $section,
                'parent' => $parent,
                'parent_id' => $parent,
                'level' => 0,
                'order' => isset($row['order']) ? max(0, (int) $row['order']) : null,
                'visible' => (bool) ($row['visible'] ?? true),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>  $items
     * @return array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>
     */
    private function repairHierarchy(array $items): array
    {
        // Snap registry-pinned items back into place before any other repair.
        // This heals a stored tree the mobile editor mangled (e.g. "Store
        // Settings" dropped under "Subscription & Billing", "Taxes &
        // Compliance" under "Financial & Currency", or "API & Integrations"
        // ejected to the top level): "settings" is a first-class parent and
        // every Store Settings tab is its direct child.
        foreach (self::FORCED_ROOT as $rootKey) {
            if (isset($items[$rootKey])) {
                $items[$rootKey]['parent'] = null;
                $items[$rootKey]['parent_id'] = null;
                $items[$rootKey]['level'] = 0;
            }
        }

        foreach (self::FORCED_PARENTS as $childKey => $parentKey) {
            if (! isset($items[$childKey], $items[$parentKey])) {
                continue;
            }
            if ($items[$childKey]['section'] !== $items[$parentKey]['section']) {
                continue;
            }
            $items[$childKey]['parent'] = $parentKey;
            $items[$childKey]['parent_id'] = $parentKey;
        }

        // Break missing/cross-section/self links, cycles, and parent chains
        // deeper than Main Menu -> Sub-Menu -> Sub-Sub-Menu. The pass is
        // deterministic: only the item currently being inspected is
        // promoted, so no valid sibling branch is discarded with it.
        foreach (array_keys($items) as $key) {
            $parent = $items[$key]['parent'];
            if ($parent === null) {
                continue;
            }

            $visited = [$key => true];
            $depth = 0;
            while ($parent !== null) {
                if (! isset($items[$parent])
                    || isset($visited[$parent])
                    || $items[$parent]['section'] !== $items[$key]['section']) {
                    $items[$key]['parent'] = $items[$key]['parent_id'] = null;
                    break;
                }

                $visited[$parent] = true;
                $depth++;
                if ($depth > self::MAX_LEVEL) {
                    $items[$key]['parent'] = $items[$key]['parent_id'] = null;
                    break;
                }
                $parent = $items[$parent]['parent'];
            }
        }

        // All remaining chains are valid and at most two links long.
        foreach (array_keys($items) as $key) {
            $level = 0;
            $parent = $items[$key]['parent'];
            while ($parent !== null && isset($items[$parent]) && $level < self::MAX_LEVEL) {
                $level++;
                $parent = $items[$parent]['parent'];
            }
            $items[$key]['level'] = $level;
            $items[$key]['parent_id'] = $items[$key]['parent'];
        }

        return $items;
    }

    /**
     * @param  list<array{key: string, order: int, custom_title?: string}>  $sections
     * @param  array<string, array{section: ?string}>  $items
     * @return list<array{key: string, order: int}>
     */
    private function appendMissingSections(array $sections, array $items): array
    {
        $known = array_fill_keys(array_column($sections, 'key'), true);
        foreach ($items as $item) {
            $key = $item['section'];
            if ($key === null || isset($known[$key])) {
                continue;
            }
            $known[$key] = true;
            $sections[] = ['key' => $key, 'order' => count($sections)];
        }

        return $sections;
    }

    /**
     * @param  list<array{key: string, order: int, custom_title?: string}>  $sections
     * @param  array<string, array{key: string, section: ?string, parent: ?string, parent_id: ?string, level: int, order: ?int, visible: bool}>  $items
     * @return list<array{key: string, order: int, custom_title?: string, items: list<array<string, mixed>>}>
     */
    private function buildTree(array $sections, array $items): array
    {
        $childrenByParent = [];
        foreach ($items as $key => $item) {
            $bucket = $item['section'].'|'.($item['parent'] ?? '');
            $childrenByParent[$bucket][$key] = $item;
        }

        $sortRows = static function (array &$rows): void {
            uasort($rows, static function ($a, $b) {
                $orderA = $a['order'] ?? PHP_INT_MAX;
                $orderB = $b['order'] ?? PHP_INT_MAX;

                return $orderA <=> $orderB;
            });
        };

        $buildNodes = function (string $section, ?string $parent) use (&$buildNodes, &$childrenByParent, $sortRows): array {
            $bucket = $section.'|'.($parent ?? '');
            $rows = $childrenByParent[$bucket] ?? [];
            $sortRows($rows);

            return array_values(array_map(function ($row) use (&$buildNodes, $section) {
                $row['children'] = $buildNodes($section, $row['key']);

                return $row;
            }, $rows));
        };

        return array_values(array_map(function ($section) use ($buildNodes): array {
            $treeSection = [
                'key' => $section['key'],
                'order' => $section['order'],
                'items' => $buildNodes($section['key'], null),
            ];
            if (! empty($section['custom_title'])) {
                $treeSection['custom_title'] = $section['custom_title'];
            } elseif (! empty($treeSection['items'])) {
                $first = $treeSection['items'][0];
                $firstTitle = trim((string) ($first['title'] ?? $first['label'] ?? ''));
                if ($firstTitle !== '') {
                    $treeSection['custom_title'] = $firstTitle;
                }
            }

            return $treeSection;
        }, $sections));
    }
}
