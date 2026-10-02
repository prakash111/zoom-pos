@php
    $localNow = now($company?->timezone ?: config('app.timezone'));
    $greeting = $localNow->hour < 12 ? __('Good Morning') : ($localNow->hour < 17 ? __('Good Afternoon') : __('Good Evening'));
    $firstName = explode(' ', trim(auth('web')->user()?->name ?: __('there')))[0];
    $weekTotal = $weeklySales->sum('total');
    $weekOrders = $weeklySales->sum('orders');
    $chartMax = max(1, (float) $weeklySales->max('total'));
    $pointsCount = $weeklySales->count();

    $startX = 12;
    $endX = 462;
    $xStep = $pointsCount > 1 ? ($endX - $startX) / ($pointsCount - 1) : 0;

    $chartPoints = $weeklySales->values()->map(function ($day, $index) use ($startX, $xStep, $chartMax, $pointsCount) {
        $x = $pointsCount > 1 ? round($startX + $index * $xStep, 1) : 240;
        $y = round(130 - (((float) $day['total']) / $chartMax) * 105, 1);

        return [
            'x' => $x,
            'y' => $y,
            'label' => $day['label'],
            'total' => (float) $day['total'],
            'orders' => (int) $day['orders'],
        ];
    });

    if ($pointsCount > 1) {
        $linePoints = $chartPoints->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ');
        $areaPoints = $startX.',130 '.$linePoints.' '.$endX.',130';
    } else {
        $y = $chartPoints->first()['y'] ?? 130;
        $linePoints = $startX.','.$y.' '.$endX.','.$y;
        $areaPoints = $startX.',130 '.$startX.','.$y.' '.$endX.','.$y.' '.$endX.',130';
    }

    $circleRadius = $pointsCount <= 14 ? 4 : ($pointsCount <= 31 ? 2.5 : 1.5);
    $circleStroke = $pointsCount <= 14 ? 2 : 1;
    $can = fn ($module, $action = 'view') => \App\Services\Auth\PermissionChecker::can(auth('web')->user(), $module, $action);
@endphp

<section class="metro-dashboard rounded-[2rem] border border-slate-200 dark:border-[#233d59] bg-slate-50 dark:bg-[#07172b] p-4 sm:p-6 lg:p-7 text-slate-900 dark:text-slate-100 shadow-xl space-y-5" aria-label="{{ __('Store dashboard') }}">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 dark:border-[#233d59] pb-5">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-2xl">🛒</div>
            <div>
                <div class="text-xl font-black tracking-tight">{{ $company?->trade_name ?: $company?->name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Smarter Retail. Faster Growth.') }}</div>
            </div>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-300">
            <span class="rounded-xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#122a45] px-3 py-2">{{ $localNow->format('D, d M Y') }}</span>
            <span class="rounded-xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#122a45] px-3 py-2">{{ $localNow->format('h:i A') }}</span>
            <span class="rounded-full bg-emerald-500/15 px-3 py-2 font-bold text-emerald-700 dark:text-emerald-300">{{ $firstName }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 dark:border-[#29435e] bg-gradient-to-r from-emerald-50 to-slate-100 dark:from-[#102b43] dark:to-[#10243c] p-5 sm:p-6">
        <div><h1 class="text-2xl sm:text-3xl font-black">{{ $greeting }}, <span class="text-emerald-700 dark:text-emerald-400">{{ $firstName }}</span>!</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __("Here's what's happening at your store today.") }}</p></div>
        @if ($can('pos', 'create'))
            <button type="button" wire:click="openPosLayoutModal" class="rounded-xl border border-emerald-400/30 bg-emerald-500/15 px-3 py-2 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/25">{{ __('Choose POS Layout') }}</button>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => __('Total Sales'), 'value' => $currencySymbol.number_format($weekTotal, 2), 'note' => $salesOverviewPeriodLabel ?? __('Last 7 days'), 'icon' => '＄', 'color' => '#27e498', 'light_color' => '#047857'],
            ['label' => __('Total Orders'), 'value' => number_format($weekOrders), 'note' => $salesOverviewPeriodLabel ?? __('Last 7 days'), 'icon' => '🛒', 'color' => '#38bdf8', 'light_color' => '#0369a1'],
            ['label' => __('Total Customers'), 'value' => number_format($totalCustomersCount), 'note' => __('All customers'), 'icon' => '👥', 'color' => '#a78bfa', 'light_color' => '#6d28d9'],
            ['label' => __('Low Stock Items'), 'value' => number_format($lowStockCount), 'note' => __('Need attention'), 'icon' => '📦', 'color' => '#fbbf24', 'light_color' => '#92400e'],
        ] as $metric)
            <div class="min-w-0 rounded-2xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#10243c] p-4 sm:p-5">
                <div class="dashboard-metric-icon mb-3 flex h-10 w-10 items-center justify-center rounded-xl text-lg" style="--metric-accent: {{ $metric['color'] }}; --metric-light-accent: {{ $metric['light_color'] }}; background-color: {{ $metric['color'] }}22">{{ $metric['icon'] }}</div>
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $metric['label'] }}</div>
                <div class="mt-1 text-xl sm:text-2xl font-black tracking-tight break-all">{{ $metric['value'] }}</div>
                <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">{{ $metric['note'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#10243c] p-5 lg:col-span-2">
            <div class="mb-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-bold text-lg">📊 {{ __('Sales Overview') }}</h2>
                            <span class="rounded-lg border border-slate-200 dark:border-[#29435e] px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                {{ $salesOverviewPeriodLabel ?? __('Last 7 Days') }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Total') }}: <span class="font-bold text-slate-800 dark:text-slate-200">{{ $currencySymbol }}{{ number_format($weekTotal, 2) }}</span> &bull; 
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($weekOrders) }}</span> {{ __('orders') }}
                        </div>
                    </div>

                    <!-- Period Filter Controls: Weekly / Monthly / Custom Date Range -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-[#122a45] p-1 rounded-xl border border-slate-200 dark:border-[#29435e]">
                        <button type="button"
                                wire:click="setSalesOverviewPeriod('weekly')"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                    'bg-white dark:bg-[#1f3f66] text-emerald-600 dark:text-emerald-400 shadow-xs' => ($salesOverviewPeriod ?? 'weekly') === 'weekly',
                                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => ($salesOverviewPeriod ?? 'weekly') !== 'weekly',
                                ])>
                            {{ __('Weekly') }}
                        </button>
                        <button type="button"
                                wire:click="setSalesOverviewPeriod('monthly')"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                    'bg-white dark:bg-[#1f3f66] text-emerald-600 dark:text-emerald-400 shadow-xs' => ($salesOverviewPeriod ?? 'weekly') === 'monthly',
                                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => ($salesOverviewPeriod ?? 'weekly') !== 'monthly',
                                ])>
                            {{ __('Monthly') }}
                        </button>
                        <button type="button"
                                wire:click="setSalesOverviewPeriod('custom')"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1',
                                    'bg-white dark:bg-[#1f3f66] text-emerald-600 dark:text-emerald-400 shadow-xs' => ($salesOverviewPeriod ?? 'weekly') === 'custom',
                                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => ($salesOverviewPeriod ?? 'weekly') !== 'custom',
                                ])>
                            <span>📅</span> {{ __('Custom Range') }}
                        </button>
                    </div>
                </div>

                <!-- Custom Date Range Inputs -->
                @if (($salesOverviewPeriod ?? 'weekly') === 'custom')
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-[#233d59]">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('Date Range:') }}</span>
                        <div class="flex items-center gap-1.5">
                            <input type="date"
                                   wire:model.live="salesOverviewStartDate"
                                   max="{{ now()->toDateString() }}"
                                   class="rounded-xl border border-slate-300 dark:border-[#29435e] bg-slate-50 dark:bg-[#142e49] px-2.5 py-1 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <span class="text-xs text-slate-400">{{ __('to') }}</span>
                            <input type="date"
                                   wire:model.live="salesOverviewEndDate"
                                   max="{{ now()->toDateString() }}"
                                   class="rounded-xl border border-slate-300 dark:border-[#29435e] bg-slate-50 dark:bg-[#142e49] px-2.5 py-1 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <button type="button"
                                wire:click="applyCustomSalesOverviewDateRange"
                                class="px-3 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                            {{ __('Apply') }}
                        </button>
                    </div>
                @endif
            </div>

            <svg viewBox="0 0 480 150" role="img" aria-label="{{ __('Sales Overview') }}" class="h-48 w-full" preserveAspectRatio="none">
                <defs><linearGradient id="metro-sales-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="var(--dashboard-chart-accent)" stop-opacity=".30"/><stop offset="100%" stop-color="var(--dashboard-chart-accent)" stop-opacity="0"/></linearGradient></defs>
                <path d="M0 130 H480 M0 78 H480 M0 25 H480" stroke="var(--dashboard-chart-grid)" stroke-width="1" fill="none"/>
                <polygon points="{{ $areaPoints }}" fill="url(#metro-sales-fill)"/>
                <polyline points="{{ $linePoints }}" fill="none" stroke="var(--dashboard-chart-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                @foreach ($chartPoints as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $circleRadius }}" fill="var(--dashboard-chart-accent)" stroke="var(--dashboard-chart-surface)" stroke-width="{{ $circleStroke }}">
                        <title>{{ $point['label'] }}: {{ $currencySymbol }}{{ number_format($point['total'], 2) }} ({{ $point['orders'] }} {{ __('orders') }})</title>
                    </circle>
                @endforeach
            </svg>

            @if ($weeklySales->count() === 7)
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] text-slate-500 dark:text-slate-400">
                    @foreach ($weeklySales as $day)<span>{{ $day['label'] }}</span>@endforeach
                </div>
            @elseif ($weeklySales->count() <= 10)
                <div class="grid gap-1 text-center text-[10px] text-slate-500 dark:text-slate-400" style="grid-template-columns: repeat({{ $weeklySales->count() }}, minmax(0, 1fr));">
                    @foreach ($weeklySales as $day)<span>{{ $day['label'] }}</span>@endforeach
                </div>
            @else
                @php
                    $pCount = $weeklySales->count();
                    $step = max(1, (int) ceil(($pCount - 1) / 5));
                    $indices = [];
                    for ($i = 0; $i < $pCount; $i += $step) {
                        $indices[] = $i;
                    }
                    if (!in_array($pCount - 1, $indices)) {
                        $indices[] = $pCount - 1;
                    }
                @endphp
                <div class="flex justify-between items-center text-[10px] text-slate-500 dark:text-slate-400 px-3 pt-1">
                    @foreach ($indices as $idx)
                        @if (isset($weeklySales[$idx]))
                            <span>{{ $weeklySales[$idx]['label'] }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#10243c] p-5">
            <h2 class="font-bold text-lg">💼 {{ __('Amount Receivable') }}</h2>
            <div class="mt-4 text-3xl font-black">{{ $currencySymbol }}{{ number_format($receivableAmount, 2) }}</div>
            <div class="mt-6 divide-y divide-slate-200 dark:divide-[#29435e] text-sm">
                <div class="flex justify-between py-3"><span class="text-slate-600 dark:text-slate-300">{{ __('Outstanding Invoices') }}</span><strong>{{ $outstandingInvoices }}</strong></div>
                <div class="flex justify-between py-3"><span class="text-slate-600 dark:text-slate-300">{{ __('Overdue Amount') }}</span><strong>{{ $currencySymbol }}{{ number_format($overdueAmount, 2) }}</strong></div>
                <div class="flex justify-between py-3"><span class="text-slate-600 dark:text-slate-300">{{ __('Due Today') }}</span><strong>{{ $currencySymbol }}{{ number_format($dueTodayAmount, 2) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#10243c] p-5">
        <h2 class="mb-4 font-bold text-lg">⚡ {{ __('Quick Actions') }}</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Add Product'), 'icon' => '📦', 'route' => 'tenant.products.index', 'module' => 'products', 'action' => 'create'],
                ['label' => __('Create Order'), 'icon' => '🧾', 'route' => 'tenant.sales.create', 'module' => 'pos', 'action' => 'create'],
                ['label' => __('Add Customer'), 'icon' => '👤', 'route' => 'tenant.customers.index', 'module' => 'customers', 'action' => 'create'],
                ['label' => __('View Reports'), 'icon' => '📊', 'route' => 'tenant.reports.sales', 'module' => 'reports', 'action' => 'view'],
            ] as $action)
                @if ($can($action['module'], $action['action']))
                    <a href="{{ route($action['route']) }}" class="flex items-center justify-between gap-2 rounded-2xl border border-slate-200 dark:border-[#31516d] bg-slate-50 dark:bg-[#142e49] p-4 transition hover:border-emerald-400 hover:bg-emerald-50 dark:hover:bg-[#193b59]">
                        <span><span class="block text-2xl">{{ $action['icon'] }}</span><span class="mt-2 block text-sm font-bold">{{ $action['label'] }}</span></span><span class="text-slate-500 dark:text-slate-400">›</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-[#29435e] bg-white dark:bg-[#10243c] p-5">
        <div class="mb-4 flex items-center justify-between gap-2"><h2 class="font-bold text-lg">📄 {{ __('Recent Transactions') }}</h2>
            @if ($can('sales'))<a href="{{ route('tenant.sales.index') }}" class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">{{ __('View All') }}</a>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[620px] w-full text-left text-sm">
                <thead class="border-b border-slate-200 dark:border-[#29435e] text-xs text-slate-500 dark:text-slate-400"><tr><th class="py-3">{{ __('Date & Time') }}</th><th>{{ __('Order #') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-200 dark:divide-[#29435e]">
                    @forelse ($recentSales as $sale)
                        <tr><td class="py-3 text-slate-600 dark:text-slate-300">{{ $sale->created_at?->format('d M, h:i A') }}</td><td class="font-semibold">#{{ $sale->sale_number }}</td><td>{{ $sale->customer_name ?: __('Walk-in Customer') }}</td><td class="font-bold">{{ $currencySymbol }}{{ number_format((float) $sale->total, 2) }}</td><td><span class="rounded-full px-3 py-1 text-xs font-semibold {{ in_array($sale->status, ['completed', 'paid']) ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/20 text-amber-800 dark:text-amber-300' }}">{{ ucfirst($sale->status ?: 'pending') }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">{{ __('No transactions yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
