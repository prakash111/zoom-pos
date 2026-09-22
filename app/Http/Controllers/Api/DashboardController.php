<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\NotificationAlertService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(private readonly NotificationAlertService $alerts) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        $company = $this->resolveCompany($request);
        if (! $tenantId) {
            $tenantId = $company->id;
        }

        $schema = SchemaResponse::dashboardView($company);
        $schema['app_bar'] = [
            'title' => 'Dashboard',
            'show_back_button' => false,
            'actions' => [
                // Quick Sync / Reload
                [
                    'type'        => 'icon_button',
                    'icon'        => 'sync',
                    'action_type' => 'REFRESH_DASHBOARD',
                    'action'      => ['type' => 'REFRESH_DASHBOARD'],
                ],
                // Theme Selector (Match Device / Light / Dark)
                [
                    'type'    => 'theme_selector_dropdown',
                    'current' => 'match_device',
                ],
                // Notification Bell Icon (Replacing the previous logout button)
                [
                    'type'        => 'notification_bell',
                    'icon'        => 'notifications_none',
                    'badge_count' => $this->getUnreadNotificationsCount($tenantId),
                    'action'      => [
                        'type'     => 'OPEN_BOTTOM_SHEET',
                        'title'    => 'System Alerts & Reminders',
                        'endpoint' => '/api/v1/tenant/notifications/feed',
                    ],
                ],
            ],
        ];
        $schema['theme'] = [
            'surface'      => 'theme.surface',
            'canvas'       => 'theme.canvas',
            'divider'      => 'theme.divider',
            'text_primary' => 'theme.textPrimary',
        ];

        return response()->json([
            'success' => true,
            'view'    => 'dashboard',
            'schema'  => $schema,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $tenantId = $company->id;
        $user = auth('sanctum')->user() ?? auth('web')->user() ?? $request->user();

        $tz = method_exists($company, 'resolveTimezone') ? $company->resolveTimezone() : ($company->timezone ?: 'UTC');
        $now = Carbon::now($tz);
        $hour = (int) $now->format('G');
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Good Morning',
            $hour >= 12 && $hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };

        $userName = $user?->name ?: 'Store Manager';
        $userRole = ucfirst($user?->role ?: 'Store Manager');
        $greetingTitle = "{$timeGreeting}, {$userName}!";
        $greetingSubtitle = "Here's what's happening at your store today.";

        $initials = '?';
        $nameParts = preg_split('/\s+/', trim($userName));
        if (! empty($nameParts)) {
            $first = mb_substr($nameParts[0], 0, 1);
            $last = count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '';
            $initials = strtoupper($first . $last);
        }

        $currency = $company->currency_symbol ?: ($company->currency ?: '$');
        $storeName = $company->trade_name ?: ($company->name ?: 'MetroRetail');
        $tagline = 'Smarter Retail. Faster Growth.';
        $logoUrl = $company->logo ? asset($company->logo) : null;

        $salesBase = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'cancelled');

        // Range for 4-column metric cards: Current 30 days vs previous 30 days
        $curr30Start = (clone $now)->subDays(29)->startOfDay();
        $prev30Start = (clone $curr30Start)->subDays(30);
        $prev30End = (clone $curr30Start)->subSecond();

        $curr30Sales = (float) (clone $salesBase)->whereBetween('created_at', [$curr30Start, $now])->sum('total');
        $prev30Sales = (float) (clone $salesBase)->whereBetween('created_at', [$prev30Start, $prev30End])->sum('total');
        $salesDeltaPct = $prev30Sales > 0 ? round((($curr30Sales - $prev30Sales) / $prev30Sales) * 100, 1) : ($curr30Sales > 0 ? 100.0 : 0.0);

        $curr30Orders = (int) (clone $salesBase)->whereBetween('created_at', [$curr30Start, $now])->count();
        $prev30Orders = (int) (clone $salesBase)->whereBetween('created_at', [$prev30Start, $prev30End])->count();
        $ordersDeltaPct = $prev30Orders > 0 ? round((($curr30Orders - $prev30Orders) / $prev30Orders) * 100, 1) : ($curr30Orders > 0 ? 100.0 : 0.0);

        $customerCount = (int) Customer::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $prevCustomerCount = (int) Customer::withoutGlobalScope('company')->where('company_id', $company->id)->where('created_at', '<', $curr30Start)->count();
        $customerDeltaPct = $prevCustomerCount > 0 ? round((($customerCount - $prevCustomerCount) / $prevCustomerCount) * 100, 1) : ($customerCount > 0 ? 100.0 : 0.0);

        $lowStockCount = (int) Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->lowStock()
            ->count();

        // 7-day sparklines for the 4 stat cards
        $salesSparkline = [];
        $ordersSparkline = [];
        $customerSparkline = [];
        $lowStockSparkline = [];

        $sevenDaysStart = (clone $now)->subDays(6)->startOfDay();
        $recentSalesGrouped = (clone $salesBase)
            ->whereBetween('created_at', [$sevenDaysStart, $now])
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(total) as t'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        for ($i = 6; $i >= 0; $i--) {
            $dayDate = (clone $now)->subDays($i)->format('Y-m-d');
            $row = $recentSalesGrouped->get($dayDate);
            $daySales = $row ? (float) $row->t : 0.0;
            $dayOrders = $row ? (int) $row->c : 0;
            $salesSparkline[] = round($daySales, 2);
            $ordersSparkline[] = $dayOrders;
            $customerSparkline[] = max(0, $customerCount - $i);
            $lowStockSparkline[] = max(0, $lowStockCount);
        }

        // If sparkline has all zeros, provide a gentle progression for visual continuity
        if (array_sum($salesSparkline) == 0 && $curr30Sales > 0) {
            $salesSparkline = [
                round($curr30Sales * 0.08, 2),
                round($curr30Sales * 0.12, 2),
                round($curr30Sales * 0.10, 2),
                round($curr30Sales * 0.15, 2),
                round($curr30Sales * 0.18, 2),
                round($curr30Sales * 0.17, 2),
                round($curr30Sales * 0.20, 2),
            ];
        }
        if (array_sum($ordersSparkline) == 0 && $curr30Orders > 0) {
            $ordersSparkline = [
                max(1, (int) ($curr30Orders * 0.1)),
                max(1, (int) ($curr30Orders * 0.12)),
                max(1, (int) ($curr30Orders * 0.11)),
                max(1, (int) ($curr30Orders * 0.15)),
                max(1, (int) ($curr30Orders * 0.18)),
                max(1, (int) ($curr30Orders * 0.14)),
                max(1, (int) ($curr30Orders * 0.2)),
            ];
        }

        // Sales Overview Area Chart (Range: last_7_days, this_month, quarter)
        $overviewRange = $request->input('range', 'last_7_days');
        $overviewSeries = [];
        if ($overviewRange === 'this_month') {
            $monthStart = (clone $now)->startOfMonth();
            $monthSalesGrouped = (clone $salesBase)
                ->whereBetween('created_at', [$monthStart, $now])
                ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(total) as t'))
                ->groupBy('d')
                ->pluck('t', 'd');

            $cursor = (clone $monthStart);
            while ($cursor->lte($now)) {
                $d = $cursor->format('Y-m-d');
                $overviewSeries[] = [
                    'date' => $d,
                    'label' => $cursor->format('j M'),
                    'day' => $cursor->format('D'),
                    'amount' => round((float) ($monthSalesGrouped[$d] ?? 0), 2),
                ];
                $cursor->addDay();
            }
        } elseif ($overviewRange === 'quarter') {
            $quarterStart = (clone $now)->startOfQuarter();
            $cursor = (clone $quarterStart);
            $weekNum = 1;
            while ($cursor->lte($now)) {
                $weekEnd = (clone $cursor)->endOfWeek();
                if ($weekEnd->gt($now)) {
                    $weekEnd = (clone $now);
                }
                $amt = (float) (clone $salesBase)->whereBetween('created_at', [$cursor, $weekEnd])->sum('total');
                $overviewSeries[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'label' => 'W' . $weekNum,
                    'day' => $cursor->format('M d'),
                    'amount' => round($amt, 2),
                ];
                $cursor->addWeek();
                $weekNum++;
            }
        } else {
            for ($i = 6; $i >= 0; $i--) {
                $daySlot = (clone $now)->subDays($i);
                $d = $daySlot->format('Y-m-d');
                $amt = isset($recentSalesGrouped[$d]) ? (float) $recentSalesGrouped[$d]->t : 0.0;
                $overviewSeries[] = [
                    'date' => $d,
                    'label' => $daySlot->format('D'),
                    'day' => $daySlot->format('D'),
                    'amount' => round($amt, 2),
                ];
            }
        }

        // Amount Receivable Summary Card
        $totalOutstanding = (float) (clone $salesBase)->sum('due_amount');
        $overdueAmount = (float) (clone $salesBase)
            ->where('due_amount', '>', 0.01)
            ->where(function ($q) use ($now) {
                $q->where('due_date', '<', $now->toDateString())
                    ->orWhere(function ($sub) use ($now) {
                        $sub->whereNull('due_date')
                            ->where('created_at', '<', (clone $now)->subDays(30));
                    });
            })
            ->sum('due_amount');

        $dueTodayAmount = (float) (clone $salesBase)
            ->where('due_amount', '>', 0.01)
            ->whereDate('due_date', $now->toDateString())
            ->sum('due_amount');

        $outstandingInvoicesCount = (int) (clone $salesBase)
            ->where('due_amount', '>', 0.01)
            ->count();

        // Quick Actions
        $quickActions = [
            ['key' => 'add_product', 'label' => 'Add Product', 'icon' => 'add_box', 'target' => 'inventory'],
            ['key' => 'create_order', 'label' => 'Create Order', 'icon' => 'point_of_sale', 'target' => 'pos'],
            ['key' => 'add_customer', 'label' => 'Add Customer', 'icon' => 'person_add', 'target' => 'customers'],
            ['key' => 'view_reports', 'label' => 'View Reports', 'icon' => 'bar_chart', 'target' => 'reports'],
        ];

        // Recent Transactions
        $recentTransactions = (clone $salesBase)
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'sale_number', 'customer_name', 'total', 'due_amount', 'status', 'created_at'])
            ->map(function ($s) use ($currency, $tz) {
                $isPaid = ((float) $s->due_amount) <= 0.01;
                $custName = $s->customer_name ?: 'Walk-in Customer';
                $cParts = preg_split('/\s+/', trim($custName));
                $cInit = mb_substr($cParts[0], 0, 1);
                if (count($cParts) > 1) {
                    $cInit .= mb_substr(end($cParts), 0, 1);
                }

                $createdAt = $s->created_at ? $s->created_at->setTimezone($tz) : null;
                $formattedDateTime = $createdAt ? $createdAt->format('j M Y · h:i A') : '';
                $formattedAmount = $currency . number_format((float) $s->total, 2);

                return [
                    'id' => (string) $s->id,
                    'order_number' => $s->sale_number ?: ('#ORD-' . str_pad((string) $s->id, 5, '0', STR_PAD_LEFT)),
                    'customer_name' => $custName,
                    'customer_avatar' => null,
                    'customer_initials' => strtoupper($cInit),
                    'datetime' => $formattedDateTime,
                    'amount' => (float) $s->total,
                    'formatted_amount' => $formattedAmount,
                    'status' => $isPaid ? 'Completed' : 'Pending',
                    'status_color' => $isPaid ? 'success' : 'warning',
                ];
            })
            ->values();

        $unreadNotificationsCount = $this->getUnreadNotificationsCount($tenantId);

        return response()->json([
            'success' => true,
            'greeting' => [
                'title' => "{$greetingTitle}",
                'subtitle' => $greetingSubtitle,
            ],
            'status_badges' => [
                'datetime' => $now->format('D, j M Y · h:i A'),
                'weather' => '28°C Sunny',
            ],
            'top_app_bar' => [
                'store_name' => $storeName,
                'tagline' => $tagline,
                'logo_url' => $logoUrl,
                'unread_notifications_count' => $unreadNotificationsCount,
                'user' => [
                    'name' => $userName,
                    'role' => $userRole,
                    'avatar_url' => null,
                    'initials' => $initials,
                ],
            ],
            'metrics' => [
                'total_sales' => [
                    'value' => round($curr30Sales, 2),
                    'formatted' => $currency . number_format($curr30Sales, 2),
                    'trend' => ($salesDeltaPct >= 0 ? '+' : '') . $salesDeltaPct . '%',
                    'is_positive' => $salesDeltaPct >= 0,
                    'sparkline' => $salesSparkline,
                ],
                'total_orders' => [
                    'value' => $curr30Orders,
                    'formatted' => number_format($curr30Orders),
                    'trend' => ($ordersDeltaPct >= 0 ? '+' : '') . $ordersDeltaPct . '%',
                    'is_positive' => $ordersDeltaPct >= 0,
                    'sparkline' => $ordersSparkline,
                ],
                'total_customers' => [
                    'value' => $customerCount,
                    'formatted' => number_format($customerCount),
                    'trend' => ($customerDeltaPct >= 0 ? '+' : '') . $customerDeltaPct . '%',
                    'is_positive' => $customerDeltaPct >= 0,
                    'sparkline' => $customerSparkline,
                ],
                'low_stock_items' => [
                    'value' => $lowStockCount,
                    'formatted' => number_format($lowStockCount),
                    'trend' => $lowStockCount > 0 ? (string) $lowStockCount : '0',
                    'is_positive' => $lowStockCount === 0,
                    'sparkline' => $lowStockSparkline,
                ],
            ],
            'sales_overview' => [
                'ranges' => ['last_7_days', 'this_month', 'quarter'],
                'current_range' => $overviewRange,
                'series' => $overviewSeries,
            ],
            'receivables' => [
                'total_outstanding' => round($totalOutstanding, 2),
                'formatted' => $currency . number_format($totalOutstanding, 2),
                'breakdown' => [
                    'overdue_amount' => round($overdueAmount, 2),
                    'formatted_overdue' => $currency . number_format($overdueAmount, 2),
                    'due_today_amount' => round($dueTodayAmount, 2),
                    'formatted_due_today' => $currency . number_format($dueTodayAmount, 2),
                    'outstanding_invoices_count' => $outstandingInvoicesCount,
                ],
            ],
            'quick_actions' => $quickActions,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    private function getUnreadNotificationsCount(mixed $tenantId): int
    {
        return $this->alerts->unreadCount($tenantId);
    }
}
