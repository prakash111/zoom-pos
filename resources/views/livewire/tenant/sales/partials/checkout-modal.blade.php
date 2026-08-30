<!-- Final POS Checkout & Split Payment Modal (Instant Client-Side Alpine Mount) -->
<template x-teleport="body">
<div x-show="checkoutOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-on:keydown.escape.window="closeCheckout()"
     x-on:keydown.f10.window.prevent="$wire.save()"
     class="fixed inset-0 z-50 h-dvh overflow-hidden"
     style="display: none;"
     x-cloak
     wire:cloak>

    <div class="modal-backdrop absolute inset-0 bg-slate-950/70 backdrop-blur-[3px] transition-opacity"
         aria-hidden="true"
         @click="closeCheckout()"></div>

    <div class="relative z-10 h-full min-h-0 flex items-center justify-center p-3 sm:p-6 text-center pointer-events-none">
        <!-- Modal Card Canvas with Spring Hardware Easing -->
        <div x-show="checkoutOpen"
             x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2"
             @click.outside="closeCheckout()"
             class="modal-transition relative pointer-events-auto w-full max-w-4xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 border border-white/60 dark:border-slate-700 rounded-2xl shadow-[0_32px_80px_-20px_rgba(15,23,42,0.55)] overflow-hidden text-left flex flex-col max-h-[92dvh]">

        <!-- Modal Header Bar -->
        <div class="px-5 sm:px-7 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-lg font-black shadow-sm shrink-0">
                    💳
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>{{ __("POS Checkout & Payment") }}</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-2">
                        <span>{{ __("Order") }} <strong class="text-slate-600 dark:text-slate-300">#{{ $orderNumber }}</strong></span>
                        <span>&bull;</span>
                        <span>{{ count($items) }} {{ __("Items") }}</span>
                        <span>&bull;</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 font-bold font-mono text-[11px]">
                            {{ __("Total:") }} {{ $company->formatMoney($this->total) }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="toggleSplitPayment"
                        @class([
                            'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer border',
                            'bg-blue-600 text-white border-blue-500 shadow-md shadow-blue-500/25' => $isSplitPayment,
                            'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700' => ! $isSplitPayment,
                        ])>
                    <span>🔀 {{ __('Split Payment:') }} {{ $isSplitPayment ? __('ON') : __('OFF') }}</span>
                </button>

                <button type="button"
                        @click="closeCheckout()"
                        class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center justify-center font-bold text-base active:scale-95 cursor-pointer"
                        title="{{ __('Close (Esc)') }}">
                    &times;
                </button>
            </div>
        </div>

        <!-- Modal Body Scrollable Area -->
        <div class="flex-1 min-h-0 overflow-y-auto space-y-4 px-5 sm:px-7 py-5 text-xs bg-white dark:bg-slate-900">

            <!-- 1. Interactive Customer Context & Selection Section -->
            <div wire:key="checkout-customer-panel">
            @if ($inModalNewCustomerOpen)
                <!-- Mini Create Customer Form -->
                <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800/60 border-2 border-blue-200 dark:border-blue-900/70 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between pb-2 border-b border-blue-100 dark:border-slate-700">
                        <span class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('New Customer') }}
                        </span>
                        <button type="button" wire:click="closeInModalCustomer" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-300 hover:underline font-bold">
                            {{ __('Back to Search') }}
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 -mt-2">{{ __('Create the customer profile and attach it to this sale.') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">{{ __('Full Name *') }}</label>
                            <input type="text" wire:model="inModalNewCustomerName" placeholder="{{ __('Customer name') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
                            @error('inModalNewCustomerName') <span class="text-[10px] text-red-400 font-semibold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">{{ __('Phone Number') }}</label>
                            <input type="text" wire:model="inModalNewCustomerPhone" placeholder="{{ __('Phone / WhatsApp') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">{{ __('Email Address') }}</label>
                            <input type="email" wire:model="inModalNewCustomerEmail" placeholder="{{ __('Email') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeInModalCustomer" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" wire:click="createInModalCustomer" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-sm">
                            {{ __('Save & Select Customer') }}
                        </button>
                    </div>
                </div>
            @elseif ($inModalCustomerSearchOpen)
                <!-- Searchable Customer Selector Dropdown -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/70 border-2 border-blue-200 dark:border-blue-900/70 space-y-3 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="relative flex-1">
                            <input type="text"
                                   wire:model.live.debounce.250ms="inModalCustomerSearch"
                                   placeholder="{{ __('Type customer name, phone number, or email...') }}"
                                   autofocus
                                   class="w-full text-xs font-medium rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 py-2.5 pl-9 pr-3 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                            <span class="absolute left-2.5 top-2.5 text-slate-400">🔍</span>
                        </div>
                        <button type="button" wire:click="openInModalNewCustomer" class="px-3 py-2 rounded-xl text-xs font-black bg-blue-600 text-white hover:bg-blue-700 shrink-0 shadow-xs flex items-center gap-1">
                            ➕ {{ __('New') }}
                        </button>
                        <button type="button" wire:click="closeInModalCustomer" class="px-2.5 py-2 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 shrink-0">
                            ✕
                        </button>
                    </div>

                    <!-- Results List -->
                    <div class="max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
                        <!-- Walk-in option -->
                        <button type="button"
                                wire:click="selectInModalCustomer(null)"
                                class="w-full p-3 text-left flex items-center justify-between hover:bg-blue-50 dark:hover:bg-blue-950/40 transition">
                            <div class="flex items-center gap-2">
                                <span class="text-base">👤</span>
                                <div>
                                    <div class="font-black text-slate-800 dark:text-slate-200 text-xs">{{ __('Walk-in Regular Customer') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ __('No loyalty account / anonymous walk-in') }}</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full">{{ __('Select') }}</span>
                        </button>

                        @forelse ($this->inModalCustomerResults as $cust)
                            <button type="button"
                                    wire:click="selectInModalCustomer({{ $cust->id }})"
                                    class="w-full p-3 text-left flex items-center justify-between hover:bg-blue-50 dark:hover:bg-blue-950/40 transition">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">👤</span>
                                    <div>
                                        <div class="font-black text-slate-900 dark:text-white text-xs flex items-center gap-1.5">
                                            <span>{{ $cust->name }}</span>
                                            @if ($cust->loyalty_points > 0)
                                                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-black bg-blue-950 text-blue-300 border border-blue-800">
                                                    ★ {{ $cust->loyalty_points }} pts
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $cust->phone ?: ($cust->email ?: __('No phone')) }}
                                            @if ($cust->credit_limit > 0)
                                                &bull; <span class="text-amber-400 font-bold">{{ __('Credit Limit:') }} {{ $company->formatMoney($cust->credit_limit) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <span class="text-[10px] font-black text-blue-400 bg-blue-950/60 px-2.5 py-1 rounded-lg border border-blue-800">{{ __('Select') }}</span>
                            </button>
                        @empty
                            <div class="p-3 text-center text-xs text-slate-400">
                                {{ __('No customers match your search.') }}
                                <button type="button" wire:click="openInModalNewCustomer" class="font-bold text-blue-400 underline ml-1">
                                    {{ __('Create "+ New Customer"') }}
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                <!-- Interactive Customer Context Banner -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-base font-bold shrink-0">
                            👤
                        </div>
                        <div>
                            <div class="font-black text-slate-800 dark:text-white text-xs flex items-center gap-2">
                                <span>{{ $this->selectedCustomer?->name ?: __('Walk-in Regular Customer') }}</span>
                                @if ($this->selectedCustomer)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-950 text-blue-300 border border-blue-800">
                                        ★ {{ $this->selectedCustomer->loyalty_points }} {{ __('pts') }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                                <span>{{ $this->selectedCustomer?->phone ?: ($this->selectedCustomer?->email ?: __('No contact details')) }}</span>
                                @if ($this->selectedCustomer && $this->selectedCustomerDebt > 0)
                                    <span class="font-bold text-rose-400 bg-rose-950/40 border border-rose-800/80 px-1.5 py-0.2 rounded text-[10px]">
                                        {{ __('Debt:') }} {{ $company->formatMoney($this->selectedCustomerDebt) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button"
                                wire:click="toggleInModalCustomerSearch"
                                class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-2xs flex items-center gap-1">
                            <span>🔍</span>
                            <span>{{ __('Change') }}</span>
                        </button>
                        <button type="button"
                                wire:click="openInModalNewCustomer"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition cursor-pointer flex items-center gap-1.5"
                                title="{{ __('Add New Customer') }}">
                            <span>＋</span><span class="hidden sm:inline">{{ __('New') }}</span>
                        </button>
                        @if ($this->selectedCustomer)
                            <button type="button"
                                    wire:click="selectInModalCustomer(null)"
                                    class="px-2 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 transition cursor-pointer"
                                    title="{{ __('Reset to Walk-in') }}">
                                ✕
                            </button>
                        @endif
                    </div>
                </div>
            @endif
            </div>

            <!-- 2. Sales Representative Assignment Card -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-base font-bold shrink-0">
                        👔
                    </div>
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs">{{ __("Assigned Salesperson") }}</div>
                        <div class="text-[10px] text-slate-400">{{ __("Commission & receipt attribution") }}</div>
                    </div>
                </div>
                <div class="w-48 sm:w-56">
                    <select wire:model="salespersonId" class="w-full text-xs font-bold rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 py-1.5 px-2.5 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500">
                        @foreach ($users as $u)
                            @php
                                $staffId = data_get($u, 'id');
                                $staffName = data_get($u, 'name');
                                $staffRole = data_get($u, 'role', 'Staff');
                            @endphp
                            @continue(blank($staffId) || blank($staffName))
                            <option value="{{ $staffId }}">{{ $staffName }} ({{ ucfirst($staffRole ?: 'Staff') }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- 3. Payment Method(s) Configuration -->
            <div wire:key="checkout-payment-panel">
            @if (! $isSplitPayment)
                <!-- Single Payment Mode -->
                <div class="space-y-4 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/40">
                    <label class="block font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider text-[11px]">{{ __("Primary Payment Method") }}</label>
                    @php
                        $allMethods = collect($paymentMethods)->pluck('code')->all();
                        $paymentMethodsList = collect($paymentMethods);
                        if (!in_array('credit', $allMethods)) {
                            $paymentMethodsList->push((object)['code' => 'credit', 'name' => __('Credit / Deferred Account')]);
                        }
                        if ($this->canConsign && !in_array('consignment', $allMethods)) {
                            $paymentMethodsList->push((object)['code' => 'consignment', 'name' => __('Consignment Dispatch')]);
                        }
                        $knownPaymentLabels = [
                            'cash' => __('Cash'),
                            'card' => __('Card'),
                            'card_credit' => __('Credit Card'),
                            'card_debit' => __('Debit Card'),
                            'transfer' => __('Transfer'),
                            'bank_transfer' => __('Bank Transfer'),
                            'pix' => __('PIX'),
                            'credit' => __('Credit / Deferred Account'),
                            'deferred' => __('Credit / Deferred Account'),
                            'consignment' => __('Consignment Dispatch'),
                            'consign' => __('Consignment Dispatch'),
                        ];
                        $vuePaymentMethods = $paymentMethodsList->map(fn ($method) => [
                            'code' => (string) $method->code,
                            'name' => (string) ($knownPaymentLabels[$method->code] ?? $method->name),
                        ])->values()->all();
                    @endphp
                    <div wire:ignore
                         data-vue-payment-selector
                         data-livewire-id="{{ $this->getId() }}"
                         data-selected="{{ $paymentMethod }}"
                         data-methods="{{ base64_encode(json_encode($vuePaymentMethods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
                        data-label="{{ __('Primary Payment Method') }}">
                        <div class="grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-2">
                            @foreach ($paymentMethodsList as $pm)
                                @php($cleanPaymentName = $knownPaymentLabels[$pm->code] ?? $pm->name)
                                <button type="button"
                                        wire:click="$set('paymentMethod', '{{ $pm->code }}')"
                                        class="min-h-12 py-2.5 px-3 rounded-xl text-xs font-bold text-center border bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center gap-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ in_array($pm->code, ['consignment', 'consign'], true) ? 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' : 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' }}"/></svg>
                                    {{ $cleanPaymentName }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Consignment Notice Block -->
                    @if ($paymentMethod === 'consignment')
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-extrabold text-xs">
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        <span>{{ __("Consignment Dispatch Mode") }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-mono font-bold">
                                        {{ $company->formatMoney($this->total) }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-600 dark:text-amber-200/80 leading-relaxed">
                                    {{ __("Stock will be immediately deducted from shelf inventory and assigned to the customer's open consignment ledger. No immediate cash register inflow will be booked until merchandise is reconciled and finalized.") }}
                                </p>
                            </div>
                        </div>
                    <!-- Cash Tendered & Change Due (If Cash is chosen) -->
                    @elseif ($paymentMethod === 'cash')
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                            <div wire:key="vue-cash-calculator-{{ (int) round($this->total * 100) }}"
                                 wire:ignore
                                 data-vue-cash-calculator
                                 data-livewire-id="{{ $this->getId() }}"
                                 data-total="{{ (float) $this->total }}"
                                 data-tendered="{{ (float) $cashTendered }}"
                                 data-decimals="{{ $company->currency_decimals ?? 2 }}"
                                 data-symbol="{{ $company->currency_symbol ?: '$' }}"
                                 data-symbol-position="{{ $company->currency_symbol_position ?: 'prefix' }}"
                                 data-input-label="{{ __('Cash Tendered by Customer') }}"
                                 data-change-label="{{ __('Change Due to Customer') }}"
                                 data-presets-label="{{ __('Exact / Presets:') }}"
                                 data-exact-label="{{ __('Exact') }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-slate-400 mb-1">{{ __('Cash Tendered by Customer') }}</label>
                                        <input type="number"
                                               wire:model="cashTendered"
                                               min="0"
                                               step="0.5"
                                               class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm font-mono font-bold">
                                    </div>
                                    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 p-2.5">
                                        <div class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400">{{ __('Change Due to Customer') }}</div>
                                        <div class="text-lg font-black font-mono text-emerald-600 dark:text-emerald-300">{{ $company->formatMoney($this->changeDue) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($paymentMethod === 'pix')
                        <!-- Dynamic PIX QR Code & Copia e Cola Display -->
                        <div class="pt-2 border-t border-slate-800 space-y-3">
                            <div class="p-3.5 rounded-2xl bg-teal-950/40 border border-teal-800/80 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-teal-300 font-extrabold text-xs">
                                        <span class="text-base">⚡</span>
                                        <span>{{ __("PIX Instant Payment Terminal") }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 text-[10px] font-mono font-bold">
                                        {{ $company->formatMoney($this->total) }}
                                    </span>
                                </div>

                                <div class="flex flex-col sm:flex-row items-center gap-4 bg-slate-950/80 p-3 rounded-xl border border-teal-900/50">
                                    <!-- QR Code Graphic (Inline Vector SVG / Canvas) -->
                                    <div class="w-32 h-32 bg-white p-2 rounded-xl shrink-0 flex items-center justify-center shadow-md overflow-hidden">
                                        @if (!empty($this->pixQrSvg))
                                            <div class="w-full h-full flex items-center justify-center [&>svg]:w-full [&>svg]:h-full">
                                                {!! $this->pixQrSvg !!}
                                            </div>
                                        @elseif ($company?->pix_qr_image)
                                            <img src="{{ Storage::url($company->pix_qr_image) }}" alt="PIX QR" class="w-full h-full object-contain">
                                        @else
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($this->pixPayload) }}" alt="PIX QR" class="w-full h-full object-contain">
                                        @endif
                                    </div>

                                    <!-- PIX Details & Clipboard Copy -->
                                    <div class="flex-1 min-w-0 space-y-2 text-xs w-full">
                                        <div class="grid grid-cols-2 gap-2 text-[10px] pb-1 border-b border-slate-800">
                                            <div>
                                                <span class="text-slate-400 font-bold uppercase">{{ __("Beneficiary / Store") }}:</span>
                                                <div class="text-slate-200 font-extrabold truncate">{{ tenant_setting('pix_holder_name', $company->pix_merchant_name ?: $company->name) }}</div>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 font-bold uppercase">{{ __("City") }}:</span>
                                                <div class="text-slate-200 font-extrabold truncate">{{ tenant_setting('pix_city', $company->pix_merchant_city ?: ($company->city ?: 'Brasilia')) }}</div>
                                            </div>
                                        </div>

                                        @if ($company?->pix_key)
                                            <div>
                                                <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("PIX Key") }} ({{ strtoupper($company->pix_key_type ?? 'KEY') }}):</span>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <code class="bg-slate-900 px-2 py-1 rounded-lg text-[11px] text-teal-300 font-mono font-bold truncate flex-1 border border-slate-800">{{ $company->pix_key }}</code>
                                                    <button type="button"
                                                            x-data
                                                            @click="navigator.clipboard.writeText('{{ addslashes($company->pix_key) }}'); alert('{{ __('PIX Key copied!') }}')"
                                                            class="px-2 py-1 rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-bold text-[10px] transition shrink-0 cursor-pointer">
                                                        {{ __("Copy Key") }}
                                                    </button>
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <span class="text-[10px] text-slate-400 font-bold uppercase">{{ __("PIX Code (Copia e Cola)") }}:</span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <input type="text" readonly value="{{ $this->pixPayload }}" class="bg-slate-900 px-2 py-1 rounded-lg text-[10px] text-slate-300 font-mono truncate flex-1 border border-slate-800">
                                                <button type="button"
                                                        x-data
                                                        @click="navigator.clipboard.writeText('{{ addslashes($this->pixPayload) }}'); alert('{{ __('PIX Copy and Paste code copied successfully!') }}')"
                                                        class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] shadow-sm transition shrink-0 cursor-pointer flex items-center gap-1">
                                                    <span>📋</span>
                                                    <span>{{ __("Copy PIX Code") }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif (in_array($paymentMethod, ['card', 'card_credit', 'card_debit']))
                        <!-- Card Processing & Merchant Fee Deductions (Taxa de Cartão) -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-3">
                            <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-4 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                        <span>💳</span> {{ __("Card Processing & Machine Fees") }}
                                    </span>
                                    <div class="flex text-[10px] font-bold bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                                        <button type="button"
                                                wire:click="$set('cardType', 'debit')"
                                                @class(['px-3 py-1.5 rounded-lg transition cursor-pointer', 'bg-white dark:bg-blue-600 text-blue-700 dark:text-white shadow-sm' => $cardType === 'debit', 'text-slate-500 hover:text-slate-800 dark:hover:text-white' => $cardType !== 'debit'])>
                                            {{ __("Debit Card") }}
                                        </button>
                                        <button type="button"
                                                wire:click="$set('cardType', 'credit')"
                                                @class(['px-3 py-1.5 rounded-lg transition cursor-pointer', 'bg-white dark:bg-blue-600 text-blue-700 dark:text-white shadow-sm' => $cardType === 'credit', 'text-slate-500 hover:text-slate-800 dark:hover:text-white' => $cardType !== 'credit'])>
                                            {{ __("Credit Card") }}
                                        </button>
                                    </div>
                                </div>

                                @if ($cardType === 'credit')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">{{ __("Installments") }}</label>
                                            <select wire:model.live="installments" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-2 px-3 font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                                                @foreach ($this->installmentOptions as $opt)
                                                    <option value="{{ $opt['installments'] }}">{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="text-[11px] text-slate-400">
                                            <span>{{ __("Applied Fee Rate:") }} <strong class="text-amber-400 font-mono">{{ $this->cardFeePercentage }}%</strong></span>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400">
                                        <span>{{ __("Debit Card Fee Rate:") }} <strong class="text-amber-400 font-mono">{{ $this->cardFeePercentage }}%</strong></span>
                                    </div>
                                @endif

                                <!-- Financial Net Receivables Summary Matrix -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-3 border-t border-slate-200 dark:border-slate-700 text-center">
                                    <div class="bg-slate-50 dark:bg-slate-800 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700">
                                        <div class="text-[9px] font-bold text-slate-400 uppercase">{{ __("Gross (Customer)") }}</div>
                                        <div class="text-sm font-black text-slate-900 dark:text-white font-mono mt-0.5">{{ $company->formatMoney($this->total) }}</div>
                                    </div>
                                    <div class="bg-rose-50 dark:bg-rose-950/30 p-2.5 rounded-xl border border-rose-200 dark:border-rose-900">
                                        <div class="text-[9px] font-bold text-rose-400 uppercase">{{ __("Fee (Deduction)") }}</div>
                                        <div class="text-xs font-black text-rose-400 font-mono mt-0.5">-{{ $company->formatMoney($this->merchantFeeAmount) }}</div>
                                    </div>
                                    <div class="bg-emerald-50 dark:bg-emerald-950/30 p-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900">
                                        <div class="text-[9px] font-bold text-emerald-400 uppercase">{{ __("Net (Receivable)") }}</div>
                                        <div class="text-xs font-black text-emerald-400 font-mono mt-0.5">+{{ $company->formatMoney($this->netReceivableAmount) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($paymentMethod === 'credit')
                        <!-- Deferred / Credit Sale Configuration (Customer Account) -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-3">
                            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-xs space-y-2">
                                <div class="font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <span>📅</span>
                                    <span>{{ __("Credit / Deferred Sale (Customer Account)") }}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ __("No immediate payment is collected. The total balance of :amount will be recorded into Accounts Receivable.", ['amount' => $company->formatMoney($this->total)]) }}
                                </p>
                                @if (! $this->selectedCustomer)
                                    <div class="text-rose-700 dark:text-rose-300 font-bold text-[11px] bg-rose-50 dark:bg-rose-950/40 p-2.5 rounded-lg border border-rose-200 dark:border-rose-900">
                                        ⚠️ {{ __("Please select a Customer in POS before confirming a credit sale.") }}
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-300 mb-1.5 text-xs">
                                    {{ __("Receivable Due Date *") }}
                                </label>
                                <input type="date"
                                       wire:model="dueDate"
                                       class="w-full py-2.5 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                                @error('dueDate') <span class="text-rose-400 text-[10px] font-bold">{{ $message }}</span> @enderror
                                @error('customerId') <span class="text-rose-400 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <!-- Multi / Split Payment Mode -->
                <div class="space-y-3 p-3.5 rounded-2xl border border-blue-900/60 bg-blue-950/20">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-blue-700 dark:text-blue-300 uppercase tracking-wider text-[11px]">{{ __("Split Payment Entries") }}</span>
                        <button type="button"
                                wire:click="addSplitRow"
                                class="px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-[11px] shadow-2xs transition active:scale-95 cursor-pointer">
                            + {{ __("Add Method Split") }}
                        </button>
                    </div>

                    <div class="space-y-2">
                        @foreach ($splitPayments as $idx => $sp)
                            <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800 grid grid-cols-12 gap-2 items-center">
                                <!-- Method Select -->
                                <div class="col-span-4">
                                    <label class="block text-[9px] font-bold text-slate-400 mb-0.5">{{ __("Method") }} #{{ $idx + 1 }}</label>
                                    <select wire:model.live="splitPayments.{{ $idx }}.payment_method" class="w-full text-xs rounded-lg border-slate-700 bg-slate-800 py-1.5 px-2 font-bold text-white">
                                        @foreach ($paymentMethods as $pm)
                                            <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Amount Allocated -->
                                <div class="col-span-3">
                                    <label class="block text-[9px] font-bold text-slate-400 mb-0.5">{{ __("Amount ($)") }}</label>
                                    <input type="number"
                                           wire:model.live="splitPayments.{{ $idx }}.amount"
                                           min="0"
                                           step="0.5"
                                           class="w-full text-xs rounded-lg border-slate-700 bg-slate-800 py-1.5 px-2 font-mono font-bold text-white">
                                </div>

                                <!-- Reference / Auth No -->
                                <div class="col-span-4">
                                    <label class="block text-[9px] font-bold text-slate-400 mb-0.5">{{ __("Ref / Card Last4") }}</label>
                                    <input type="text"
                                           wire:model.live="splitPayments.{{ $idx }}.reference_number"
                                           placeholder="{{ __("TXN / Auth #") }}"
                                           class="w-full text-xs rounded-lg border-slate-700 bg-slate-800 py-1.5 px-2 text-white">
                                </div>

                                <!-- Remove Button -->
                                <div class="col-span-1 text-center pt-3">
                                    <button type="button"
                                            wire:click="removeSplitRow({{ $idx }})"
                                            class="w-7 h-7 rounded-md bg-rose-950/60 text-rose-400 hover:bg-rose-900 font-black text-xs transition cursor-pointer"
                                            title="{{ __("Remove split line") }}">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Split Payment Live Calculations Dashboard -->
                    <div class="pt-2 border-t border-blue-900/60 grid grid-cols-3 gap-2 text-center">
                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <div class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Total Bill") }}</div>
                            <div class="text-sm font-black text-white font-mono">{{ $company->formatMoney($this->total) }}</div>
                        </div>

                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <div class="text-[10px] text-slate-400 font-bold uppercase">{{ __("Allocated") }}</div>
                            <div class="text-sm font-black text-blue-400 font-mono">{{ $company->formatMoney($this->splitTotalPaid) }}</div>
                        </div>

                        <div @class([
                            'p-2 rounded-xl border',
                            'bg-rose-950/40 border-rose-800 text-rose-300' => $this->remainingBalance > 0,
                            'bg-emerald-950/40 border-emerald-800 text-emerald-300' => $this->remainingBalance <= 0,
                        ])>
                            <div class="text-[10px] font-bold uppercase">
                                {{ $this->remainingBalance > 0 ? __('Remaining Balance') : ($this->remainingBalance < 0 ? __('Change Due') : __('Fully Settled')) }}
                            </div>
                            <div class="text-sm font-black font-mono">
                                {{ $company->formatMoney(abs($this->remainingBalance)) }}
                            </div>
                        </div>
                    </div>

                    <!-- Credit Sale Due Date (If Partial / Unpaid Balance Remains) -->
                    @if ($this->remainingBalance > 0)
                        <div class="p-2.5 rounded-xl bg-amber-950/40 border border-amber-800 flex items-center justify-between gap-2">
                            <div class="text-amber-300 font-semibold text-[11px]">
                                ⚠️ <strong>{{ __("Credit / Receivable Sale:") }}</strong> {{ $company->formatMoney($this->remainingBalance) }} {{ __("will be logged to Accounts Receivable.") }}
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <label class="text-[10px] font-bold text-amber-200">{{ __("Due Date:") }}</label>
                                <input type="date" wire:model="dueDate" class="text-xs py-1 px-2 rounded-lg border-amber-700 bg-slate-800 text-white">
                            </div>
                        </div>
                    @endif

                </div>
            @endif
            </div>

            <!-- 4. Dedicated Order Notes & Remarks Text Area -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider text-[11px]">
                    {{ __("Order Notes & Remarks (Printed on Receipt)") }}
                </label>
                <textarea wire:model="notes"
                          rows="2"
                          placeholder="{{ __("Add special instructions, client PO reference, warranty notes or cashier remarks...") }}"
                          class="w-full p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500"></textarea>
            </div>

        </div>

        <!-- Modal Footer Action Buttons -->
        <div class="px-5 sm:px-7 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 bg-slate-50 dark:bg-slate-900 shrink-0">
            <div class="flex items-center">
                <button type="button"
                        @click="closeCheckout()"
                        class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 active:scale-95">
                    <span>{{ __("Cancel") }}</span>
                    <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-900 text-slate-400 font-black">Esc</kbd>
                </button>
            </div>

            <button type="button"
                    @click="openPreview()"
                    wire:click="openInvoicePreview"
                    class="flex-1 py-3 px-5 rounded-xl bg-[#006aff] hover:bg-[#0055d6] text-white font-black text-sm shadow-lg shadow-blue-500/20 transition active:scale-[0.98] inline-flex items-center justify-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>{{ __("Review Invoice & Continue") }} ({{ $company->formatMoney($this->total) }})</span>
            </button>
        </div>

    </div>
    </div>
</div>
</template>
