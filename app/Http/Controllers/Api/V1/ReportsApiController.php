<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Company;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Ports app/Livewire/Tenant/Reports/Index.php's computed properties
 * (getKpiMetricsProperty, getSalesSummaryProperty, getTopProductsProperty,
 * getPaymentMethodsSummaryProperty, getTillClosingsProperty,
 * getCommissionReportProperty, getAgingReportProperty, getDreStatementProperty,
 * exportCsv) into plain JSON/CSV endpoints — same queries, same numbers as
 * the web Reports tab, just without the Livewire view state.
 */
class ReportsApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function summary(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);
        $salesperson = $request->query('salesperson_id');

        return response()->json([
            'success' => true,
            'kpis' => $this->kpiMetrics($company, $start, $end, $salesperson),
            'summary' => $this->salesSummary($company, $start, $end, $salesperson),
            'top_products' => $this->topProducts($company, $start, $end, $salesperson),
        ]);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);

        return response()->json(['success' => true, 'dre' => $this->dreStatement($company, $start, $end)]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);
        $salesperson = $request->query('salesperson_id');

        return response()->json([
            'success' => true,
            'payment_methods' => $this->paymentMethodsSummary($company, $start, $end, $salesperson),
        ]);
    }

    public function tillClosings(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);

        $registers = CashRegister::withoutGlobalScope('company')
            ->with(['opener', 'closer'])
            ->where('company_id', $company->id)
            ->where('status', 'closed')
            ->when($start, fn ($q) => $q->whereDate('opened_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('opened_at', '<=', $end))
            ->orderByDesc('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (CashRegister $r) => [
                'id' => $r->id,
                'terminal_id' => $r->terminal_id,
                'opened_at' => $r->opened_at?->toIso8601String(),
                'closed_at' => $r->closed_at?->toIso8601String(),
                'opened_by_name' => $r->opener?->name ?? 'Cashier',
                'closed_by_name' => $r->closer?->name ?? '—',
                'opening_balance' => (float) $r->opening_balance,
                'expected_closing_balance' => (float) $r->expected_closing_balance,
                'counted_closing_balance' => (float) $r->counted_closing_balance,
                'cash_difference' => (float) $r->cash_difference,
            ]);

        return response()->json(['success' => true, 'till_closings' => $registers]);
    }

    public function commissions(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);
        $salesperson = $request->query('salesperson_id');

        return response()->json([
            'success' => true,
            'commissions' => $this->commissionReport($company, $start, $end, $salesperson),
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json(['success' => true] + $this->agingReport($company));
    }

    public function export(Request $request)
    {
        $company = $this->resolveCompany($request);
        [$start, $end] = $this->dateRange($request);
        $report = $request->query('report', 'sales_summary');
        $filename = 'report-'.$report.'-'.now()->format('Ymd-His').'.csv';

        $rows = [];

        if ($report === 'sales_summary') {
            $s = $this->salesSummary($company, $start, $end, null);
            $rows[] = ['Metric', 'Value'];
            $rows[] = ['Gross Sales', number_format($s['gross_sales'], 2)];
            $rows[] = ['Discounts Given', number_format($s['total_discount'], 2)];
            $rows[] = ['Sales Tax Collected', number_format($s['total_tax'], 2)];
            $rows[] = ['Total Paid Collected', number_format($s['total_paid'], 2)];
            $rows[] = ['Outstanding Receivables', number_format($s['total_due'], 2)];
            $rows[] = ['Orders Count', $s['orders_count']];
            $rows[] = ['Average Order Value', number_format($s['aov'], 2)];
            $rows[] = ['Total Items Sold', $s['items_sold_count']];
            $rows[] = [];
            $rows[] = ['Top Products', 'Units Sold', 'Gross Revenue'];
            foreach ($this->topProducts($company, $start, $end, null) as $tp) {
                $rows[] = [$tp['name'], $tp['qty'], number_format($tp['revenue'], 2)];
            }
        } elseif ($report === 'payment_methods') {
            $rows[] = ['Payment Method', 'Transaction Count', 'Total Amount', 'Share %'];
            foreach ($this->paymentMethodsSummary($company, $start, $end, null) as $pm) {
                $rows[] = [ucfirst($pm['method']), $pm['count'], number_format($pm['amount'], 2), $pm['share'].'%'];
            }
        } elseif ($report === 'commissions') {
            $rows[] = ['Staff Name', 'Role', 'Orders Count', 'Total Sales Revenue', 'Commission Rate', 'Total Commission Earned'];
            foreach ($this->commissionReport($company, $start, $end, null) as $c) {
                $rows[] = [$c['name'], $c['role'], $c['sales_count'], number_format($c['total_revenue'], 2), $c['formatted_rate'], number_format($c['total_commission'], 2)];
            }
        } elseif ($report === 'aging') {
            $aging = $this->agingReport($company);
            $rows[] = ['Aging Bracket', 'Invoices Count', 'Outstanding Total'];
            foreach ($aging['brackets'] as $b) {
                $rows[] = [$b['label'], $b['count'], number_format($b['amount'], 2)];
            }
            $rows[] = [];
            $rows[] = ['Customer Name', 'Phone', 'Oldest Due Date', 'Days Overdue', 'Unpaid Invoices', 'Outstanding Due'];
            foreach ($aging['customers'] as $c) {
                $rows[] = [$c['name'], $c['phone'], $c['oldest_due_date']->format('Y-m-d'), $c['days_overdue'], $c['invoices_count'], number_format($c['total_due'], 2)];
            }
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row))."\r\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function dateRange(Request $request): array
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        if (! $start && ! $end) {
            $now = now();
            $start = $now->copy()->startOfMonth()->format('Y-m-d');
            $end = $now->copy()->endOfMonth()->format('Y-m-d');
        }

        return [$start, $end];
    }

    private function baseSalesQuery(Company $company, ?string $start, ?string $end, ?string $salesperson)
    {
        $query = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->whereNull('operation_type')->orWhere('operation_type', 'sale'));

        if ($start) {
            $query->whereDate('created_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('created_at', '<=', $end);
        }
        if ($salesperson) {
            $query->where('user_id', $salesperson);
        }

        return $query;
    }

    private function previousDateRange(?string $start, ?string $end): array
    {
        if (! $start || ! $end) {
            return [null, null];
        }

        try {
            $startC = Carbon::parse($start);
            $endC = Carbon::parse($end);
            $days = max(1, $startC->diffInDays($endC) + 1);
            $prevEnd = $startC->copy()->subDay();
            $prevStart = $prevEnd->copy()->subDays($days - 1);

            return [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private function growth(float $current, float $previous): array
    {
        if ($previous <= 0) {
            $rate = $current > 0 ? 100.0 : 0.0;

            return ['value' => $rate, 'direction' => $current >= 0 ? 'up' : 'down', 'is_positive' => $current >= 0];
        }

        $rate = round((($current - $previous) / $previous) * 100, 1);

        return ['value' => abs($rate), 'direction' => $rate >= 0 ? 'up' : 'down', 'is_positive' => $rate >= 0];
    }

    private function kpiMetrics(Company $company, ?string $start, ?string $end, ?string $salesperson): array
    {
        $currentSales = (clone $this->baseSalesQuery($company, $start, $end, $salesperson))
            ->where('status', 'completed')->get();

        $totalRevenue = (float) $currentSales->sum('total');
        $transactionsCount = $currentSales->count();
        $aov = $transactionsCount > 0 ? round($totalRevenue / $transactionsCount, 2) : 0.0;

        [$prevStart, $prevEnd] = $this->previousDateRange($start, $end);
        $prevRevenue = 0.0;
        $prevTransactions = 0;

        if ($prevStart && $prevEnd) {
            $prevSales = (clone $this->baseSalesQuery($company, $prevStart, $prevEnd, $salesperson))
                ->where('status', 'completed')->get();
            $prevRevenue = (float) $prevSales->sum('total');
            $prevTransactions = $prevSales->count();
        }

        $prevAov = $prevTransactions > 0 ? round($prevRevenue / $prevTransactions, 2) : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'revenue_growth' => $this->growth($totalRevenue, $prevRevenue),
            'transactions_count' => $transactionsCount,
            'transactions_growth' => $this->growth((float) $transactionsCount, (float) $prevTransactions),
            'aov' => $aov,
            'aov_growth' => $this->growth($aov, $prevAov),
        ];
    }

    private function salesSummary(Company $company, ?string $start, ?string $end, ?string $salesperson): array
    {
        $base = $this->baseSalesQuery($company, $start, $end, $salesperson);
        $completed = (clone $base)->where('status', 'completed')->get();
        $cancelled = (clone $base)->where('status', 'cancelled')->get();

        $grossSales = (float) $completed->sum('total');
        $ordersCount = $completed->count();

        $itemsSold = 0.0;
        foreach ($completed as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $itemsSold += (float) ($item['quantity'] ?? 0);
            }
        }

        return [
            'gross_sales' => $grossSales,
            'net_sales' => max(0, $grossSales),
            'total_discount' => (float) $completed->sum('discount'),
            'total_tax' => (float) $completed->sum('tax_amount'),
            'total_paid' => (float) $completed->sum('paid_amount'),
            'total_due' => (float) $completed->sum('due_amount'),
            'orders_count' => $ordersCount,
            'aov' => $ordersCount > 0 ? round($grossSales / $ordersCount, 2) : 0.0,
            'items_sold_count' => $itemsSold,
            'cancelled_count' => $cancelled->count(),
            'cancelled_value' => (float) $cancelled->sum('total'),
        ];
    }

    private function topProducts(Company $company, ?string $start, ?string $end, ?string $salesperson): array
    {
        $sales = (clone $this->baseSalesQuery($company, $start, $end, $salesperson))
            ->where('status', 'completed')->get(['items']);

        $map = [];
        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $item) {
                $name = $item['name'] ?? 'Custom Item';
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? 0);

                $map[$name] ??= ['name' => $name, 'qty' => 0.0, 'revenue' => 0.0];
                $map[$name]['qty'] += $qty;
                $map[$name]['revenue'] += $qty * $price;
            }
        }

        return collect($map)->sortByDesc('revenue')->take(15)->values()->all();
    }

    private function paymentMethodsSummary(Company $company, ?string $start, ?string $end, ?string $salesperson): array
    {
        $query = OrderPayment::query()
            ->where('company_id', $company->id)
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'));

        if ($start) {
            $query->whereDate('order_payments.created_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('order_payments.created_at', '<=', $end);
        }
        if ($salesperson) {
            $query->whereHas('sale', fn ($q) => $q->where('user_id', $salesperson));
        }

        $records = $query->selectRaw('payment_method, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payment_method')->get();

        $grandTotal = (float) $records->sum('total_amount');

        return $records->map(function ($row) use ($grandTotal) {
            $amt = (float) $row->total_amount;

            return [
                'method' => $row->payment_method ?: 'cash',
                'amount' => $amt,
                'count' => (int) $row->transaction_count,
                'share' => $grandTotal > 0 ? round(($amt / $grandTotal) * 100, 1) : 0.0,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    private function commissionReport(Company $company, ?string $start, ?string $end, ?string $salesperson): array
    {
        $sales = (clone $this->baseSalesQuery($company, $start, $end, $salesperson))
            ->where('status', 'completed')->with('user')->get();
        $users = User::withoutGlobalScope('company')->where('company_id', $company->id)->get()->keyBy('id');

        $staffMap = [];
        foreach ($sales as $sale) {
            $uId = $sale->user_id ?? 0;
            $user = $users->get($uId) ?? $sale->user;
            $commRate = (float) ($sale->commission_rate ?? ($user->commission_rate ?? 0));
            $commType = (string) ($sale->commission_type ?? ($user->commission_type ?? 'percentage'));

            $staffMap[$uId] ??= [
                'user_id' => $uId,
                'name' => $user?->name ?? 'Unassigned / Counter',
                'role' => ucfirst($user?->role ?? 'Staff'),
                'sales_count' => 0,
                'total_revenue' => 0.0,
                'commission_rate' => $commRate,
                'formatted_rate' => app(CommissionService::class)->formatRate($commRate, $commType),
                'total_commission' => 0.0,
            ];

            $staffMap[$uId]['sales_count']++;
            $staffMap[$uId]['total_revenue'] += (float) $sale->total;
            $staffMap[$uId]['total_commission'] += (float) ($sale->commission_amount ?? 0);
        }

        return collect($staffMap)->sortByDesc('total_revenue')->values()->all();
    }

    private function agingReport(Company $company): array
    {
        $now = now();
        $unpaidSales = Sale::query()
            ->withoutGlobalScope('company')
            ->with('customer')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->orderBy('due_date')
            ->get();

        $brackets = [
            '0_30' => ['label' => '0 - 30 Days (Current)', 'amount' => 0.0, 'count' => 0],
            '31_60' => ['label' => '31 - 60 Days (Past Due)', 'amount' => 0.0, 'count' => 0],
            '61_90' => ['label' => '61 - 90 Days (Late)', 'amount' => 0.0, 'count' => 0],
            '90_plus' => ['label' => '90+ Days (Critical)', 'amount' => 0.0, 'count' => 0],
        ];

        $customerLedgers = [];

        foreach ($unpaidSales as $sale) {
            $dueDate = $sale->due_date ?? $sale->created_at;
            $daysDiff = max(0, $dueDate->diffInDays($now, false));
            $dueAmt = (float) $sale->due_amount;
            $bracket = $daysDiff <= 30 ? '0_30' : ($daysDiff <= 60 ? '31_60' : ($daysDiff <= 90 ? '61_90' : '90_plus'));
            $brackets[$bracket]['amount'] += $dueAmt;
            $brackets[$bracket]['count']++;

            $cId = $sale->customer_id ?? 0;
            $customerLedgers[$cId] ??= [
                'customer_id' => $cId,
                'name' => $sale->customer?->name ?? ($sale->customer_name ?: 'Walk-in Client'),
                'phone' => $sale->customer?->phone ?? '',
                'total_due' => 0.0,
                'oldest_due_date' => $dueDate,
                'days_overdue' => $daysDiff,
                'invoices_count' => 0,
            ];

            $customerLedgers[$cId]['total_due'] += $dueAmt;
            $customerLedgers[$cId]['invoices_count']++;
            if ($dueDate->lt($customerLedgers[$cId]['oldest_due_date'])) {
                $customerLedgers[$cId]['oldest_due_date'] = $dueDate;
                $customerLedgers[$cId]['days_overdue'] = $daysDiff;
            }
        }

        return [
            'total_receivables' => (float) $unpaidSales->sum('due_amount'),
            'brackets' => $brackets,
            'customers' => collect($customerLedgers)->sortByDesc('total_due')->values()->all(),
        ];
    }

    private function dreStatement(Company $company, ?string $start, ?string $end): array
    {
        $sales = $this->baseSalesQuery($company, $start, $end, null)->where('status', '!=', 'cancelled')->get();

        $grossSales = (float) $sales->sum('total') + (float) $sales->sum('discount');
        $discounts = (float) $sales->sum('discount');
        $taxes = (float) $sales->sum('tax_amount');
        $netRevenue = round($grossSales - $discounts - $taxes, 2);

        $cogs = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->items ?? [] as $it) {
                $prodId = $it['product_id'] ?? null;
                $qty = (float) ($it['quantity'] ?? 0);
                if ($prodId) {
                    $p = Product::withoutGlobalScope('company')->find($prodId);
                    $cost = $p ? (float) ($p->cost_price ?? 0) : 0;
                    $cogs += $qty * $cost;
                }
            }
        }
        $cogs = round($cogs, 2);
        $grossProfit = round($netRevenue - $cogs, 2);

        $cardFees = (float) $sales->sum('merchant_fee_amount');
        $commissions = (float) $sales->sum('commission_amount');

        $cashExpenses = 0.0;
        if ($start && $end) {
            $cashExpenses = (float) CashRegisterTransaction::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereIn('type', ['cash_out'])
                ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->sum('amount');
        }

        $operatingExpenses = round($cardFees + $commissions + $cashExpenses, 2);
        $ebitda = round($grossProfit - $operatingExpenses, 2);

        return [
            'gross_revenue' => $grossSales,
            'discounts' => $discounts,
            'taxes' => $taxes,
            'net_revenue' => $netRevenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0,
            'card_fees' => $cardFees,
            'commissions' => $commissions,
            'cash_expenses' => $cashExpenses,
            'operating_expenses' => $operatingExpenses,
            'ebitda' => $ebitda,
            'net_margin' => $grossSales > 0 ? round(($ebitda / $grossSales) * 100, 2) : 0.0,
            'orders_count' => $sales->count(),
        ];
    }
}
