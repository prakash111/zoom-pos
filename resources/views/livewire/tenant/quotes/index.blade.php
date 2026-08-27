<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Quotes & Proposals") }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Create formal quotations and convert them to sales in 1-click") }}</p>
        </div>

        <a wire:navigate.hover href="{{ route('tenant.quotes.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 active:scale-95 transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ __("New Quotation") }}</span>
        </a>
    </div>

    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif

    <!-- Search & Filter Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="relative flex-1 max-w-md">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __("Search quotation # or client name...") }}"
                   class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 rounded-2xl border-none shadow-[0_2px_15px_rgb(0,0,0,0.03)] text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-slate-100">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        <!-- Filter pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
            @foreach (['all' => __('All Quotes'), 'draft' => __('Draft'), 'sent' => __('Sent'), 'accepted' => __('Accepted'), 'converted' => __('Converted'), 'rejected' => __('Rejected')] as $sKey => $sLabel)
                <button type="button"
                        wire:click="$set('statusFilter', '{{ $sKey }}')"
                        @class([
                            'px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition shadow-xs cursor-pointer',
                            'bg-blue-600 text-white' => $statusFilter === $sKey,
                            'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' => $statusFilter !== $sKey,
                        ])>
                    {{ $sLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Bulk Actions Toolbar -->
    @if (count($selectedQuotes) > 0)
        <div class="p-3 bg-blue-50 dark:bg-blue-950/60 rounded-2xl border border-blue-100 dark:border-blue-900/50 flex items-center justify-between gap-4 animate-in fade-in">
            <span class="text-xs font-bold text-blue-900 dark:text-blue-300">
                {{ count($selectedQuotes) }} {{ __("quotation(s) selected") }}
            </span>
            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="bulkDelete"
                        wire:confirm="{{ __("Are you sure you want to delete the selected quotations?") }}"
                        class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition cursor-pointer">
                    {{ __("Delete Selected") }}
                </button>
            </div>
        </div>
    @endif

    <!-- Quotes Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="text-slate-400 dark:text-slate-500 font-bold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="pb-3 w-8">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500/20 dark:bg-slate-800 transition cursor-pointer">
                        </th>
                        <th class="pb-3">{{ __("Quote #") }}</th>
                        <th class="pb-3">{{ __("Client") }}</th>
                        <th class="pb-3">{{ __("Date") }}</th>
                        <th class="pb-3 text-right">{{ __("Total") }}</th>
                        <th class="pb-3 text-center">{{ __("Status") }}</th>
                        <th class="pb-3 text-right">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($quotes as $quote)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3.5">
                                <input type="checkbox"
                                       wire:model.live="selectedQuotes"
                                       value="{{ $quote->id }}"
                                       class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500/20 dark:bg-slate-800 transition cursor-pointer">
                            </td>
                            <td class="py-3.5 font-mono font-bold text-blue-600 dark:text-blue-400">
                                <a wire:navigate.hover href="{{ route('tenant.quotes.show', $quote) }}" class="hover:underline">
                                    {{ $quote->sale_number }}
                                </a>
                            </td>
                            <td class="py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                {{ $quote->customer_name ?? '—' }}
                            </td>
                            <td class="py-3.5 text-slate-400 text-xs">
                                {{ $quote->created_at ? $quote->created_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="py-3.5 text-right font-black text-slate-900 dark:text-white">
                                ${{ number_format($quote->total, 2) }}
                            </td>
                            <td class="py-3.5 text-center">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-extrabold',
                                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $quote->status === 'draft',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' => $quote->status === 'sent',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $quote->status === 'accepted' || $quote->status === 'converted',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $quote->status === 'rejected',
                                ])>{{ ucfirst($quote->status) }}</span>
                            </td>
                            <td class="py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($quote->status !== 'converted')
                                        @if (auth('web')->user()?->hasPermission('quotes', 'convert_to_sale'))
                                            <button type="button"
                                                    wire:click="convertToSale({{ $quote->id }})"
                                                    wire:confirm="{{ __("Convert quote #") }}{{ $quote->sale_number }} {{ __("to Sale invoice?") }}"
                                                    class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition active:scale-95 cursor-pointer">
                                                {{ __("Convert") }}
                                            </button>
                                        @endif
                                        <a wire:navigate.hover href="{{ route('tenant.quotes.edit', $quote) }}"
                                           class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                                            {{ __("Edit") }}
                                        </a>
                                    @endif
                                    <a wire:navigate.hover href="{{ route('tenant.quotes.show', $quote) }}"
                                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 hover:bg-blue-100 transition">
                                        {{ __("View") }}
                                    </a>
                                    <a href="{{ route('tenant.quotes.pdf', $quote) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       data-turbo="false"
                                       class="px-2 py-1 text-slate-400 hover:text-slate-600 transition font-bold text-xs"
                                       title="{{ __("Download / Stream PDF") }}">
                                        {{ __("PDF") }}
                                    </a>
                                    <button type="button"
                                            wire:click="deleteQuote({{ $quote->id }})"
                                            wire:confirm="{{ __("Are you sure you want to delete this quote?") }}"
                                            class="text-rose-400 hover:text-rose-600 font-bold text-xs px-1 cursor-pointer">
                                        &times;
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                {{ __("No quotations found matching the filter criteria.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($quotes->hasPages())
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>
</div>
