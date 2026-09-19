<div class="space-y-5">
    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4">
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __("Search by sale # or customer…") }}"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl py-2.5 pl-10 pr-4 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500 shadow-xs">
        </div>

        <a wire:navigate.hover href="{{ route('tenant.sales.create') }}"
           class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>+ {{ __("New Sale") }}</span>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">{{ __("Sale #") }}</th>
                        <th class="px-5 py-3.5">{{ __("Customer") }}</th>
                        <th class="px-5 py-3.5">{{ __("Total") }}</th>
                        <th class="px-5 py-3.5">{{ __("Status") }}</th>
                        <th class="px-5 py-3.5">{{ __("Date") }}</th>
                        <th class="px-5 py-3.5 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($sales as $sale)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="px-5 py-4 font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                                <div class="flex items-center gap-1.5">
                                    <a wire:navigate.hover href="{{ route('tenant.sales.show', $sale) }}" class="hover:underline">
                                        {{ $sale->sale_number }}
                                    </a>
                                    @if ($sale->service_type === 'storefront')
                                        <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 uppercase tracking-wider">
                                            {{ __('Storefront') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-800 dark:text-slate-200">
                                <div class="font-bold text-xs sm:text-sm">{{ $sale->customer_name ?? __('Walk-in') }}</div>
                                @if ($sale->customer_phone)
                                    <div class="text-[11px] text-slate-400">📞 {{ $sale->customer_phone }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-bold text-slate-900 dark:text-white">${{ number_format($sale->total, 2) }}</td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-bold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $sale->status === 'completed',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' => $sale->status === 'pending',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $sale->status === 'cancelled',
                                ])>{{ ucfirst($sale->status) }}</span>
                            </td>
                            <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $sale->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4 text-right">
                                <a wire:navigate.hover href="{{ route('tenant.sales.show', $sale) }}" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline px-2.5 py-1 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 transition">
                                    {{ __("View") }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-xs">
                                {{ __("No sales found.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $sales->links() }}</div>
</div>
