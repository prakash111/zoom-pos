<?php

namespace App\View\Composers;

use App\Models\Company;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * layouts/tenant.blade.php used to compute this same block of company/theme/
 * permission/active-route flags twice per request (once in <head>, once in
 * <body>) via inline @php, driving ~17 PermissionChecker::allows() calls and
 * ~20 routeIs() checks evaluated inline on every page load. Centralizing it
 * here means it runs once per request, from a class that can be unit tested
 * on its own, and the layout just consumes already-computed values.
 */
class TenantNavigationComposer
{
    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        $user = auth()->user();
        $company = $user?->company ?? new Company;
        $themeClasses = $company->getThemeColorClasses();
        $checker = app(PermissionChecker::class);

        $allows = fn (string $module, string $action = 'view'): bool => ! $user || $user->isPrivilegedRole() || $checker->allows($user, $module, $action);

        $isQuotes = $this->request->routeIs('tenant.quotes.*') || $this->request->routeIs('tenant.quotations.*');
        $isSalesTargets = $this->request->routeIs('tenant.sales-targets.*');
        $isCashRegister = $this->request->routeIs('tenant.financials.cash_register');
        $isReceivables = $this->request->routeIs('tenant.financials.receivables');
        $isPayables = $this->request->routeIs('tenant.financials.payables');

        $view->with([
            'tenantCompany' => $company,
            'themeClasses' => $themeClasses,
            'uiAccentColorHex' => $company->primary_color ?: ($themeClasses['hex'] ?? '#2563eb'),
            'isRestaurant' => $user?->company?->isRestaurantMode() ?? false,
            'isPosScreen' => $this->request->routeIs('tenant.sales.create') || $this->request->routeIs('tenant.restaurant.pos'),

            'canQuotes' => $allows('quotes'),
            'canSales' => $allows('sales'),
            'canConsignments' => $allows('consignments'),
            'canServiceOrders' => $allows('service_orders'),
            'canPos' => $allows('pos', 'create'),
            'canProducts' => $allows('products'),
            'canCategories' => $allows('categories'),
            'canUnits' => $allows('units'),
            'canSuppliers' => $allows('suppliers'),
            'canCustomers' => $allows('customers'),
            'canCatalog' => $allows('catalog'),
            'canCashRegister' => $allows('cash_register'),
            'canFinance' => $allows('finance'),
            'canReports' => $allows('reports'),
            'canTargets' => $allows('targets'),
            'canSettings' => $allows('settings'),
            'canUsers' => $allows('users'),

            'isHome' => $this->request->routeIs('tenant.dashboard'),
            'isQuotes' => $isQuotes,
            'isConsignments' => $this->request->routeIs('tenant.consignments.*'),
            'isServiceOrders' => $this->request->routeIs('tenant.service-orders.*'),
            'isSalesTargets' => $isSalesTargets,
            'isTransaction' => $this->request->routeIs('tenant.sales.index') || $this->request->routeIs('tenant.sales.show'),
            'isCasier' => $this->request->routeIs('tenant.sales.create'),
            'isRestaurantPos' => $this->request->routeIs('tenant.restaurant.pos'),
            'isTables' => $this->request->routeIs('tenant.restaurant.tables'),
            'isKds' => $this->request->routeIs('tenant.restaurant.kds'),
            'isProducts' => $this->request->routeIs('tenant.products.*'),
            'isCategories' => $this->request->routeIs('tenant.categories.*'),
            'isBrands' => $this->request->routeIs('tenant.brands.*'),
            'isUnits' => $this->request->routeIs('tenant.units.*'),
            'isSuppliers' => $this->request->routeIs('tenant.suppliers.*'),
            'isCustomers' => $this->request->routeIs('tenant.customers.*'),
            'isCatalog' => $this->request->routeIs('tenant.catalog.*'),
            'isCashRegister' => $isCashRegister,
            'isReceivables' => $isReceivables,
            'isPayables' => $isPayables,
            'isFinancials' => $isCashRegister || $isReceivables || $isPayables,
            'isReports' => $this->request->routeIs('tenant.reports.*') || $isSalesTargets,
            'isSettings' => $this->request->routeIs('tenant.settings.*'),
            'isLanguages' => $this->request->routeIs('tenant.languages.*'),
            'isUsers' => $this->request->routeIs('tenant.users.*'),
            'isDevices' => $this->request->routeIs('tenant.devices.*'),
            'isBilling' => $this->request->routeIs('tenant.billing.*') || $this->request->routeIs('tenant.activate'),
        ]);
    }
}
