<div class="space-y-6 max-w-5xl mx-auto" x-data="{ showFinalizeModal: @entangle('showFinalizeModal') }">

    <!-- Flash Status Messages -->
    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-emerald-100 dark:border-emerald-900/50 animate-in fade-in">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3.5 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-rose-100 dark:border-rose-900/50 animate-in fade-in">
            <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a wire:navigate.hover href="{{ route('tenant.consignments.index') }}" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 text-xs font-bold transition">
                &larr; {{ __("Back") }}
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">{{ $consignment->consignment_number }}</h1>
                    <span @class([
                        'px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider',
                        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $consignment->status === 'draft',
                        'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' => $consignment->status === 'dispatched',
                        'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' => $consignment->status === 'reconciled',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $consignment->status === 'finalized',
                    ])>{{ $consignment->status }}</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ __("Client:") }} <strong class="text-slate-700 dark:text-slate-300">{{ $consignment->customer_name }}</strong> &bull;
                    {{ __("Created") }} {{ $consignment->created_at->format('M d, Y') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($consignment->status === 'draft')
                <button type="button" wire:click="dispatchGoods" class="px-4 py-2.5 rounded-xl text-xs font-black bg-purple-600 hover:bg-purple-700 text-white shadow-md shadow-purple-500/25 active:scale-95 transition cursor-pointer">
                    🚀 {{ __("Dispatch to Client") }}
                </button>
            @elseif ($consignment->status === 'dispatched' || $consignment->status === 'reconciled')
                <button type="button" wire:click="saveReconciliation" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition cursor-pointer">
                    💾 {{ __("Save Reconciliation") }}
                </button>

                <button type="button" wire:click="openFinalizeModal" class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-500/25 active:scale-95 transition cursor-pointer">
                    🧾 {{ __("Finalize to Sale Invoice") }}
                </button>
            @elseif ($consignment->status === 'finalized' && $consignment->sale_id)
                <a wire:navigate.hover href="{{ route('tenant.sales.show', $consignment->sale_id) }}" class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-md transition">
                    👁️ {{ __("View Generated Sale Invoice") }} &rarr;
                </a>
            @endif
        </div>
    </div>

    <!-- Metadata & Value Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Dispatched Amount -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __("Dispatched Amount") }}</div>
            <div class="text-2xl font-black text-purple-600 font-mono mt-1">
                {{ $company ? $company->formatMoney($this->totalDispatchedAmount) : '$' . number_format($this->totalDispatchedAmount, 2) }}
            </div>
        </div>

        <!-- Returned Amount -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __("Returned Amount (Returned)") }}</div>
            <div class="text-2xl font-black text-slate-700 dark:text-slate-300 font-mono mt-1">
                {{ $company ? $company->formatMoney($this->returnedAmount) : '$' . number_format($this->returnedAmount, 2) }}
            </div>
        </div>

        <!-- Sold Revenue to Bill -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __("Sold Revenue to Bill") }}</div>
            <div class="text-2xl font-black text-emerald-600 font-mono mt-1">
                {{ $company ? $company->formatMoney($this->soldRevenue) : '$' . number_format($this->soldRevenue, 2) }}
            </div>
        </div>
    </div>

    <!-- Reconciliation Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Consigned Items & Stock Reconciliation") }}</h3>
                <p class="text-xs text-slate-400">{{ __("Enter returned vs sold quantities. Sold items will be billed and deducted from main inventory.") }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 uppercase tracking-wider text-[11px] font-extrabold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">{{ __("Product") }}</th>
                        <th class="px-4 py-3 text-center">{{ __("Dispatched") }}</th>
                        <th class="px-4 py-3 text-center">{{ __("Returned Qty") }}</th>
                        <th class="px-4 py-3 text-center">{{ __("Sold Qty") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Unit Price") }}</th>
                        <th class="px-4 py-3 text-right">{{ __("Billed Total") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @foreach ($items as $itemId => $item)
                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-bold text-xs text-slate-900 dark:text-white">
                                {{ $item['product_name'] }}
                            </td>
                            <td class="py-3 px-4 text-xs font-semibold font-mono text-center text-slate-700 dark:text-slate-300">
                                {{ (float) $item['dispatched_qty'] }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($consignment->status === 'finalized')
                                    <span class="font-mono font-bold text-slate-500">{{ (float) $item['returned_qty'] }}</span>
                                @else
                                    <input type="number" 
                                           wire:model.live.debounce.250ms="items.{{ $itemId }}.returned_qty"
                                           min="0" 
                                           max="{{ (float) $item['dispatched_qty'] }}" 
                                           step="any"
                                           class="w-20 text-center py-1 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-bold focus:ring-2 focus:ring-blue-500">
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs font-bold font-mono text-emerald-600 text-center">
                                {{ (float) $item['sold_qty'] }}
                            </td>
                            <td class="py-3 px-4 text-xs font-mono text-right text-slate-500">
                                {{ $company ? $company->formatMoney($item['unit_price']) : '$' . number_format($item['unit_price'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-xs font-bold font-mono text-right text-slate-900 dark:text-white">
                                {{ $company ? $company->formatMoney($item['billed_total']) : '$' . number_format($item['billed_total'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Finalize Modal -->
    <div x-show="showFinalizeModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in"
             @click.outside="showFinalizeModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <span>🧾</span> {{ __("Finalize Sale Invoice") }}
                </h3>
                <button type="button" @click="showFinalizeModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-base cursor-pointer">&times;</button>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ __("An official sale invoice will be generated for") }} <strong class="text-emerald-600 font-bold text-sm">{{ $company ? $company->formatMoney($this->soldRevenue) : '$' . number_format($this->soldRevenue, 2) }}</strong> {{ __("and sold stock will be deducted from inventory.") }}
            </p>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __("Agreed Payment Method") }}</label>
                <select wire:model="paymentMethod" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold">
                    <option value="pix">⚡ PIX</option>
                    <option value="cash">💵 Cash</option>
                    <option value="card">💳 Card</option>
                    <option value="transfer">🏦 Transfer</option>
                    <option value="boleto">📄 {{ __('Bank Slip / Deferred') }}</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="showFinalizeModal = false" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 cursor-pointer">
                    {{ __("Cancel") }}
                </button>
                <button type="button" wire:click="confirmAndGenerateInvoice" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition cursor-pointer">
                    {{ __("Confirm & Generate Invoice") }} ({{ $company ? $company->formatMoney($this->soldRevenue) : '$' . number_format($this->soldRevenue, 2) }})
                </button>
            </div>
        </div>
    </div>

</div>
