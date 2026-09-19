<div class="space-y-6">
    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center justify-between gap-3 shadow-sm border border-emerald-200 dark:border-emerald-800/50">
            <div class="flex items-center gap-2.5">
                <span class="text-base">🎉</span>
                <span>
                    {{ session('status') }}
                    @if (session('published_url'))
                        — <a href="{{ session('published_url') }}" target="_blank" class="underline font-mono font-bold text-emerald-900 dark:text-emerald-200">{{ session('published_url') }}</a>
                    @endif
                </span>
            </div>
            @if (session('published_url'))
                <a href="{{ session('published_url') }}" target="_blank" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                    {{ __('Open Storefront') }} &nearr;
                </a>
            @endif
        </div>
    @endif

    <!-- eCommerce Business Website Builder Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Header Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 uppercase tracking-wider">
                        {{ __('Omnichannel eCommerce Engine') }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                        {{ __('Live Web Store') }}
                    </span>
                </div>
                <h2 class="text-xl font-black text-slate-900 dark:text-white mt-1.5">{{ __('Create & Manage eCommerce Business Website') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-2xl mt-0.5">
                    {{ __('Launch a complete online storefront for your business with real-time stock sync, WhatsApp checkout, rich product descriptions, and integrated quotations and invoices.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="openNewProductModal"
                        class="px-4 py-2.5 rounded-2xl text-xs font-extrabold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 flex items-center gap-1.5 transition cursor-pointer">
                    <span>+</span>
                    <span>{{ __('Upload New Product') }}</span>
                </button>
            </div>
        </div>

        <!-- Integrated Business Features Badge Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-black text-base">📦</div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block">{{ __('Active Inventory') }}</span>
                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ $totalActiveProducts }} {{ __('Products') }}</span>
                </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-black text-base">💬</div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block">{{ __('Direct Ordering') }}</span>
                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ __('1-Click WhatsApp') }}</span>
                </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center font-black text-base">📋</div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block">{{ __('Quotations Sync') }}</span>
                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ $quotesCount }} {{ __('Quotations') }}</span>
                </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 flex items-center justify-center font-black text-base">🧾</div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block">{{ __('Invoicing Sync') }}</span>
                    <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ $invoicesCount }} {{ __('Sales Records') }}</span>
                </div>
            </div>
        </div>

        <!-- Store Website Configuration -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store / Website Title *') }}</label>
                <input type="text" wire:model="title" placeholder="{{ __('e.g. Acme Superstore Online Catalog') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('WhatsApp Checkout Number') }}</label>
                <input type="text" wire:model="whatsappNumber" placeholder="{{ __('e.g. +919876543210') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                <p class="text-[10px] text-slate-400 mt-1">{{ __('Customer cart orders are formatted and sent straight to this WhatsApp number.') }}</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Website Link Lifetime') }}</label>
                <select wire:model="ttlDays" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold">
                    <option value="0">🌐 {{ __('Permanent Business Website (Never Expires)') }}</option>
                    <option value="30">📅 {{ __('30 Days Temporary Campaign') }}</option>
                    <option value="7">📅 {{ __('7 Days Promo Showcase') }}</option>
                    <option value="1">⏱️ {{ __('24 Hours Flash Sale') }}</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Description & Visitor Notice') }}</label>
                <textarea wire:model="description" rows="2" placeholder="{{ __('Welcome message, operating hours, delivery details or promotional notice shown at top of website...') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                @error('description') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Integrated Features Toggles -->
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-6">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="enableQuotations" class="rounded-lg text-blue-600 focus:ring-blue-500">
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable Instant Quotations / Proposal Request') }}</span>
            </label>
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="enableInvoices" class="rounded-lg text-blue-600 focus:ring-blue-500">
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable Digital Receipt & Invoice Lookup for Customers') }}</span>
            </label>
        </div>

        <!-- Products Merging & Selection Section -->
        <div class="space-y-3 pt-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <label class="block text-xs font-extrabold text-slate-900 dark:text-white">{{ __('Merge & Select Products for eCommerce Website') }}</label>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Selected:') }} <strong class="text-blue-600 dark:text-blue-400">{{ count($selectedProductIds) }}</strong> {{ __('of') }} {{ $totalActiveProducts }} {{ __('active products') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="selectAllProducts"
                            class="px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 dark:text-blue-300 text-xs font-bold transition">
                        ✓ {{ __('Merge All Products') }}
                    </button>
                    @if (count($selectedProductIds) > 0)
                        <button type="button"
                                wire:click="clearSelectedProducts"
                                class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-xs font-bold transition">
                            ✕ {{ __('Clear') }}
                        </button>
                    @endif
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="text"
                       wire:model.live.debounce.300ms="productSearch"
                       placeholder="{{ __('Search product name, code, barcode, description…') }}"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                <select wire:model.live="filterCategory"
                        class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            @error('selectedProductIds') <p class="text-rose-600 text-xs font-bold">{{ $message }}</p> @enderror

            <!-- Product Selection Scroll Area -->
            <div class="max-h-72 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-2xl divide-y divide-slate-100 dark:divide-slate-800 p-1.5 bg-slate-50/50 dark:bg-slate-800/30">
                @forelse ($products as $product)
                    <label class="flex items-center gap-3 px-3.5 py-2.5 text-xs sm:text-sm hover:bg-white dark:hover:bg-slate-800 rounded-xl cursor-pointer transition {{ in_array($product->id, $selectedProductIds) ? 'bg-blue-50/50 dark:bg-blue-950/20' : '' }}">
                        <input type="checkbox" wire:model="selectedProductIds" value="{{ $product->id }}" class="rounded-lg text-blue-600 focus:ring-blue-500">
                        
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            @else
                                <x-pos-product-icon :name="$product->name" size="xs" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $product->name }}</span>
                                @if ($product->code)
                                    <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">{{ $product->code }}</span>
                                @endif
                            </div>
                            @if ($product->description)
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 line-clamp-1 mt-0.5">{{ $product->description }}</p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-xs font-black text-slate-900 dark:text-white">${{ number_format($product->sale_price, 2) }}</div>
                            <span class="text-[10px] text-slate-400">{{ __('Stock:') }} {{ (float) $product->current_stock }}</span>
                        </div>
                    </label>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-slate-400">
                        {{ __('No products match your search/filter.') }}
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Launch Button -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Ready to publish with') }} <strong class="text-blue-600 dark:text-blue-400">{{ count($selectedProductIds) }}</strong> {{ __('items selected') }}.
            </span>
            <button wire:click="publish"
                    type="button"
                    class="px-6 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition cursor-pointer flex items-center gap-2">
                <span>🚀</span>
                <span>{{ __('Publish eCommerce Business Website') }}</span>
            </button>
        </div>
    </div>

    <!-- Active Published eCommerce Storefronts -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-extrabold text-sm text-slate-900 dark:text-white flex items-center justify-between">
            <span>{{ __('Live eCommerce Websites & Published Storefronts') }}</span>
            <span class="text-xs text-slate-400 font-normal">{{ count($catalogs) }} {{ __('active links') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 text-[11px] font-bold uppercase tracking-wider text-left">
                    <tr>
                        <th class="px-6 py-3">{{ __('Website Title') }}</th>
                        <th class="px-6 py-3">{{ __('Catalog Products') }}</th>
                        <th class="px-6 py-3">{{ __('Status / Expiry') }}</th>
                        <th class="px-6 py-3">{{ __('Live Link') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($catalogs as $catalog)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4">
                                <div class="font-extrabold text-slate-800 dark:text-slate-100">{{ $catalog->title }}</div>
                                @if ($catalog->description)
                                    <div class="text-[11px] text-slate-400 line-clamp-1 max-w-xs">{{ $catalog->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-bold text-xs">
                                    {{ count($catalog->product_ids ?? []) }} {{ __('items') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($catalog->isExpired())
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">{{ __('Expired') }}</span>
                                @elseif (! $catalog->expires_at)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">🌐 {{ __('Permanent Website') }}</span>
                                @else
                                    <span class="text-xs text-slate-500 font-mono">{{ __('Expires:') }} {{ $catalog->expires_at->format('Y-m-d') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('catalog.show', $catalog->id) }}" target="_blank" class="px-3 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold inline-flex items-center gap-1 transition">
                                        <span>{{ __('Open Store') }}</span> &nearr;
                                    </a>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="revoke('{{ $catalog->id }}')" wire:confirm="{{ __('Revoke this eCommerce storefront link?') }}" type="button" class="text-xs font-bold text-rose-600 hover:underline px-2.5 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                    {{ __('Revoke') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                                {{ __('No eCommerce storefronts launched yet. Use the form above to publish your first online business website!') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fast Inline Product Upload Modal -->
    @if ($showProductModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('Upload & Add New Product') }}</h3>
                    <button type="button" wire:click="closeNewProductModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Product Name *') }}</label>
                        <input type="text" wire:model="newProductName" placeholder="{{ __('e.g. Wireless Bluetooth Barcode Scanner') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                        @error('newProductName') <p class="text-rose-600 text-[11px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Product Description') }}</label>
                        <textarea wire:model="newProductDescription" rows="3" placeholder="{{ __('Detailed features, warranty, specs and details displayed to customers on your eCommerce store...') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500"></textarea>
                        @error('newProductDescription') <p class="text-rose-600 text-[11px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Sale Price ($) *') }}</label>
                            <input type="number" step="0.01" wire:model="newProductPrice" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                            @error('newProductPrice') <p class="text-rose-600 text-[11px] mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Current Stock *') }}</label>
                            <input type="number" step="1" wire:model="newProductStock" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                            @error('newProductStock') <p class="text-rose-600 text-[11px] mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Category') }}</label>
                        <select wire:model="newProductCategory" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
                            <option value="">— {{ __('Select Category') }} —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="closeNewProductModal" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="saveNewProduct" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20">
                        {{ __('Save & Include in Store') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
