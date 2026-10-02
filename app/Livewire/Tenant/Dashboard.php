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

    public string $salesOverviewPeriod = 'weekly'; // weekly | monthly | custom

    public ?string $salesOverviewStartDate = null;

    public ?string $salesOverviewEndDate = null;

    public function mount(): void
    {
        $this->salesOverviewStartDate = now()->subDays(6)->toDateString();
        $this->salesOverviewEndDate = now()->toDateString();
    }

    public function setSalesOverviewPeriod(string $period): void
    {
        if (! in_array($period, ['weekly', 'monthly', 'custom'])) {
            return;
        }

        $this->salesOverviewPeriod = $period;

        if ($period === 'weekly') {
            $this->salesOverviewStartDate = now()->subDays(6)->toDateString();
            $this->salesOverviewEndDate = now()->toDateString();
        } elseif ($period === 'monthly') {
            $this->salesOverviewStartDate = now()->subDays(29)->toDateString();
            $this->salesOverviewEndDate = now()->toDateString();
        } elseif ($period === 'custom') {
            if (! $this->salesOverviewStartDate) {
                $this->salesOverviewStartDate = now()->subDays(6)->toDateString();
            }
            if (! $this->salesOverviewEndDate) {
                $this->salesOverviewEndDate = now()->toDateString();
            }
        }
    }

    public function updatedSalesOverviewStartDate(): void
    {
        $this->salesOverviewPeriod = 'custom';
        $this->normalizeCustomOverviewDates();
    }

    public function updatedSalesOverviewEndDate(): void
    {
        $this->salesOverviewPeriod = 'custom';
        $this->normalizeCustomOverviewDates();
    }

    public function applyCustomSalesOverviewDateRange(): void
    {
        $this->salesOverviewPeriod = 'custom';
        $this->normalizeCustomOverviewDates();
    }

    protected function normalizeCustomOverviewDates(): void
    {
        if ($this->salesOverviewStartDate && $this->salesOverviewEndDate) {
            if ($this->salesOverviewStartDate > $this->salesOverviewEndDate) {
                $tmp = $this->salesOverviewStartDate;
                $this->salesOverviewStartDate = $this->salesOverviewEndDate;
                $this->salesOverviewEndDate = $tmp;
            }
        }
    }

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
        $tz = $company?->timezone ?: config('app.timezone');
        $localNow = now($tz);

        if ($this->salesOverviewPeriod === 'monthly') {
            $start = $localNow->copy()->subDays(29)->startOfDay();
            $end = $localNow->copy()->endOfDay();
            $daysCount = 30;
            $periodLabel = __('Last 30 Days');
        } elseif ($this->salesOverviewPeriod === 'custom' && $this->salesOverviewStartDate && $this->salesOverviewEndDate) {
            try {
                $start = \Illuminate\Support\Carbon::parse($this->salesOverviewStartDate, $tz)->startOfDay();
                $end = \Illuminate\Support\Carbon::parse($this->salesOverviewEndDate, $tz)->endOfDay();
                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }
                if ($start->diffInDays($end) > 90) {
                    $start = $end->copy()->subDays(90)->startOfDay();
                }
                $daysCount = (int) $start->diffInDays($end) + 1;
            } catch (\Throwable $e) {
                $start = $localNow->copy()->subDays(6)->startOfDay();
                $end = $localNow->copy()->endOfDay();
                $daysCount = 7;
            }
            $periodLabel = $start->format('d M') . ' - ' . $end->format('d M');
        } else {
            $this->salesOverviewPeriod = 'weekly';
            $start = $localNow->copy()->subDays(6)->startOfDay();
            $end = $localNow->copy()->endOfDay();
            $daysCount = 7;
            $periodLabel = __('Last 7 Days');
        }

        $dailyRows = Sale::where($salesOnly)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(total) as total, COUNT(*) as orders')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');

        $weeklySales = collect(range(0, max(0, $daysCount - 1)))->map(function ($offset) use ($dailyRows, $start) {
            $day = $start->copy()->addDays($offset);
            $row = $dailyRows->get($day->toDateString());

            return [
                'date' => $day->toDateString(),
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
            'salesOverviewPeriod' => $this->salesOverviewPeriod,
            'salesOverviewPeriodLabel' => $periodLabel,
            'salesOverviewStartDate' => $this->salesOverviewStartDate,
            'salesOverviewEndDate' => $this->salesOverviewEndDate,
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
