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
        $menuComponents = $this->getDrawerMenuComponents($request);

        return response()->json([
            'success'    => true,
            'components' => $menuComponents,
            'menu'       => $menuComponents,
        ]);
    }

    /**
     * Build the standard SDUI drawer menu components.
     *
     * @param  Request|null  $request
     * @return array<int, array<string, mixed>>
     */
    public function getDrawerMenuComponents(?Request $request = null, ?Company $company = null): array
    {
        if ($company === null && $request !== null) {
            try {
                $company = $this->resolveCompany($request);
            } catch (\Throwable) {
                // Public/legacy callers without tenant context retain the
                // static compatibility menu below.
            }
        }

        // An authenticated tenant's persisted hierarchy always wins. The
        // registry decorates the saved keys with current route/icon metadata
        // while preserving section, parent, visibility, and custom-title
        // overrides from companies.nav_config.
        if ($company !== null) {
            return $this->formatCustomMenuComponents(
                TenantNavRegistry::getEffectiveNavForTenant($company)
            );
        }

        $menuComponents = [];

        // HOME
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Home',
            'icon'        => 'home',
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/dashboard',
        ];

        // ==========================================
        // SECTION 1: POINT OF SALE / CASHIER
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];

        // First Parent Item becomes the clickable header
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Point of Sale',
            'icon'        => 'point_of_sale',
            'style'       => ['fontWeight' => 'bold', 'textColor' => '#F97316'],
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/pos',
        ];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Sales & Invoices',
            'icon'        => 'receipt_long',
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/invoices',
        ];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Quotations & Proposals',
            'icon'        => 'description',
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/tenant/views/quotations',
        ];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Consignments',
            'icon'        => 'local_shipping',
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/consignments',
        ];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Customers & CRM',
            'icon'        => 'people',
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/customers',
        ];

        // ==========================================
        // SECTION 2: LEADS & CRM (CLICKABLE HEADER)
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];

        // "Lead Dashboard" is the clickable parent header
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Lead Dashboard',
            'icon'        => 'grid_view',
            'style'       => ['fontWeight' => 'bold', 'textColor' => '#F97316'],
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/tenant/views/leads', // Opens Lead Management / Dashboard
        ];

        // ==========================================
        // SECTION 3: PRODUCTS & INVENTORY
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Product Catalog',
            'icon'        => 'inventory_2',
            'style'       => ['fontWeight' => 'bold', 'textColor' => '#F97316'],
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/products',
            'children'    => [
                ['type' => 'list_tile', 'title' => 'Categories', 'icon' => 'label', 'action_type' => 'NAVIGATE_TO', 'route' => '/categories'],
                ['type' => 'list_tile', 'title' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'action_type' => 'NAVIGATE_TO', 'route' => '/brands'],
                ['type' => 'list_tile', 'title' => 'Units of Measure', 'icon' => 'straighten', 'action_type' => 'NAVIGATE_TO', 'route' => '/units'],
                ['type' => 'list_tile', 'title' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'action_type' => 'NAVIGATE_TO', 'route' => '/suppliers'],
                ['type' => 'list_tile', 'title' => 'Taxes & Compliance', 'icon' => 'percent', 'action_type' => 'NAVIGATE_TO', 'route' => '/taxes'],
                ['type' => 'list_tile', 'title' => 'Online Digital Catalog', 'icon' => 'qr_code_2', 'action_type' => 'NAVIGATE_TO', 'route' => '/digital-catalog'],
            ],
        ];

        // ==========================================
        // SECTION 4: FINANCIAL MANAGEMENT
        // ==========================================
        $menuComponents[] = ['type' => 'divider'];
        $menuComponents[] = [
            'type'        => 'list_tile',
            'title'       => 'Cash Register',
            'icon'        => 'account_balance_wallet',
            'style'       => ['fontWeight' => 'bold', 'textColor' => '#F97316'],
            'action_type' => 'NAVIGATE_TO',
            'route'       => '/register',
            'children'    => [
                ['type' => 'list_tile', 'title' => 'Accounts Receivable', 'icon' => 'notifications_active', 'action_type' => 'NAVIGATE_TO', 'route' => '/receivables'],
                ['type' => 'list_tile', 'title' => 'Accounts Payable', 'icon' => 'receipt', 'action_type' => 'NAVIGATE_TO', 'route' => '/payables'],
                ['type' => 'list_tile', 'title' => 'Sales Targets', 'icon' => 'flag', 'action_type' => 'NAVIGATE_TO', 'route' => '/targets'],
                ['type' => 'list_tile', 'title' => 'Reports', 'icon' => 'bar_chart', 'action_type' => 'NAVIGATE_TO', 'route' => '/reports'],
            ],
        ];

        return $menuComponents;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function formatCustomMenuComponents(array $sections): array
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

        $route = trim((string) ($item['route'] ?? $item['target_endpoint'] ?? $item['endpoint'] ?? ''));
        $children = [];
        foreach (is_array($item['children'] ?? null) ? $item['children'] : [] as $child) {
            if (! is_array($child)) {
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

        $drawerHeader = $company ? $company->getDrawerHeaderPayload() : [];
        $sections = $company ? TenantNavRegistry::getEffectiveNavForTenant($company) : [];
        $menuComponents = $this->getDrawerMenuComponents($request, $company);

        return response()->json([
            'success'         => true,
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
