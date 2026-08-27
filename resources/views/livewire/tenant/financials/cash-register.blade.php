<div class="space-y-5 text-xs font-sans">

    <!-- 1. Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">🗄️</span>
                <div>
                    <h1 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __("Cash Register & Shift Audit Ledger") }}
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5 font-medium">
                        {{ __("5-Tier daily register lifecycle: Opening floats, Sangria (withdrawals), Suprimento (injections), and midnight shift reconciliation.") }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if ($openRegister)
                <button type="button"
                        wire:click="openMovementModal('cash_in', 'suprimento')"
                        class="px-3.5 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                    <span>➕</span>
                    <span>{{ __("Cash Addition") }}</span>
                </button>
                <button type="button"
                        wire:click="openMovementModal('cash_out', 'sangria')"
                        class="px-3.5 py-2 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md shadow-amber-600/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                    <span>➖</span>
                    <span>{{ __("Sangria (Cash Out)") }}</span>
                </button>
                <button type="button"
                        wire:click="openCloseModal"
                        class="px-3.5 py-2 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                    <span>🔒</span>
                    <span>{{ __("Close Register") }}</span>
                </button>
            @else
                <button type="button"
                        wire:click="openRegisterModal"
                        class="px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-lg shadow-blue-600/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                    <span>🔓</span>
                    <span>{{ __("Open Today's Register") }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Status Alerts -->
    @if (session('status'))
        <div class="p-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center justify-between gap-2 shadow-xs">
            <div class="flex items-center gap-2">
                <span class="text-base">✓</span>
                <span>{{ session('status') }}</span>
            </div>
            @if ($lastCreatedTransactionId)
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('tenant.cash_register.movement.view', $lastCreatedTransactionId) }}" target="_blank" class="px-2.5 py-1 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 text-[11px] font-extrabold">
                        🖨️ {{ __("Print Slip") }}
                    </a>
                </div>
            @endif
        </div>
    @endif
    @if (session('error'))
        <div class="p-3.5 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold flex items-center gap-2 shadow-xs">
            <span class="text-base">⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Stale Midnight Warning Banner -->
    @if ($openRegister && $openRegister->isStaleMidnight())
        <div class="p-4 rounded-3xl bg-amber-500/10 border-2 border-amber-500/40 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-md">
            <div class="flex items-start gap-3">
                <span class="text-2xl">⏰</span>
                <div>
                    <div class="font-black text-sm text-amber-800 dark:text-amber-300">{{ __("Midnight Session Rollover Detected") }}</div>
                    <div class="text-xs text-amber-700/80 dark:text-amber-300/80 mt-0.5">
                        {{ __("This cash register was opened on") }} <strong>{{ $openRegister->opened_at->format('d M Y') }}</strong> ({{ $openRegister->opened_at->diffForHumans() }}). {{ __("Please perform end-of-day settlement to close the previous shift before opening today's register.") }}
                    </div>
                </div>
            </div>
            <button type="button" wire:click="openCloseModal" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs shrink-0 cursor-pointer shadow-md">
                {{ __("Settle & Close Shift") }} &rarr;
            </button>
        </div>
    @endif

    <!-- 2. Live Shift Dashboard (When Open) -->
    @if ($openRegister && $liveMetrics)
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- Opening Float -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Starting Cash Float") }}</span>
                    <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono mt-1">${{ number_format($liveMetrics['opening_balance'], 2) }}</div>
                </div>
                <div class="text-[10px] text-slate-400 font-semibold mt-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __("By") }} {{ $liveMetrics['opened_by_name'] }} &bull; {{ $openRegister->opened_at->format('H:i') }}</span>
                </div>
            </div>

            <!-- Gross & Cash Sales -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __("Cash Sales / Gross") }}</span>
                    <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-1">${{ number_format($liveMetrics['cash_sales'], 2) }}</div>
                </div>
                <div class="text-[10px] text-slate-400 font-semibold mt-2">
                    ${{ number_format($liveMetrics['total_sales'], 2) }} {{ __("total gross") }} ({{ $liveMetrics['sale_count'] }} {{ __("sales") }})
                </div>
            </div>

            <!-- Cash In / Out (Suprimento / Sangria) -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __("Drawer Movements") }}</span>
                    <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-white font-mono mt-1">
                        <span class="text-emerald-600">+${{ number_format($liveMetrics['cash_in'], 2) }}</span> /
                        <span class="text-rose-600">-${{ number_format($liveMetrics['cash_out'], 2) }}</span>
                    </div>
                </div>
                <div class="text-[10px] text-slate-400 font-semibold mt-2">
                    {{ $openRegister->transactions->count() }} {{ __("manual transaction(s)") }}
                </div>
            </div>

            <!-- Expected Cash in Drawer -->
            <div class="bg-blue-50/80 dark:bg-blue-950/40 p-4 rounded-3xl border border-blue-200 dark:border-blue-900 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ __("Expected Drawer Cash") }}</span>
                        <span class="text-[9px] font-black px-1.5 py-0.5 rounded-full bg-blue-600 text-white uppercase">{{ __("Live X-Report") }}</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-blue-700 dark:text-blue-300 font-mono mt-1">${{ number_format($liveMetrics['expected_cash'], 2) }}</div>
                </div>
                <div class="text-[10px] text-blue-500 dark:text-blue-400 font-semibold mt-2 flex items-center justify-between">
                    <span>{{ __("Terminal:") }} {{ $openRegister->terminal_id ?? 'Main POS' }}</span>
                    <a href="{{ route('tenant.cash_register.z_report.view', $openRegister->id) }}" target="_blank" class="hover:underline font-bold">
                        🖨️ {{ __("X-Slip") }} &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Breakdown by Payment Method & Real-time Drawer Movements -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Payment Methods Breakdown -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>💳</span>
                        <span>{{ __("Sales by Payment Method (Current Shift)") }}</span>
                    </h3>
                    <span class="text-[11px] font-bold text-slate-400">{{ $liveMetrics['sale_count'] }} {{ __("sales") }}</span>
                </div>
                <div class="space-y-2">
                    @forelse ($liveMetrics['payments_by_method'] as $method => $data)
                        <div class="flex items-center justify-between p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="text-base">
                                    {{ match(strtolower($method)) { 'cash' => '💵', 'card' => '💳', 'upi' => '📱', 'credit' => '📝', default => '🏷️' } }}
                                </span>
                                <div>
                                    <div class="font-bold text-slate-700 dark:text-slate-200 capitalize">{{ $method }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $data['count'] }} {{ __("transaction(s)") }}</div>
                                </div>
                            </div>
                            <span class="font-mono font-black text-sm text-slate-900 dark:text-white">${{ number_format($data['total'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-slate-400 italic py-4 text-center">{{ __("No sales recorded yet during this shift session.") }}</p>
                    @endforelse
                </div>
            </div>

            <!-- Cash Drawer Movements (Sangria / Suprimento) -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📋</span>
                        <span>{{ __("Live Drawer Movement Log") }}</span>
                    </h3>
                    <div class="flex items-center gap-1.5">
                        <button type="button" wire:click="openMovementModal('cash_in', 'suprimento')" class="px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 font-bold text-[10px] cursor-pointer">
                            + {{ __("Cash Addition") }}
                        </button>
                        <button type="button" wire:click="openMovementModal('cash_out', 'sangria')" class="px-2 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 hover:bg-amber-100 font-bold text-[10px] cursor-pointer">
                            - {{ __("Sangria") }}
                        </button>
                    </div>
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @forelse ($movements as $m)
                        <div class="flex items-center justify-between p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' => $m->type === 'cash_in',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' => $m->type === 'cash_out',
                                    ])>
                                        {{ $m->getCategoryLabel() }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $m->voucher_number ?? "#{$m->id}" }}</span>
                                </div>
                                @if ($m->reason)
                                    <div class="text-[11px] text-slate-600 dark:text-slate-300 font-medium mt-0.5">{{ $m->reason }}</div>
                                @endif
                                <div class="text-[9px] text-slate-400 mt-0.5">
                                    {{ $m->created_at->format('H:i') }} &bull; {{ $m->creator?->name ?? 'Staff' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-mono font-black text-sm {{ $m->type === 'cash_in' ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ $m->type === 'cash_in' ? '+' : '-' }}${{ number_format($m->amount, 2) }}
                                </span>
                                <div class="mt-1 flex items-center justify-end gap-1">
                                    <a href="{{ route('tenant.cash_register.movement.view', $m->id) }}" target="_blank" class="text-slate-400 hover:text-blue-600 text-[10px]" title="{{ __('Print Voucher Slip') }}">🖨️</a>
                                    <a href="{{ route('tenant.cash_register.movement.pdf', ['tx' => $m->id, 'download' => 1]) }}" class="text-slate-400 hover:text-blue-600 text-[10px]" title="{{ __('Download PDF') }}">📥</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-400 italic py-6 text-center">{{ __("No manual cash movements recorded during this shift.") }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- 3. Comprehensive Cash Register Audit History & Shift Ledger Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <!-- Table Header & Filters Toolbar -->
        <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📊</span>
                        <span>{{ __("Cash Register Audit History & Shift Ledger") }}</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __("Complete historical ledger of all cashier shift sessions, physical cash counts, and variance settlements.") }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-2.5 pt-2">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">{{ __("Status") }}</label>
                    <select wire:model.live="statusFilter" class="w-full py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200">
                        <option value="all">{{ __("All Sessions") }}</option>
                        <option value="open">{{ __("Open Sessions") }}</option>
                        <option value="closed">{{ __("Closed / Settled") }}</option>
                        <option value="flagged_variance">{{ __("Flagged Variances (Over/Short)") }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">{{ __("Cashier / Staff") }}</label>
                    <select wire:model.live="cashierId" class="w-full py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200">
                        <option value="">{{ __("All Cashiers") }}</option>
                        @foreach ($cashiers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->role }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">{{ __("From Date") }}</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">{{ __("To Date") }}</label>
                    <input type="date" wire:model.live="dateTo" class="w-full py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 font-bold">
                </div>

                <div class="flex items-end">
                    <button type="button" wire:click="resetFilters" class="w-full py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-extrabold transition cursor-pointer">
                        {{ __("Reset Filters") }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-[10px] uppercase font-extrabold text-slate-400 tracking-wider">
                    <tr>
                        <th class="px-4 py-3">{{ __("Session ID") }}</th>
                        <th class="px-4 py-3">{{ __("Shift Period") }}</th>
                        <th class="px-4 py-3">{{ __("Cashier / Staff") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Opening") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Expected") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Counted") }}</th>
                        <th class="px-4 py-3 text-center">{{ __("Variance") }}</th>
                        <th class="px-4 py-3 text-center">{{ __("Status") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($history as $reg)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-mono font-black text-slate-900 dark:text-white">
                                #{{ $reg->id }}
                                <div class="text-[9px] text-slate-400 font-normal">{{ $reg->terminal_id ?? 'Main POS' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $reg->opened_at->format('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $reg->opened_at->format('H:i') }} &rarr; {{ $reg->closed_at ? $reg->closed_at->format('H:i') : __('Open') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-700 dark:text-slate-200">{{ $reg->opener?->name ?? '—' }}</div>
                                @if ($reg->closer && $reg->closer->id !== $reg->opened_by)
                                    <div class="text-[10px] text-slate-400">Closed by {{ $reg->closer->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                ${{ number_format($reg->opening_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                ${{ number_format($reg->expected_closing_balance ?? $reg->opening_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-black text-slate-900 dark:text-white">
                                ${{ number_format($reg->counted_closing_balance ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($reg->isOpen())
                                    <span class="text-[10px] text-slate-400 italic">{{ __("In Progress") }}</span>
                                @else
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-[10px] font-black font-mono inline-block',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' => $reg->cash_difference == 0,
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300' => $reg->cash_difference < 0,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' => $reg->cash_difference > 0,
                                    ])>
                                        {{ $reg->cash_difference >= 0 ? '+' : '' }}${{ number_format($reg->cash_difference, 2) }}
                                        ({{ $reg->cash_difference == 0 ? __('Balanced') : ($reg->cash_difference < 0 ? __('Short') : __('Over')) }})
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span @class([
                                    'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300' => $reg->isOpen(),
                                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => ! $reg->isOpen(),
                                ])>
                                    {{ $reg->isOpen() ? __('OPEN') : __('CLOSED') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            wire:click="viewRegister({{ $reg->id }})"
                                            class="px-2.5 py-1 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-300 hover:bg-blue-600 hover:text-white font-bold text-[11px] transition cursor-pointer"
                                            title="{{ __('View Shift Breakdown & Audit Details') }}">
                                        👁️ {{ __("Summary") }}
                                    </button>
                                    <a href="{{ route('tenant.cash_register.z_report.view', $reg->id) }}"
                                       target="_blank"
                                       class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-[11px] transition"
                                       title="{{ __('Print 80mm Z-Report') }}">
                                        🖨️
                                    </a>
                                    <a href="{{ route('tenant.cash_register.z_report.pdf', ['register' => $reg->id, 'download' => 1]) }}"
                                       class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-[11px] transition"
                                       title="{{ __('Download PDF Z-Report') }}">
                                        📥
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-slate-400 italic">
                                {{ __("No cash register shift sessions match the selected filters.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $history->links() }}
        </div>
    </div>

    <!-- ==========================================
         MODAL 1: OPEN CASH REGISTER
         ========================================== -->
    @if ($showOpenModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showOpenModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🔓</span>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Open Daily Cash Register") }}</h3>
                    </div>
                    <button type="button" wire:click="$set('showOpenModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Terminal / Workstation Name") }}</label>
                        <input type="text" wire:model="terminalId" class="w-full py-2 px-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-extrabold text-slate-700 dark:text-slate-300">{{ __("Starting Float Amount ($)") }}</label>
                            <button type="button" wire:click="$toggle('showDenominationCounter')" class="text-[11px] font-extrabold text-blue-600 hover:underline cursor-pointer">
                                {{ $showDenominationCounter ? __('Hide Denominations') : __('Use Denomination Counter') }}
                            </button>
                        </div>
                        <input type="number" min="0" step="0.01" wire:model="openingBalance" class="w-full py-2.5 px-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-base font-mono font-black focus:ring-2 focus:ring-blue-500 text-slate-900 dark:text-white">
                        @error('openingBalance') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Denomination Counter Tool -->
                    @if ($showDenominationCounter)
                        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Physical Cash Denomination Counter") }}</div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['500', '200', '100', '50', '20', '10', '5', '2', '1'] as $denom)
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-10 text-right font-mono font-bold text-xs text-slate-600 dark:text-slate-300">${{ $denom }}:</span>
                                        <input type="number" min="0" wire:model.live="denominations.{{ $denom }}" class="w-14 py-1 px-1.5 text-center rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-mono text-xs font-bold">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Opening Notes / Handover Remarks (Optional)") }}</label>
                        <textarea wire:model="openingNotes" rows="2" placeholder="{{ __('e.g. Received float from morning shift supervisor') }}" class="w-full py-2 px-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showOpenModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __("Cancel") }}
                    </button>
                    <button type="button" wire:click="openRegister" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs cursor-pointer shadow-md">
                        {{ __("Confirm & Open Shift") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ==========================================
         MODAL 2: CASH DRAWER MOVEMENT (SANGRIA / SUPRIMENTO)
         ========================================== -->
    @if ($showMovementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showMovementModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">{{ $movementType === 'cash_in' ? '➕' : '➖' }}</span>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">
                            {{ $movementType === 'cash_in' ? __('Cash Addition') : __('Cash Withdrawal') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="$set('showMovementModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Operation Category") }}</label>
                        <select wire:model="movementCategory" class="w-full py-2 px-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                            @if ($movementType === 'cash_in')
                                <option value="suprimento">{{ __("Cash Addition") }}</option>
                                <option value="change_injection">{{ __("Change Top-Up from Safe") }}</option>
                                <option value="other">{{ __("Other Cash Inflow") }}</option>
                            @else
                                <option value="sangria">{{ __("Sangria / Safe Drop / Bank Deposit") }}</option>
                                <option value="petty_cash">{{ __("Petty Cash Operational Expense") }}</option>
                                <option value="supplier_payment">{{ __("Direct Supplier / Vendor Cash Payment") }}</option>
                                <option value="other">{{ __("Other Cash Withdrawal") }}</option>
                            @endif
                        </select>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Amount ($)") }}</label>
                        <input type="number" min="0.01" step="0.01" wire:model="movementAmount" class="w-full py-2.5 px-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-base font-mono font-black focus:ring-2 focus:ring-blue-500 text-slate-900 dark:text-white">
                        @error('movementAmount') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Reason / Description / Recipient") }}</label>
                        <input type="text" wire:model="movementReason" placeholder="{{ __('e.g. Milk & supplies purchase, Bank vault transfer') }}" class="w-full py-2 px-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showMovementModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __("Cancel") }}
                    </button>
                    <button type="button" wire:click="recordMovement" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs cursor-pointer shadow-md">
                        {{ __("Record & Generate Slip") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ==========================================
         MODAL 3: CLOSE REGISTER & SETTLEMENT
         ========================================== -->
    @if ($showCloseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showCloseModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🔒</span>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Close Register & Generate Shift Z-Report") }}</h3>
                    </div>
                    <button type="button" wire:click="$set('showCloseModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("System Expected Cash") }}</span>
                        <div class="text-lg font-mono font-black text-slate-900 dark:text-white">${{ number_format($closeExpectedCash, 2) }}</div>
                    </div>
                    <span class="text-xs text-slate-400 font-semibold">{{ __("Float + Cash Sales + Movements") }}</span>
                </div>

                <div>
                    <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Physical Counted Cash in Drawer ($)") }}</label>
                    <input type="number" min="0" step="0.01" wire:model.live="countedClosingBalance" class="w-full py-2.5 px-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-lg font-mono font-black text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                    @error('countedClosingBalance') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Live Variance Badge -->
                <div @class([
                    'p-3.5 rounded-2xl flex items-center justify-between border shadow-xs',
                    'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' => $this->closeVariance == 0,
                    'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200' => $this->closeVariance < 0,
                    'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200' => $this->closeVariance > 0,
                ])>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider">
                            {{ $this->closeVariance == 0 ? __('✓ BALANCED REGISTER') : ($this->closeVariance < 0 ? __('⚠️ CASH SHORTAGE') : __('⚠️ CASH OVERAGE')) }}
                        </span>
                        <div class="text-xs font-semibold">
                            {{ $this->closeVariance == 0 ? __('Drawer matches system calculations exactly.') : __('Difference recorded in permanent shift audit.') }}
                        </div>
                    </div>
                    <span class="font-mono font-black text-base">
                        {{ $this->closeVariance >= 0 ? '+' : '' }}${{ number_format($this->closeVariance, 2) }}
                    </span>
                </div>

                <div>
                    <label class="block font-extrabold text-slate-700 dark:text-slate-300 mb-1">{{ __("Closing Settlement Remarks (Optional)") }}</label>
                    <textarea wire:model="closeNotes" rows="2" placeholder="{{ __('e.g. Safe deposit completed; ₹500 kept for tomorrow opening') }}" class="w-full py-2 px-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showCloseModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __("Cancel") }}
                    </button>
                    <button type="button" wire:click="closeRegister" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs cursor-pointer shadow-md">
                        {{ __("Confirm & Close Shift") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ==========================================
         MODAL 4: VIEW DETAILED SHIFT Z-REPORT
         ========================================== -->
    @if ($viewingRegister && $viewingMetrics)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.closeViewRegister()">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-xl w-full p-6 space-y-4 max-h-[88vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📊</span>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ __("Shift Z-Report") }} &bull; #{{ $viewingRegister->id }}
                            </h3>
                            <div class="text-xs text-slate-400 font-medium">
                                {{ $viewingRegister->opened_at->format('d M Y') }} &bull; {{ $viewingRegister->terminal_id ?? 'Main POS' }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('tenant.cash_register.z_report.view', $viewingRegister->id) }}" target="_blank" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-xs transition flex items-center gap-1">
                            🖨️ {{ __("Print 80mm") }}
                        </a>
                        <a href="{{ route('tenant.cash_register.z_report.pdf', ['register' => $viewingRegister->id, 'download' => 1]) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition">
                            📥 {{ __("PDF") }}
                        </a>
                        <button type="button" wire:click="closeViewRegister" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-xl cursor-pointer ml-1">&times;</button>
                    </div>
                </div>

                <!-- Key Metrics Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Opening Float") }}</span>
                        <div class="font-mono font-black text-sm text-slate-900 dark:text-white">${{ number_format($viewingMetrics['opening_balance'], 2) }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Total Gross") }}</span>
                        <div class="font-mono font-black text-sm text-slate-900 dark:text-white">${{ number_format($viewingMetrics['total_sales'], 2) }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Expected Cash") }}</span>
                        <div class="font-mono font-black text-sm text-slate-900 dark:text-white">${{ number_format($viewingMetrics['expected_cash'], 2) }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Counted Cash") }}</span>
                        <div class="font-mono font-black text-sm text-slate-900 dark:text-white">${{ number_format($viewingMetrics['counted_cash'], 2) }}</div>
                    </div>
                </div>

                <!-- Variance Banner -->
                <div @class([
                    'p-3 rounded-2xl flex items-center justify-between border font-bold text-xs',
                    'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' => $viewingMetrics['variance'] == 0,
                    'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200' => $viewingMetrics['variance'] < 0,
                    'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200' => $viewingMetrics['variance'] > 0,
                ])>
                    <span>{{ __("Variance (Over / Short):") }}</span>
                    <span class="font-mono font-black text-sm">
                        {{ $viewingMetrics['variance'] >= 0 ? '+' : '' }}${{ number_format($viewingMetrics['variance'], 2) }}
                        ({{ $viewingMetrics['variance'] == 0 ? __('BALANCED') : ($viewingMetrics['variance'] < 0 ? __('SHORT') : __('OVER')) }})
                    </span>
                </div>

                <!-- Sales by Payment Method -->
                <div>
                    <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">{{ __("Sales by Payment Method") }}</h4>
                    <div class="space-y-1.5">
                        @forelse ($viewingMetrics['payments_by_method'] as $method => $data)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                <span class="capitalize font-bold text-slate-700 dark:text-slate-300">{{ $method }} ({{ $data['count'] }})</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white">${{ number_format($data['total'], 2) }}</span>
                            </div>
                        @empty
                            <p class="text-slate-400 italic text-xs">{{ __("No sales recorded.") }}</p>
                        @endforelse
                    </div>
                </div>

                <!-- Drawer Movements -->
                @if ($viewingRegister->transactions && $viewingRegister->transactions->isNotEmpty())
                    <div>
                        <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">{{ __("Drawer Movements Recorded") }}</h4>
                        <div class="space-y-1.5 max-h-40 overflow-y-auto">
                            @foreach ($viewingRegister->transactions as $tx)
                                <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 text-xs">
                                    <div>
                                        <span class="font-bold {{ $tx->type === 'cash_in' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $tx->getCategoryLabel() }}</span>
                                        @if ($tx->reason)
                                            <span class="text-slate-400">&bull; {{ $tx->reason }}</span>
                                        @endif
                                    </div>
                                    <span class="font-mono font-bold">{{ $tx->type === 'cash_in' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- WhatsApp Share Button -->
                @php
                    $whatsAppZUrl = $reportService->generateZReportWhatsAppUrl($viewingRegister);
                @endphp
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ $whatsAppZUrl }}" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5">
                        <span>💬</span>
                        <span>{{ __("Share Z-Report on WhatsApp") }}</span>
                    </a>
                    <button type="button" wire:click="closeViewRegister" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __("Close") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
