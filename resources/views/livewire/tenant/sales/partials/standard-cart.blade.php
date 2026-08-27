<!-- Right Sidebar: Strict Viewport Containment -->
<div class="hidden lg:flex w-full lg:w-96 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 flex flex-col h-full max-h-full min-h-0 shadow-sm shrink-0 overflow-hidden">

    <!-- 1. Top Section: Customer / Member Selection (Fixed) -->
    <div class="pb-3 border-b border-slate-150 dark:border-slate-800 flex-shrink-0">
        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase mb-1">
            <span>{{ __('Customer / Member') }}</span>
            <button type="button" @click="openCustomerSearch = true" wire:click="openCustomerSelectModal" class="text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                {{ __('Search') }} 🔍
            </button>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="customerId" class="flex-1 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold truncate text-slate-800 dark:text-slate-100">
                <option value="">{{ __('Walk-in Regular Customer') }}</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone ?: $c->email }})</option>
                @endforeach
            </select>
            <button type="button" wire:click="openNewCustomerModal" class="px-2.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold whitespace-nowrap cursor-pointer">
                + {{ __('New') }}
            </button>
        </div>
    </div>

    <!-- 2. Middle Section: Scrollable Cart Line Items -->
    <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 my-2 pr-1 space-y-2">
        @forelse($items as $index => $item)
            @if (!empty($item['product_id']) || !empty($item['name']))
                <div class="flex items-center justify-between gap-2 pt-2 text-xs">
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $item['name'] }}</div>
                        <div class="mt-1">
                            <!-- Clean Editable Price without outer $ symbol -->
                            @if ($this->canOverridePrice)
                                <input type="number"
                                       step="0.01"
                                       value="{{ $item['price'] }}"
                                       wire:change="applyPriceOverride({{ $index }}, $event.target.value)"
                                       wire:model.live.debounce.250ms="items.{{ $index }}.price"
                                       class="w-16 px-2 py-0.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-1 focus:ring-blue-500">
                            @else
                                <span class="text-xs font-bold text-slate-500">{{ $company->formatMoney($item['price']) }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Qty Counter -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5 border border-slate-200 dark:border-slate-700 shrink-0">
                        <button type="button" wire:click="decrementQty({{ $index }})" class="w-6 h-6 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition cursor-pointer">−</button>
                        <span class="px-1.5 text-xs font-bold text-slate-800 dark:text-white font-mono">{{ (float)$item['quantity'] }}</span>
                        <button type="button" wire:click="incrementQty({{ $index }})" class="w-6 h-6 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition cursor-pointer">+</button>
                    </div>

                    <!-- Total & Remove -->
                    <div class="text-right flex items-center gap-1.5 shrink-0">
                        <span class="font-mono font-black text-slate-800 dark:text-slate-100">{{ $company->formatMoney((float)$item['price'] * (float)$item['quantity']) }}</span>
                        <button type="button" wire:click="removeFromCart({{ $index }})" class="text-slate-400 hover:text-rose-500 text-sm cursor-pointer font-bold">✕</button>
                    </div>
                </div>
            @endif
        @empty
            <div class="h-full flex flex-col items-center justify-center py-10 text-slate-400 text-xs text-center">
                <span>🛒 {{ __('Cart is empty') }}</span>
            </div>
        @endforelse
    </div>

    <!-- 3. Bottom Section: Totals, Taxes, and Action Button (Pinned to Bottom) -->
    <div class="pt-3 border-t border-slate-150 dark:border-slate-800 space-y-1.5 flex-shrink-0 bg-white dark:bg-slate-900">
        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>{{ __('Subtotal') }}</span>
            <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $company->formatMoney($this->subtotal) }}</span>
        </div>

        @if(isset($this->flattenedTaxComponents) && count($this->flattenedTaxComponents) > 0)
            @foreach($this->flattenedTaxComponents as $tax)
                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ $tax['name'] }} ({{ $tax['rate'] }}%)</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">+{{ $company->formatMoney($tax['amount']) }}</span>
                </div>
            @endforeach
        @elseif($this->taxAmount > 0)
            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                <span>{{ __('Tax') }}</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">+{{ $company->formatMoney($this->taxAmount) }}</span>
            </div>
        @endif

        @if ($this->discount > 0)
            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>{{ __('Discount') }}</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">-{{ $company->formatMoney($this->discount) }}</span>
            </div>
        @endif

        <div class="flex items-center justify-between pt-1 border-t border-dashed border-slate-200 dark:border-slate-700">
            <span class="text-xs font-bold text-slate-800 dark:text-white">{{ __('Total Payable') }}</span>
            <span class="text-base font-black text-blue-600 dark:text-blue-400 font-mono">{{ $company->formatMoney($this->total) }}</span>
        </div>

        <!-- Interactive Checkout Button -->
        <button type="button"
                @click="openCheckout(); $wire.openCheckoutModal()"
                @disabled($this->cartItemCount <= 0)
                class="w-full mt-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white rounded-2xl font-bold text-xs shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2 transition cursor-pointer">
            <span>⚡ {{ __('Charge') }} {{ $company->formatMoney($this->total) }}</span>
            <kbd class="text-[9px] bg-blue-800/80 px-1.5 py-0.5 rounded font-mono">F10</kbd>
        </button>
    </div>
</div>
