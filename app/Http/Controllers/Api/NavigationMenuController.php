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

        // 1. Check for saved custom layout FIRST from tenant_settings
        if ($tenantId && Schema::hasTable('tenant_settings')) {
            $customSetting = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', 'navigation_menu_custom')
                ->value('value');

            if (! empty($customSetting)) {
                $sections = json_decode($customSetting, true);
                if (is_array($sections) && ! empty($sections)) {
                    $components = $this->formatCustomMenuComponents($sections);

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
                $components = $this->formatCustomMenuComponents($customTree);

                return response()->json([
                    'success'    => true,
                    'components' => $components,
                    'menu'       => $components,
                ]);
            }
        }

        // 3. Fallback to default structure only if user never customized it
        $defaultComponents = $this->getDefaultMenuComponents();

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
     * @return list<array<string, mixed>>
     */
    public function formatCustomMenuComponents(array $sections): array
    {
        $components = [[
            'type' => 'list_tile',
            'key' => 'home',
            'title' => 'Home',
            'icon' => 'home',
            'action_type' => 'NAVIGATE_TO',
            'route' => '/dashboard',
        ]];

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
            if ($sectionTitle === '') {
                $sectionTitle = trim((string) ($items[0]['title'] ?? $items[0]['label'] ?? $section['title'] ?? $section['label'] ?? ''));
            }

            $components[] = ['type' => 'divider', 'section_key' => $sectionKey];
            if ($sectionTitle !== '') {
                $components[] = [
                    'type' => 'section_header',
                    'section_key' => $sectionKey,
                    'title' => $sectionTitle,
                ];
            }

            foreach ($items as $item) {
                $component = $this->formatCustomMenuItem($item, $sectionKey, null, 0);
                if ($component !== null) {
                    $components[] = $component;
                }
            }
        }

        return $components;
    }

    /**
     * Format an individual custom item node.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function formatCustomMenuItem(array $item, string $sectionKey, ?string $parentKey, int $level): ?array
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
        foreach (is_array($item['children'] ?? null) ? $item['children'] : [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $childKey = trim((string) ($child['key'] ?? $child['id'] ?? ''));
            // If settings was nested inside children, don't nest it
            if ($childKey === 'settings') {
                continue;
            }
            $formatted = $this->formatCustomMenuItem($child, $sectionKey, $key, $level + 1);
            if ($formatted !== null) {
                $children[] = $formatted;
            }
        }

        return [
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
            'children' => $children,
        ];
    }

    /**
     * Default drawer menu components fallback.
     *
     * @return list<array<string, mixed>>
     */
    public function getDefaultMenuComponents(): array
    {
        return (new NavigationController)->getDrawerMenuComponents();
    }
}
