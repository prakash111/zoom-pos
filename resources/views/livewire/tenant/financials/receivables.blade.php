<div class="space-y-4 text-xs font-sans">
    
    <!-- Top Action & Breadcrumb Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-lg">📈</span>
                <h1 class="text-base font-black text-slate-900 dark:text-white tracking-tight">{{ __("Accounts Receivable (Customer Credit & Invoices)") }}</h1>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Track customer balances, credit sales, partial payments, due dates, and overdue accounts.") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.sales.create') }}" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span>+ {{ __("New POS Credit Sale") }}</span>
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Total Outstanding -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Total Outstanding") }}</span>
                <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-white font-mono mt-0.5">${{ number_format($totalOutstanding, 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ $pendingInvoicesCount }} {{ __('active invoices') }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg font-black">
                💵
            </div>
        </div>

        <!-- 2. Overdue Receivables -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-500">{{ __("Overdue Balance") }}</span>
                <div class="text-lg sm:text-xl font-black text-rose-600 dark:text-rose-400 font-mono mt-0.5">${{ number_format($totalOverdue, 2) }}</div>
                <div class="text-[10px] text-rose-500 font-semibold mt-0.5">{{ __("Requires follow-up") }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-lg font-black">
                ⚠️
            </div>
        </div>

        <!-- 3. Collected This Month -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __("Collected This Month") }}</span>
                <div class="text-lg sm:text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">${{ number_format($collectedThisMonth, 2) }}</div>
                <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">{{ now()->format('F Y') }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg font-black">
                ✅
            </div>
        </div>

        <!-- 4. Active Accounts -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-500">{{ __("Debtor Customers") }}</span>
                <div class="text-lg sm:text-xl font-black text-purple-600 dark:text-purple-400 font-mono mt-0.5">{{ count($customersWithBalances->where('total_due_balance', '>', 0)) }}</div>
                <div class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ __("With pending dues") }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg font-black">
                👥
            </div>
        </div>
    </div>

    <!-- Status Alert Flash -->
    @if (session('status'))
        <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <!-- Main High-Density Data Container -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        
        <!-- Filter Toolbar -->
        <div class="p-3 sm:p-3.5 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-850">
            
            <!-- Left Tabs (Invoices Ledger | Customer Balances) -->
            <div class="flex items-center gap-1 bg-slate-200/80 dark:bg-slate-800 p-1 rounded-xl shrink-0">
                <button type="button"
                        wire:click="setTab('invoices')"
                        @class([
                            'px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer',
                            'bg-white dark:bg-slate-700 text-blue-600 dark:text-white shadow-2xs' => $activeTab === 'invoices',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900' => $activeTab !== 'invoices',
                        ])>
                    📋 {{ __("Credit Invoices Ledger") }}
                </button>
                <button type="button"
                        wire:click="setTab('customers')"
                        @class([
                            'px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer',
                            'bg-white dark:bg-slate-700 text-blue-600 dark:text-white shadow-2xs' => $activeTab === 'customers',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900' => $activeTab !== 'customers',
                        ])>
                    👥 {{ __("Customer Directory Balances") }}
                </button>
            </div>

            <!-- Right Search and Filter Dropdowns -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Search Box -->
                <div class="relative w-48 sm:w-64">
                    <input type="text"
                           wire:model.live.debounce.250ms="search"
                           placeholder="{{ __("Search invoice #, customer...") }}"
                           class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-1 focus:ring-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        🔍
                    </div>
                </div>

                @if ($activeTab === 'invoices')
                    <!-- Status Filter -->
                    <select wire:model.live="statusFilter" class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <option value="">{{ __("All Statuses") }}</option>
                        <option value="pending">{{ __("Pending / Unpaid") }}</option>
                        <option value="partially_paid">{{ __("Partially Paid") }}</option>
                        <option value="overdue">{{ __("Overdue Accounts") }}</option>
                        <option value="paid">{{ __("Fully Settled (Paid)") }}</option>
                    </select>

                    <!-- Customer Filter -->
                    <select wire:model.live="customerFilter" class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-300 max-w-[160px] truncate">
                        <option value="">{{ __("All Customers") }}</option>
                        @foreach ($allCustomers as $ac)
                            <option value="{{ $ac->id }}">{{ $ac->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: CREDIT INVOICES LEDGER DATA TABLE (High-Density Desktop Grid)     -->
        <!-- ========================================================================= -->
        @if ($activeTab === 'invoices')
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700 sticky top-0">
                        <tr>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Sale / Inv #") }}</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Customer") }}</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Date") }}</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Due Date") }}</th>
                            <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Total Bill") }}</th>
                            <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Paid") }}</th>
                            <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Balance Due") }}</th>
                            <th class="py-2.5 px-3 text-center border-r border-slate-200 dark:border-slate-700/60">{{ __("Status") }}</th>
                            <th class="py-2.5 px-3 text-center">{{ __("Actions") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-[11px] font-medium">
                        @forelse ($invoices as $inv)
                            @php
                                $isOverdue = $inv->due_amount > 0 && $inv->due_date && $inv->due_date->isPast();
                            @endphp
                            <tr class="hover:bg-blue-50/40 dark:hover:bg-slate-800/50 transition">
                                <!-- Inv # -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-mono font-bold text-blue-600 dark:text-blue-400">
                                    <a wire:navigate.hover href="{{ route('tenant.sales.show', $inv) }}" class="hover:underline">{{ $inv->sale_number }}</a>
                                </td>

                                <!-- Customer -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-bold text-slate-800 dark:text-slate-200">
                                    {{ $inv->customer_name ?: ($inv->customer?->name ?: __('Walk-in')) }}
                                    @if ($inv->customer?->phone)
                                        <span class="block text-[10px] text-slate-400 font-normal">{{ $inv->customer->phone }}</span>
                                    @endif
                                </td>

                                <!-- Date -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-slate-500 font-mono">
                                    {{ $inv->created_at ? $inv->created_at->format('Y-m-d') : '—' }}
                                </td>

                                <!-- Due Date -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-mono">
                                    @if ($inv->due_date)
                                        <span @class([
                                            'font-bold',
                                            'text-rose-600 dark:text-rose-400 font-black' => $isOverdue,
                                            'text-slate-600 dark:text-slate-300' => ! $isOverdue,
                                        ])>
                                            {{ $inv->due_date->format('Y-m-d') }}
                                            @if ($isOverdue)
                                                <span class="text-[9px] block text-rose-500 font-sans uppercase">OVERDUE</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-normal">{{ __("Immediate") }}</span>
                                    @endif
                                </td>

                                <!-- Total Bill -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    ${{ number_format($inv->total, 2) }}
                                </td>

                                <!-- Paid -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    ${{ number_format($inv->paid_amount, 2) }}
                                </td>

                                <!-- Balance Due -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-extrabold">
                                    @if ($inv->due_amount > 0)
                                        <span class="text-rose-600 dark:text-rose-400">${{ number_format($inv->due_amount, 2) }}</span>
                                    @else
                                        <span class="text-slate-400 font-normal">$0.00</span>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-center">
                                    @if ($inv->due_amount <= 0.001)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            {{ __("PAID") }}
                                        </span>
                                    @elseif ($isOverdue)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            {{ __("OVERDUE") }}
                                        </span>
                                    @elseif ($inv->paid_amount > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            {{ __("PARTIAL") }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            UN{{ __("PAID") }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-2 px-3 text-center space-x-1.5 whitespace-nowrap">
                                    @if ($inv->due_amount > 0)
                                        <button type="button"
                                                wire:click="openPaymentModal({{ $inv->id }})"
                                                class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] transition shadow-2xs active:scale-95 cursor-pointer">
                                            + {{ __("Collect Pay") }}
                                        </button>
                                    @endif

                                    <button type="button" x-data
                                            x-on:click="$dispatch('open-print-preview', { url: @js(route('tenant.sales.pdf', ['sale' => $inv, 'embed' => 1])), title: @js(__('Invoice Preview')) })"
                                            class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 font-bold text-[10px] transition" title="{{ __("Print receipt/invoice") }}">
                                        🖨️
                                    </button>

                                    <a wire:navigate.hover href="{{ route('tenant.sales.show', $inv) }}" class="px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 font-bold text-[10px] hover:underline">
                                        {{ __("View") }} &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    {{ __("No accounts receivable records match current filters.") }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-3 border-t border-slate-200 dark:border-slate-800">
                {{ $invoices->links() }}
            </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: CUSTOMER DIRECTORY BALANCES                                       -->
        <!-- ========================================================================= -->
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Customer Name") }}</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Phone / Contact") }}</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("City / Location") }}</th>
                            <th class="py-2.5 px-3 text-center border-r border-slate-200 dark:border-slate-700/60">{{ __("Pending Invoices") }}</th>
                            <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Total Outstanding Balance") }}</th>
                            <th class="py-2.5 px-3 text-center">{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-[11px] font-medium">
                        @forelse ($customersWithBalances as $c)
                            <tr class="hover:bg-blue-50/40 dark:hover:bg-slate-800/50 transition">
                                <td class="py-2.5 px-3 border-r border-slate-100 dark:border-slate-800 font-bold text-slate-900 dark:text-white">
                                    {{ $c->name }}
                                </td>
                                <td class="py-2.5 px-3 border-r border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-300 font-mono">
                                    {{ $c->phone ?: ($c->email ?: '—') }}
                                </td>
                                <td class="py-2.5 px-3 border-r border-slate-100 dark:border-slate-800 text-slate-500">
                                    {{ $c->city ? $c->city . ($c->state ? ', ' . $c->state : '') : '—' }}
                                </td>
                                <td class="py-2.5 px-3 border-r border-slate-100 dark:border-slate-800 text-center font-bold font-mono">
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-[10px]',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $c->pending_sales_count > 0,
                                        'bg-slate-100 text-slate-500 dark:bg-slate-800' => $c->pending_sales_count <= 0,
                                    ])>
                                        {{ $c->pending_sales_count }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-extrabold">
                                    @if ($c->total_due_balance > 0)
                                        <span class="text-rose-600 dark:text-rose-400 text-xs">${{ number_format($c->total_due_balance, 2) }}</span>
                                    @else
                                        <span class="text-emerald-600 font-bold">$0.00</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <button type="button"
                                            wire:click="$set('customerFilter', {{ $c->id }}); setTab('invoices')"
                                            class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 font-bold hover:underline">
                                        {{ __("View Invoices") }} &rarr;
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    {{ __("No customer records found.") }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    <!-- Manual Payment Logging Modal -->
    @if ($showPaymentModal && $selectedSale)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             x-data
             x-on:keydown.escape.window="$wire.closePaymentModal()"
             x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-5 sm:p-6 space-y-4">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Record Receivable Payment") }}</h3>
                        <p class="text-xs text-slate-400">{{ __('Sale') }} #{{ $selectedSale->sale_number }} &bull; {{ __('Customer:') }} {{ $selectedSale->customer_name ?: __('Walk-in') }}</p>
                    </div>
                    <button type="button" wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <!-- Invoice Quick Snapshot -->
                <div class="grid grid-cols-3 gap-2 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 text-center font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold block uppercase">{{ __("Total Bill") }}</span>
                        <span class="text-xs font-black text-slate-900 dark:text-white">${{ number_format($selectedSale->total, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-emerald-600 font-bold block uppercase">{{ __("Paid so far") }}</span>
                        <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">${{ number_format($selectedSale->paid_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-rose-500 font-bold block uppercase">{{ __("Current Due") }}</span>
                        <span class="text-xs font-black text-rose-600 dark:text-rose-400">${{ number_format($selectedSale->due_amount, 2) }}</span>
                    </div>
                </div>

                <!-- Form Inputs -->
                <div class="space-y-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Collection Amount ($)") }}</label>
                        <input type="number"
                               wire:model="paymentAmount"
                               step="0.01"
                               min="0.01"
                               class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                        @error('paymentAmount') <span class="text-rose-600 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Method") }}</label>
                            <select wire:model="paymentMethod" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                                @foreach ($paymentMethods as $pm)
                                    <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Date") }}</label>
                            <input type="date" wire:model="paymentDate" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Reference / Transaction / Check #") }}</label>
                        <input type="text" wire:model="referenceNumber" placeholder="{{ __("e.g. TXN-998822 or Check #4401") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Remarks") }}</label>
                        <textarea wire:model="paymentNotes" rows="2" placeholder="{{ __("Notes regarding installment / settlement...") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                    <button type="button" wire:click="closePaymentModal" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs">
                        Cancel
                    </button>

                    <button type="button" wire:click="recordPayment" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md shadow-emerald-500/25 active:scale-95 transition">
                        {{ __("Confirm Collection Receipt") }}
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
