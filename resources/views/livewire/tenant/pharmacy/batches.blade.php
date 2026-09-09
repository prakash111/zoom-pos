<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Drug Batches & Expiry') }}</h2>
            <p class="text-xs text-slate-400">{{ __('First-Expiry-First-Out stock, batch-wise') }}</p>
        </div>
        <button wire:click="newBatch" type="button"
                class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md active:scale-95 transition">
            + {{ __('Register Batch') }}
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search medicine, batch, rack…') }}"
               class="flex-1 min-w-[220px] rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
        @foreach (['all' => __('All'), 'safe' => __('Safe'), 'near_expiry' => __('Near expiry'), 'expired' => __('Expired')] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')" type="button"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $filter === $key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('Register a drug batch') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Medicine / Product *') }}</label>
                    <select wire:model="productId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">{{ __('Select a product…') }}</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}{{ $p->generic_name ? ' · '.$p->generic_name : '' }}</option>
                        @endforeach
                    </select>
                    @error('productId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Batch number *') }}</label>
                    <input type="text" wire:model="batchNumber" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('batchNumber') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Rack location') }}</label>
                    <input type="text" wire:model="rackLocation" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Manufacturing date') }}</label>
                    <input type="date" wire:model="manufacturingDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Expiry date *') }}</label>
                    <input type="date" wire:model="expiryDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('expiryDate') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quantity *') }}</label>
                    <input type="number" min="0" wire:model="stockQty" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('stockQty') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Cost price') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="costPrice" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Selling price (MRP)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="sellingPrice" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Alert days before expiry') }}</label>
                    <input type="number" min="1" wire:model="alertDaysBeforeExpiry" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md active:scale-95 transition">{{ __('Save Batch') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Medicine') }}</th>
                    <th class="px-5 py-3.5">{{ __('Batch') }}</th>
                    <th class="px-5 py-3.5">{{ __('Expiry') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('Stock') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('MRP') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($batches as $b)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                            {{ $b->product?->name ?? __('Unknown') }}
                            @if ($b->product?->generic_name)
                                <span class="block text-[11px] font-normal text-slate-400">{{ $b->product->generic_name }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 font-mono text-xs text-slate-500">
                            {{ $b->batch_number }}
                            @if ($b->rack_location)<span class="block text-slate-400">{{ $b->rack_location }}</span>@endif
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 font-semibold" style="color: {{ $b->expiry_color }}">
                                {{ $b->expiry_date?->format('Y-m-d') ?? __('n/a') }}
                            </span>
                            <span class="block text-[11px] text-slate-400">
                                {{ $b->days_until_expiry !== null ? __(':n days', ['n' => $b->days_until_expiry]) : '—' }} · {{ str_replace('_', ' ', $b->expiry_status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold">{{ (int) $b->stock_qty }}</td>
                        <td class="px-5 py-3.5 text-right text-slate-500">{{ number_format((float) $b->selling_price, 2) }}</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-2">
                            <button wire:click="startAdjust({{ $b->id }})" type="button" class="text-xs font-bold text-blue-600 hover:underline">{{ __('Adjust') }}</button>
                            <button wire:click="startReturn({{ $b->id }})" type="button" class="text-xs font-bold text-amber-600 hover:underline">{{ __('Return') }}</button>
                        </td>
                    </tr>
                    @if ($actingBatchId === $b->id)
                        <tr class="bg-slate-50 dark:bg-slate-800/40">
                            <td colspan="6" class="px-5 py-4">
                                <div class="flex flex-wrap items-end gap-3">
                                    @if ($actingMode === 'adjust')
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('New stock quantity') }}</label>
                                            <input type="number" min="0" wire:model="adjustNewStock" class="w-36 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                                            @error('adjustNewStock') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                                        </div>
                                    @else
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Return quantity') }}</label>
                                            <input type="number" min="1" wire:model="returnQty" class="w-36 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                                            @error('returnQty') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-[200px]">
                                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Reason') }}</label>
                                        <input type="text" wire:model="reason" class="w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                                    </div>
                                    <button wire:click="confirmAction" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">{{ __('Confirm') }}</button>
                                    <button wire:click="cancelAction" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 transition">{{ __('Cancel') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No batches match this view.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
