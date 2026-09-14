<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Get Drawer Menu SDUI Schema with clickable parent tiles & dividers.
     *
     * @param  Request|null  $request
     * @return JsonResponse
     */
    public function getDrawerMenu(?Request $request = null): JsonResponse
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
    public function getDrawerMenuComponents(?Request $request = null): array
    {
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
        $menuComponents = $this->getDrawerMenuComponents($request);

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
