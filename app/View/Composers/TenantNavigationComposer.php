<?php

namespace App\View\Composers;

use App\Models\Company;
use App\Models\Store;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
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

        // Compute vertical and operating mode flags based on company's licensed modules
        $verticalKeys = $user ? $company->licensedModuleKeys() : ['retail'];
        $activeVertical = collect($verticalKeys)
            ->first(fn ($k) => in_array($k, ['pharmacy', 'service_booking', 'salon', 'salon_bookings', 'repair_technician', 'repair', 'repairs'], true))
            ?? ($verticalKeys[0] ?? 'retail');

        $isRestaurantMode = $company->isRestaurantMode();
        $type = strtoupper((string) ($company->business_type ?? $company->store_type ?? ''));
        $hasRestaurant = $company->hasModule('restaurant') || $type === 'RESTAURANT';
        $isPharmacy = $company->hasModule('pharmacy') || $type === 'PHARMACY';
        $isSalon = $company->hasModule('service_booking') || $company->hasModule('salon')
            || in_array($activeVertical, ['salon', 'salon_bookings', 'beauty'], true) || $type === 'SALON';
        $isRepair = $company->hasModule('repair_technician') || $company->hasModule('repairtechnician')
            || in_array($activeVertical, ['repair', 'repairs', 'electronics_service'], true) || in_array($type, ['REPAIR', 'REPAIRS'], true);
        $isLeadManagement = $company->hasModule('leadmanagement') || $company->hasModule('lead_management');
        $isAllModulesDemo = $company->is_demo
            && in_array(strtolower((string) $company->email), [
                'demo@zoomnearby.com',
                'allmodules.demo@zoomnearby.com',
            ], true);

        $isVerticalOnly = in_array($type, ['SALON', 'PHARMACY', 'RESTAURANT', 'REPAIR', 'REPAIRS'], true)
            && ! (is_array($company->licensed_modules) && in_array('retail', $company->licensed_modules, true))
            && ! in_array($company->pos_mode, ['retail', 'general', 'general_retail'], true);

        $hasRetail = ! $isVerticalOnly && ($company->hasModule('retail') || $company->isGeneralMode() || (! $isRestaurantMode && ! $isPharmacy && ! $isSalon && ! $isRepair));

        $isQuotes = $this->request->routeIs('tenant.quotes.*') || $this->request->routeIs('tenant.quotations.*');
        $isSalesTargets = $this->request->routeIs('tenant.sales-targets.*');
        $isCashRegister = $this->request->routeIs('tenant.financials.cash_register');
        $isReceivables = $this->request->routeIs('tenant.financials.receivables');
        $isPayables = $this->request->routeIs('tenant.financials.payables');

        // Settings > Navigation Menu customization — see Company::
        // normalizedNavConfig(). Shared (not just $view->with(), which is
        // scoped to this one view) because x-nav.drawer-item/drawer-link
        // are separate anonymous-component view instances that need it too.
        $navConfig = $company->normalizedNavConfig();
        $hiddenNavKeys = collect($navConfig['items'] ?? [])->where('visible', false)->pluck('key')->all();
        ViewFacade::share(['hiddenNavKeys' => $hiddenNavKeys, 'tenantNavConfig' => $navConfig]);

        $canStores = $user && $company->exists && $allows('stores');
        $switchableStores = collect();
        $storeLimit = (int) ($company->plan?->store_limit ?? 1);
        $storeCount = 0;
        if ($canStores) {
            $stores = Store::where('company_id', $company->id)->where('is_active', true);
            if (! $user->isPrivilegedRole()) {
                $stores->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
            }
            $switchableStores = $stores->orderByDesc('is_primary')->orderBy('name')->get();
            $storeCount = Store::where('company_id', $company->id)->count();
        }
        $activeStoreId = app()->bound('tenant.store_id') ? app('tenant.store_id') : $user?->current_store_id;

        $view->with([
            'tenantCompany' => $company,
            'canStores' => $canStores,
            'switchableStores' => $switchableStores,
            'activeStore' => $switchableStores->firstWhere('id', $activeStoreId),
            'tenantStoreCount' => $storeCount,
            'tenantStoreLimit' => $storeLimit,
            'canCreateStore' => $canStores && $allows('stores', 'create'),
            'canAddStore' => $canStores && $allows('stores', 'create') && ($storeLimit === -1 || $storeCount < $storeLimit),
            'themeClasses' => $themeClasses,
            'uiAccentColorHex' => $company->primary_color ?: ($themeClasses['hex'] ?? '#2563eb'),
            'isRestaurant' => $isRestaurantMode,
            'isRestaurantMode' => $isRestaurantMode,
            'hasRestaurant' => $hasRestaurant,
            'hasRetail' => $hasRetail,
            'activeVertical' => $activeVertical,
            'isPharmacy' => $isPharmacy,
            'isSalon' => $isSalon,
            'isRepair' => $isRepair,
            'isLeadManagement' => $isLeadManagement,
            'isAllModulesDemo' => $isAllModulesDemo,
            'isVerticalStore' => $isPharmacy || $isSalon || $isRepair || ($hasRestaurant && ! $isRestaurantMode),
            'isPosScreen' => $this->request->routeIs('tenant.sales.create') || $this->request->routeIs('tenant.restaurant.pos'),

            'canQuotes' => $allows('quotes'),
            'canLeads' => $isLeadManagement && $allows('leads'),
            'canSales' => $allows('sales'),
            'canConsignments' => $allows('consignments'),
            'canServiceOrders' => $allows('service_orders'),
            'canRepair' => $allows('repair'),
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
            'isLeads' => $this->request->routeIs('tenant.leads.*'),
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
