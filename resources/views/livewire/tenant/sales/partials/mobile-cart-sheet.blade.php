<!-- Floating Sticky Mobile Cart Action Pill (lg:hidden) -->
<div class="lg:hidden fixed !bottom-0 inset-x-0 z-40 translate-y-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 px-3 sm:px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-8px_20px_rgba(0,0,0,0.1)]"
     x-show="!mobileCartOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4">
    <button type="button" 
            @click="mobileCartOpen = true"
            class="w-full max-w-md mx-auto py-3 px-3 sm:px-5 bg-[#006aff] hover:bg-[#0055d6] text-white rounded-2xl shadow-xl shadow-blue-500/25 flex items-center justify-between gap-2 font-bold active:scale-95 transition">
        <div class="flex items-center gap-2.5">
            <span class="bg-black/25 backdrop-blur-xs px-2.5 py-1 rounded-xl text-xs font-black tracking-wide border border-white/10">
                {{ $this->cartItemCount }} {{ __("Items") }}
            </span>
            <span class="hidden min-[390px]:inline text-xs sm:text-sm font-extrabold">{{ __("View Current Sale") }}</span>
        </div>
        <div class="flex items-center gap-1.5 font-black text-sm sm:text-base">
            <span>{{ $company->formatMoney($this->total) }}</span>
            <span class="text-xs opacity-80">▲</span>
        </div>
    </button>
</div>

<!-- Slide-Up Bottom Sheet Modal (lg:hidden) -->
<div x-show="mobileCartOpen"
     x-cloak
     class="lg:hidden relative z-50">
    
    <!-- Dark Backdrop Overlay -->
    <div x-show="mobileCartOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileCartOpen = false"
         class="fixed inset-0 bg-black/60 backdrop-blur-xs"></div>

    <!-- Bottom Sheet Container -->
    <div x-show="mobileCartOpen"
         x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-50 bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-t-[2.5rem] shadow-2xl border-t border-slate-200 dark:border-slate-800 max-h-[min(88dvh,52rem)] flex flex-col overflow-hidden pb-safe pb-[env(safe-area-inset-bottom)]">
        
        <!-- Drag Handle Indicator -->
        <div @click="mobileCartOpen = false" class="w-full py-2.5 flex items-center justify-center cursor-pointer touch-none">
            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full"></div>
        </div>

        <!-- Sheet Header: Customer Selector & Close -->
        <div class="px-4 pb-3 pt-1 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Customer / Member") }}</span>
                        <button type="button" wire:click="openCustomerSelectModal" class="text-[10px] text-blue-500 font-bold hover:underline cursor-pointer">🔍 {{ __("Search") }}</button>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <select wire:model.live="customerId" class="flex-1 text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-bold py-1.5 px-2.5 focus:ring-blue-500 truncate">
                            <option value="">{{ __("Walk-in Regular Customer") }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone ?: $c->email }})</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="openQuickCustomerModal" class="p-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 font-black text-xs shrink-0" title="{{ __("Add Customer") }}">
                            + {{ __("New") }}
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" @click="mobileCartOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center font-bold text-sm shrink-0">
                ✕
            </button>
        </div>

        <!-- Sheet Body: Scrollable Cart Line Items -->
        <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-2.5 pr-3">
            @forelse ($items as $index => $item)
                @if (!empty($item['product_id']) || !empty($item['name']))
                    <div class="bg-slate-50 dark:bg-slate-800/70 rounded-2xl p-3 flex items-center justify-between gap-3 border border-slate-100 dark:border-slate-700/50">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ $item['name'] }}</div>
                            <div class="text-[11px] text-blue-600 dark:text-blue-400 font-extrabold mt-0.5 flex items-center gap-1">
                                @if ($this->canOverridePrice)
                                    <input type="number" min="0" step="0.01" value="{{ $item['price'] }}"
                                           wire:change="applyPriceOverride({{ $index }}, $event.target.value)"
                                           class="w-16 py-0 px-1 text-[11px] font-extrabold rounded-md border border-blue-200 dark:border-blue-800 bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400">
                                @else
                                    <span>{{ $company->formatMoney($item['price']) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Touch-friendly Quantity Stepper -->
                        <div class="flex items-center gap-1 bg-white dark:bg-slate-700 rounded-xl p-1 shadow-2xs border border-slate-200 dark:border-slate-600 shrink-0">
                            <button type="button" wire:click="decreaseQuantity({{ $index }})" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-600 hover:bg-rose-100 hover:text-rose-600 font-black text-sm flex items-center justify-center transition active:scale-90 cursor-pointer">-</button>
                            <span class="w-7 text-center text-xs font-black text-slate-800 dark:text-white">{{ (int)$item['quantity'] }}</span>
                            <button type="button" wire:click="increaseQuantity({{ $index }})" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-600 hover:bg-blue-100 hover:text-blue-600 font-black text-sm flex items-center justify-center transition active:scale-90 cursor-pointer">+</button>
                        </div>

                        <div class="text-right shrink-0 min-w-[55px]">
                            <div class="text-xs font-black text-slate-900 dark:text-white">
                                {{ $company->formatMoney((float)$item['quantity'] * (float)$item['price']) }}
                            </div>
                            <button type="button" wire:click="removeItem({{ $index }})" class="text-[11px] text-rose-500 font-bold p-0.5 hover:underline cursor-pointer">
                                {{ __("Remove") }}
                            </button>
                        </div>
                    </div>
                @endif
            @empty
                <div class="py-12 text-center text-slate-400 text-xs">
                    <span class="text-4xl block mb-2 opacity-50">🛒</span>
                    {{ __("Your sale cart is empty. Tap products to add items.") }}
                </div>
            @endforelse
        </div>

        <!-- Sheet Footer: Totals, Payment & Pinned Complete Checkout -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-950/80 space-y-2.5 shrink-0 text-xs font-semibold">
            <!-- Totals breakdown -->
            <div class="space-y-1">
                <div class="flex justify-between text-slate-500 dark:text-slate-400">
                    <span>{{ __("Subtotal") }}</span>
                    <span class="text-slate-900 dark:text-white font-bold">{{ $company->formatMoney($this->subtotal) }}</span>
                </div>
                {{-- Dynamic Fiscal Tax Line Items & Sub-Components --}}
                @if ($this->taxAmount > 0)
                    @if (!empty($this->flattenedTaxComponents))
                        @foreach ($this->flattenedTaxComponents as $taxComp)
                            <div class="flex justify-between text-slate-500 dark:text-slate-400 text-[11px]">
                                <span class="flex items-center gap-1">
                                    <span>{{ $taxComp['name'] }} ({{ $taxComp['rate'] }}%)</span>
                                </span>
                                <span class="text-slate-900 dark:text-white font-bold font-mono">+{{ $company->formatMoney($taxComp['amount']) }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>{{ __('Tax') }}</span>
                            <span class="text-slate-900 dark:text-white font-bold font-mono">+{{ $company->formatMoney($this->taxAmount) }}</span>
                        </div>
                    @endif
                @endif
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                    <span>{{ __("Discount") }}</span>
                    <input type="number" wire:model.live="discount" min="0" step="0.5" class="w-20 py-1 px-2 text-right rounded-lg text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-bold">
                </div>
                <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-1 border-t border-slate-200 dark:border-slate-800">
                    <span>{{ __("Total Payable") }}</span>
                    <span class="text-blue-600 dark:text-blue-400 text-lg">{{ $company->formatMoney($this->total) }}</span>
                </div>
            </div>

            <!-- Complete Checkout Action Button -->
            <div class="pt-1">
                <button type="button"
                    @click="mobileCartOpen = false; openCheckout(); $wire.openCheckoutModal()"
                    @disabled($this->cartItemCount <= 0)
                    wire:loading.attr="disabled"
                    wire:target="openCheckoutModal"
                    class="w-full inline-flex items-center justify-center px-5 py-3.5 rounded-xl bg-[#006aff] hover:bg-[#0055d6] text-white text-base font-black shadow-lg shadow-blue-500/25 transition active:scale-[0.97] disabled:bg-slate-300 disabled:text-slate-500 dark:disabled:bg-slate-700 dark:disabled:text-slate-400 disabled:shadow-none disabled:pointer-events-none cursor-pointer">
                    <span wire:loading.remove wire:target="openCheckoutModal">{{ __("Charge") }} {{ $company->formatMoney($this->total) }} / {{ __("Checkout") }}</span>
                    <span wire:loading.delay wire:target="openCheckoutModal">{{ __("Preparing...") }}</span>
                </button>
            </div>
        </div>

    </div>
</div>
