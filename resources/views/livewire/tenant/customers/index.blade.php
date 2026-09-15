<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4">
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search by name, email, phone…') }}"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl py-2.5 pl-10 pr-4 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500 shadow-xs">
        </div>

        <button wire:click="newCustomer"
                type="button"
                class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>+ {{ __('New Customer') }}</span>
        </button>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">
                {{ $editingId ? __('Edit Customer') : __('Add New Customer') }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer Name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Document / Tax ID') }}</label>
                    <input type="text" wire:model="document" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email') }}</label>
                    <input type="email" wire:model="email" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone') }}</label>
                    <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Address') }}</label>
                    <input type="text" wire:model="address" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('City') }}</label>
                    <input type="text" wire:model="city" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('State') }}</label>
                    <input type="text" wire:model="state" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                    {{ __('Save Customer') }}
                </button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Customer') }}</th>
                        <th class="px-5 py-3.5">{{ __('Contact') }}</th>
                        <th class="px-5 py-3.5">{{ __('Loyalty Points') }}</th>
                        <th class="px-5 py-3.5">{{ __('Credit Balance') }}</th>
                        <th class="px-5 py-3.5 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($customers as $customer)
                        @php $due = $customer->total_due; @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $customer->name }}</div>
                                @if ($customer->document)
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $customer->document }}</div>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400 text-xs">
                                <div>{{ $customer->email ?: '—' }}</div>
                                @if ($customer->phone)
                                    <div class="text-[11px] text-slate-400">{{ $customer->phone }}</div>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300">
                                    {{ $customer->loyalty_points }} {{ __('pts') }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5">
                                @if ($due > 0)
                                    <button type="button"
                                            wire:click="openCreditLedger({{ $customer->id }})"
                                            class="px-2.5 py-1 rounded-full text-xs font-black bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 hover:bg-rose-100 border border-rose-200 dark:border-rose-800 transition cursor-pointer">
                                        ⚠️ ${{ number_format($due, 2) }} {{ __('Due') }}
                                    </button>
                                @else
                                    <button type="button"
                                            wire:click="openCreditLedger({{ $customer->id }})"
                                            class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 cursor-pointer">
                                        {{ __('Clear / $0.00') }}
                                    </button>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                                <button wire:click="openCreditLedger({{ $customer->id }})" type="button" class="text-xs font-bold text-indigo-600 hover:underline px-2 py-1 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition cursor-pointer">{{ __('Ledger') }}</button>
                                <button wire:click="startPointsAdjust({{ $customer->id }})" type="button" class="text-xs font-bold text-slate-500 hover:text-blue-600 px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">{{ __('Points') }}</button>
                                <button wire:click="edit({{ $customer->id }})" type="button" class="text-xs font-bold text-blue-600 hover:underline px-2 py-1 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 transition cursor-pointer">{{ __('Edit') }}</button>
                                <button wire:click="delete({{ $customer->id }})" wire:confirm="{{ __('Delete this customer?') }}" type="button" class="text-xs font-bold text-rose-600 hover:underline px-2 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer">{{ __('Delete') }}</button>
                            </td>
                        </tr>

                        @if ($pointsAdjustId === $customer->id)
                            <tr class="bg-blue-50/50 dark:bg-blue-950/20">
                                <td colspan="5" class="px-5 py-4">
                                    <div class="flex items-end gap-3">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Points Delta (+/-)') }}</label>
                                            <input type="number" wire:model="pointsDelta" class="w-36 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                                        </div>
                                        <button wire:click="applyPointsAdjust" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition cursor-pointer">{{ __('Apply') }}</button>
                                        <button wire:click="$set('pointsAdjustId', null)" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">{{ __('Cancel') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-xs">
                                {{ __('No customers registered yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>

    <!-- Customer Credit History & Ledger Modal (Req 15) -->
    @if ($showCreditModal && $creditCustomer)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.closeCreditLedger()">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>💳</span>
                            <span>{{ __('Credit Ledger:') }} {{ $creditCustomer->name }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $creditCustomer->phone ?: ($creditCustomer->email ?: __('Registered Client')) }}</p>
                    </div>
                    <button type="button" wire:click="closeCreditLedger" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-xl cursor-pointer">&times;</button>
                </div>

                <!-- Balance Summary Banner -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Total Outstanding Due') }}</div>
                        <div class="text-xl font-black text-rose-700 dark:text-rose-300 font-mono mt-0.5">
                            ${{ number_format($creditCustomer->total_due, 2) }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ __('Loyalty Points') }}</div>
                        <div class="text-xl font-black text-blue-700 dark:text-blue-300 font-mono mt-0.5">
                            {{ $creditCustomer->loyalty_points }} {{ __('pts') }}
                        </div>
                    </div>
                </div>

                <!-- Transaction List -->
                <div class="space-y-2">
                    <h4 class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('Credit Sales & Deferred Orders') }}</h4>
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        @forelse ($creditSales as $cs)
                            <div @class([
                                'p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3 text-xs',
                                'dark:bg-[#132A24] dark:border-emerald-500/30' => $cs->payment_status === 'paid',
                                'dark:bg-[#182230] dark:border-slate-700' => $cs->payment_status !== 'paid',
                            ])>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <strong class="font-mono text-slate-900 dark:text-white">#{{ $cs->sale_number }}</strong>
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-[10px] font-black uppercase',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' => $cs->payment_status === 'paid',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300' => $cs->payment_status === 'partially_paid',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300' => $cs->payment_status === 'pending',
                                        ])>
                                            {{ ucfirst(str_replace('_', ' ', $cs->payment_status ?: 'pending')) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1">
                                        {{ $cs->created_at->format('d M Y') }} &bull; {{ __('Total:') }} ${{ number_format($cs->total, 2) }}
                                        @if ($cs->due_date)
                                            &bull; <span class="{{ $cs->due_date->isPast() && (float)$cs->due_amount > 0 ? 'text-rose-600 font-bold' : '' }}">{{ __('Due:') }} {{ $cs->due_date->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <div class="text-[10px] text-slate-400 font-semibold">{{ __('Due Balance') }}</div>
                                    <div class="font-mono font-black text-sm text-slate-900 dark:text-white">
                                        ${{ number_format($cs->due_amount, 2) }}
                                    </div>
                                    @if ((float) $cs->due_amount > 0)
                                        <button type="button"
                                                wire:click="openSettleModal({{ $cs->id }})"
                                                class="mt-1 px-3 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] shadow-xs active:scale-95 transition cursor-pointer">
                                            {{ __('Settle / Collect') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800 text-center text-slate-400 text-xs">
                                {{ __('No credit sales or receivables recorded for this customer.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="closeCreditLedger" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Debt Settlement Modal (Req 16) -->
    @if ($showSettleModal && $selectedSale)
        <div class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.closeSettleModal()">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Record Debt Settlement') }}</h3>
                    <button type="button" wire:click="closeSettleModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-semibold">{{ __('Order Reference:') }}</span>
                        <strong class="font-mono text-slate-800 dark:text-slate-100">#{{ $selectedSale->sale_number }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-semibold">{{ __('Current Outstanding:') }}</span>
                        <strong class="font-mono text-rose-600 dark:text-rose-400 font-black">${{ number_format($selectedSale->due_amount, 2) }}</strong>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Amount ($) *') }}</label>
                        <input type="number" step="any" min="0.01" max="{{ $selectedSale->due_amount }}" wire:model.live="paymentAmount" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-mono font-bold text-sm">
                        @error('paymentAmount') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Method *') }}</label>
                        <select wire:model="paymentMethod" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-bold">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="card">{{ __('Credit / Debit Card') }}</option>
                            <option value="pix">{{ __('PIX / Instant Transfer') }}</option>
                            <option value="bank_transfer">{{ __('Bank Transfer / Wire') }}</option>
                            <option value="check">{{ __('Check / Cheque') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes / Memo') }}</label>
                        <input type="text" wire:model="paymentNotes" placeholder="{{ __('e.g. Partial cash received at counter') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="closeSettleModal" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="recordSettlement" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition active:scale-95 cursor-pointer">
                        {{ __('Confirm Settlement') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
