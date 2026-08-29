<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">📄</span>
                <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">{{ __('Create New Quotation') }}</h2>
            </div>
            <p class="text-xs text-slate-400 mt-1 ml-11">{{ __('Quote Reference:') }} <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ $quoteNumber }}</span></p>
        </div>
        <a wire:navigate.hover href="{{ route('tenant.quotes.index') }}" class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">&larr; {{ __('Back to Quotes') }}</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-8 space-y-6 min-w-0">
            <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">👤 {{ __('Client & Representative') }}</h3>
                    <button type="button" wire:click="$set('showQuickCustomerModal', true)" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('Add New Client') }}</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Select Client / Customer') }}</label>
                        <select wire:model="customerId" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- {{ __('Direct Proposal / Walk-in Client') }} --</option>
                            @foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Sales Representative') }}</label>
                        <select wire:model="userId" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500">
                            @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role ?? 'Staff') }})</option>@endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Initial Status') }}</label>
                        <select wire:model="status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500">
                            <option value="draft">{{ __('Draft Proposal') }}</option><option value="sent">{{ __('Sent to Client') }}</option><option value="accepted">{{ __('Accepted / Approved') }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">📦 {{ __('Line Items & Deliverables Scope') }}</h3>
                    <button type="button" wire:click="addItem" class="px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">+ {{ __('Add Item Row') }}</button>
                </div>
                <div class="space-y-3">
                    @foreach ($items as $index => $item)
                        <div wire:key="quote-item-{{ $index }}" class="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                                <div class="sm:col-span-6 flex gap-2 min-w-0">
                                    <input type="text" wire:model="items.{{ $index }}.name" placeholder="{{ __('Item or service name *') }}" class="min-w-0 flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-100">
                                    <select wire:model.live="items.{{ $index }}.product_id" class="w-32 px-2 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                        <option value="">{{ __('Catalog...') }}</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                                    </select>
                                </div>
                                <input type="number" step="any" min="0.01" wire:model.live.debounce.250ms="items.{{ $index }}.quantity" placeholder="{{ __('Qty') }}" class="sm:col-span-2 w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-center">
                                <input type="number" step="0.01" min="0" wire:model.live.debounce.250ms="items.{{ $index }}.price" placeholder="{{ __('Price') }}" class="sm:col-span-2 w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-right font-mono">
                                <div class="sm:col-span-2 flex items-center justify-between gap-2 pl-1">
                                    <span class="font-black font-mono text-xs text-slate-800 dark:text-slate-100">${{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0), 2) }}</span>
                                    @if (count($items) > 1)<button type="button" wire:click="removeItem({{ $index }})" class="text-rose-500 hover:text-rose-700 p-1 font-bold">✕</button>@endif
                                </div>
                            </div>
                            <input type="text" wire:model="items.{{ $index }}.description" placeholder="{{ __('Scope specifications / item deliverables description (optional)...') }}" class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800 rounded-xl text-[11px] text-slate-500 dark:text-slate-400">
                        </div>
                    @endforeach
                </div>
                @error('items') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
                @error('items.*.name') <p class="text-rose-600 text-xs">{{ __('Please provide a valid name for all items.') }}</p> @enderror
            </section>

            <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">📝 {!! __('Quote Terms & Notes') !!} · {{ __('Warranty & Bank Instructions') }}</h3>
                <x-rich-text-editor wire:model="quoteNotes" placeholder="{{ __('Enter quotation settlement conditions, delivery terms, or bank instructions...') }}" height="180" />
            </section>
        </div>

        <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-20 min-w-0">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 xl:p-6 shadow-sm space-y-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-3 border-b border-slate-100 dark:border-slate-800">💳 {{ __('Settlement & Tax Rules') }}</h3>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span>{{ __('Discount') }}</span><div class="flex bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg text-[10px]">
                        <button type="button" wire:click="$set('discountType', 'fixed')" @class(['px-2 py-0.5 rounded-md font-bold transition', 'bg-white dark:bg-slate-700 shadow-xs text-blue-600' => $discountType === 'fixed'])>{{ __('Fixed ($)') }}</button>
                        <button type="button" wire:click="$set('discountType', 'percent')" @class(['px-2 py-0.5 rounded-md font-bold transition', 'bg-white dark:bg-slate-700 shadow-xs text-blue-600' => $discountType === 'percent'])>{{ __('Percent (%)') }}</button>
                    </div></div>
                    <input type="number" step="0.01" min="0" wire:model.live.debounce.250ms="discountValue" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-mono">
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between"><label class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Applicable Tax Rule') }}</label><label class="flex items-center gap-1.5 text-[10px] text-slate-400 cursor-pointer"><input type="checkbox" wire:model.live="isTaxExempt" class="rounded border-slate-300 text-blue-600 focus:ring-0"><span>{{ __('Exempt') }}</span></label></div>
                    <select wire:model.live="selectedTaxRuleId" @disabled($isTaxExempt || empty($availableTaxRules)) class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold disabled:opacity-50">
                        @forelse ($availableTaxRules as $rule)<option value="{{ $rule['id'] }}">{{ $rule['name'] }} ({{ $rule['rate'] }}%{{ !empty($rule['sub_components']) ? ' - '.collect($rule['sub_components'])->pluck('name')->join(' + ') : '' }})</option>@empty<option value="">{{ __('No active tax rules configured') }}</option>@endforelse
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-3">
                    <div class="space-y-1"><label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Payment Method') }}</label><select wire:model="agreedPaymentMethod" class="w-full px-2.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold">@foreach ($availablePaymentMethods as $method)<option value="{{ $method->code }}">{{ $method->name }}</option>@endforeach</select></div>
                    <div class="space-y-1"><label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Validity Expiry') }}</label><input type="date" wire:model="dueDate" class="w-full px-2.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold"></div>
                </div>
                <div class="space-y-1"><label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Payment Terms') }}</label><select wire:model="paymentTerms" class="w-full px-2.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold"><option value="Due on Receipt">{{ __('Due on Receipt') }}</option><option value="Net 7">{{ __('Net 7 (7 Days)') }}</option><option value="Net 15">{{ __('Net 15 (15 Days)') }}</option><option value="Net 30">{{ __('Net 30 (30 Days)') }}</option><option value="Net 60">{{ __('Net 60 (60 Days)') }}</option><option value="Custom">{{ __('Custom / Staged') }}</option></select></div>
                <div class="pt-4 border-t border-dashed border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500 dark:text-slate-400"><span>{{ __('Subtotal') }}</span><span class="font-mono font-bold text-slate-800 dark:text-slate-200">${{ number_format($this->subtotal, 2) }}</span></div>
                    @if ($this->calculatedDiscount > 0)<div class="flex justify-between text-rose-500"><span>{{ __('Discount') }}</span><span class="font-mono font-bold">-${{ number_format($this->calculatedDiscount, 2) }}</span></div>@endif
                    <div class="flex justify-between gap-3 text-slate-500 dark:text-slate-400"><span class="truncate">{{ $isTaxExempt ? __('Tax Exempt') : $taxName }} ({{ $isTaxExempt ? 0 : $taxPercent }}%)</span><span class="font-mono font-bold text-slate-800 dark:text-slate-200">+${{ number_format($this->tax, 2) }}</span></div>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between items-baseline gap-3"><span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">{{ __('Grand Total') }}</span><span class="text-2xl font-black text-blue-600 dark:text-blue-400 font-mono">${{ number_format($this->total, 2) }}</span></div>
                </div>
                <x-ui.button wire:click="save" wire:loading.attr="disabled" full><span wire:loading.remove>⚡ {{ __('Save & Generate Proposal') }}</span><span wire:loading>{{ __('Saving...') }}</span></x-ui.button>
            </div>
        </aside>
    </div>

    <!-- Quick Customer Modal -->
    @if ($showQuickCustomerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-200">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200/60 dark:border-white/10 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">{{ __("Add New Client") }}</h3>
                    <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="text-slate-400 font-bold cursor-pointer hover:text-slate-600">&times;</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Client Name *") }}</label>
                        <input type="text" wire:model="newCustomerName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Tax ID / VAT / Document") }}</label>
                        <input type="text" wire:model.blur="newCustomerDocument" placeholder="{{ __("e.g. TAX-8891, VAT12345") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Phone / WhatsApp") }}</label>
                        <input type="text" wire:model.blur="newCustomerPhone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Email") }}</label>
                        <input type="email" wire:model.blur="newCustomerEmail" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">Cancel</button>
                    <x-ui.button wire:click="createQuickCustomer">Save</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
