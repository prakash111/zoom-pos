<div class="space-y-6">

    <!-- Flash Status Messages -->
    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-emerald-100 dark:border-emerald-900/50 animate-in fade-in">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Header & Statistics Cards -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-xl shadow-xs">
                📦
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Consignments") }}</h1>
                <p class="text-xs text-slate-400 mt-0.5">{{ __("Dispatch goods on consignment, track client returns vs sold merchandise, and convert to invoice.") }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.consignments.create') }}"
               class="px-4 py-2.5 rounded-xl text-xs font-black bg-purple-600 hover:bg-purple-700 text-white shadow-md shadow-purple-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span>+ {{ __("New Consignment") }}</span>
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-2xs">
            <div class="text-[10px] uppercase font-bold text-slate-400">{{ __("Active Dispatched") }}</div>
            <div class="text-xl font-black text-purple-600 dark:text-purple-400 font-mono mt-1">{{ $stats['dispatched'] }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-2xs">
            <div class="text-[10px] uppercase font-bold text-slate-400">{{ __("Dispatched Value") }}</div>
            <div class="text-xl font-black text-slate-900 dark:text-white font-mono mt-1">{{ $company->formatMoney($stats['dispatched_value']) }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-2xs">
            <div class="text-[10px] uppercase font-bold text-slate-400">{{ __("Awaiting Invoice") }}</div>
            <div class="text-xl font-black text-amber-500 font-mono mt-1">{{ $stats['reconciled'] }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-2xs">
            <div class="text-[10px] uppercase font-bold text-slate-400">{{ __("Finalized Sales") }}</div>
            <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-1">{{ $stats['finalized'] }}</div>
        </div>
    </div>

    <!-- Table Section with Filters & Search -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Filter Tabs -->
            <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
                @foreach (['all' => 'All', 'draft' => 'Draft', 'dispatched' => 'Dispatched', 'reconciled' => 'Reconciled', 'finalized' => 'Finalized'] as $st => $lbl)
                    <button type="button"
                            wire:click="$set('statusFilter', '{{ $st }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer',
                                'bg-white dark:bg-slate-700 text-purple-600 dark:text-white shadow-2xs' => $statusFilter === $st,
                                'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' => $statusFilter !== $st,
                            ])>
                        {{ __($lbl) }}
                    </button>
                @endforeach
            </div>

            <!-- Search Input -->
            <div class="relative w-full sm:w-64">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="{{ __("Search number or client...") }}"
                       class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-medium focus:ring-purple-500">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 uppercase tracking-wider text-[11px] font-extrabold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">{{ __("Number") }}</th>
                        <th class="px-4 py-3">{{ __("Customer") }}</th>
                        <th class="px-4 py-3">{{ __("Status") }}</th>
                        <th class="px-4 py-3">{{ __("Due Date") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Dispatched Value") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Sold Total") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($consignments as $c)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3.5">
                                <a wire:navigate.hover href="{{ route('tenant.consignments.show', $c) }}" class="font-mono font-black text-purple-600 dark:text-purple-400 hover:underline">
                                    {{ $c->consignment_number }}
                                </a>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $c->created_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                {{ $c->customer_name }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span @class([
                                    'px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider',
                                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $c->status === 'draft',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' => $c->status === 'dispatched',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' => $c->status === 'reconciled',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $c->status === 'finalized',
                                ])>{{ $c->status }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">
                                {{ $c->due_date ? $c->due_date->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                {{ $company->formatMoney($c->total_dispatched_amount) }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono font-black text-emerald-600 dark:text-emerald-400">
                                {{ $company->formatMoney($c->total_sold_amount) }}
                            </td>
                            <td class="px-4 py-3.5 text-right space-x-2">
                                <a wire:navigate.hover href="{{ route('tenant.consignments.show', $c) }}"
                                   class="px-2.5 py-1 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 hover:bg-purple-100 font-bold text-xs">
                                    {{ __("View") }} &rarr;
                                </a>
                                @if ($c->status !== 'finalized')
                                    <button type="button"
                                            wire:click="deleteConsignment({{ $c->id }})"
                                            wire:confirm="{{ __("Are you sure you want to delete this consignment?") }}"
                                            class="text-rose-500 hover:text-rose-700 font-bold text-xs cursor-pointer">
                                        ✕
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                <span class="text-3xl block mb-2">📦</span>
                                {{ __("No consignments found.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-3">
            {{ $consignments->links() }}
        </div>
    </div>

</div>
