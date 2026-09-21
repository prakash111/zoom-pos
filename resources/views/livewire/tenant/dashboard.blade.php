<div class="space-y-6">

    @if ($isRestaurant)
        <!-- ========================================== -->
        <!-- RESTAURANT & FOOD POS MODE DASHBOARD       -->
        <!-- ========================================== -->

        <!-- Restaurant Hero Banner -->
        <div class="bg-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-6 border border-emerald-500/20">

            <div class="relative z-10 max-w-xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-xs font-bold text-lime-200 mb-3">
                    <span class="w-2 h-2 rounded-full bg-lime-400 animate-pulse"></span>
                    <span>Food & Restaurant Mode Active &bull; {{ auth()->user()?->company?->name }}</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight leading-tight">
                    Welcome back, {{ auth()->user()->name }}!
                </h2>
                <p class="text-emerald-100 text-xs sm:text-sm mt-1.5 leading-relaxed opacity-90">
                    Floor plan is active. Take Dine-In orders, manage tables, monitor live kitchen tickets, and handle takeaway or deliveries.
                </p>
            </div>

            <div class="relative z-10 flex flex-wrap items-center gap-3">
                <a href="{{ route('tenant.restaurant.pos') }}"
                   class="px-6 py-3.5 rounded-2xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-sm shadow-md active:scale-95 transition-all flex items-center gap-2">
                    <span>🍽️ Open Restaurant POS</span>
                </a>

                <a href="{{ route('tenant.restaurant.kds') }}"
                   class="px-5 py-3.5 rounded-2xl bg-white/15 hover:bg-white/25 text-white font-extrabold text-sm transition-all flex items-center gap-1.5">
                    <span>🍳 Kitchen KDS</span>
                </a>

                <a href="{{ route('tenant.restaurant.tables') }}"
                   class="px-4 py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-sm transition-all">
                    🪑 Tables
                </a>
            </div>
        </div>

        <!-- Executive KPI Metrics Section -->
        <div class="space-y-4" x-data="{ period: 'daily' }">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Executive Summary') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Real-time financial performance overview') }}</p>
                </div>
                <!-- Daily / Monthly Period Switcher -->
                <div class="flex items-center gap-1 bg-slate-200/80 dark:bg-slate-800 p-1 rounded-2xl">
                    <button type="button"
                            @click="period = 'daily'"
                            :class="period === 'daily' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3 py-1 rounded-xl text-xs font-extrabold transition cursor-pointer">
                        {{ __('Today (Daily)') }}
                    </button>
                    <button type="button"
                            @click="period = 'monthly'"
                            :class="period === 'monthly' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3 py-1 rounded-xl text-xs font-extrabold transition cursor-pointer">
                        {{ __('This Month') }}
                    </button>
                </div>
            </div>

            <!-- 4-Card Revenue & Profit Metrics Grid (High-Impact KPI Stat Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- 1. Total Revenue -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all">
                    <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-indigo-500/10 to-blue-500/0 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400" x-text="period === 'daily' ? @js(__('Today\'s Revenue')) : @js(__('Monthly Revenue'))">{{ __("Today's Revenue") }}</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 flex items-center justify-center text-sm shadow-xs">💰</span>
                    </div>
                    <div class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-2"
                         x-text="period === 'daily' ? '{{ $currencySymbol }}{{ number_format($dailyRevenue, 2) }}' : '{{ $currencySymbol }}{{ number_format($monthlyRevenue, 2) }}'">
                        {{ $currencySymbol }}{{ number_format($dailyRevenue, 2) }}
                    </div>
                    <div class="flex items-center justify-between gap-1 text-[11px] font-semibold mt-1">
                        <span class="flex items-center gap-1 {{ $revenueGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span>{{ $revenueGrowth >= 0 ? '↑ +' : '↓ ' }}{{ $revenueGrowth }}%</span>
                            <span class="text-slate-400 font-normal">vs prev</span>
                        </span>
                        <a wire:navigate.hover href="{{ route('tenant.reports.sales') }}" class="text-blue-500 hover:underline font-bold text-[11px]">{{ __("View Sales") }} &rarr;</a>
                    </div>
                </div>

                <!-- 2. Net Estimated Profit -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all">
                    <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-emerald-500/10 to-teal-500/0 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400" x-text="period === 'daily' ? @js(__('Today\'s Net Profit')) : @js(__('Monthly Net Profit'))">{{ __("Today's Net Profit") }}</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 flex items-center justify-center text-sm shadow-xs">📈</span>
                    </div>
                    <div class="text-2xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 mt-2"
                         x-text="period === 'daily' ? '{{ $currencySymbol }}{{ number_format($dailyProfit, 2) }}' : '{{ $currencySymbol }}{{ number_format($monthlyProfit, 2) }}'">
                        {{ $currencySymbol }}{{ number_format($dailyProfit, 2) }}
                    </div>
                    <div class="flex items-center justify-between gap-1 text-[11px] mt-1">
                        <span class="text-slate-500">{{ __("Margin:") }} <strong class="text-slate-700 dark:text-slate-200 font-bold" x-text="period === 'daily' ? '{{ $dailyProfitMargin }}%' : '{{ $monthlyProfitMargin }}%'">{{ $dailyProfitMargin }}%</strong></span>
                        <a wire:navigate.hover href="{{ route('tenant.reports.profit-loss') }}" class="text-emerald-500 hover:underline font-bold text-[11px]">{{ __("P&L Breakdown") }} &rarr;</a>
                    </div>
                </div>

                <!-- 3. Completed Orders / Sales Count -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all">
                    <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-violet-500/10 to-purple-500/0 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400" x-text="period === 'daily' ? @js(__('Today\'s Invoices')) : @js(__('Monthly Invoices'))">{{ __("Today's Invoices") }}</span>
                        <span class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 flex items-center justify-center text-sm shadow-xs">🧾</span>
                    </div>
                    <div class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-2"
                         x-text="period === 'daily' ? '{{ $dailyOrdersCount }}' : '{{ $monthlyOrdersCount }}'">
                        {{ $dailyOrdersCount }}
                    </div>
                    <div class="flex items-center justify-between gap-1 text-[11px] mt-1">
                        <span class="text-slate-400">{{ __("Avg items:") }} <strong class="text-slate-700 dark:text-slate-300">{{ $avgItemsPerOrder }}</strong></span>
                        <a wire:navigate.hover href="{{ route('tenant.sales.index') }}" class="text-violet-500 hover:underline font-bold text-[11px]">{{ __("All Orders") }} &rarr;</a>
                    </div>
                </div>

                <!-- 4. Average Ticket / AOV -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all">
                    <div class="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-amber-500/10 to-orange-500/0 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __("Avg Ticket (AOV)") }}</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 flex items-center justify-center text-sm shadow-xs">🎯</span>
                    </div>
                    <div class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-2"
                         x-text="period === 'daily' ? '{{ $currencySymbol }}{{ number_format($dailyAov, 2) }}' : '{{ $currencySymbol }}{{ number_format($monthlyAov, 2) }}'">
                        {{ $currencySymbol }}{{ number_format($dailyAov, 2) }}
                    </div>
                    <div class="flex items-center justify-between gap-1 text-[11px] mt-1">
                        <span class="text-slate-500">{{ __("Per Client Spend") }}</span>
                        <a wire:navigate.hover href="{{ route('tenant.customers.index') }}" class="text-amber-500 hover:underline font-bold text-[11px]">{{ __("Customer Dir") }} &rarr;</a>
                    </div>
                </div>

            </div>
        </div>

        <!-- 4 Restaurant Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            <!-- Metric 1: Today's Revenue -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl shadow-sm">
                    $
                </div>
                <div>
                    <div class="text-xs font-semibold text-slate-400 dark:text-slate-500">Today's Dining Sales</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-0.5">${{ number_format($todaySalesTotal, 2) }}</div>
                </div>
            </div>

            <!-- Metric 2: Occupied Tables -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xl shadow-sm">
                    🪑
                </div>
                <div>
                    <div class="text-xs font-semibold text-slate-400 dark:text-slate-500">Occupied Tables</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-0.5">{{ $activeTablesCount }} / {{ $totalTablesCount }}</div>
                </div>
            </div>

            <!-- Metric 3: Orders in Kitchen -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xl shadow-sm">
                    🍳
                </div>
                <div>
                    <div class="text-xs font-semibold text-slate-400 dark:text-slate-500">Orders in Kitchen</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-0.5">{{ $pendingKotsCount }} Active</div>
                </div>
            </div>

            <!-- Metric 4: Total KOTs Today -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-xl shadow-sm">
                    📋
                </div>
                <div>
                    <div class="text-xs font-semibold text-slate-400 dark:text-slate-500">Total KOTs Today</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-0.5">{{ $totalKotsTodayCount }} Tickets</div>
                </div>
            </div>
        </div>

        <!-- Floor Plan Overview & Recent KOTs -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Floor Plan Live Table Map -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Floor Plan & Table Status</h3>
                        <p class="text-xs text-slate-400">Click any table to immediately open POS and take order</p>
                    </div>
                    <a href="{{ route('tenant.restaurant.tables') }}" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
                        Manage Floor Plan &rarr;
                    </a>
                </div>

                @forelse ($activeFloors as $floor)
                    <div class="space-y-2">
                        <div class="text-xs font-black uppercase text-slate-400 tracking-wider">
                            {{ $floor->name }}
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($floor->tables as $t)
                                <a href="{{ route('tenant.restaurant.pos', ['table_id' => $t->id]) }}"
                                   @class([
                                       'p-3.5 rounded-2xl border-2 transition flex flex-col justify-between hover:scale-102 group',
                                       'border-emerald-500/40 bg-emerald-50/20 dark:bg-emerald-950/20' => $t->status === 'available',
                                       'border-blue-500 bg-blue-50 dark:bg-blue-950/40' => $t->status === 'occupied',
                                       'border-amber-500/60 bg-amber-50/30 dark:bg-amber-950/30' => $t->status === 'reserved',
                                       'border-purple-500/60 bg-purple-50/30 dark:bg-purple-950/30' => $t->status === 'billed',
                                   ])>
                                    <div class="flex justify-between items-start">
                                        <span class="font-extrabold text-sm text-slate-900 dark:text-white">{{ $t->table_number }}</span>
                                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                            {{ $t->status }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-2 flex items-center justify-between">
                                        <span>👤 {{ $t->seating_capacity }} seats</span>
                                        <span class="text-lime-600 dark:text-lime-400 font-bold opacity-0 group-hover:opacity-100 transition">&rarr;</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-slate-400 text-xs">
                        No dining floor areas created yet.
                    </div>
                @endforelse
            </div>

            <!-- Right 1 Col: Live Kitchen Queue -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Live Kitchen Tickets</h3>
                    <a href="{{ route('tenant.restaurant.kds') }}" class="text-xs font-bold text-lime-600 dark:text-lime-400 hover:underline">
                        Open KDS &rarr;
                    </a>
                </div>

                <div class="space-y-3">
                    @forelse ($recentKots as $kot)
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="font-extrabold text-xs text-slate-900 dark:text-white">{{ $kot->kot_number }}</span>
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[9px] font-black uppercase',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $kot->status === 'pending',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' => $kot->status === 'preparing',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $kot->status === 'ready',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $kot->status === 'served',
                                ])>
                                    {{ $kot->status }}
                                </span>
                            </div>

                            <div class="text-xs font-bold text-lime-600 dark:text-lime-400">
                                {{ $kot->table_name ?: ucfirst($kot->service_type) }}
                            </div>

                            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ count($kot->items ?? []) }} food items &bull; {{ $kot->created_at->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-400 text-xs">
                            No recent kitchen tickets.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    @else
        @include('livewire.tenant.partials.metro-dashboard')
    @endif

    <!-- ========================================================= -->
    <!-- INTERACTIVE POS LAYOUT SELECTION & LAUNCH MODAL          -->
    <!-- ========================================================= -->
    @if ($showPosLayoutModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 sm:p-8 max-w-3xl w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-6 animate-in fade-in zoom-in-95">

                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-black text-xl text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🎨 Select POS Layout & Interface Design</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Choose your preferred register design before entering the Point of Sale terminal
                        </p>
                    </div>
                    <button type="button"
                            wire:click="$set('showPosLayoutModal', false)"
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-slate-500 hover:text-slate-900 dark:hover:text-white text-base font-black transition">
                        &times;
                    </button>
                </div>

                <!-- 3 Layout Selection Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <!-- Layout 1: Standard Retail Scan -->
                    <div wire:click="selectLayout('standard')"
                         @class([
                             'p-5 rounded-3xl border-2 flex flex-col justify-between transition-all duration-200 cursor-pointer text-left relative space-y-4 shadow-sm active:scale-95',
                             'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 dark:border-blue-500 ring-2 ring-blue-500/20' => $selectedPosLayout === 'standard',
                             'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700' => $selectedPosLayout !== 'standard',
                         ])>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-3xl">🛒</span>
                                @if ($selectedPosLayout === 'standard')
                                    <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-black">✓</span>
                                @endif
                            </div>
                            <h4 class="font-black text-base text-slate-900 dark:text-white">Standard Scanner</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Fast barcode scanning, 3-column product cards, category chips & right-side cart drawer.
                            </p>
                            <div class="pt-2 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                                Best for: General Retail, Supermarkets, Convenience stores
                            </div>
                        </div>
                    </div>

                    <!-- Layout 2: Supermarket Touch POS -->
                    <div wire:click="selectLayout('touch')"
                         @class([
                             'p-5 rounded-3xl border-2 flex flex-col justify-between transition-all duration-200 cursor-pointer text-left relative space-y-4 shadow-sm active:scale-95',
                             'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/40 dark:border-emerald-500 ring-2 ring-emerald-500/20' => $selectedPosLayout === 'touch',
                             'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700' => $selectedPosLayout !== 'touch',
                         ])>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-3xl">🏪</span>
                                @if ($selectedPosLayout === 'touch')
                                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black">✓</span>
                                @endif
                            </div>
                            <h4 class="font-black text-base text-slate-900 dark:text-white">Touch Department</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Left vertical function menu, vibrant department tiles, paginated grid & thermal receipt checkout slip.
                            </p>
                            <div class="pt-2 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                Best for: Touch registers, high-traffic counters, department stores
                            </div>
                        </div>
                    </div>

                    <!-- Layout 3: Square Stand POS -->
                    <div wire:click="selectLayout('stand')"
                         @class([
                             'p-5 rounded-3xl border-2 flex flex-col justify-between transition-all duration-200 cursor-pointer text-left relative space-y-4 shadow-sm active:scale-95',
                             'border-purple-500 bg-purple-50/50 dark:bg-purple-950/40 dark:border-purple-500 ring-2 ring-purple-500/20' => $selectedPosLayout === 'stand',
                             'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700' => $selectedPosLayout !== 'stand',
                         ])>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-3xl">📱</span>
                                @if ($selectedPosLayout === 'stand')
                                    <span class="w-5 h-5 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-black">✓</span>
                                @endif
                            </div>
                            <h4 class="font-black text-base text-slate-900 dark:text-white">Square Stand</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Clean photo-first tiles, Favorites / Library / Keypad tabs, discount presets & quick blue "Charge" pill.
                            </p>
                            <div class="pt-2 text-[10px] font-bold text-purple-600 dark:text-purple-400">
                                Best for: Modern tablet/iPad stands, boutique shops, cafes
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button"
                            wire:click="$set('showPosLayoutModal', false)"
                            class="px-5 py-2.5 rounded-2xl text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white transition">
                        Cancel
                    </button>

                    <button type="button"
                            wire:click="launchPosWithLayout"
                            class="px-7 py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm shadow-xl shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                        <span>Launch Selected POS Register &rarr;</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
