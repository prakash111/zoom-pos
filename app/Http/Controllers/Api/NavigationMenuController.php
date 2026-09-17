<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NavigationMenuController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Persist navigation menu customization to database and invalidate cache.
     * Prioritizes tenant_settings and keeps Company nav_config synchronized.
     */
    public function saveMenuSettings(Request $request): JsonResponse
    {
        $tenantId = null;
        $company = null;
        $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();

        if ($user) {
            $tenantId = $user->tenant_id ?? $user->company_id;
        }

        try {
            $company = $this->resolveCompany($request);
            if ($company) {
                $tenantId = $company->id ?? $tenantId;
            }
        } catch (\Throwable) {
            if ($tenantId) {
                $company = Company::find($tenantId);
            }
        }

        if (! $company && $tenantId) {
            $company = Company::where('id', (string) $tenantId)
                ->orWhere('slug', (string) $tenantId)
                ->orWhere('unique_account_id', (string) $tenantId)
                ->first();
            if ($company) {
                $tenantId = $company->id;
            }
        }

        $sections = $request->input('sections', []);
        $items = $request->input('items', []);
        $tree = $request->input('tree', []);

        // Allow sections, tree, or items payload
        if (empty($sections) && empty($tree) && empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid menu configuration.',
            ], 422);
        }

        $normalizer = app(TenantNavigationConfigService::class);
        $navConfig = $normalizer->normalize($request->all());

        if ($tenantId) {
            $tenantIdStr = (string) $tenantId;
            // Persist as JSON string to tenant_settings
            if (Schema::hasTable('tenant_settings')) {
                DB::table('tenant_settings')->updateOrInsert(
                    ['tenant_id' => $tenantIdStr, 'key' => 'navigation_menu_custom'],
                    [
                        'value'      => json_encode(! empty($sections) ? $sections : $navConfig['tree']),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // Invalidate runtime cache
            Cache::forget("tenant_{$tenantIdStr}_drawer_menu");
            Cache::forget("navigation_menu_{$tenantIdStr}");
        }

        if ($company) {
            $company->forceFill([
                'nav_config' => $navConfig,
                'navigation_menu_customization' => $navConfig['tree'],
            ])->save();

            $cidStr = (string) $company->id;
            Cache::forget("tenant_{$cidStr}_drawer_menu");
            Cache::forget("navigation_menu_{$cidStr}");
            if ($user) {
                AuditLog::record('company.settings_updated', $company->id, $user->id, ['section' => 'nav_config']);
            }
        }

        $persisted = $company ? $company->fresh()->normalizedNavConfig() : $navConfig;

        return response()->json([
            'success' => true,
            'message' => 'Navigation layout saved successfully.',
            'data'    => $sections ?: $navConfig['tree'],
            'nav'     => $persisted,
        ]);
    }

    /**
     * Alias for saveMenuSettings.
     */
    public function save(Request $request): JsonResponse
    {
        return $this->saveMenuSettings($request);
    }

    /**
     * Alias for saveMenuSettings.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->saveMenuSettings($request);
    }

    /**
     * Get Drawer Menu prioritizing saved custom layout over default presets.
     */
    public function getDrawerMenu(Request $request): JsonResponse
    {
        $tenantId = null;
        $company = null;
        $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();

        if ($user) {
            $tenantId = $user->tenant_id ?? $user->company_id;
        }

        try {
            $company = $this->resolveCompany($request);
            if ($company) {
                $tenantId = $tenantId ?: $company->id;
            }
        } catch (\Throwable) {
            if ($tenantId) {
                $company = Company::find($tenantId);
            }
        }

        $selectedColor = null;
        if ($tenantId) {
            $preferences = \App\Models\TenantSetting::get($tenantId, 'app_preferences', []);
            if (is_array($preferences)) {
                $selectedColor = $preferences['drawer_text_icon_color']
                    ?? $preferences['drawer_text_and_icons']
                    ?? $preferences['drawer_icon_color']
                    ?? $preferences['drawer_text_color']
                    ?? null;
            }
        }

        // 1. Check for saved custom layout FIRST from tenant_settings
        if ($tenantId && Schema::hasTable('tenant_settings')) {
            $customSetting = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', 'navigation_menu_custom')
                ->value('value');

            if (! empty($customSetting)) {
                $sections = json_decode($customSetting, true);
                if (is_array($sections) && ! empty($sections)) {
                    $components = $this->formatCustomMenuComponents($sections, $selectedColor);

                    return response()->json([
                        'success'    => true,
                        'components' => $components,
                        'menu'       => $components,
                    ]);
                }
            }
        }

        // 2. Check for saved custom layout from Company nav_config / navigation_menu_customization
        if ($company !== null) {
            $customTree = TenantNavRegistry::buildCustomNavTree($company);
            if (! empty($customTree)) {
                $components = $this->formatCustomMenuComponents($customTree, $selectedColor);

                return response()->json([
                    'success'    => true,
                    'components' => $components,
                    'menu'       => $components,
                ]);
            }
        }

        // 3. Fallback to default structure only if user never customized it
        $defaultComponents = $this->getDefaultMenuComponents($request, $company, $selectedColor);

        return response()->json([
            'success'    => true,
            'components' => $defaultComponents,
            'menu'       => $defaultComponents,
        ]);
    }

    /**
     * Format custom sections into SDUI drawer components.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public function formatCustomMenuComponents(array $sections, ?string $selectedColor = null): array
    {
        $sections = $this->sanitizeConsignmentsHierarchy($sections);
        $homeItem = [
            'type' => 'list_tile',
            'key' => 'home',
            'title' => 'Home',
            'icon' => 'home',
            'action_type' => 'NAVIGATE_TO',
            'route' => '/dashboard',
        ];

        if ($selectedColor !== null && $selectedColor !== '') {
            $homeItem['icon_color'] = $selectedColor;
            $homeItem['leading'] = [
                'type' => 'icon',
                'name' => 'home',
                'color' => $selectedColor,
            ];
            $homeItem['style'] = [
                'textColor' => $selectedColor,
                'iconColor' => $selectedColor,
            ];
        }

        $components = [$homeItem];

        $isFlatList = ! empty($sections) && isset($sections[0]) && is_array($sections[0]) && ! isset($sections[0]['items']);

        if ($isFlatList) {
            $formattedItems = $this->buildComponentsFromFlatItems($sections, '', $selectedColor);
            foreach ($formattedItems as $c) {
                $components[] = $c;
            }

            return $components;
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $items = array_values(array_filter(
                is_array($section['items'] ?? null) ? $section['items'] : [],
                static fn ($item): bool => is_array($item) && ($item['visible'] ?? true) !== false
            ));
            if ($items === []) {
                continue;
            }

            $sectionKey = trim((string) ($section['key'] ?? $section['id'] ?? ''));
            $sectionTitle = trim((string) ($section['custom_title'] ?? ''));
            $legacySectionTitles = [
                'cashier_sales' => ['Point of Sale', 'Cashier & Sales'],
                'financial_management' => ['Cash Register', 'Accounts Receivable', 'Financial Management'],
                'restaurant_operations' => ['Restaurant Operations'],
                'pharmacy_management' => ['PHARMACY OPERATIONS', 'New Prescription Intake'],
                'salon_bookings' => ['Book Service / Appointment'],
                'repair_service' => ['REPAIR OPERATIONS & SALES', 'Repair Workbench'],
            ];
            if ($sectionTitle === '' || in_array($sectionTitle, $legacySectionTitles[$sectionKey] ?? [], true)) {
                $sectionTitle = trim((string) ($section['title'] ?? $section['label'] ?? ''));
            }
            if ($sectionTitle === '') {
                $sectionTitle = [
                    'cashier_sales' => 'Retail & Cashier',
                    'financial_management' => 'Finance & Targets',
                    'restaurant_operations' => 'Cafe & Restaurant',
                    'pharmacy_management' => 'Pharmacy & Healthcare',
                    'salon_bookings' => 'Salon & Bookings',
                    'repair_service' => 'Service & Repairs',
                ][$sectionKey] ?? '';
            }

            $components[] = ['type' => 'divider', 'section_key' => $sectionKey];
            if ($sectionTitle !== '') {
                $components[] = [
                    'type' => 'section_header',
                    'section_key' => $sectionKey,
                    'title' => $sectionTitle,
                ];
            }

            $formattedItems = $this->buildComponentsFromFlatItems($items, $sectionKey, $selectedColor);
            foreach ($formattedItems as $component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /** Ensure legacy saved menus cannot render Consignments beneath Quotations. */
    private function sanitizeConsignmentsHierarchy(array $sections): array
    {
        $promoted = [];
        $flatCoreKeys = ['pos', 'sales', 'quotations', 'consignments', 'customers', 'cash_register'];
        $walk = function (array &$items) use (&$walk, &$promoted, $flatCoreKeys): void {
            foreach ($items as &$item) {
                if (! is_array($item)) continue;
                if (in_array(strtolower((string) ($item['key'] ?? $item['id'] ?? '')), $flatCoreKeys, true)) {
                    $promoted[] = $item;
                    $item = null;
                    continue;
                }
                if (isset($item['children']) && is_array($item['children'])) {
                    $walk($item['children']);
                    $item['children'] = array_values(array_filter($item['children']));
                    if ($item['children'] === []) unset($item['children']);
                }
            }
            $items = array_values(array_filter($items));
        };
        foreach ($sections as &$section) {
            if (! isset($section['items']) || ! is_array($section['items'])) continue;
            foreach ($section['items'] as &$item) {
                if (is_array($item) && isset($item['children']) && is_array($item['children'])) {
                    $walk($item['children']);
                    $item['children'] = array_values(array_filter($item['children']));
                    if ($item['children'] === []) unset($item['children']);
                }
            }
        }
        foreach ($sections as &$section) {
            if (($section['key'] ?? $section['id'] ?? '') === 'cashier_sales' && $promoted) {
                $existing = array_map(fn ($i) => (string) ($i['key'] ?? $i['id'] ?? ''), $section['items'] ?? []);
                foreach ($promoted as $item) {
                    $key = (string) ($item['key'] ?? $item['id'] ?? '');
                    if ($key !== '' && ! in_array($key, $existing, true)) {
                        $section['items'][] = $item;
                        $existing[] = $key;
                    }
                }
                break;
            }
        }
        return $sections;
    }

    /**
     * Sequential hierarchy builder that breaks out of the active parent whenever
     * an item has level === 0 (or indent === 0 / parent_id === null).
     *
     * @param  list<array<string, mixed>>  $rawItems
     * @param  string  $sectionKey
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public function buildComponentsFromFlatItems(array $rawItems, string $sectionKey = '', ?string $selectedColor = null): array
    {
        $formattedComponents = [];
        $currentRoot = null;

        foreach ($rawItems as $item) {
            if (! is_array($item) || ($item['visible'] ?? true) === false) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $level = (int) ($item['level'] ?? $item['indent'] ?? 0);
            $parentId = $item['parent_id'] ?? $item['parent'] ?? null;
            if ($parentId !== null) {
                $parentId = trim((string) $parentId);
                if ($parentId === '') {
                    $parentId = null;
                }
            }

            // Forced root items like settings are always root
            if ($key === 'settings') {
                $level = 0;
                $parentId = null;
            }

            // Core commerce links are always standalone siblings. Ignore
            // stale parent/indent metadata from older saved drawer layouts.
            if (in_array($key, ['pos', 'sales', 'quotations', 'consignments', 'customers', 'cash_register'], true)) {
                $level = 0;
                $parentId = null;
            }

            $isRoot = ($level === 0 && empty($parentId));

            // If it's a Main Menu item, reset active parent
            if ($isRoot) {
                // Push previous root if existing
                if ($currentRoot !== null) {
                    $formattedComponents[] = $currentRoot;
                }

                $currentRoot = $this->formatCustomMenuItem($item, $sectionKey, null, 0, $selectedColor);
            } else {
                // Sub-menu item
                $parentKey = $parentId ?? ($currentRoot !== null ? ($currentRoot['key'] ?? null) : null);
                $childNode = $this->formatCustomMenuItem($item, $sectionKey, $parentKey, max(1, $level), $selectedColor);

                if ($childNode !== null) {
                    if ($currentRoot !== null) {
                        $currentRoot['children'] ??= [];
                        $currentRoot['children'][] = $childNode;
                    } else {
                        // Fallback if list starts without a root
                        $formattedComponents[] = $childNode;
                    }
                }
            }
        }

        // Flush final root node
        if ($currentRoot !== null) {
            $formattedComponents[] = $currentRoot;
        }

        return $formattedComponents;
    }

    /**
     * Format an individual custom item node.
     *
     * @param  array<string, mixed>  $item
     * @param  string  $sectionKey
     * @param  string|null  $parentKey
     * @param  int  $level
     * @param  string|null  $selectedColor
     * @return array<string, mixed>|null
     */
    private function formatCustomMenuItem(array $item, string $sectionKey, ?string $parentKey, int $level, ?string $selectedColor = null): ?array
    {
        if (($item['visible'] ?? true) === false) {
            return null;
        }

        $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
        if ($key === '') {
            return null;
        }

        // Store Settings must always be an independent root item (never nested under subscription)
        if ($key === 'settings') {
            $parentKey = null;
            $level = 0;
        }

        $route = trim((string) ($item['route'] ?? $item['target_endpoint'] ?? $item['endpoint'] ?? ''));
        $children = [];
        $rawChildren = in_array($key, ['pos', 'sales', 'quotations', 'consignments', 'customers', 'cash_register'], true)
            ? []
            : (is_array($item['children'] ?? null) ? $item['children'] : []);
        foreach ($rawChildren as $child) {
            if (! is_array($child)) {
                continue;
            }
            $childKey = trim((string) ($child['key'] ?? $child['id'] ?? ''));
            // If settings was nested inside children, don't nest it
            if ($childKey === 'settings') {
                continue;
            }
            $formatted = $this->formatCustomMenuItem($child, $sectionKey, $key, $level + 1, $selectedColor);
            if ($formatted !== null) {
                $children[] = $formatted;
            }
        }

        $node = [
            'type' => 'list_tile',
            'key' => $key,
            'section' => $sectionKey,
            'parent' => $parentKey,
            'parent_id' => $parentKey,
            'level' => min(TenantNavigationConfigService::MAX_LEVEL, max(0, $level)),
            'title' => (string) ($item['title'] ?? $item['label'] ?? $key),
            'icon' => (string) ($item['icon'] ?? 'widgets'),
            'action_type' => 'NAVIGATE_TO',
            'route' => $route,
        ];

        // Flat links must not carry an empty children collection. Some mobile
        // clients interpret the presence of that key (or a stale accordion
        // flag) as an expandable tile.
        if ($children !== []) {
            $node['children'] = $children;
        }

        return TenantNavRegistry::formatDrawerItem($node, $selectedColor);
    }

    /**
     * Format a drawer item ensuring explicit icon color and leading component.
     *
     * @param  array<string, mixed>  $item
     * @param  string|null  $selectedColor
     * @return array<string, mixed>
     */
    public function formatDrawerItem(array $item, ?string $selectedColor = null): array
    {
        return TenantNavRegistry::formatDrawerItem($item, $selectedColor);
    }

    /**
     * Default drawer menu components fallback.
     *
     * @param  Request|null  $request
     * @param  Company|null  $company
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public function getDefaultMenuComponents(?Request $request = null, ?Company $company = null, ?string $selectedColor = null): array
    {
        return (new NavigationController)->getDrawerMenuComponents($request, $company, $selectedColor);
    }
}
