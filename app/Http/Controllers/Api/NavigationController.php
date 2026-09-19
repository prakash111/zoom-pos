<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Get Drawer Menu SDUI Schema with clickable parent tiles & dividers.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getDrawerMenu(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable) {
            // fallback
        }
        if ($company === null) {
            $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();
            if ($user) {
                $tenantId = $user->tenant_id ?? $user->company_id;
                if ($tenantId) {
                    $company = Company::find($tenantId);
                }
            }
        }

        $tenantId = $company?->id ?? (auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? null);
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

        $menuComponents = $this->getDrawerMenuComponents($request, $company, $selectedColor);
        $drawerHeader = $company ? $company->getDrawerHeaderPayload() : [];

        $payload = [
            'success'    => true,
            'components' => $menuComponents,
            'menu'       => $menuComponents,
        ];

        if ($company !== null) {
            $payload['header'] = $drawerHeader;
            $payload['drawer_header'] = $drawerHeader;
            $payload['store_name'] = $company->display_name;
            $payload['business_name'] = $company->display_name;
            $payload['tenant_name'] = $company->display_name;
            $payload['display_name'] = $company->display_name;
            $payload['title'] = $company->display_name;
            $payload['store_type'] = $company->store_type;
        }

        return response()->json($payload);
    }

    /**
     * Build the standard SDUI drawer menu components.
     *
     * @param  Request|null  $request
     * @param  Company|null  $company
     * @param  string|null  $selectedColor
     * @return array<int, array<string, mixed>>
     */
    public function getDrawerMenuComponents(?Request $request = null, ?Company $company = null, ?string $selectedColor = null): array
    {
        $tenantId = null;
        if ($company === null && $request !== null) {
            try {
                $company = $this->resolveCompany($request);
            } catch (\Throwable) {
                // Public/legacy callers without tenant context retain the
                // static compatibility menu below.
            }
        }

        if ($company !== null) {
            $tenantId = $company->id;
        } else {
            $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();
            if ($user) {
                $tenantId = $user->tenant_id ?? $user->company_id;
                if ($tenantId) {
                    $company = Company::find($tenantId);
                }
            }
        }

        if ($selectedColor === null && $tenantId) {
            $preferences = \App\Models\TenantSetting::get($tenantId, 'app_preferences', []);
            if (is_array($preferences)) {
                $selectedColor = $preferences['drawer_text_icon_color']
                    ?? $preferences['drawer_text_and_icons']
                    ?? $preferences['drawer_icon_color']
                    ?? $preferences['drawer_text_color']
                    ?? null;
            }
        }

        // Check for saved custom layout FIRST from tenant_settings
        if ($tenantId && \Illuminate\Support\Facades\Schema::hasTable('tenant_settings')) {
            $customSetting = \Illuminate\Support\Facades\DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', 'navigation_menu_custom')
                ->value('value');

            if (! empty($customSetting)) {
                $customSections = json_decode($customSetting, true);
                if (is_array($customSections) && ! empty($customSections)) {
                    return $this->formatCustomMenuComponents($customSections, $selectedColor);
                }
            }
        }

        // An authenticated tenant's persisted hierarchy always wins. The
        // registry decorates the saved keys with current route/icon metadata
        // while preserving section, parent, visibility, and custom-title
        // overrides from companies.nav_config.
        if ($company !== null) {
            return $this->formatCustomMenuComponents(
                TenantNavRegistry::getEffectiveNavForTenant($company, $selectedColor),
                $selectedColor
            );
        }

        $menuComponents = [];

        // HOME
        $menuComponents[] = $this->buildFallbackTile('Home', 'home', '/dashboard', $selectedColor);

        // ==========================================
        // SECTION 1: POINT OF SALE / CASHIER
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];

        // First Parent Item becomes the clickable header
        $menuComponents[] = $this->buildFallbackTile('Point of Sale', 'point_of_sale', '/pos', $selectedColor, true);
        $menuComponents[] = $this->buildFallbackTile('Sales & Invoices', 'receipt_long', '/invoices', $selectedColor);
        $menuComponents[] = $this->buildFallbackTile('Quotations & Proposals', 'description', '/tenant/views/quotations', $selectedColor);
        $menuComponents[] = $this->buildFallbackTile('Consignments', 'local_shipping', '/consignments', $selectedColor);
        $menuComponents[] = $this->buildFallbackTile('Customers & CRM', 'people', '/customers', $selectedColor);

        // ==========================================
        // SECTION 2: LEADS & CRM (CLICKABLE HEADER)
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];

        // "Lead Dashboard" is the clickable parent header
        $menuComponents[] = $this->buildFallbackTile('Lead Dashboard', 'grid_view', '/tenant/views/leads', $selectedColor, true);

        // ==========================================
        // SECTION 3: PRODUCTS & INVENTORY
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];
        $menuComponents[] = $this->buildFallbackTile('Product Catalog', 'inventory_2', '/products', $selectedColor, true, [
            ['type' => 'list_tile', 'title' => 'Categories', 'icon' => 'label', 'action_type' => 'NAVIGATE_TO', 'route' => '/categories'],
            ['type' => 'list_tile', 'title' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'action_type' => 'NAVIGATE_TO', 'route' => '/brands'],
            ['type' => 'list_tile', 'title' => 'Units of Measure', 'icon' => 'straighten', 'action_type' => 'NAVIGATE_TO', 'route' => '/units'],
            ['type' => 'list_tile', 'title' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'action_type' => 'NAVIGATE_TO', 'route' => '/suppliers'],
            ['type' => 'list_tile', 'title' => 'Taxes & Compliance', 'icon' => 'percent', 'action_type' => 'NAVIGATE_TO', 'route' => '/taxes'],
            ['type' => 'list_tile', 'title' => 'Online Digital Catalog', 'icon' => 'qr_code_2', 'action_type' => 'NAVIGATE_TO', 'route' => '/digital-catalog'],
        ]);

        // ==========================================
        // SECTION 4: FINANCIAL MANAGEMENT
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];
        $menuComponents[] = $this->buildFallbackTile('Cash Register', 'account_balance_wallet', '/register', $selectedColor, true, [
            ['type' => 'list_tile', 'title' => 'Accounts Receivable', 'icon' => 'notifications_active', 'action_type' => 'NAVIGATE_TO', 'route' => '/receivables'],
            ['type' => 'list_tile', 'title' => 'Accounts Payable', 'icon' => 'receipt', 'action_type' => 'NAVIGATE_TO', 'route' => '/payables'],
            ['type' => 'list_tile', 'title' => 'Sales Targets', 'icon' => 'flag', 'action_type' => 'NAVIGATE_TO', 'route' => '/targets'],
            ['type' => 'list_tile', 'title' => 'Reports', 'icon' => 'bar_chart', 'action_type' => 'NAVIGATE_TO', 'route' => '/reports'],
        ]);

        return $menuComponents;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    private function formatCustomMenuComponents(array $sections, ?string $selectedColor = null): array
    {
        return (new NavigationMenuController)->formatCustomMenuComponents($sections, $selectedColor);
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
     * Build static fallback SDUI tile with backward compatibility.
     *
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $route
     * @param  string|null  $selectedColor
     * @param  bool  $isHeader
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function buildFallbackTile(string $title, string $icon, string $route, ?string $selectedColor = null, bool $isHeader = false, array $children = []): array
    {
        $highlightTextColor = $selectedColor ?: '#F97316';
        $tile = [
            'type'        => 'list_tile',
            'title'       => $title,
            'icon'        => $icon,
            'action_type' => 'NAVIGATE_TO',
            'route'       => $route,
        ];

        if ($isHeader) {
            $tile['style'] = ['fontWeight' => 'bold', 'textColor' => $highlightTextColor];
            if ($selectedColor !== null && $selectedColor !== '') {
                $tile['style']['iconColor'] = $selectedColor;
                $tile['icon_color'] = $selectedColor;
                $tile['leading'] = [
                    'type' => 'icon',
                    'name' => $icon,
                    'color' => $selectedColor,
                ];
            }
        } elseif ($selectedColor !== null && $selectedColor !== '') {
            $tile['icon_color'] = $selectedColor;
            $tile['leading'] = [
                'type' => 'icon',
                'name' => $icon,
                'color' => $selectedColor,
            ];
            $tile['style'] = [
                'textColor' => $selectedColor,
                'iconColor' => $selectedColor,
            ];
        }

        if (! empty($children)) {
            $formattedChildren = [];
            foreach ($children as $child) {
                if (is_array($child)) {
                    if ($selectedColor !== null && $selectedColor !== '') {
                        $child = TenantNavRegistry::formatDrawerItem($child, $selectedColor);
                    }
                    $formattedChildren[] = $child;
                }
            }
            $tile['children'] = $formattedChildren;
        }

        return $tile;
    }

    /**
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
        foreach (is_array($item['children'] ?? null) ? $item['children'] : [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $childKey = trim((string) ($child['key'] ?? $child['id'] ?? ''));
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
            'children' => $children,
        ];

        return TenantNavRegistry::formatDrawerItem($node, $selectedColor);
    }

    /**
     * Get Drawer Navigation with both legacy sections and SDUI components.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getDrawerNavigation(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            // fallback
        }
        if ($company === null) {
            $user = auth()->user() ?? auth('tenant_api')->user() ?? auth('web')->user();
            if ($user) {
                $tenantId = $user->tenant_id ?? $user->company_id;
                if ($tenantId) {
                    $company = Company::find($tenantId);
                }
            }
        }

        $tenantId = $company?->id ?? (auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? null);
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

        $drawerHeader = $company ? $company->getDrawerHeaderPayload() : [];
        $sections = $company ? TenantNavRegistry::getEffectiveNavForTenant($company, $selectedColor) : [];
        $menuComponents = $this->getDrawerMenuComponents($request, $company, $selectedColor);

        return response()->json([
            'success'         => true,
            'store_name'      => $company?->display_name,
            'business_name'   => $company?->display_name,
            'tenant_name'     => $company?->display_name,
            'display_name'    => $company?->display_name,
            'title'           => $company?->display_name,
            'store_type'      => $company?->store_type ?? 'RESTAURANT',
            'header'          => $drawerHeader,
            'drawer_header'   => $drawerHeader,
            'sections'        => $sections,
            'navigation'      => $sections,
            'components'      => $menuComponents,
            'menu'            => $menuComponents,
            'menu_components' => $menuComponents,
        ]);
    }
}
