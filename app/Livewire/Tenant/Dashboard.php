<?php

namespace App\Livewire\Tenant;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Services\FinancialAnalyticsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public bool $showPosLayoutModal = false;

    public string $selectedPosLayout = 'standard';

    public function openPosLayoutModal(): void
    {
        $company = auth('web')->user()?->company;
        $this->selectedPosLayout = $company?->pos_layout ?: 'standard';
        $this->showPosLayoutModal = true;
    }

    public function selectLayout(string $layout): void
    {
        $this->selectedPosLayout = $layout;
    }

    public function launchPosWithLayout(?string $layout = null): void
    {
        $layoutToUse = $layout ?: $this->selectedPosLayout;
        session(['tenant_pos_layout' => $layoutToUse]);

        $company = auth('web')->user()?->company;
        if ($company && $company->pos_layout !== $layoutToUse) {
            $company->update(['pos_layout' => $layoutToUse]);
        }

        $this->showPosLayoutModal = false;

        $this->redirectRoute('tenant.sales.create', ['layout' => $layoutToUse], navigate: true);
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $company = Company::find($companyId);
        $isRestaurant = $company?->isRestaurantMode();

        $financialAnalytics = app(FinancialAnalyticsService::class);
        $kpiData = $company ? $financialAnalytics->getExecutiveDashboardKpis($company) : [
            'dailyRevenue' => 0.0,
            'monthlyRevenue' => 0.0,
            'dailyProfit' => 0.0,
            'monthlyProfit' => 0.0,
            'dailyProfitMargin' => 0.0,
            'monthlyProfitMargin' => 0.0,
            'dailyOrdersCount' => 0,
            'monthlyOrdersCount' => 0,
            'dailyAov' => 0.0,
            'monthlyAov' => 0.0,
            'revenueGrowth' => 0.0,
            'monthlyRevenueGrowth' => 0.0,
            'avgItemsPerOrder' => 0.0,
            'currencySymbol' => '$',
        ];

        $todaySalesTotal = $kpiData['dailyRevenue'];
        $todayOrdersCount = $kpiData['dailyOrdersCount'];

        if ($isRestaurant) {
            $totalTablesCount = DiningTable::count();
            $activeTablesCount = DiningTable::where('status', 'occupied')->count();
            $pendingKotsCount = KitchenTicket::whereIn('status', ['pending', 'preparing'])->count();
            $totalKotsTodayCount = KitchenTicket::whereDate('created_at', today())->count();

            $activeFloors = DiningFloor::with('tables')->orderBy('order_index')->get();
            $recentKots = KitchenTicket::with('sale', 'table')->latest()->limit(5)->get();

            return view('livewire.tenant.dashboard', array_merge([
                'company' => $company,
                'isRestaurant' => true,
                'todaySalesTotal' => $todaySalesTotal,
                'todayOrdersCount' => $todayOrdersCount,
                'totalTablesCount' => $totalTablesCount,
                'activeTablesCount' => $activeTablesCount,
                'pendingKotsCount' => $pendingKotsCount,
                'totalKotsTodayCount' => $totalKotsTodayCount,
                'activeFloors' => $activeFloors,
                'recentKots' => $recentKots,
            ], $kpiData));
        }

        $totalProductsCount = Product::where('active', true)->count();
        $totalCustomersCount = Customer::count();
        $recentSales = Sale::orderByDesc('created_at')->limit(5)->get();
        $popularProducts = Product::where('active', true)->limit(8)->get();
        $lowStockProducts = Product::where('active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->limit(4)
            ->get();
        $categories = Category::where('active', true)->orWhereNull('active')->limit(6)->get();
        $salesTargetProgress = SalesTarget::getProgress($companyId, null, (int) now()->year, (int) now()->month);

        return view('livewire.tenant.dashboard', array_merge([
            'company' => $company,
            'isRestaurant' => false,
            'todaySalesTotal' => $todaySalesTotal,
            'todayOrdersCount' => $todayOrdersCount,
            'totalProductsCount' => $totalProductsCount,
            'totalCustomersCount' => $totalCustomersCount,
            'recentSales' => $recentSales,
            'popularProducts' => $popularProducts,
            'lowStockProducts' => $lowStockProducts,
            'categories' => $categories,
            'salesTargetProgress' => $salesTargetProgress,
        ], $kpiData));
    }
}
