<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Header Section -->
    <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a wire:navigate.hover href="{{ route('tenant.consignments.index') }}" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 text-xs font-bold transition">
                &larr; {{ __("Back") }}
            </a>
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Create Consignment") }}</h1>
                <p class="text-xs text-slate-400">{{ __("Select recipient customer and add products with quantities to dispatch on consignment.") }}</p>
            </div>
        </div>
    </div>

    <!-- Consignment Form Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Client & Due Date -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Recipient Customer *") }}</label>
                <select wire:model="customerId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-purple-500 py-2.5 px-3">
                    <option value="">-- {{ __("Select Customer") }} --</option>
                    @foreach ($customers as $cust)
                        <option value="{{ $cust->id }}">{{ $cust->name }} ({{ $cust->phone ?: $cust->email }})</option>
                    @endforeach
                </select>
                @error('customerId') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Expected Reconciliation Due Date") }}</label>
                <input type="date" wire:model="dueDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-purple-500 py-2 px-3">
            </div>
        </div>

        <!-- Merchandise Items Grid -->
        <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __("Consigned Items List") }}</h3>
                <button type="button" wire:click="addItem" class="px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 hover:bg-purple-100 text-xs font-bold cursor-pointer">
                    + {{ __("Add Item") }}
                </button>
            </div>

            <div class="space-y-2">
                @foreach ($items as $idx => $it)
                    <div class="grid grid-cols-12 gap-2 items-center p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50">
                        <div class="col-span-12 sm:col-span-5">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase sm:hidden mb-0.5">{{ __("Product") }}</label>
                            <select wire:model.live="items.{{ $idx }}.product_id" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-xs font-bold py-1.5 px-2.5">
                                <option value="">-- {{ __("Choose Product") }} --</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (Stock: {{ (float)$p->current_stock }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-4 sm:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase sm:hidden mb-0.5">{{ __("Qty") }}</label>
                            <input type="number" step="1" min="1" wire:model.live="items.{{ $idx }}.quantity" placeholder="{{ __("Qty") }}"
                                   class="w-full text-center rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono font-bold py-1.5 px-2">
                        </div>

                        <div class="col-span-4 sm:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase sm:hidden mb-0.5">{{ __("Unit Price") }}</label>
                            <input type="number" step="0.01" min="0" wire:model.live="items.{{ $idx }}.unit_price" placeholder="{{ __("Price") }}"
                                   class="w-full text-right rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono font-bold py-1.5 px-2">
                        </div>

                        <div class="col-span-3 sm:col-span-2 text-right">
                            <span class="text-xs font-black font-mono text-slate-900 dark:text-white">
                                {{ $company->formatMoney($it['total']) }}
                            </span>
                        </div>

                        <div class="col-span-1 text-center">
                            <button type="button" wire:click="removeItem({{ $idx }})" class="text-rose-500 hover:text-rose-700 text-xs font-bold cursor-pointer">✕</button>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('items') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Notes & Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Consignment Terms & Notes") }}</label>
                <textarea wire:model="notes" rows="3" placeholder="{{ __("e.g. Unsold products to be returned within 15 days in original condition...") }}"
                          class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium focus:ring-purple-500"></textarea>
            </div>

            <div class="flex flex-col justify-between bg-slate-50 dark:bg-slate-800/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                <div class="flex justify-between items-center text-slate-500">
                    <span class="text-xs font-bold">{{ __("Total Dispatched Value") }}</span>
                    <span class="text-xl font-black font-mono text-purple-600 dark:text-purple-400">
                        {{ $company->formatMoney($this->totalDispatched) }}
                    </span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3">
                    <button type="button" wire:click="save('draft')" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition cursor-pointer">
                        {{ __("Save as Draft") }}
                    </button>
                    <button type="button" wire:click="save('dispatched')" class="px-6 py-2.5 rounded-xl text-xs font-black bg-purple-600 hover:bg-purple-700 text-white shadow-lg shadow-purple-500/25 active:scale-95 transition cursor-pointer">
                        🚀 {{ __("Dispatch Merchandise") }}
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>
