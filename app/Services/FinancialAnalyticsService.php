<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\VendorBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FinancialAnalyticsService
{
    /**
     * Cache TTL in seconds for executive KPI aggregations.
     */
    public const CACHE_TTL_SECONDS = 60;

    /**
     * Get instant executive KPIs for a company (Daily vs. Monthly).
     *
     * @return array{
     *     dailyRevenue: float,
     *     monthlyRevenue: float,
     *     dailyProfit: float,
     *     monthlyProfit: float,
     *     dailyProfitMargin: float,
     *     monthlyProfitMargin: float,
     *     dailyOrdersCount: int,
     *     monthlyOrdersCount: int,
     *     dailyAov: float,
     *     monthlyAov: float,
     *     revenueGrowth: float,
     *     monthlyRevenueGrowth: float,
     *     avgItemsPerOrder: float,
     *     currencySymbol: string,
     * }
     */
    public function getExecutiveDashboardKpis(Company $company, bool $forceFresh = false): array
    {
        $cacheKey = "tenant_executive_kpis_{$company->id}";

        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($company) {
            return $this->computeExecutiveKpis($company);
        });
    }

    /**
     * Invalidate cached metrics for a company upon sale or financial events.
     */
    public function clearCache(Company|string $company): void
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        Cache::forget("tenant_executive_kpis_{$companyId}");
    }

    /**
     * Perform the actual database aggregations and calculations.
     */
    protected function computeExecutiveKpis(Company $company): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // 1. Daily Sales & Orders
        $todaySales = Sale::where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        $dailyRevenue = (float) $todaySales->sum('total');
        $dailyOrdersCount = $todaySales->count();
        $dailyAov = $dailyOrdersCount > 0 ? round($dailyRevenue / $dailyOrdersCount, 2) : 0.0;

        // Daily Items Count & Average Items Per Order
        $dailyTotalItems = 0;
        foreach ($todaySales as $s) {
            $items = is_array($s->items) ? $s->items : (json_decode($s->items, true) ?: []);
            foreach ($items as $it) {
                $dailyTotalItems += (float) ($it['quantity'] ?? 1);
            }
        }
        $avgItemsPerOrder = $dailyOrdersCount > 0 ? round($dailyTotalItems / $dailyOrdersCount, 1) : 0.0;

        // 2. Yesterday Sales for Daily Growth %
        $yesterdayRevenue = (float) Sale::where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('total');

        $revenueGrowth = $yesterdayRevenue > 0
            ? round((($dailyRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($dailyRevenue > 0 ? 100.0 : 0.0);

        // 3. Monthly Sales & Orders
        $monthSales = Sale::where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get();

        $monthlyRevenue = (float) $monthSales->sum('total');
        $monthlyOrdersCount = $monthSales->count();
        $monthlyAov = $monthlyOrdersCount > 0 ? round($monthlyRevenue / $monthlyOrdersCount, 2) : 0.0;

        // 4. Previous Month Sales for Monthly Growth %
        $prevMonthRevenue = (float) Sale::where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
            ->sum('total');

        $monthlyRevenueGrowth = $prevMonthRevenue > 0
            ? round((($monthlyRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1)
            : ($monthlyRevenue > 0 ? 100.0 : 0.0);

        // 5. Pre-load Product Cost Prices for COGS
        $productCostMap = Product::where('company_id', $company->id)
            ->pluck('cost_price', 'id')
            ->all();

        // 6. Compute Daily COGS & Net Profit
        $dailyCogs = $this->calculateCogsForSales($todaySales, $productCostMap);
        $dailyExpenses = (float) VendorBill::where('company_id', $company->id)
            ->where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereDate('bill_date', $todayStart->toDateString())
                    ->orWhereBetween('bill_date', [$todayStart, $todayEnd]);
            })
            ->whereNotIn('status', ['cancelled'])
            ->sum('amount');

        $dailyProfit = round($dailyRevenue - $dailyCogs - $dailyExpenses, 2);
        $dailyProfitMargin = $dailyRevenue > 0 ? round(($dailyProfit / $dailyRevenue) * 100, 1) : 0.0;

        // 7. Compute Monthly COGS & Net Profit
        $monthlyCogs = $this->calculateCogsForSales($monthSales, $productCostMap);
        $monthlyExpenses = (float) VendorBill::where('company_id', $company->id)
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->where(function ($sub) use ($monthStart, $monthEnd) {
                    $sub->whereDate('bill_date', '>=', $monthStart->toDateString())
                        ->whereDate('bill_date', '<=', $monthEnd->toDateString());
                })->orWhereBetween('bill_date', [$monthStart, $monthEnd]);
            })
            ->whereNotIn('status', ['cancelled'])
            ->sum('amount');

        $monthlyProfit = round($monthlyRevenue - $monthlyCogs - $monthlyExpenses, 2);
        $monthlyProfitMargin = $monthlyRevenue > 0 ? round(($monthlyProfit / $monthlyRevenue) * 100, 1) : 0.0;

        $dailyCustomersCount = $todaySales->pluck('customer_id')->filter()->unique()->count();
        if ($dailyCustomersCount === 0 && $dailyOrdersCount > 0) {
            $dailyCustomersCount = $dailyOrdersCount;
        }

        $monthlyCustomersCount = $monthSales->pluck('customer_id')->filter()->unique()->count();
        if ($monthlyCustomersCount === 0 && $monthlyOrdersCount > 0) {
            $monthlyCustomersCount = $monthlyOrdersCount;
        }

        return [
            'dailyRevenue' => $dailyRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'dailyProfit' => $dailyProfit,
            'monthlyProfit' => $monthlyProfit,
            'dailyProfitMargin' => $dailyProfitMargin,
            'monthlyProfitMargin' => $monthlyProfitMargin,
            'dailyOrdersCount' => $dailyOrdersCount,
            'monthlyOrdersCount' => $monthlyOrdersCount,
            'dailyCustomersCount' => $dailyCustomersCount,
            'monthlyCustomersCount' => $monthlyCustomersCount,
            'dailyAov' => $dailyAov,
            'monthlyAov' => $monthlyAov,
            'revenueGrowth' => $revenueGrowth,
            'monthlyRevenueGrowth' => $monthlyRevenueGrowth,
            'avgItemsPerOrder' => $avgItemsPerOrder,
            'currencySymbol' => $company->currency_symbol ?: '$',
        ];
    }

    /**
     * Calculate total Cost of Goods Sold (COGS) for a collection of sales.
     *
     * @param  Collection<int, Sale>  $sales
     * @param  array<string|int, float|string>  $productCostMap
     */
    protected function calculateCogsForSales($sales, array $productCostMap): float
    {
        $totalCogs = 0.0;

        foreach ($sales as $sale) {
            $items = is_array($sale->items) ? $sale->items : (json_decode($sale->items, true) ?: []);

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $prodId = $item['product_id'] ?? null;
                $cost = (float) ($item['cost_price'] ?? ($productCostMap[$prodId] ?? 0.0));

                $totalCogs += ($qty * $cost);
            }
        }

        return round($totalCogs, 2);
    }
}
