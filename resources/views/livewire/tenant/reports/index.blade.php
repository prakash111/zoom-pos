<div class="space-y-6 text-xs font-sans pb-12 w-full"
     data-apexcharts-dashboard
     x-data="reportsDashboard({
         activeTab: @entangle('activeTab'),
         currencySymbol: '{{ $company->currency_symbol ?? '$' }}',
         salesTrendData: @js($chartSalesTrend),
         topProductsData: @js($chartTopProducts),
         paymentMethodsData: @js($chartPaymentMethods),
         tillClosingsData: @js($chartTillClosings),
         commissionsData: @js($chartCommissions),
         agingData: @js($chartAging)
     })"
     x-init="initDashboard()"
     x-cloak>

    <!-- Header with Filters & Export Actions -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4 w-full">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-base shadow-md shadow-blue-500/20 shrink-0">
                        📈
                    </span>
                    <span>{{ __("Financial Reports & Business Analytics") }}</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ __("Track sales performance, payment channels, cashier audits, salesperson commissions, and debt aging.") }}
                </p>
            </div>

            <!-- Export & Print Actions -->
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        onclick="window.print()"
                        class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-extrabold text-xs shadow-xs transition active:scale-95 flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    <span>{{ __("Print View") }}</span>
                </button>

                <button type="button"
                        wire:click="exportCsv"
                        class="px-4 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span>{{ __("Export CSV") }}</span>
                </button>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end w-full">
            <!-- Preset Quick Filters -->
            <div class="lg:col-span-6 space-y-1">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ __("Date Range Preset") }}</label>
                <div class="flex flex-wrap gap-1.5">
                    @php
                        $presets = [
                            'today' => __('Today'),
                            'yesterday' => __('Yesterday'),
                            'this_week' => __('This Week'),
                            'this_month' => __('This Month'),
                            'last_month' => __('Last Month'),
                            'this_year' => __('This Year'),
                            'all_time' => __('All Time'),
                        ];
                    @endphp
                    @foreach ($presets as $code => $lbl)
                        <button type="button"
                                wire:click="setDatePreset('{{ $code }}')"
                                @class([
                                    'px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer border',
                                    'bg-blue-600 text-white border-blue-600 shadow-xs' => $datePreset === $code,
                                    'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/60' => $datePreset !== $code,
                                ])>
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Custom Start Date -->
            <div class="lg:col-span-2 space-y-1">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ __("Start Date") }}</label>
                <input type="date" wire:model.live="startDate" class="w-full py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200">
            </div>

            <!-- Custom End Date -->
            <div class="lg:col-span-2 space-y-1">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ __("End Date") }}</label>
                <input type="date" wire:model.live="endDate" class="w-full py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200">
            </div>

            <!-- Staff Member Filter -->
            <div class="lg:col-span-2 space-y-1">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ __("Salesperson / Staff") }}</label>
                <select wire:model.live="salespersonFilter" class="w-full py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200">
                    <option value="">-- {{ __("All Staff Members") }} --</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- 1. Visual KPI Summary Cards (High-Impact KPI Stat Card Metric Grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4.5 w-full">
        
        <!-- KPI Card 1: Total Revenue / Collections -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all group w-full">
            <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-emerald-500/10 to-teal-500/0 rounded-full blur-xl pointer-events-none group-hover:from-emerald-500/20 transition-all"></div>
            <div class="flex items-center justify-between relative z-10">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ __("Total Revenue") }}
                </span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-sm shadow-xs">
                    💵
                </div>
            </div>
            <div class="mt-2.5 relative z-10">
                <div class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-baseline gap-1">
                    <span>
                        {{ $company->formatMoney($kpiMetrics['total_revenue']) }}
                    </span>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    @php $revGrowth = $kpiMetrics['revenue_growth']; @endphp
                    <span @class([
                        'inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-black',
                        'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' => $revGrowth['is_positive'],
                        'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400' => ! $revGrowth['is_positive'],
                    ])>
                        <span>{{ $revGrowth['is_positive'] ? '↑' : '↓' }}</span>
                        <span>{{ $revGrowth['label'] }}</span>
                    </span>
                    <span class="text-[11px] text-slate-400">{{ __("vs. previous period") }}</span>
                </div>
            </div>
        </div>

        <!-- KPI Card 2: Total Transactions Count -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all group w-full">
            <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-blue-500/10 to-indigo-500/0 rounded-full blur-xl pointer-events-none group-hover:from-blue-500/20 transition-all"></div>
            <div class="flex items-center justify-between relative z-10">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ __("Transactions Count") }}
                </span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-sm shadow-xs">
                    🧾
                </div>
            </div>
            <div class="mt-2.5 relative z-10">
                <div class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-baseline gap-1">
                    <span>
                        {{ number_format($kpiMetrics['transactions_count']) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400 lowercase">{{ __("orders") }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    @php $ordersGrowth = $kpiMetrics['transactions_growth']; @endphp
                    <span @class([
                        'inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-black',
                        'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400' => $ordersGrowth['is_positive'],
                        'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400' => ! $ordersGrowth['is_positive'],
                    ])>
                        <span>{{ $ordersGrowth['is_positive'] ? '↑' : '↓' }}</span>
                        <span>{{ $ordersGrowth['label'] }}</span>
                    </span>
                    <span class="text-[11px] text-slate-400">{{ __("order volume") }}</span>
                </div>
            </div>
        </div>

        <!-- KPI Card 3: Average Order Value (AOV) -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all group w-full">
            <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-purple-500/10 to-violet-500/0 rounded-full blur-xl pointer-events-none group-hover:from-purple-500/20 transition-all"></div>
            <div class="flex items-center justify-between relative z-10">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ __("Average Order Value (AOV)") }}
                </span>
                <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-sm shadow-xs">
                    🎯
                </div>
            </div>
            <div class="mt-2.5 relative z-10">
                <div class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-baseline gap-1">
                    <span>
                        {{ $company->formatMoney($kpiMetrics['aov']) }}
                    </span>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    @php $aovGrowth = $kpiMetrics['aov_growth']; @endphp
                    <span @class([
                        'inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-black',
                        'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400' => $aovGrowth['is_positive'],
                        'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400' => ! $aovGrowth['is_positive'],
                    ])>
                        <span>{{ $aovGrowth['is_positive'] ? '↑' : '↓' }}</span>
                        <span>{{ $aovGrowth['label'] }}</span>
                    </span>
                    <span class="text-[11px] text-slate-400">{{ __("per basket ticket") }}</span>
                </div>
            </div>
        </div>

        <!-- KPI Card 4: Top Performing Channel / Salesperson -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all group w-full">
            <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-amber-500/10 to-orange-500/0 rounded-full blur-xl pointer-events-none group-hover:from-amber-500/20 transition-all"></div>
            <div class="flex items-center justify-between relative z-10">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ $kpiMetrics['top_contributor']['label'] }}
                </span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-sm shadow-xs">
                    🏆
                </div>
            </div>
            <div class="mt-2.5 relative z-10">
                <div class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white truncate" title="{{ $kpiMetrics['top_contributor']['name'] }}">
                    {{ $kpiMetrics['top_contributor']['name'] }}
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">
                        {{ $company->formatMoney($kpiMetrics['top_contributor']['amount']) }}
                    </span>
                    <span class="text-[11px] text-slate-400">({{ $kpiMetrics['top_contributor']['share'] }}% {{ __("share") }})</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Navigation Tabs Navigation -->
    <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 w-full">
        <button type="button"
                wire:click="setTab('sales_summary')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'sales_summary',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'sales_summary',
                ])>
            <span>📊</span>
            <span>{{ __("Sales & Products") }}</span>
        </button>

        <button type="button"
                wire:click="setTab('dre')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'dre',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'dre',
                ])>
            <span>📑</span>
            <span>{{ __("Income Statement (DRE)") }}</span>
        </button>

        <button type="button"
                wire:click="setTab('payment_methods')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'payment_methods',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'payment_methods',
                ])>
            <span>💳</span>
            <span>{{ __("Payment Methods") }}</span>
        </button>

        <button type="button"
                wire:click="setTab('till_closings')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'till_closings',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'till_closings',
                ])>
            <span>🗄️</span>
            <span>{{ __("Till Closings (Z-Reports)") }}</span>
        </button>

        <button type="button"
                wire:click="setTab('commissions')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'commissions',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'commissions',
                ])>
            <span>👔</span>
            <span>{{ __("Salesperson & Commissions") }}</span>
        </button>

        <button type="button"
                wire:click="setTab('aging')"
                @class([
                    'px-4 py-2.5 rounded-xl font-black text-xs transition flex items-center gap-2 cursor-pointer',
                    'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'aging',
                    'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $activeTab !== 'aging',
                ])>
            <span>⏳</span>
            <span>{{ __("Receivables Aging") }}</span>
        </button>
    </div>

    <!-- TAB 1: Sales Summary & Product Breakdown -->
    @if ($activeTab === 'sales_summary')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <!-- 2-Column Responsive Grid for Charts matching prompt specifications -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full mb-8">
                <!-- Chart 1: Sales & Order Volume Trend -->
                <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>📈</span>
                                <span>{{ __("Sales & Order Volume Trend") }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Revenue velocity and transaction count over time") }}</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200/50 dark:border-blue-800/50 shrink-0">
                            {{ __("Spline Velocity") }}
                        </span>
                    </div>

                    <div id="salesVolumeChart" class="w-full flex-1 min-h-[280px]"></div>
                </div>

                <!-- Chart 2: Top 5 Products -->
                <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>🏆</span>
                                <span>{{ __("Top 5 Products") }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Highest gross revenue generators") }}</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50 shrink-0">
                            {{ __("Top Gross") }}
                        </span>
                    </div>

                    <div id="topProductsChart" class="w-full flex-1 min-h-[280px]"></div>
                </div>
            </div>

            <!-- Financial Summary Matrix Cards (Full-width grid: grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 w-full mb-8) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 w-full mb-8">
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Gross Sales") }}</div>
                    <div class="text-base font-black text-slate-900 dark:text-white mt-1">{{ $company->formatMoney($salesSummary['gross_sales']) }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Discounts Given") }}</div>
                    <div class="text-base font-black text-amber-600 dark:text-amber-400 mt-1">-{{ $company->formatMoney($salesSummary['total_discount']) }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Sales Tax") }}</div>
                    <div class="text-base font-black text-slate-600 dark:text-slate-300 mt-1">+{{ $company->formatMoney($salesSummary['total_tax']) }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Cash & Card Collected") }}</div>
                    <div class="text-base font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $company->formatMoney($salesSummary['total_paid']) }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Credit / Due Amount") }}</div>
                    <div class="text-base font-black text-rose-600 dark:text-rose-400 mt-1">{{ $company->formatMoney($salesSummary['total_due']) }}</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __("Total Units Sold") }}</div>
                    <div class="text-base font-black text-blue-600 dark:text-blue-400 mt-1">{{ number_format($salesSummary['items_sold_count']) }}</div>
                </div>
            </div>

            <!-- Top Products Detailed Breakdown Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Top Selling Products & Item Performance") }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ __("Aggregated item revenue, quantities moved, and sales contribution") }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">{{ __("Rank") }}</th>
                                <th class="px-5 py-3.5">{{ __("Product / Line Item") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Units Sold") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Gross Revenue") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Revenue Share") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @forelse ($topProducts as $index => $prod)
                                @php
                                    $prodShare = $salesSummary['gross_sales'] > 0 ? round(($prod['revenue'] / $salesSummary['gross_sales']) * 100, 1) : 0;
                                @endphp
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-black text-slate-400">
                                        #{{ $index + 1 }}
                                    </td>
                                    <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                        {{ $prod['name'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                        {{ number_format($prod['qty']) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-slate-900 dark:text-white">
                                        {{ $company->formatMoney($prod['revenue']) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                                <div class="h-full bg-blue-600 rounded-full" style="width: {{ min(100, $prodShare) }}%;"></div>
                                            </div>
                                            <span class="font-mono text-[11px] font-bold text-slate-500">{{ $prodShare }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                        {{ __("No products sold in the selected date range.") }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: Income Statement / DRE (Demonstrativo do Resultado do Exercício) -->
    @if ($activeTab === 'dre')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <!-- DRE Header Summary Banner -->
            <div class="bg-slate-900 text-white p-6 sm:p-8 rounded-3xl border border-indigo-900/50 shadow-lg space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">📑</span>
                        <div>
                            <h2 class="text-lg sm:text-xl font-black tracking-tight">{{ __("Income Statement (DRE - Demonstrativo do Resultado do Exercício)") }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">{{ __("Official financial P&L breakdown according to standard accounting standards.") }}</p>
                        </div>
                    </div>

                    <div class="text-right bg-white/10 px-4 py-2 rounded-2xl border border-white/10">
                        <div class="text-[10px] uppercase font-mono font-bold text-indigo-300">{{ __("Operating Profit (EBITDA)") }}</div>
                        <div class="text-xl sm:text-2xl font-black font-mono {{ $dreStatement['ebitda'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $company->formatMoney($dreStatement['ebitda']) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed DRE Structure Card -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 dark:border-slate-800 space-y-5">
                <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs sm:text-sm font-semibold">
                    
                    <!-- 1. Receita Bruta -->
                    <div class="py-3 flex justify-between items-center text-slate-900 dark:text-white">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs">1</span>
                            <span class="font-black text-sm uppercase tracking-wide">{{ __("Gross Revenue from Sales (Receita Bruta)") }}</span>
                        </div>
                        <span class="text-base font-black font-mono text-slate-900 dark:text-white">
                            +{{ $company->formatMoney($dreStatement['gross_revenue']) }}
                        </span>
                    </div>

                    <!-- 2. Deduções -->
                    <div class="py-3 pl-8 space-y-2 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl px-4 my-1">
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 text-xs">
                            <span class="italic">{{ __("(-) Discounts Allowed (Descontos Comerciais)") }}</span>
                            <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['discounts']) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 text-xs">
                            <span class="italic">{{ __("(-) Sales Taxes / Impostos sobre Vendas") }}</span>
                            <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['taxes']) }}</span>
                        </div>
                    </div>

                    <!-- 3. Receita Líquida -->
                    <div class="py-3 flex justify-between items-center text-blue-600 dark:text-blue-400 bg-blue-50/40 dark:bg-blue-950/20 px-4 rounded-xl my-1">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">=</span>
                            <span class="font-black text-sm uppercase tracking-wide">{{ __("Net Revenue from Sales (Receita Líquida)") }}</span>
                        </div>
                        <span class="text-base font-black font-mono">
                            {{ $company->formatMoney($dreStatement['net_revenue']) }}
                        </span>
                    </div>

                    <!-- 4. CPV / CMV -->
                    <div class="py-3 pl-8 flex justify-between items-center text-slate-700 dark:text-slate-300">
                        <span class="italic">{{ __("(-) Cost of Goods Sold / CMV / CPV (Custo dos Produtos)") }}</span>
                        <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['cogs']) }}</span>
                    </div>

                    <!-- 5. Lucro Bruto -->
                    <div class="py-3 flex justify-between items-center text-emerald-600 dark:text-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/20 px-4 rounded-xl my-1">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">=</span>
                            <span class="font-black text-sm uppercase tracking-wide">{{ __("Gross Profit (Lucro Bruto)") }}</span>
                            <span class="text-[10px] bg-emerald-100 dark:bg-emerald-900/60 px-2 py-0.5 rounded-md font-mono font-bold">{{ $dreStatement['gross_margin'] }}% {{ __("margin") }}</span>
                        </div>
                        <span class="text-base font-black font-mono">
                            {{ $company->formatMoney($dreStatement['gross_profit']) }}
                        </span>
                    </div>

                    <!-- 6. Despesas Operacionais -->
                    <div class="py-3 pl-8 space-y-2 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl px-4 my-1">
                        <div class="text-[11px] font-bold uppercase text-slate-400 mb-1">{{ __("(-) Operating Expenses (Despesas Operacionais)") }}</div>
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 text-xs">
                            <span class="italic">{{ __("Card Processing & Machine Merchant Fees (Taxas de Cartão)") }}</span>
                            <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['card_fees']) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 text-xs">
                            <span class="italic">{{ __("Sales Staff Commissions (Comissões)") }}</span>
                            <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['commissions']) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400 text-xs">
                            <span class="italic">{{ __("Petty Cash Drops & Register Expenses (Despesas de Caixa)") }}</span>
                            <span class="font-mono text-rose-500 font-bold">-{{ $company->formatMoney($dreStatement['cash_expenses']) }}</span>
                        </div>
                    </div>

                    <!-- 7. Resultado Líquido / EBITDA -->
                    <div class="py-4 flex justify-between items-center text-white bg-slate-900 dark:bg-slate-800 px-5 rounded-2xl my-2 shadow-md">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-xl bg-emerald-500 text-slate-900 flex items-center justify-center font-black text-sm">✓</span>
                            <div>
                                <span class="font-black text-base uppercase tracking-wide">{{ __("Net Operating Income / EBITDA (Lucro Líquido Operacional)") }}</span>
                                <div class="text-[10px] text-slate-400 font-medium">{{ __("Net Margin:") }} <strong class="text-emerald-400 font-mono">{{ $dreStatement['net_margin'] }}%</strong></div>
                            </div>
                        </div>
                        <span class="text-xl font-black font-mono {{ $dreStatement['ebitda'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $company->formatMoney($dreStatement['ebitda']) }}
                        </span>
                    </div>

                </div>
            </div>

        </div>
    @endif

    <!-- TAB 2: Payment Method Breakdown -->
    @if ($activeTab === 'payment_methods')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <!-- 2-Column Responsive Grid for Payment Methods Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full mb-8">
                
                <!-- Animated Donut Chart with Center Total Value -->
                <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>🍩</span>
                                <span>{{ __("Revenue by Tender") }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Proportional distribution of payment methods") }}</p>
                        </div>
                    </div>

                    <div id="paymentMethodsChart" class="w-full flex-1 min-h-[280px]"></div>
                </div>

                <!-- Volume Share Meters & Key Metrics -->
                <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>📊</span>
                                <span>{{ __("Tender Share & Volume Breakdown") }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Exact volume share meters and transaction velocity") }}</p>
                        </div>
                    </div>

                    <div class="space-y-3.5 pt-2 flex-1 flex flex-col justify-center">
                        @forelse ($paymentMethodsSummary as $pm)
                            @php
                                $colorClass = match(strtolower($pm['method'])) {
                                    'cash' => 'from-emerald-500 to-teal-500',
                                    'card' => 'from-blue-500 to-indigo-500',
                                    'credit' => 'from-rose-500 to-amber-500',
                                    'transfer' => 'from-purple-500 to-indigo-500',
                                    default => 'from-slate-500 to-slate-700',
                                };
                            @endphp
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-r {{ $colorClass }}"></span>
                                        <span class="font-black text-slate-800 dark:text-slate-100 capitalize text-xs">{{ $pm['method'] }}</span>
                                        <span class="text-[10px] text-slate-400 font-bold">({{ $pm['count'] }} {{ __("transactions") }})</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-black text-slate-900 dark:text-white text-xs">{{ $company->formatMoney($pm['amount']) }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-50 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400">
                                            {{ $pm['share'] }}%
                                        </span>
                                    </div>
                                </div>
                                <!-- Mini Progress Bar Meter -->
                                <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r {{ $colorClass }} rounded-full transition-all duration-500" style="width: {{ $pm['share'] }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400">
                                {{ __("No payment transactions recorded in the selected period.") }}
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

            <!-- Detailed Payment Reconciliation Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Payment Channel & Method Distribution") }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __("Breakdown of transactions processed per payment gateway and tender method") }}</p>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">{{ __("Payment Method") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Transaction Count") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Total Amount Collected") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Average Ticket") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Volume Share") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @forelse ($paymentMethodsSummary as $pm)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200 capitalize">
                                        {{ $pm['method'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                        {{ $pm['count'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-slate-900 dark:text-white">
                                        {{ $company->formatMoney($pm['amount']) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-600 dark:text-slate-300">
                                        {{ $company->formatMoney($pm['count'] > 0 ? $pm['amount'] / $pm['count'] : 0) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-blue-50 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300">
                                            {{ $pm['share'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                        {{ __("No payment transactions recorded in the selected period.") }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: Till Closings & Cash Register Audits -->
    @if ($activeTab === 'till_closings')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <!-- Grouped Bar Chart: Expected vs Counted Closing Cash -->
            <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between mb-8">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <span>⚖️</span>
                            <span>{{ __("Till Closing Balance Audit (Expected vs. Counted)") }}</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Comparison of system expected cash vs. cashier counted cash to identify shift discrepancies") }}</p>
                    </div>
                </div>

                <div id="tillClosingsChart" class="w-full flex-1 min-h-[280px]"></div>
            </div>

            <!-- Historical Z-Reports Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Cash Register Shifts & Historical Z-Reports") }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ __("Full audit trail of opening floats, cash drawer sales, pay-ins/outs, and variance") }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">{{ __("Shift ID") }}</th>
                                <th class="px-5 py-3.5">{{ __("Opened") }}</th>
                                <th class="px-5 py-3.5">{{ __("Closed") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Float") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Expected") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Counted") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Discrepancy") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @forelse ($tillClosings as $reg)
                                @php
                                    $disc = (float) ($reg->closing_cash ?? 0) - (float) ($reg->expected_cash ?? 0);
                                @endphp
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                        #{{ $reg->id }}
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300">
                                        <div>{{ $reg->opened_at ? $reg->opened_at->format('d M Y, H:i') : '—' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $reg->opener?->name }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300">
                                        <div>{{ $reg->closed_at ? $reg->closed_at->format('d M Y, H:i') : '—' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $reg->closer?->name }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold">
                                        {{ $company->formatMoney($reg->opening_float) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold">
                                        {{ $company->formatMoney($reg->expected_cash) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold">
                                        {{ $company->formatMoney($reg->closing_cash) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black">
                                        @if (abs($disc) < 0.01)
                                            <span class="text-emerald-600 dark:text-emerald-400">✓ {{ __("Balanced") }}</span>
                                        @elseif ($disc > 0)
                                            <span class="text-blue-600 dark:text-blue-400">+{{ $company->formatMoney($disc) }} ({{ __("Over") }})</span>
                                        @else
                                            <span class="text-rose-600 dark:text-rose-400">{{ $company->formatMoney($disc) }} ({{ __("Short") }})</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <button type="button"
                                                wire:click="viewRegister({{ $reg->id }})"
                                                class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950 hover:text-blue-600 dark:hover:text-blue-400 font-extrabold text-[11px] transition cursor-pointer">
                                            📜 {{ __("View Z-Report") }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                        {{ __("No closed cash register sessions found in this date range.") }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($tillClosings instanceof \Illuminate\Pagination\LengthAwarePaginator && $tillClosings->hasPages())
                    <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                        {{ $tillClosings->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- TAB 4: Salesperson Performance & Commission Ledger -->
    @if ($activeTab === 'commissions')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-data="{ showLeaderboard: false }">
            
            <!-- Stacked Column Chart / Leaderboard Toggle Container -->
            <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <span>👔</span>
                            <span>{{ __("Salesperson Performance & Commissions") }}</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Base sales volume vs. earned commission payout per staff member") }}</p>
                    </div>

                    <!-- Toggle View Switcher -->
                    <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                        <button type="button"
                                x-on:click="showLeaderboard = false; $nextTick(() => renderCommissionsChart())"
                                :class="!showLeaderboard ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 rounded-lg transition cursor-pointer">
                            📊 {{ __("Stacked Chart") }}
                        </button>
                        <button type="button"
                                x-on:click="showLeaderboard = true"
                                :class="showLeaderboard ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 rounded-lg transition cursor-pointer">
                            🏆 {{ __("Leaderboard Cards") }}
                        </button>
                    </div>
                </div>

                <!-- Chart View -->
                <div x-show="!showLeaderboard" id="commissionsChart" class="w-full flex-1 min-h-[280px]"></div>

                <!-- Leaderboard Cards View -->
                <div x-show="showLeaderboard" x-cloak x-transition class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2 w-full">
                    @forelse ($commissionReport as $idx => $comm)
                        @php
                            $medal = match($idx) {
                                0 => '🥇',
                                1 => '🥈',
                                2 => '🥉',
                                default => '⭐',
                            };
                        @endphp
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 space-y-3 relative overflow-hidden">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl">{{ $medal }}</span>
                                    <div>
                                        <div class="font-black text-slate-900 dark:text-white text-sm">{{ $comm['name'] }}</div>
                                        <div class="text-[10px] text-slate-400 font-bold">{{ $comm['role'] }}</div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-50 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400">
                                    {{ $comm['sales_count'] }} {{ __("sales") }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 text-xs">
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Sales Volume") }}</div>
                                    <div class="font-mono font-black text-slate-900 dark:text-white mt-0.5">{{ $company->formatMoney($comm['total_revenue']) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Commission Payout") }}</div>
                                    <div class="font-mono font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $company->formatMoney($comm['total_commission']) }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-3 py-8 text-center text-slate-400">
                            {{ __("No sales staff activity recorded in this period.") }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Detailed Staff Performance Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Salesperson Performance & Commission Ledger") }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __("Individual staff performance, completed tickets, and commission earnings") }}</p>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">{{ __("Staff Name") }}</th>
                                <th class="px-5 py-3.5">{{ __("Role") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Orders Count") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Total Sales Volume") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Commission Rate") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Total Commission Earned") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @forelse ($commissionReport as $comm)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                        {{ $comm['name'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-500 capitalize">
                                        {{ $comm['role'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold">
                                        {{ $comm['sales_count'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-slate-900 dark:text-white">
                                        {{ $company->formatMoney($comm['total_revenue']) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-600 dark:text-slate-300">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-blue-50 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400">
                                            {{ $comm['formatted_rate'] ?? ($comm['commission_rate'] . '%') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-emerald-600 dark:text-emerald-400">
                                        {{ $company->formatMoney($comm['total_commission']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                        {{ __("No sales staff activity recorded in this period.") }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 5: Accounts Receivable Aging Report -->
    @if ($activeTab === 'aging')
        <div class="space-y-6 w-full" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <!-- 2-Column Responsive Grid for Aging Visuals -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full mb-8">
                
                <!-- Color-Coded Aging Donut Chart -->
                <div class="w-full bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm min-h-[360px] flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>⏳</span>
                                <span>{{ __("Debt Aging Matrix") }}</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ __("Visual breakdown of overdue receivables by risk tier") }}</p>
                        </div>
                    </div>

                    <div id="agingChart" class="w-full flex-1 min-h-[280px]"></div>
                </div>

                <!-- 4 Aging Bracket Metrics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                    @foreach ($agingReport['brackets'] as $key => $bracket)
                        @php
                            $badgeColor = match($key) {
                                '0_30' => 'border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400',
                                '31_60' => 'border-amber-500/30 bg-amber-50/50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400',
                                '61_90' => 'border-orange-500/30 bg-orange-50/50 dark:bg-orange-950/30 text-orange-700 dark:text-orange-400',
                                '90_plus' => 'border-rose-500/30 bg-rose-50/50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400',
                            };
                            $dotColor = match($key) {
                                '0_30' => 'bg-emerald-500',
                                '31_60' => 'bg-amber-500',
                                '61_90' => 'bg-orange-500',
                                '90_plus' => 'bg-rose-500',
                            };
                        @endphp
                        <div class="rounded-2xl p-5 border {{ $badgeColor }} shadow-xs space-y-3 flex flex-col justify-between min-h-[170px]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $dotColor }}"></span>
                                    <span class="font-extrabold text-xs uppercase tracking-wide">{{ $bracket['label'] }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-white/80 dark:bg-slate-900/80 shadow-2xs">
                                    {{ $bracket['count'] }} {{ __("invoices") }}
                                </span>
                            </div>
                            <div>
                                <div class="text-2xl font-black font-mono">
                                    {{ $company->formatMoney($bracket['amount']) }}
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    {{ $agingReport['total_receivables'] > 0 ? round(($bracket['amount'] / $agingReport['total_receivables']) * 100, 1) : 0 }}% {{ __("of total receivables") }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

            <!-- Detailed Customer Aging Ledger Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden w-full">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Customer Accounts Receivable Aging Analysis") }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __("Detailed overdue balances sorted by highest total outstanding debt") }}</p>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">{{ __("Customer Name") }}</th>
                                <th class="px-5 py-3.5">{{ __("Contact Phone") }}</th>
                                <th class="px-5 py-3.5">{{ __("Oldest Due Date") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Days Overdue") }}</th>
                                <th class="px-5 py-3.5 text-center">{{ __("Unpaid Invoices") }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __("Outstanding Debt") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @forelse ($agingReport['customers'] as $cust)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                        {{ $cust['name'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-500 font-mono">
                                        {{ $cust['phone'] ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300">
                                        {{ $cust['oldest_due_date']->format('d M Y') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($cust['days_overdue'] > 90)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300">
                                                {{ $cust['days_overdue'] }} {{ __("days (Critical)") }}
                                            </span>
                                        @elseif ($cust['days_overdue'] > 30)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-50 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300">
                                                {{ $cust['days_overdue'] }} {{ __("days (Late)") }}
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                                                {{ $cust['days_overdue'] }} {{ __("days (Current)") }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold">
                                        {{ $cust['invoices_count'] }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-rose-600 dark:text-rose-400">
                                        {{ $company->formatMoney($cust['total_due']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                        {{ __("No outstanding accounts receivable found! All invoices are fully paid.") }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Z-Report Modal in Reports -->
    @if ($viewingRegister)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs"
             x-transition>
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📜</span>
                            <span>{{ __("Cashier Shift Z-Report") }} (#{{ $viewingRegister->id }})</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $viewingRegister->opened_at ? $viewingRegister->opened_at->format('d M Y, H:i') : '' }} — 
                            {{ $viewingRegister->closed_at ? $viewingRegister->closed_at->format('d M Y, H:i') : __('Open') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeViewRegister" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                        ✕
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400 text-[11px] block">{{ __("Opened By") }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">{{ $viewingRegister->opener?->name ?? '—' }}</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400 text-[11px] block">{{ __("Closed By") }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">{{ $viewingRegister->closer?->name ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2 font-mono">
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __("Opening Float:") }}</span>
                            <span>{{ $company->formatMoney($viewingRegister->opening_float) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __("Cash Sales:") }}</span>
                            <span>+{{ $company->formatMoney($viewingRegister->cash_sales_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __("Card Sales:") }}</span>
                            <span>+{{ $company->formatMoney($viewingRegister->card_sales_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __("Cash In (Pay-ins):") }}</span>
                            <span>+{{ $company->formatMoney($viewingRegister->cash_in_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __("Cash Out (Drops):") }}</span>
                            <span>-{{ $company->formatMoney($viewingRegister->cash_out_amount) }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between font-black text-slate-900 dark:text-white">
                            <span>{{ __("Expected Cash in Drawer:") }}</span>
                            <span>{{ $company->formatMoney($viewingRegister->expected_cash) }}</span>
                        </div>
                        <div class="flex justify-between font-black text-slate-900 dark:text-white">
                            <span>{{ __("Actual Counted Cash:") }}</span>
                            <span>{{ $company->formatMoney($viewingRegister->closing_cash) }}</span>
                        </div>
                        @php
                            $vDisc = (float) ($viewingRegister->closing_cash ?? 0) - (float) ($viewingRegister->expected_cash ?? 0);
                        @endphp
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between font-black">
                            <span>{{ __("Shift Variance / Discrepancy:") }}</span>
                            @if (abs($vDisc) < 0.01)
                                <span class="text-emerald-600 dark:text-emerald-400">✓ {{ __("Balanced") }}</span>
                            @elseif ($vDisc > 0)
                                <span class="text-blue-600 dark:text-blue-400">+{{ $company->formatMoney($vDisc) }} ({{ __("Over") }})</span>
                            @else
                                <span class="text-rose-600 dark:text-rose-400">{{ $company->formatMoney($vDisc) }} ({{ __("Short") }})</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button"
                            onclick="window.print()"
                            class="px-4 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                        🖨️ {{ __("Print Z-Report") }}
                    </button>
                    <button type="button"
                            wire:click="closeViewRegister"
                            class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition cursor-pointer">
                        {{ __("Close") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>

<!-- Alpine.js & ApexCharts Integration Engine -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('reportsDashboard', (config) => ({
        activeTab: config.activeTab,
        currencySymbol: config.currencySymbol || '$',
        salesTrendData: config.salesTrendData || {},
        topProductsData: config.topProductsData || {},
        paymentMethodsData: config.paymentMethodsData || {},
        tillClosingsData: config.tillClosingsData || {},
        commissionsData: config.commissionsData || {},
        agingData: config.agingData || {},
        
        charts: {},

        isDark() {
            return document.documentElement.classList.contains('dark');
        },

        initDashboard() {
            window._reportsDashboard = this;

            this.$nextTick(() => {
                this.renderActiveTabCharts();
            });

            // ApexCharts now loads lazily (bundled via Vite, dynamically
            // imported only when this dashboard is on the page — see
            // resources/js/charts-loader.js) instead of via a blocking
            // <script> tag, so it may not be ready yet on first paint.
            // renderActiveTabCharts() no-ops if window.ApexCharts isn't
            // defined; this retries once the async import actually resolves.
            window.addEventListener('apexcharts-ready', () => {
                this.$nextTick(() => this.renderActiveTabCharts());
            }, { once: true });

            this.$watch('activeTab', () => {
                this.$nextTick(() => {
                    this.renderActiveTabCharts();
                });
            });

            // Watch for Livewire tab change & filter events
            window.addEventListener('report-tab-changed', () => {
                this.$nextTick(() => {
                    this.renderActiveTabCharts();
                });
            });

            window.addEventListener('report-filters-updated', () => {
                this.$nextTick(() => {
                    this.renderActiveTabCharts();
                });
            });

            document.addEventListener('livewire:navigated', () => {
                this.$nextTick(() => {
                    this.renderActiveTabCharts();
                });
            });

            // Dark mode observer
            const observer = new MutationObserver(() => {
                this.renderActiveTabCharts();
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        destroyChart(key) {
            if (this.charts[key]) {
                try {
                    this.charts[key].destroy();
                } catch (e) {}
                delete this.charts[key];
            }
        },

        renderActiveTabCharts() {
            if (typeof window.ApexCharts === 'undefined') return;

            setTimeout(() => {
                const isDark = this.isDark();
                const foreColor = isDark ? '#94a3b8' : '#64748b';
                const gridColor = isDark ? '#334155' : '#f1f5f9';

                if (this.activeTab === 'sales_summary') {
                    this.renderSalesVolumeChart(foreColor, gridColor);
                    this.renderTopProductsChart(foreColor, gridColor);
                } else if (this.activeTab === 'payment_methods') {
                    this.renderPaymentMethodsChart(foreColor, gridColor);
                } else if (this.activeTab === 'till_closings') {
                    this.renderTillClosingsChart(foreColor, gridColor);
                } else if (this.activeTab === 'commissions') {
                    this.renderCommissionsChart(foreColor, gridColor);
                } else if (this.activeTab === 'aging') {
                    this.renderAgingChart(foreColor, gridColor);
                }
            }, 50);
        },

        renderSalesVolumeChart(foreColor, gridColor) {
            const el = document.getElementById('salesVolumeChart');
            if (!el) return;
            this.destroyChart('salesVolume');

            const categories = this.salesTrendData.categories || [];
            const revenue = this.salesTrendData.revenue || [];
            const orders = this.salesTrendData.orders || [];

            const options = {
                chart: {
                    type: 'area',
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                series: [
                    { name: 'Gross Revenue', data: revenue, type: 'area' },
                    { name: 'Orders Count', data: orders, type: 'line' }
                ],
                colors: ['#2563eb', '#10b981'],
                fill: {
                    type: ['gradient', 'solid'],
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                stroke: {
                    curve: 'smooth',
                    width: [3, 2],
                    dashArray: [0, 4]
                },
                xaxis: {
                    categories: categories,
                    labels: { style: { colors: foreColor, fontSize: '11px', fontWeight: 600 } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: [
                    {
                        labels: {
                            style: { colors: foreColor, fontSize: '11px' },
                            formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                        }
                    },
                    {
                        opposite: true,
                        labels: {
                            style: { colors: foreColor, fontSize: '11px' },
                            formatter: (val) => Math.round(val || 0)
                        }
                    }
                ],
                grid: { borderColor: gridColor, strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: foreColor } },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: {
                        formatter: (val, opt) => opt.seriesIndex === 0 ? this.currencySymbol + Number(val || 0).toFixed(2) : Math.round(val || 0) + ' orders'
                    }
                }
            };

            this.charts['salesVolume'] = new ApexCharts(el, options);
            this.charts['salesVolume'].render();
        },

        renderTopProductsChart(foreColor, gridColor) {
            const el = document.getElementById('topProductsChart');
            if (!el) return;
            this.destroyChart('topProducts');

            const categories = this.topProductsData.categories || [];
            const revenues = this.topProductsData.revenues || [];

            const options = {
                chart: {
                    type: 'bar',
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 6,
                        barHeight: '55%',
                        distributed: true
                    }
                },
                colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'],
                series: [{ name: 'Revenue', data: revenues.length > 0 ? revenues : [0] }],
                xaxis: {
                    categories: categories.length > 0 ? categories : ['No Data'],
                    labels: {
                        style: { colors: foreColor, fontSize: '10px' },
                        formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                    }
                },
                yaxis: {
                    labels: { style: { colors: foreColor, fontSize: '11px', fontWeight: 600 } }
                },
                grid: { borderColor: gridColor, strokeDashArray: 4 },
                legend: { show: false },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: { formatter: (val) => this.currencySymbol + Number(val || 0).toFixed(2) }
                }
            };

            this.charts['topProducts'] = new ApexCharts(el, options);
            this.charts['topProducts'].render();
        },

        renderPaymentMethodsChart(foreColor, gridColor) {
            const el = document.getElementById('paymentMethodsChart');
            if (!el) return;
            this.destroyChart('paymentMethods');

            const labels = this.paymentMethodsData.labels || [];
            const series = this.paymentMethodsData.series || [];
            const totalGross = this.paymentMethodsData.total_gross || 0;

            const hasData = series.length > 0 && series.some(v => v > 0);

            const options = {
                chart: {
                    type: 'donut',
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                series: hasData ? series : [1],
                labels: hasData ? labels : ['No Data'],
                colors: hasData ? ['#10b981', '#3b82f6', '#f43f5e', '#8b5cf6', '#f59e0b'] : ['#94a3b8'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '12px', fontWeight: 700, color: foreColor },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    fontWeight: 900,
                                    color: this.isDark() ? '#ffffff' : '#0f172a',
                                    formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                                },
                                total: {
                                    show: true,
                                    label: 'Total Value',
                                    fontSize: '11px',
                                    fontWeight: 800,
                                    color: foreColor,
                                    formatter: () => this.currencySymbol + Number(totalGross || 0).toLocaleString()
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: foreColor } },
                stroke: { width: 2, colors: [this.isDark() ? '#0f172a' : '#ffffff'] },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: { formatter: (val) => this.currencySymbol + Number(val || 0).toFixed(2) }
                }
            };

            this.charts['paymentMethods'] = new ApexCharts(el, options);
            this.charts['paymentMethods'].render();
        },

        renderTillClosingsChart(foreColor, gridColor) {
            const el = document.getElementById('tillClosingsChart');
            if (!el) return;
            this.destroyChart('tillClosings');

            const categories = this.tillClosingsData.categories || [];
            const expected = this.tillClosingsData.expected || [];
            const counted = this.tillClosingsData.counted || [];

            const options = {
                chart: {
                    type: 'bar',
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                series: [
                    { name: 'Expected System Cash', data: expected.length > 0 ? expected : [0] },
                    { name: 'Counted Closing Cash', data: counted.length > 0 ? counted : [0] }
                ],
                colors: ['#3b82f6', '#10b981'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '45%',
                        borderRadius: 6
                    }
                },
                xaxis: {
                    categories: categories.length > 0 ? categories : ['No Shifts'],
                    labels: { style: { colors: foreColor, fontSize: '11px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: {
                        style: { colors: foreColor, fontSize: '11px' },
                        formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                    }
                },
                grid: { borderColor: gridColor, strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: foreColor } },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: { formatter: (val) => this.currencySymbol + Number(val || 0).toFixed(2) }
                }
            };

            this.charts['tillClosings'] = new ApexCharts(el, options);
            this.charts['tillClosings'].render();
        },

        renderCommissionsChart(foreColor, gridColor) {
            const el = document.getElementById('commissionsChart');
            if (!el) return;
            this.destroyChart('commissions');

            const categories = this.commissionsData.categories || [];
            const netSales = this.commissionsData.net_sales || [];
            const commissions = this.commissionsData.commissions || [];

            const options = {
                chart: {
                    type: 'bar',
                    stacked: true,
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                series: [
                    { name: 'Net Store Revenue', data: netSales.length > 0 ? netSales : [0] },
                    { name: 'Staff Commission Earned', data: commissions.length > 0 ? commissions : [0] }
                ],
                colors: ['#3b82f6', '#10b981'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '40%',
                        borderRadius: 6
                    }
                },
                xaxis: {
                    categories: categories.length > 0 ? categories : ['No Staff Data'],
                    labels: { style: { colors: foreColor, fontSize: '11px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: {
                        style: { colors: foreColor, fontSize: '11px' },
                        formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                    }
                },
                grid: { borderColor: gridColor, strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: foreColor } },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: { formatter: (val) => this.currencySymbol + Number(val || 0).toFixed(2) }
                }
            };

            this.charts['commissions'] = new ApexCharts(el, options);
            this.charts['commissions'].render();
        },

        renderAgingChart(foreColor, gridColor) {
            const el = document.getElementById('agingChart');
            if (!el) return;
            this.destroyChart('aging');

            const labels = this.agingData.labels || [];
            const series = this.agingData.series || [];
            const colors = this.agingData.colors || ['#10b981', '#f59e0b', '#f97316', '#ef4444'];
            const total = this.agingData.total || 0;

            const hasData = series.length > 0 && series.some(v => v > 0);

            const options = {
                chart: {
                    type: 'donut',
                    height: 280,
                    width: '100%',
                    fontFamily: 'inherit',
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                series: hasData ? series : [1],
                labels: hasData ? labels : ['No Overdue Debt'],
                colors: hasData ? colors : ['#10b981'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '11px', fontWeight: 700, color: foreColor },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    fontWeight: 900,
                                    color: this.isDark() ? '#ffffff' : '#0f172a',
                                    formatter: (val) => this.currencySymbol + Number(val || 0).toLocaleString()
                                },
                                total: {
                                    show: true,
                                    label: 'Total Due',
                                    fontSize: '11px',
                                    fontWeight: 800,
                                    color: foreColor,
                                    formatter: () => this.currencySymbol + Number(total || 0).toLocaleString()
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', horizontalAlign: 'center', labels: { colors: foreColor } },
                stroke: { width: 2, colors: [this.isDark() ? '#0f172a' : '#ffffff'] },
                tooltip: {
                    theme: this.isDark() ? 'dark' : 'light',
                    y: { formatter: (val) => this.currencySymbol + Number(val || 0).toFixed(2) }
                }
            };

            this.charts['aging'] = new ApexCharts(el, options);
            this.charts['aging'].render();
        }
    }));
});
</script>
