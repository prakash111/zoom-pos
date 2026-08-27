<?php

namespace App\Livewire\Tenant\Reports;

use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Reports & Financial Analytics'])]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'sales_summary'; // sales_summary | dre | payment_methods | till_closings | commissions | aging

    public string $datePreset = 'this_month'; // today | yesterday | this_week | this_month | last_month | this_year | all_time | custom

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $salespersonFilter = '';

    public ?int $viewingRegisterId = null;

    public function mount(): void
    {
        $requestedTab = request()->query('tab');
        if ($requestedTab && in_array($requestedTab, ['sales_summary', 'dre', 'payment_methods', 'till_closings', 'commissions', 'aging', 'profit_loss'])) {
            $this->activeTab = $requestedTab === 'profit_loss' ? 'dre' : $requestedTab;
        }

        $this->applyDatePreset('this_month');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->dispatch('report-tab-changed', ['tab' => $tab]);
    }

    public function setDatePreset(string $preset): void
    {
        $this->datePreset = $preset;
        $this->applyDatePreset($preset);
        $this->resetPage();
        $this->dispatch('report-filters-updated');
    }

    protected function applyDatePreset(string $preset): void
    {
        $now = now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->copy()->startOfDay()->format('Y-m-d');
                $this->endDate = $now->copy()->endOfDay()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = $now->copy()->subDay()->startOfDay()->format('Y-m-d');
                $this->endDate = $now->copy()->subDay()->endOfDay()->format('Y-m-d');
                break;
            case 'this_week':
                $this->startDate = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->endDate = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->startDate = $now->copy()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->endDate = $now->copy()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $this->startDate = $now->copy()->startOfYear()->format('Y-m-d');
                $this->endDate = $now->copy()->endOfYear()->format('Y-m-d');
                break;
            case 'all_time':
                $this->startDate = null;
                $this->endDate = null;
                break;
            case 'custom':
                // retain existing custom startDate and endDate
                break;
        }
    }

    public function updatedStartDate(): void
    {
        $this->datePreset = 'custom';
        $this->resetPage();
        $this->dispatch('report-filters-updated');
    }

    public function updatedEndDate(): void
    {
        $this->datePreset = 'custom';
        $this->resetPage();
        $this->dispatch('report-filters-updated');
    }

    public function updatedSalespersonFilter(): void
    {
        $this->resetPage();
        $this->dispatch('report-filters-updated');
    }

    protected function companyId(): ?string
    {
        return app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;
    }

    protected function applyDateRange($query, string $dateColumn = 'created_at')
    {
        if ($this->startDate) {
            $query->whereDate($dateColumn, '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate($dateColumn, '<=', $this->endDate);
        }

        return $query;
    }

    protected function filteredSalesQuery()
    {
        $companyId = $this->companyId();

        $query = Sale::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($query);

        if (filled($this->salespersonFilter)) {
            $query->where('user_id', $this->salespersonFilter);
        }

        return $query;
    }

    protected function getPreviousDateRange(): array
    {
        if (! $this->startDate || ! $this->endDate) {
            return [null, null];
        }

        try {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            $days = max(1, $start->diffInDays($end) + 1);

            $prevEnd = $start->copy()->subDay();
            $prevStart = $prevEnd->copy()->subDays($days - 1);

            return [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    protected function calculateGrowth(float $current, float $previous): array
    {
        if ($previous <= 0) {
            $rate = $current > 0 ? 100.0 : 0.0;

            return [
                'value' => $rate,
                'direction' => $current >= 0 ? 'up' : 'down',
                'label' => ($current >= 0 ? '+' : '').number_format($rate, 1).'%',
                'is_positive' => $current >= 0,
            ];
        }

        $diff = $current - $previous;
        $rate = round(($diff / $previous) * 100, 1);

        return [
            'value' => abs($rate),
            'direction' => $rate >= 0 ? 'up' : 'down',
            'label' => ($rate >= 0 ? '+' : '').$rate.'%',
            'is_positive' => $rate >= 0,
        ];
    }

    /**
     * Dynamic 4-Card KPI Metric Grid Property
     */
    public function getKpiMetricsProperty(): array
    {
        $companyId = $this->companyId();

        // Current period completed sales
        $currentQuery = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($currentQuery);

        if (filled($this->salespersonFilter)) {
            $currentQuery->where('user_id', $this->salespersonFilter);
        }

        $currentSales = $currentQuery->get();
        $totalRevenue = (float) $currentSales->sum('total');
        $transactionsCount = $currentSales->count();
        $aov = $transactionsCount > 0 ? round($totalRevenue / $transactionsCount, 2) : 0.0;

        // Previous period completed sales
        [$prevStart, $prevEnd] = $this->getPreviousDateRange();
        $prevRevenue = 0.0;
        $prevTransactions = 0;
        $prevAov = 0.0;

        if ($prevStart && $prevEnd) {
            $prevQuery = Sale::query()
                ->where('company_id', $companyId)
                ->where('status', 'completed')
                ->where(function ($q) {
                    $q->whereNull('operation_type')
                        ->orWhere('operation_type', 'sale');
                })
                ->whereDate('created_at', '>=', $prevStart)
                ->whereDate('created_at', '<=', $prevEnd);

            if (filled($this->salespersonFilter)) {
                $prevQuery->where('user_id', $this->salespersonFilter);
            }

            $prevSales = $prevQuery->get();
            $prevRevenue = (float) $prevSales->sum('total');
            $prevTransactions = $prevSales->count();
            $prevAov = $prevTransactions > 0 ? round($prevRevenue / $prevTransactions, 2) : 0.0;
        }

        $revenueGrowth = $this->calculateGrowth($totalRevenue, $prevRevenue);
        $transactionsGrowth = $this->calculateGrowth((float) $transactionsCount, (float) $prevTransactions);
        $aovGrowth = $this->calculateGrowth($aov, $prevAov);

        // Top contributor determination (Salesperson or Payment Channel)
        $topStaff = User::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $currentSales->pluck('user_id')->filter()->unique())
            ->get()
            ->map(function ($user) use ($currentSales) {
                $rev = (float) $currentSales->where('user_id', $user->id)->sum('total');

                return [
                    'name' => $user->name,
                    'role' => ucfirst($user->role ?? 'Staff'),
                    'revenue' => $rev,
                ];
            })
            ->sortByDesc('revenue')
            ->first();

        // Top payment method
        $topPayment = OrderPayment::query()
            ->where('company_id', $companyId)
            ->whereIn('sale_id', $currentSales->pluck('id'))
            ->selectRaw('payment_method, SUM(amount) as total_amt')
            ->groupBy('payment_method')
            ->orderByDesc('total_amt')
            ->first();

        $topContributor = [
            'label' => $topStaff ? 'Top Sales Representative' : 'Top Payment Gateway',
            'name' => $topStaff ? $topStaff['name'] : ($topPayment ? ucfirst($topPayment->payment_method) : 'General Sales'),
            'amount' => $topStaff ? $topStaff['revenue'] : ($topPayment ? (float) $topPayment->total_amt : $totalRevenue),
            'share' => $totalRevenue > 0 && $topStaff ? round(($topStaff['revenue'] / $totalRevenue) * 100, 1) : 100.0,
            'subtitle' => $topStaff ? $topStaff['role'] : 'Standard Channel',
        ];

        return [
            'total_revenue' => $totalRevenue,
            'previous_revenue' => $prevRevenue,
            'revenue_growth' => $revenueGrowth,
            'transactions_count' => $transactionsCount,
            'previous_transactions' => $prevTransactions,
            'transactions_growth' => $transactionsGrowth,
            'aov' => $aov,
            'previous_aov' => $prevAov,
            'aov_growth' => $aovGrowth,
            'top_contributor' => $topContributor,
        ];
    }

    /**
     * Tab 1: Sales Summary & Aggregates
     */
    public function getSalesSummaryProperty(): array
    {
        $companyId = $this->companyId();

        $baseQuery = Sale::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($baseQuery);

        if (filled($this->salespersonFilter)) {
            $baseQuery->where('user_id', $this->salespersonFilter);
        }

        $completedSales = (clone $baseQuery)->where('status', 'completed')->get();
        $cancelledSales = (clone $baseQuery)->where('status', 'cancelled')->get();

        $grossSales = (float) $completedSales->sum('total');
        $totalDiscount = (float) $completedSales->sum('discount');
        $totalTax = (float) $completedSales->sum('tax');
        $netSales = max(0, $grossSales);
        $totalPaid = (float) $completedSales->sum('paid_amount');
        $totalDue = (float) $completedSales->sum('due_amount');
        $ordersCount = $completedSales->count();
        $aov = $ordersCount > 0 ? round($grossSales / $ordersCount, 2) : 0.0;

        // Calculate total items sold
        $totalItemsSold = 0;
        foreach ($completedSales as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $totalItemsSold += (float) ($item['quantity'] ?? 0);
            }
        }

        return [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'orders_count' => $ordersCount,
            'aov' => $aov,
            'items_sold_count' => $totalItemsSold,
            'cancelled_count' => $cancelledSales->count(),
            'cancelled_value' => (float) $cancelledSales->sum('total'),
        ];
    }

    /**
     * Top Selling Products Breakdown
     */
    public function getTopProductsProperty(): array
    {
        $companyId = $this->companyId();

        $query = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($query);

        if (filled($this->salespersonFilter)) {
            $query->where('user_id', $this->salespersonFilter);
        }

        $sales = $query->get(['items']);
        $productMap = [];

        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $name = $item['name'] ?? 'Custom Item';
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $lineTotal = $qty * $price;

                if (! isset($productMap[$name])) {
                    $productMap[$name] = [
                        'name' => $name,
                        'qty' => 0.0,
                        'revenue' => 0.0,
                    ];
                }

                $productMap[$name]['qty'] += $qty;
                $productMap[$name]['revenue'] += $lineTotal;
            }
        }

        return collect($productMap)
            ->sortByDesc('revenue')
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * Chart: Sales & Orders Daily/Hourly Trend (Tab 1 Area/Spline)
     */
    public function getChartSalesTrendProperty(): array
    {
        $companyId = $this->companyId();

        $query = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($query);

        if (filled($this->salespersonFilter)) {
            $query->where('user_id', $this->salespersonFilter);
        }

        $sales = $query->get(['created_at', 'total']);

        // Decide grouping granularity: hourly (if 1 day) or daily
        $isSingleDay = $this->startDate && $this->endDate && $this->startDate === $this->endDate;

        if ($isSingleDay) {
            $categories = [];
            $revenueSeries = [];
            $ordersSeries = [];

            for ($h = 0; $h < 24; $h++) {
                $hourLabel = sprintf('%02d:00', $h);
                $categories[] = $hourLabel;
                $matching = $sales->filter(fn ($s) => $s->created_at && (int) $s->created_at->format('G') === $h);
                $revenueSeries[] = (float) round($matching->sum('total'), 2);
                $ordersSeries[] = $matching->count();
            }

            return [
                'categories' => $categories,
                'revenue' => $revenueSeries,
                'orders' => $ordersSeries,
            ];
        }

        // Daily grouping
        $start = $this->startDate ? Carbon::parse($this->startDate) : ($sales->min('created_at') ? Carbon::parse($sales->min('created_at')) : now()->subDays(14));
        $end = $this->endDate ? Carbon::parse($this->endDate) : now();

        if ($start->diffInDays($end) > 60) {
            $start = $end->copy()->subDays(60);
        }

        $categories = [];
        $revenueSeries = [];
        $ordersSeries = [];

        $period = CarbonPeriod::create($start->startOfDay(), '1 day', $end->endOfDay());

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $categories[] = $date->format('d M');
            $matching = $sales->filter(fn ($s) => $s->created_at && $s->created_at->format('Y-m-d') === $dateStr);
            $revenueSeries[] = (float) round($matching->sum('total'), 2);
            $ordersSeries[] = $matching->count();
        }

        return [
            'categories' => $categories,
            'revenue' => $revenueSeries,
            'orders' => $ordersSeries,
        ];
    }

    /**
     * Chart: Top 5 Best-Selling Products (Tab 1 Horizontal Bar)
     */
    public function getChartTopProductsProperty(): array
    {
        $top5 = array_slice($this->topProducts, 0, 5);

        return [
            'categories' => array_column($top5, 'name'),
            'revenues' => array_map(fn ($item) => round((float) ($item['revenue'] ?? 0), 2), $top5),
            'quantities' => array_map(fn ($item) => round((float) ($item['qty'] ?? 0), 1), $top5),
        ];
    }

    /**
     * Tab 2: Payment Methods Breakdown
     */
    public function getPaymentMethodsSummaryProperty(): array
    {
        $companyId = $this->companyId();

        $query = OrderPayment::query()
            ->where('company_id', $companyId)
            ->whereHas('sale', function ($q) {
                $q->where('status', 'completed');
            });

        $this->applyDateRange($query, 'order_payments.created_at');

        if (filled($this->salespersonFilter)) {
            $query->whereHas('sale', fn ($q) => $q->where('user_id', $this->salespersonFilter));
        }

        $records = $query->selectRaw('payment_method, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payment_method')
            ->get();

        $grandTotal = (float) $records->sum('total_amount');

        return $records->map(function ($row) use ($grandTotal) {
            $amt = (float) $row->total_amount;
            $share = $grandTotal > 0 ? round(($amt / $grandTotal) * 100, 1) : 0.0;

            return [
                'method' => $row->payment_method ?: 'cash',
                'amount' => $amt,
                'count' => (int) $row->transaction_count,
                'share' => $share,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    /**
     * Chart: Payment Methods Breakdown (Tab 2 Donut)
     */
    public function getChartPaymentMethodsProperty(): array
    {
        $summary = $this->paymentMethodsSummary;
        $labels = [];
        $series = [];
        $percentages = [];
        $totalGross = 0.0;

        foreach ($summary as $item) {
            $labels[] = ucfirst((string) $item['method']);
            $series[] = round((float) $item['amount'], 2);
            $percentages[] = (float) $item['share'];
            $totalGross += (float) $item['amount'];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'percentages' => $percentages,
            'total_gross' => round($totalGross, 2),
        ];
    }

    /**
     * Tab 3: Till Closings & Cash Register Audits
     */
    public function getTillClosingsProperty()
    {
        $companyId = $this->companyId();

        $query = CashRegister::query()
            ->with(['opener', 'closer'])
            ->where('company_id', $companyId)
            ->where('status', 'closed');

        $this->applyDateRange($query, 'opened_at');

        return $query->orderByDesc('opened_at')->paginate(12, ['*'], 'registersPage');
    }

    /**
     * Chart: Till Closings Audit Comparison (Tab 3 Grouped Bar)
     */
    public function getChartTillClosingsProperty(): array
    {
        $companyId = $this->companyId();

        $query = CashRegister::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed');

        $this->applyDateRange($query, 'opened_at');

        $registers = $query->orderByDesc('opened_at')->take(10)->get()->reverse();

        $categories = [];
        $expected = [];
        $counted = [];
        $discrepancy = [];

        foreach ($registers as $reg) {
            $label = '#'.$reg->id.' ('.($reg->opened_at ? $reg->opened_at->format('d M') : 'Till').')';
            $categories[] = $label;
            $exp = (float) ($reg->expected_cash ?? 0);
            $cnt = (float) ($reg->closing_cash ?? 0);
            $expected[] = round($exp, 2);
            $counted[] = round($cnt, 2);
            $discrepancy[] = round($cnt - $exp, 2);
        }

        return [
            'categories' => $categories,
            'expected' => $expected,
            'counted' => $counted,
            'discrepancy' => $discrepancy,
        ];
    }

    public function viewRegister(int $id): void
    {
        $this->viewingRegisterId = $id;
    }

    public function closeViewRegister(): void
    {
        $this->viewingRegisterId = null;
    }

    /**
     * Tab 4: Salesperson Performance & Commissions Ledger
     */
    public function getCommissionReportProperty(): array
    {
        $companyId = $this->companyId();

        $query = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $this->applyDateRange($query);

        if (filled($this->salespersonFilter)) {
            $query->where('user_id', $this->salespersonFilter);
        }

        $sales = $query->with('user')->get();
        $users = User::where('company_id', $companyId)->get()->keyBy('id');

        $staffMap = [];

        foreach ($sales as $sale) {
            $uId = $sale->user_id ?? 0;
            $user = $users->get($uId) ?? $sale->user;
            $uName = $user?->name ?? 'Unassigned / Counter';
            $uRole = ucfirst($user?->role ?? 'Staff');
            $commRate = (float) ($sale->commission_rate ?? ($user?->commission_rate ?? 0));
            $commType = (string) ($sale->commission_type ?? ($user?->commission_type ?? 'percentage'));
            $commAmount = (float) ($sale->commission_amount ?? 0);

            if (! isset($staffMap[$uId])) {
                $staffMap[$uId] = [
                    'user_id' => $uId,
                    'name' => $uName,
                    'role' => $uRole,
                    'sales_count' => 0,
                    'total_revenue' => 0.0,
                    'commission_rate' => $commRate,
                    'commission_type' => $commType,
                    'formatted_rate' => app(\App\Services\CommissionService::class)->formatRate($commRate, $commType),
                    'total_commission' => 0.0,
                ];
            }

            $staffMap[$uId]['sales_count']++;
            $staffMap[$uId]['total_revenue'] += (float) $sale->total;
            $staffMap[$uId]['total_commission'] += $commAmount;
        }

        return collect($staffMap)->sortByDesc('total_revenue')->values()->all();
    }

    /**
     * Chart: Staff Sales vs Commissions (Tab 4 Stacked Column)
     */
    public function getChartCommissionsProperty(): array
    {
        $report = $this->commissionReport;
        $categories = [];
        $netSales = [];
        $commissions = [];
        $orderCounts = [];

        foreach ($report as $row) {
            $categories[] = $row['name'];
            $netSales[] = round(max(0, (float) $row['total_revenue'] - (float) $row['total_commission']), 2);
            $commissions[] = round((float) $row['total_commission'], 2);
            $orderCounts[] = (int) $row['sales_count'];
        }

        return [
            'categories' => $categories,
            'net_sales' => $netSales,
            'commissions' => $commissions,
            'order_counts' => $orderCounts,
        ];
    }

    /**
     * Tab 5: Accounts Receivable Aging Analysis (0-30, 31-60, 61-90, 90+)
     */
    public function getAgingReportProperty(): array
    {
        $companyId = $this->companyId();
        $now = now();

        $unpaidSales = Sale::query()
            ->with('customer')
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->orderBy('due_date')
            ->get();

        $brackets = [
            '0_30' => ['label' => '0 - 30 Days (Current)', 'amount' => 0.0, 'count' => 0, 'color' => '#10b981'],
            '31_60' => ['label' => '31 - 60 Days (Past Due)', 'amount' => 0.0, 'count' => 0, 'color' => '#eab308'],
            '61_90' => ['label' => '61 - 90 Days (Late)', 'amount' => 0.0, 'count' => 0, 'color' => '#f97316'],
            '90_plus' => ['label' => '90+ Days (Critical)', 'amount' => 0.0, 'count' => 0, 'color' => '#ef4444'],
        ];

        $customerLedgers = [];

        foreach ($unpaidSales as $sale) {
            $dueDate = $sale->due_date ?? $sale->created_at;
            $daysDiff = max(0, $dueDate->diffInDays($now, false));
            $dueAmt = (float) $sale->due_amount;

            if ($daysDiff <= 30) {
                $brackets['0_30']['amount'] += $dueAmt;
                $brackets['0_30']['count']++;
            } elseif ($daysDiff <= 60) {
                $brackets['31_60']['amount'] += $dueAmt;
                $brackets['31_60']['count']++;
            } elseif ($daysDiff <= 90) {
                $brackets['61_90']['amount'] += $dueAmt;
                $brackets['61_90']['count']++;
            } else {
                $brackets['90_plus']['amount'] += $dueAmt;
                $brackets['90_plus']['count']++;
            }

            $cId = $sale->customer_id ?? 0;
            $cName = $sale->customer?->name ?? ($sale->customer_name ?: 'Walk-in Client');
            $cPhone = $sale->customer?->phone ?? '';

            if (! isset($customerLedgers[$cId])) {
                $customerLedgers[$cId] = [
                    'customer_id' => $cId,
                    'name' => $cName,
                    'phone' => $cPhone,
                    'total_due' => 0.0,
                    'oldest_due_date' => $dueDate,
                    'days_overdue' => $daysDiff,
                    'invoices_count' => 0,
                ];
            }

            $customerLedgers[$cId]['total_due'] += $dueAmt;
            $customerLedgers[$cId]['invoices_count']++;
            if ($dueDate->lt($customerLedgers[$cId]['oldest_due_date'])) {
                $customerLedgers[$cId]['oldest_due_date'] = $dueDate;
                $customerLedgers[$cId]['days_overdue'] = $daysDiff;
            }
        }

        $totalReceivables = (float) $unpaidSales->sum('due_amount');

        return [
            'total_receivables' => $totalReceivables,
            'brackets' => $brackets,
            'customers' => collect($customerLedgers)->sortByDesc('total_due')->values()->all(),
        ];
    }

    /**
     * Chart: Aging Distribution (Tab 5 Donut / Bar)
     */
    public function getChartAgingProperty(): array
    {
        $aging = $this->agingReport;
        $labels = [];
        $series = [];
        $colors = [];
        $counts = [];

        foreach ($aging['brackets'] as $b) {
            $labels[] = $b['label'];
            $series[] = round((float) $b['amount'], 2);
            $colors[] = $b['color'];
            $counts[] = (int) $b['count'];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
            'counts' => $counts,
            'total' => round((float) $aging['total_receivables'], 2),
        ];
    }

    public function exportCsv()
    {
        $filename = 'report-'.$this->activeTab.'-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            if ($this->activeTab === 'sales_summary') {
                fputcsv($file, ['Metric', 'Value']);
                $s = $this->salesSummary;
                fputcsv($file, ['Gross Sales', number_format($s['gross_sales'], 2)]);
                fputcsv($file, ['Discounts Given', number_format($s['total_discount'], 2)]);
                fputcsv($file, ['Sales Tax Collected', number_format($s['total_tax'], 2)]);
                fputcsv($file, ['Total Paid Collected', number_format($s['total_paid'], 2)]);
                fputcsv($file, ['Outstanding Receivables', number_format($s['total_due'], 2)]);
                fputcsv($file, ['Orders Count', $s['orders_count']]);
                fputcsv($file, ['Average Order Value', number_format($s['aov'], 2)]);
                fputcsv($file, ['Total Items Sold', $s['items_sold_count']]);

                fputcsv($file, []);
                fputcsv($file, ['Top Products', 'Units Sold', 'Gross Revenue']);
                foreach ($this->topProducts as $tp) {
                    fputcsv($file, [$tp['name'], $tp['qty'], number_format($tp['revenue'], 2)]);
                }
            } elseif ($this->activeTab === 'payment_methods') {
                fputcsv($file, ['Payment Method', 'Transaction Count', 'Total Amount', 'Share %']);
                foreach ($this->paymentMethodsSummary as $pm) {
                    fputcsv($file, [ucfirst($pm['method']), $pm['count'], number_format($pm['amount'], 2), $pm['share'].'%']);
                }
            } elseif ($this->activeTab === 'commissions') {
                fputcsv($file, ['Staff Name', 'Role', 'Orders Count', 'Total Sales Revenue', 'Commission Rate', 'Total Commission Earned']);
                foreach ($this->commissionReport as $comm) {
                    fputcsv($file, [$comm['name'], $comm['role'], $comm['sales_count'], number_format($comm['total_revenue'], 2), $comm['commission_rate'].'%', number_format($comm['total_commission'], 2)]);
                }
            } elseif ($this->activeTab === 'aging') {
                fputcsv($file, ['Aging Bracket', 'Invoices Count', 'Outstanding Total']);
                $aging = $this->agingReport;
                foreach ($aging['brackets'] as $b) {
                    fputcsv($file, [$b['label'], $b['count'], number_format($b['amount'], 2)]);
                }
                fputcsv($file, []);
                fputcsv($file, ['Customer Name', 'Phone', 'Oldest Due Date', 'Days Overdue', 'Unpaid Invoices', 'Outstanding Due']);
                foreach ($aging['customers'] as $c) {
                    fputcsv($file, [$c['name'], $c['phone'], $c['oldest_due_date']->format('Y-m-d'), $c['days_overdue'], $c['invoices_count'], number_format($c['total_due'], 2)]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getDreStatementProperty(): array
    {
        $sales = $this->filteredSalesQuery()
            ->where('status', '!=', 'cancelled')
            ->get();

        $grossSales = (float) $sales->sum('total') + (float) $sales->sum('discount');
        $discounts = (float) $sales->sum('discount');
        $taxes = (float) $sales->sum('tax_amount');
        $netRevenue = round($grossSales - $discounts - $taxes, 2);

        // COGS (Cost of goods sold)
        $cogs = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $it) {
                $prodId = $it['product_id'] ?? null;
                $qty = (float) ($it['quantity'] ?? 0);
                if ($prodId) {
                    $p = Product::find($prodId);
                    $cost = $p ? (float) ($p->cost_price ?? ($p->purchase_price ?? 0)) : 0;
                    $cogs += ($qty * $cost);
                }
            }
        }
        $cogs = round($cogs, 2);
        $grossProfit = round($netRevenue - $cogs, 2);

        // Operating expenses: card fees, commissions, register expenses
        $cardFees = (float) $sales->sum('merchant_fee_amount');
        $commissions = (float) $sales->sum('commission_amount');

        $companyId = $this->companyId();
        $cashExpenses = (float) CashRegisterTransaction::where('company_id', $companyId)
            ->whereIn('type', ['drop', 'pay_out'])
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->sum('amount');

        $totalOperatingExpenses = round($cardFees + $commissions + $cashExpenses, 2);
        $ebitda = round($grossProfit - $totalOperatingExpenses, 2);
        $netMargin = $grossSales > 0 ? round(($ebitda / $grossSales) * 100, 2) : 0.0;
        $grossMargin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0;

        return [
            'gross_revenue' => $grossSales,
            'discounts' => $discounts,
            'taxes' => $taxes,
            'net_revenue' => $netRevenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'card_fees' => $cardFees,
            'commissions' => $commissions,
            'cash_expenses' => $cashExpenses,
            'operating_expenses' => $totalOperatingExpenses,
            'ebitda' => $ebitda,
            'net_margin' => $netMargin,
            'orders_count' => $sales->count(),
        ];
    }

    public function render()
    {
        $companyId = $this->companyId();
        $company = auth('web')->user()?->company;
        $users = User::where('company_id', $companyId)->orderBy('name')->get();

        $viewingRegister = $this->viewingRegisterId
            ? CashRegister::with('opener', 'closer', 'transactions')->find($this->viewingRegisterId)
            : null;

        return view('livewire.tenant.reports.index', [
            'company' => $company,
            'users' => $users,
            'kpiMetrics' => $this->kpiMetrics,
            'salesSummary' => $this->salesSummary,
            'dreStatement' => $this->dreStatement,
            'topProducts' => $this->topProducts,
            'paymentMethodsSummary' => $this->paymentMethodsSummary,
            'tillClosings' => $this->activeTab === 'till_closings' ? $this->tillClosings : collect(),
            'commissionReport' => $this->commissionReport,
            'agingReport' => $this->agingReport,
            'chartSalesTrend' => $this->chartSalesTrend,
            'chartTopProducts' => $this->chartTopProducts,
            'chartPaymentMethods' => $this->chartPaymentMethods,
            'chartTillClosings' => $this->chartTillClosings,
            'chartCommissions' => $this->chartCommissions,
            'chartAging' => $this->chartAging,
            'viewingRegister' => $viewingRegister,
        ]);
    }
}
