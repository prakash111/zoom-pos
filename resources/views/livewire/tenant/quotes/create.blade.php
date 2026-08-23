<div class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Create Quotation</h2>
            <p class="text-xs text-slate-400 mt-0.5">Quote Reference: <strong class="font-mono text-blue-600 dark:text-blue-400">{{ $quoteNumber }}</strong></p>
        </div>

        <a href="{{ route('tenant.quotes.index') }}" class="text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-white">
            &larr; Back to Quotes
        </a>
    </div>

    <!-- Main Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Client & Rep Selection -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
            <div class="sm:col-span-2 space-y-1.5">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                    <span>Select Client / Customer</span>
                    <button type="button" wire:click="$set('showQuickCustomerModal', true)" class="text-blue-600 hover:underline font-extrabold cursor-pointer">+ Add New Client</button>
                </div>
                <select wire:model="customerId" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-blue-500">
                    <option value="">-- Direct Proposal / Walk-in Client --</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email ?? $c->phone ?? ($c->document ? 'Tax ID: '.$c->document : 'Client') }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Sales Representative</label>
                <select wire:model="userId" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-blue-500">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ ucfirst($u->role ?? 'Staff') }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Initial Status</label>
                <select wire:model="status" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-blue-500">
                    <option value="draft">Draft Proposal</option>
                    <option value="sent">Sent to Client</option>
                    <option value="accepted">Accepted / Approved</option>
                </select>
            </div>
        </div>

        <!-- Line Items -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Line Items & Scope Specifications</h3>
                <button type="button" wire:click="addItem" class="text-xs font-extrabold text-blue-600 hover:underline flex items-center gap-1 cursor-pointer">
                    <span>+ Add Row</span>
                </button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-2">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                            <div class="flex-1">
                                <input type="text"
                                       wire:model="items.{{ $index }}.name"
                                       placeholder="Item or service name *"
                                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-blue-500">
                            </div>

                            <div class="w-36">
                                <select wire:model.live="items.{{ $index }}.product_id" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-[11px] font-semibold focus:ring-blue-500">
                                    <option value="">Catalog Item...</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} (${{ number_format($product->sale_price, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="w-24">
                                <input type="number" step="any" min="0.01" wire:model.live="items.{{ $index }}.quantity" placeholder="Qty" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-center focus:ring-blue-500">
                            </div>

                            <div class="w-28">
                                <input type="number" step="any" min="0" wire:model.live="items.{{ $index }}.price" placeholder="Price ($)" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-right focus:ring-blue-500">
                            </div>

                            <div class="w-28 text-right font-black text-xs sm:text-sm text-slate-800 dark:text-slate-100 pr-2">
                                ${{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0), 2) }}
                            </div>

                            @if (count($items) > 1)
                                <button type="button" wire:click="removeItem({{ $index }})" class="text-rose-500 hover:text-rose-700 font-bold text-base px-1.5 cursor-pointer">
                                    &times;
                                </button>
                            @endif
                        </div>

                        <!-- Item Description / Scope Notes -->
                        <div>
                            <input type="text"
                                   wire:model="items.{{ $index }}.description"
                                   placeholder="Scope specifications / item deliverables description (optional)..."
                                   class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-[11px] text-slate-600 dark:text-slate-300 focus:ring-blue-500">
                        </div>
                    </div>
                @endforeach
            </div>

            @error('items') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
            @error('items.*.name') <p class="text-rose-600 text-xs">Please provide a valid name for all items.</p> @enderror
        </div>

        <!-- Summary, Discounts, Tax & Notes -->
        <div class="flex flex-col sm:flex-row justify-between items-start pt-5 border-t border-slate-100 dark:border-slate-800 gap-6">
            <div class="w-full sm:w-80 space-y-3">
                <!-- Discount row with type switch -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Discount</label>
                        <div class="flex text-[10px] font-bold bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg">
                            <button type="button"
                                    wire:click="$set('discountType', 'fixed')"
                                    @class(['px-2 py-0.5 rounded-md transition cursor-pointer', 'bg-white dark:bg-slate-700 text-blue-600 shadow-xs' => $discountType === 'fixed'])>
                                Fixed ($)
                            </button>
                            <button type="button"
                                    wire:click="$set('discountType', 'percent')"
                                    @class(['px-2 py-0.5 rounded-md transition cursor-pointer', 'bg-white dark:bg-slate-700 text-blue-600 shadow-xs' => $discountType === 'percent'])>
                                Percent (%)
                            </button>
                        </div>
                    </div>
                    <input type="number" step="any" min="0" wire:model.live="discountValue" placeholder="{{ $discountType === 'percent' ? 'e.g. 10%' : 'e.g. $50.00' }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-blue-500">
                </div>

                <!-- Tax setting with exemption toggle -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Tax Rate (%)</label>
                        <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 cursor-pointer">
                            <input type="checkbox" wire:model.live="isTaxExempt" class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500/20 dark:bg-slate-800 transition cursor-pointer">
                            <span>Tax Exempt</span>
                        </label>
                    </div>
                    <input type="number" step="any" min="0" wire:model.live="taxPercent" @disabled($isTaxExempt) class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-blue-500 disabled:opacity-50">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Validity Expiry Date</label>
                    <input type="date" wire:model="dueDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold focus:ring-blue-500">
                </div>

                <!-- Quote Terms & Notes (Rich-Text) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Quote Terms & Notes</label>
                    <x-rich-text-editor wire:model="notes" placeholder="e.g. Scope deliverables, milestone payment terms, project phases..." height="180" />
                </div>
            </div>

            <div class="w-full sm:w-72 space-y-2 text-xs">
                <div class="flex justify-between text-slate-500 dark:text-slate-400">
                    <span>Subtotal:</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">${{ number_format($this->subtotal, 2) }}</span>
                </div>
                @if ($this->calculatedDiscount > 0)
                    <div class="flex justify-between text-rose-500 font-medium">
                        <span>Discount {{ $discountType === 'percent' ? "({$discountValue}%)" : '' }}:</span>
                        <span>-${{ number_format($this->calculatedDiscount, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-slate-500 dark:text-slate-400">
                    <span>Tax ({{ $isTaxExempt ? '0% Exempt' : "{$taxPercent}%" }}):</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">${{ number_format($this->tax, 2) }}</span>
                </div>
                <div class="flex justify-between items-baseline font-black text-slate-900 dark:text-white pt-2 border-t border-slate-100 dark:border-slate-800 text-sm">
                    <span>Grand Total:</span>
                    <span class="text-2xl">${{ number_format($this->total, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
            <button type="button"
                    wire:click="save"
                    class="px-8 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                Save & Preview Quotation
            </button>
        </div>

    </div>

    <!-- Quick Customer Modal -->
    @if ($showQuickCustomerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Add New Client</h3>
                    <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="text-slate-400 font-bold cursor-pointer">&times;</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Client Name *</label>
                        <input type="text" wire:model="newCustomerName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tax ID / VAT / Document</label>
                        <input type="text" wire:model="newCustomerDocument" placeholder="e.g. TAX-8891, VAT12345" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Phone / WhatsApp</label>
                        <input type="text" wire:model="newCustomerPhone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                        <input type="email" wire:model="newCustomerEmail" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 cursor-pointer">Cancel</button>
                    <button type="button" wire:click="createQuickCustomer" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-blue-600 text-white cursor-pointer">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
