<!-- Final POS Checkout & Split Payment Modal -->
@if ($showCheckoutModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
         x-data
         x-on:keydown.escape.window="$wire.closeCheckoutModal()"
         x-cloak>
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-3xl w-full p-5 sm:p-6 space-y-4 overflow-hidden flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg font-black shadow-xs">
                        💳
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">POS Checkout & Payment</h3>
                        <p class="text-xs text-slate-400">Order #{{ $orderNumber }} &bull; {{ count($items) }} Items &bull; Total: <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ $company->formatMoney($this->total) }}</span></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="toggleSplitPayment"
                            @class([
                                'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer border',
                                'bg-blue-600 text-white border-blue-600 shadow-xs' => $isSplitPayment,
                                'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200' => ! $isSplitPayment,
                            ])>
                        <span>🔀 Split Payment: {{ $isSplitPayment ? 'ON' : 'OFF' }}</span>
                    </button>

                    <button type="button"
                            wire:click="closeCheckoutModal"
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center font-bold text-base transition">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Modal Body Scrollable Area -->
            <div class="flex-1 min-h-0 overflow-y-auto space-y-4 pr-1 text-xs">

                <!-- 1. Customer Context Banner -->
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-base">👤</span>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                {{ $this->selectedCustomer?->name ?: 'Walk-in Regular Customer' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ $this->selectedCustomer?->phone ?: ($this->selectedCustomer?->email ?: 'No contact details') }}
                            </div>
                        </div>
                    </div>
                    @if ($this->selectedCustomer)
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300">
                            Points: {{ $this->selectedCustomer->loyalty_points }}
                        </span>
                    @endif
                </div>

                <!-- 2. Payment Method(s) Configuration -->
                @if (! $isSplitPayment)
                    <!-- Single Payment Mode -->
                    <div class="space-y-3 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[11px]">Primary Payment Method</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach ($paymentMethods as $pm)
                                <button type="button"
                                        wire:click="$set('paymentMethod', '{{ $pm->code }}')"
                                        @class([
                                            'py-2.5 px-2 rounded-xl text-xs font-bold text-center transition capitalize cursor-pointer border flex items-center justify-center gap-1.5',
                                            'bg-blue-600 text-white border-blue-600 shadow-xs' => $paymentMethod === $pm->code,
                                            'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100' => $paymentMethod !== $pm->code,
                                        ])>
                                    <span>{{ $pm->name }}</span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Cash Tendered & Change Due (If Cash is chosen) -->
                        @if ($paymentMethod === 'cash')
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Cash Tendered by Customer ($)</label>
                                    <input type="number"
                                           wire:model.live="cashTendered"
                                           min="0"
                                           step="0.5"
                                           placeholder="{{ number_format($this->total, $company->currency_decimals ?? 2) }}"
                                           class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 rounded-xl p-2.5 flex flex-col justify-center">
                                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Change Due to Customer</div>
                                    <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono">
                                        {{ $company->formatMoney($this->changeDue) }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Multi / Split Payment Mode -->
                    <div class="space-y-3 p-3.5 rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/30 dark:bg-blue-950/20">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-blue-950 dark:text-blue-200 uppercase tracking-wider text-[11px]">Split Payment Entries</span>
                            <button type="button"
                                    wire:click="addSplitRow"
                                    class="px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-[11px] shadow-2xs transition active:scale-95 cursor-pointer">
                                + Add Method Split
                            </button>
                        </div>

                        <div class="space-y-2">
                            @foreach ($splitPayments as $idx => $sp)
                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 grid grid-cols-12 gap-2 items-center">
                                    <!-- Method Select -->
                                    <div class="col-span-4">
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5">Method #{{ $idx + 1 }}</label>
                                        <select wire:model.live="splitPayments.{{ $idx }}.payment_method" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 py-1.5 px-2 font-bold">
                                            @foreach ($paymentMethods as $pm)
                                                <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Amount Allocated -->
                                    <div class="col-span-3">
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5">Amount ($)</label>
                                        <input type="number"
                                               wire:model.live="splitPayments.{{ $idx }}.amount"
                                               min="0"
                                               step="0.5"
                                               class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 py-1.5 px-2 font-mono font-bold">
                                    </div>

                                    <!-- Reference / Auth No -->
                                    <div class="col-span-4">
                                        <label class="block text-[9px] font-bold text-slate-400 mb-0.5">Ref / Card Last4</label>
                                        <input type="text"
                                               wire:model.live="splitPayments.{{ $idx }}.reference_number"
                                               placeholder="TXN / Auth #"
                                               class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 py-1.5 px-2">
                                    </div>

                                    <!-- Remove Button -->
                                    <div class="col-span-1 text-center pt-3">
                                        <button type="button"
                                                wire:click="removeSplitRow({{ $idx }})"
                                                class="w-7 h-7 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 hover:bg-rose-100 font-black text-xs transition cursor-pointer"
                                                title="Remove split line">
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Split Payment Live Calculations Dashboard -->
                        <div class="pt-2 border-t border-blue-200 dark:border-blue-800/60 grid grid-cols-3 gap-2 text-center">
                            <div class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Total Bill</div>
                                <div class="text-sm font-black text-slate-900 dark:text-white font-mono">{{ $company->formatMoney($this->total) }}</div>
                            </div>

                            <div class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Allocated</div>
                                <div class="text-sm font-black text-blue-600 dark:text-blue-400 font-mono">{{ $company->formatMoney($this->splitTotalPaid) }}</div>
                            </div>

                            <div @class([
                                'p-2 rounded-xl border',
                                'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' => $this->remainingBalance > 0,
                                'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' => $this->remainingBalance <= 0,
                            ])>
                                <div class="text-[10px] font-bold uppercase">
                                    {{ $this->remainingBalance > 0 ? 'Remaining Balance' : ($this->remainingBalance < 0 ? 'Change Due' : 'Fully Settled') }}
                                </div>
                                <div class="text-sm font-black font-mono">
                                    {{ $company->formatMoney(abs($this->remainingBalance)) }}
                                </div>
                            </div>
                        </div>

                        <!-- Credit Sale Due Date (If Partial / Unpaid Balance Remains) -->
                        @if ($this->remainingBalance > 0)
                            <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-2">
                                <div class="text-amber-800 dark:text-amber-300 font-semibold text-[11px]">
                                    ⚠️ <strong>Credit / Receivable Sale:</strong> {{ $company->formatMoney($this->remainingBalance) }} will be logged to Accounts Receivable.
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <label class="text-[10px] font-bold text-amber-900 dark:text-amber-200">Due Date:</label>
                                    <input type="date" wire:model="dueDate" class="text-xs py-1 px-2 rounded-lg border-amber-300 dark:border-amber-700 dark:bg-slate-800">
                                </div>
                            </div>
                        @endif

                    </div>
                @endif

                <!-- 3. Dedicated Order Notes & Remarks Text Area -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[11px]">
                        Order Notes & Remarks (Printed on Receipt)
                    </label>
                    <textarea wire:model="notes"
                              rows="2"
                              placeholder="Add special instructions, client PO reference, warranty notes or cashier remarks..."
                              class="w-full p-2.5 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-slate-900 dark:text-slate-100 placeholder-slate-400"></textarea>
                </div>

            </div>

            <!-- Modal Footer Action Buttons -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 shrink-0">
                <button type="button"
                        wire:click="closeCheckoutModal"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
                    Cancel [Esc]
                </button>

                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="flex-1 py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm shadow-xl shadow-blue-500/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                    <span wire:loading.remove>
                        Confirm & Complete Sale ({{ $company->formatMoney($this->total) }}) [F10]
                    </span>
                    <span wire:loading>Processing Transaction...</span>
                </button>
            </div>

        </div>
    </div>
@endif
