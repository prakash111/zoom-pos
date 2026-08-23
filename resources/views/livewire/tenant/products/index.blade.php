<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
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
                   placeholder="Search by name, code, barcode…"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl py-2.5 pl-10 pr-4 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500 shadow-xs">
        </div>

        <button wire:click="newProduct"
                type="button"
                class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>+ New Product</span>
        </button>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">
                {{ $editingId ? 'Edit Product' : 'Add New Product' }}
            </h3>

            <!-- Product Photo Upload / Image Section -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row items-center gap-5">
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-white dark:bg-slate-700/60 border-2 border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center overflow-hidden shrink-0 shadow-xs">
                    @if ($imageFile)
                        <img src="{{ $imageFile->temporaryUrl() }}" class="w-full h-full object-cover">
                    @elseif ($imageUrl)
                        <img src="{{ $imageUrl }}" class="w-full h-full object-cover">
                    @else
                        <div class="text-center p-2 text-slate-400">
                            <svg class="w-8 h-8 mx-auto mb-1 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <span class="text-[10px] font-bold">No Image</span>
                        </div>
                    @endif

                    <div wire:loading wire:target="imageFile" class="absolute inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center text-white text-[10px] font-black">
                        Uploading...
                    </div>
                </div>

                <div class="flex-1 w-full space-y-2.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Upload Product Photo</label>
                        <input type="file"
                               wire:model="imageFile"
                               accept="image/png,image/jpeg,image/webp,image/svg+xml"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-950/60 dark:file:text-blue-300 hover:file:bg-blue-100 cursor-pointer">
                        @error('imageFile') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Or Direct Image URL</label>
                        <input type="text"
                               wire:model="imageUrl"
                               placeholder="https://example.com/product-image.jpg"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Product Name *</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Item Code</label>
                    <input type="text" wire:model="code" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Barcode / SKU</label>
                    <input type="text" wire:model="barcode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Category</label>
                    <select wire:model="categoryId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Select Category —</option>
                        @foreach ($categories as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Brand</label>
                    <select wire:model="brandId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Select Brand —</option>
                        @foreach ($brands as $b) <option value="{{ $b->id }}">{{ $b->name }}</option> @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Unit</label>
                    <input type="text" wire:model="unit" placeholder="pcs, kg, box…" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cost Price ($) *</label>
                    <input type="number" step="0.01" wire:model="costPrice" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Sale Price ($) *</label>
                    <input type="number" step="0.01" wire:model="salePrice" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Current / Opening Stock *</label>
                    <input type="number" step="0.001" wire:model="currentStock" @disabled($editingId) class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm disabled:opacity-60 focus:ring-blue-500 focus:border-blue-500">
                    @if ($editingId) <p class="text-[10px] text-slate-400 mt-1">Use "Adjust Stock" button on the table.</p> @endif
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Minimum Alert Stock *</label>
                    <input type="number" step="0.001" wire:model="minimumStock" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="flex items-center gap-2 pt-5">
                    <input type="checkbox" wire:model="taxable" id="taxable" class="rounded-lg text-blue-600 focus:ring-blue-500">
                    <label for="taxable" class="text-xs font-bold text-slate-700 dark:text-slate-300">Taxable</label>
                </div>

                <div class="flex items-center gap-2 pt-5">
                    <input type="checkbox" wire:model="active" id="active" class="rounded-lg text-blue-600 focus:ring-blue-500">
                    <label for="active" class="text-xs font-bold text-slate-700 dark:text-slate-300">Active (Visible in POS)</label>
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-3">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                    Save Product
                </button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5">Product</th>
                        <th class="px-5 py-3.5">Code / SKU</th>
                        <th class="px-5 py-3.5">Stock</th>
                        <th class="px-5 py-3.5">Price</th>
                        <th class="px-5 py-3.5 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($products as $product)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 flex items-center gap-3">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0">
                                @else
                                    <x-pos-product-icon :name="$product->name" :category="$product->category_name ?? ''" size="xs" />
                                @endif
                                <div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $product->name }}</div>
                                    @unless ($product->active)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-400 font-semibold">Inactive</span>
                                    @endunless
                                </div>
                            </td>

                            <td class="px-5 py-3.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $product->code ?: '—' }}</td>

                            <td class="px-5 py-3.5">
                                <span @class([
                                    'px-2.5 py-0.5 rounded-full text-xs font-bold inline-block',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $product->current_stock <= $product->minimum_stock,
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $product->current_stock > $product->minimum_stock,
                                ])>
                                    {{ rtrim(rtrim(number_format($product->current_stock, 3), '0'), '.') }} {{ $product->unit }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">${{ number_format($product->sale_price, 2) }}</td>

                            <td class="px-5 py-3.5 text-right space-x-2">
                                <button wire:click="startAdjust({{ $product->id }})" type="button" class="text-xs font-bold text-slate-500 hover:text-blue-600 px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition">Adjust</button>
                                <button wire:click="edit({{ $product->id }})" type="button" class="text-xs font-bold text-blue-600 hover:underline px-2 py-1 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 transition">Edit</button>
                                <button wire:click="delete({{ $product->id }})" wire:confirm="Delete this product?" type="button" class="text-xs font-bold text-rose-600 hover:underline px-2 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">Delete</button>
                            </td>
                        </tr>

                        @if ($adjustingId === $product->id)
                            <tr class="bg-blue-50/50 dark:bg-blue-950/20">
                                <td colspan="5" class="px-5 py-4">
                                    <div class="flex flex-col sm:flex-row items-end gap-3">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Adjustment (+/- Qty)</label>
                                            <input type="number" step="0.001" wire:model="adjustmentQty" class="w-36 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                                        </div>
                                        <div class="flex-1 w-full">
                                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Reason</label>
                                            <input type="text" wire:model="adjustmentReason" placeholder="e.g. Restock, damaged, correction" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="applyAdjustment" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition">Apply</button>
                                            <button wire:click="$set('adjustingId', null)" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                                        </div>
                                    </div>
                                    @error('adjustmentQty') <p class="text-rose-600 text-xs mt-1.5">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-xs">
                                No products yet. Click "+ New Product" to add items.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</div>
