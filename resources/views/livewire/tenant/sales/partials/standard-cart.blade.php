<div class="w-full lg:w-96 flex flex-col bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 min-h-0 overflow-hidden shrink-0">
    
    <!-- Top Customer Row with Quick Add Button -->
    <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-1">
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Customer / Member</label>
                <button type="button" wire:click="openCustomerSelectModal" class="text-[10px] text-blue-500 hover:underline font-bold cursor-pointer">Search 🔍</button>
            </div>
            <select wire:model.live="customerId" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-semibold focus:ring-blue-500 py-1.5 px-2.5">
                <option value="">Walk-in Regular Customer</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone ?: $c->email }})</option>
                @endforeach
            </select>
        </div>

        <button type="button"
                wire:click="openQuickCustomerModal"
                class="mt-4 p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 hover:bg-blue-100 font-black text-xs transition shadow-2xs cursor-pointer"
                title="Add New Customer">
            + New
        </button>
    </div>

    <!-- Cart Items Scrollable List -->
    <div class="flex-1 min-h-0 overflow-y-auto py-2 space-y-2 pr-1">
        @forelse ($items as $index => $item)
            @if (!empty($item['product_id']) || !empty($item['name']))
                <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-2.5 flex items-center justify-between gap-2 border border-slate-100 dark:border-slate-700/50 group">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{{ $item['name'] }}</div>
                        <div class="text-[11px] text-blue-600 dark:text-blue-400 font-extrabold mt-0.5 flex items-center gap-1">
                            @if ($this->canOverridePrice)
                                <span>$</span>
                                <input type="number" min="0" step="0.01" value="{{ $item['price'] }}"
                                       wire:change="applyPriceOverride({{ $index }}, $event.target.value)"
                                       class="w-16 py-0 px-1 text-[11px] font-extrabold rounded-md border border-blue-200 dark:border-blue-800 bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400"
                                       title="Override unit price">
                            @else
                                <span>{{ $company->formatMoney($item['price']) }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Quantity Stepper -->
                    <div class="flex items-center gap-1 bg-white dark:bg-slate-700 rounded-xl p-1 shadow-2xs border border-slate-200 dark:border-slate-600 shrink-0">
                        <button type="button" wire:click="decreaseQuantity({{ $index }})" class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-600 hover:bg-rose-100 hover:text-rose-600 font-black text-xs flex items-center justify-center transition cursor-pointer">-</button>
                        <span class="w-6 text-center text-xs font-black text-slate-800 dark:text-white">{{ (int)$item['quantity'] }}</span>
                        <button type="button" wire:click="increaseQuantity({{ $index }})" class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-600 hover:bg-blue-100 hover:text-blue-600 font-black text-xs flex items-center justify-center transition cursor-pointer">+</button>
                    </div>

                    <div class="text-right shrink-0 min-w-[50px]">
                        <div class="text-xs font-black text-slate-900 dark:text-white">
                            {{ $company->formatMoney((float)$item['quantity'] * (float)$item['price']) }}
                        </div>
                        <button type="button" wire:click="removeItem({{ $index }})" class="text-[10px] text-slate-400 hover:text-rose-500 font-semibold transition cursor-pointer">✕</button>
                    </div>
                </div>
            @endif
        @empty
            <div class="py-12 text-center text-slate-400 text-xs">
                <span class="text-3xl block mb-1.5 opacity-60">🛒</span>
                Cart is empty. Tap products to add items.
            </div>
        @endforelse
    </div>

    <!-- Calculations & Payment Methods -->
    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2.5 shrink-0 text-xs font-semibold">
        <div class="flex justify-between text-slate-500 dark:text-slate-400">
            <span>Subtotal</span>
            <span class="text-slate-900 dark:text-white font-bold">{{ $company->formatMoney($this->subtotal) }}</span>
        </div>

        @if ($this->taxAmount > 0)
            <div class="flex justify-between text-slate-500 dark:text-slate-400">
                <span>Tax ({{ $taxPercent }}%)</span>
                <span class="text-slate-900 dark:text-white font-bold">{{ $company->formatMoney($this->taxAmount) }}</span>
            </div>
        @endif

        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
            <span>Discount ($)</span>
            <input type="number" wire:model.live="discount" min="0" step="0.5" class="w-20 py-1 px-2 text-right rounded-lg text-xs bg-slate-100 dark:bg-slate-800 border-none font-bold">
        </div>

        <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-1 border-t border-slate-100 dark:border-slate-800">
            <span>Total Payable</span>
            <span class="text-blue-600 dark:text-blue-400 text-lg">{{ $company->formatMoney($this->total) }}</span>
        </div>

        <!-- Payment Methods Pills -->
        <div class="pt-1">
            <div class="grid grid-cols-3 gap-1.5">
                @foreach ($paymentMethods as $pm)
                    <button type="button"
                            wire:click="$set('paymentMethod', '{{ $pm->code }}')"
                            @class([
                                'py-2 px-1 rounded-xl text-[11px] font-bold text-center transition capitalize cursor-pointer',
                                'bg-blue-600 text-white shadow-sm' => $paymentMethod === $pm->code,
                                'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' => $paymentMethod !== $pm->code,
                            ])>
                        {{ $pm->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Action Button: Save Sale -->
        <div class="pt-2">
            <button type="button"
                    wire:click="openCheckoutModal"
                    wire:loading.attr="disabled"
                    class="w-full py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm shadow-xl shadow-blue-500/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                <span wire:loading.remove>Complete Checkout ({{ $company->formatMoney($this->total) }})</span>
                <span wire:loading>Processing Sale...</span>
            </button>
        </div>
    </div>

</div>
