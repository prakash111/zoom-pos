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
        $salesOnly = fn ($query) => $query->whereNull('operation_type')->orWhere('operation_type', '!=', 'quotation');
        $recentSales = Sale::where($salesOnly)->orderByDesc('created_at')->limit(5)->get();
        $start = now()->subDays(6)->startOfDay();
        $dailyRows = Sale::where($salesOnly)
            ->where('status', 'completed')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, SUM(total) as total, COUNT(*) as orders')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');
        $weeklySales = collect(range(0, 6))->map(function ($offset) use ($dailyRows, $start) {
            $day = $start->copy()->addDays($offset);
            $row = $dailyRows->get($day->toDateString());

            return [
                'label' => $day->format('d M'),
                'total' => (float) ($row?->total ?? 0),
                'orders' => (int) ($row?->orders ?? 0),
            ];
        });
        $receivables = Sale::where($salesOnly)->where('status', 'completed')->where('due_amount', '>', 0);
        $receivableAmount = (float) (clone $receivables)->sum('due_amount');
        $outstandingInvoices = (clone $receivables)->count();
        $overdueAmount = (float) (clone $receivables)->whereDate('due_date', '<', today())->sum('due_amount');
        $dueTodayAmount = (float) (clone $receivables)->whereDate('due_date', today())->sum('due_amount');
        $popularProducts = Product::where('active', true)->limit(8)->get();
        $lowStockProducts = Product::where('active', true)
            ->lowStock()
            ->limit(4)
            ->get();
        $lowStockCount = Product::where('active', true)
            ->lowStock()->count();
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
            'weeklySales' => $weeklySales,
            'receivableAmount' => $receivableAmount,
            'outstandingInvoices' => $outstandingInvoices,
            'overdueAmount' => $overdueAmount,
            'dueTodayAmount' => $dueTodayAmount,
            'lowStockCount' => $lowStockCount,
            'popularProducts' => $popularProducts,
            'lowStockProducts' => $lowStockProducts,
            'categories' => $categories,
            'salesTargetProgress' => $salesTargetProgress,
        ], $kpiData));
    }
}
