<div class="h-[calc(100vh-5rem)] flex flex-col antialiased overflow-hidden font-sans space-y-3"
     x-data
     x-on:item-added-to-cart.window="playAddToCartBeep()">

    <!-- Top Layout Switcher & Global Status Bar -->
    <div class="flex items-center justify-between shrink-0 px-1">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xs sm:text-sm font-black text-slate-800 dark:text-slate-100 tracking-tight">Order No.</span>
                <span class="text-xs sm:text-sm font-mono font-extrabold text-blue-600 dark:text-blue-400">#{{ $orderNumber }}</span>
            </div>

            <!-- Layout Mode Switcher Pills -->
            <div class="hidden sm:flex items-center gap-1 bg-slate-200/80 dark:bg-slate-800 p-1 rounded-2xl">
                <button type="button"
                        wire:click="switchLayout('standard')"
                        @class([
                            'px-3 py-1.5 rounded-xl text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer',
                            'bg-white dark:bg-slate-700 text-blue-600 dark:text-white shadow-xs' => $layout === 'standard',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $layout !== 'standard',
                        ])>
                    <span>🛒 Standard Scanner</span>
                </button>

                <button type="button"
                        wire:click="switchLayout('touch')"
                        @class([
                            'px-3 py-1.5 rounded-xl text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer',
                            'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' => $layout === 'touch',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $layout !== 'touch',
                        ])>
                    <span>🏪 Supermarket Touch</span>
                </button>

                <button type="button"
                        wire:click="switchLayout('stand')"
                        @class([
                            'px-3 py-1.5 rounded-xl text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer',
                            'bg-white dark:bg-slate-700 text-purple-600 dark:text-purple-400 shadow-xs' => $layout === 'stand',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' => $layout !== 'stand',
                        ])>
                    <span>📱 Square Stand</span>
                </button>
            </div>
        </div>

        <!-- Right: Mobile Switcher, Fullscreen Toggle & Live Clock -->
        <div class="flex items-center gap-2.5">
            <div class="sm:hidden">
                <select wire:model.live="layout" class="text-xs rounded-xl bg-white dark:bg-slate-800 border-none font-bold py-1 px-2.5">
                    <option value="standard">🛒 Standard</option>
                    <option value="touch">🏪 Touch POS</option>
                    <option value="stand">📱 Square Stand</option>
                </select>
            </div>

            <!-- Fullscreen Zoom Toggle Button -->
            <button type="button"
                    x-on:click="$store.fullscreen.toggle()"
                    class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    title="Toggle Fullscreen Mode">
                <template x-if="!$store.fullscreen.active">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                        <span class="hidden sm:inline">Fullscreen</span>
                    </span>
                </template>
                <template x-if="$store.fullscreen.active">
                    <span class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <span class="hidden sm:inline">Exit Fullscreen</span>
                    </span>
                </template>
            </button>

            <!-- Live Clock -->
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5"
                 x-data="{ time: '' }"
                 x-init="
                    const update = () => {
                        const now = new Date();
                        time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                    };
                    update();
                    setInterval(update, 1000);
                 ">
                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span x-text="time"></span>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- LAYOUT 1: STANDARD RETAIL SCANNER POS (3-Column Items Grid + Side Cart)  -->
    <!-- ========================================================================= -->
    @if ($layout === 'standard')
        <div class="flex-1 flex flex-col lg:flex-row gap-4 sm:gap-5 min-h-0 overflow-hidden">
            
            <!-- Left: Products Browser Area -->
            <div class="flex-1 flex flex-col min-h-0 bg-slate-100 dark:bg-slate-900/60 p-4 sm:p-5 rounded-3xl space-y-3.5 overflow-hidden">
                
                <!-- Search Bar with Count Indicator -->
                <div class="relative shrink-0">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.250ms="search"
                           placeholder="Scan barcode or type product name / SKU..."
                           class="w-full pl-11 pr-24 py-3 bg-white dark:bg-slate-800 rounded-2xl border-none shadow-xs text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-slate-100 placeholder-slate-400">
                    
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500">
                            {{ count($products) }} of {{ $totalProductsCount }}
                        </span>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1 shrink-0">
                    <button type="button"
                            wire:click="selectCategory(null)"
                            @class([
                                'px-4 py-2 rounded-2xl text-xs font-extrabold whitespace-nowrap transition-all shadow-xs active:scale-95 cursor-pointer',
                                'bg-blue-600 text-white shadow-blue-500/25' => is_null($selectedCategoryId),
                                'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50' => !is_null($selectedCategoryId),
                            ])>
                        All Items
                    </button>

                    @foreach ($categories as $category)
                        <button type="button"
                                wire:click="selectCategory({{ $category->id }})"
                                @class([
                                    'px-4 py-2 rounded-2xl text-xs font-extrabold whitespace-nowrap transition-all shadow-xs active:scale-95 cursor-pointer',
                                    'bg-blue-600 text-white shadow-blue-500/25' => $selectedCategoryId === $category->id,
                                    'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50' => $selectedCategoryId !== $category->id,
                                ])>
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                <!-- Product Grid: 3 items per row on desktop -->
                <div class="flex-1 min-h-0 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-3 gap-3.5 sm:gap-4 content-start">
                    @forelse ($products as $product)
                        <button type="button"
                                wire:click="addProductToCart({{ $product->id }})"
                                class="bg-white dark:bg-slate-800/90 rounded-3xl p-3 sm:p-3.5 shadow-sm hover:shadow-xl hover:-translate-y-0.5 border border-slate-100 dark:border-slate-800/80 flex flex-col justify-between text-left transition-all group active:scale-95 duration-150 relative overflow-hidden min-h-[190px] sm:min-h-[210px] cursor-pointer">
                            
                            <!-- Product Photo / Thumbnail -->
                            <div class="relative w-full h-32 sm:h-36 bg-slate-100 dark:bg-slate-700/50 rounded-2xl overflow-hidden mb-2.5 shrink-0 flex items-center justify-center">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <x-pos-product-icon :name="$product->name" size="md" />
                                @endif

                                @if ($product->current_stock <= ($product->minimum_stock ?? 0))
                                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black bg-rose-500 text-white shadow-sm">
                                        Low Stock
                                    </span>
                                @endif

                                <span class="absolute bottom-2 right-2 px-2.5 py-0.5 rounded-full text-xs font-black bg-black/80 text-white backdrop-blur-xs">
                                    {{ $company->formatMoney($product->sale_price) }}
                                </span>
                            </div>

                            <!-- Product Details -->
                            <div class="w-full shrink-0">
                                <div class="font-extrabold text-xs sm:text-sm text-slate-800 dark:text-slate-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $product->name }}
                                </div>
                                <div class="flex items-center justify-between mt-1 text-[11px] text-slate-400 font-semibold">
                                    <span class="truncate">{{ $product->category_name ?? 'General' }}</span>
                                    <span class="shrink-0 text-slate-500 dark:text-slate-400 font-mono">{{ (int)$product->current_stock }} in stock</span>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-16 text-center text-slate-400 dark:text-slate-500">
                            <span class="text-4xl block mb-2">🔍</span>
                            No items found matching your search.
                        </div>
                    @endforelse
                </div>

            </div>

            <!-- Right: Standard Side Cart -->
            @include('livewire.tenant.sales.partials.standard-cart')

        </div>

    <!-- ========================================================================= -->
    <!-- LAYOUT 2: SUPERMARKET TOUCH DEPARTMENT POS (Matching Bi_119818936.png)     -->
    <!-- ========================================================================= -->
    @elseif ($layout === 'touch')
        <div class="flex-1 flex flex-col lg:flex-row gap-3 min-h-0 overflow-hidden bg-slate-900/40 p-2 sm:p-3 rounded-3xl border border-slate-800/80">
            
            <!-- Left: Vertical Function Toolbar matching screenshot -->
            <div class="w-24 sm:w-28 shrink-0 flex flex-col gap-2 p-1 bg-slate-850 dark:bg-slate-950/80 rounded-2xl border border-slate-800 select-none">
                <button type="button" wire:click="selectCategory(null)" class="w-full py-3 px-1 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer">
                    <span class="text-lg">📁</span>
                    <span>Category</span>
                </button>

                <button type="button" wire:click="openCustomerSelectModal" class="w-full py-3 px-1 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer">
                    <span class="text-lg">👤</span>
                    <span>Membership</span>
                </button>

                <button type="button" x-on:click="$refs.touchSearch.focus()" class="w-full py-3 px-1 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer">
                    <span class="text-lg">🏷️</span>
                    <span>SKU Scan</span>
                </button>

                <button type="button" wire:click="clearCart" class="w-full py-3 px-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer">
                    <span class="text-lg">📊</span>
                    <span>Clear Cart</span>
                </button>

                <a href="{{ route('tenant.sales.index') }}" class="w-full py-3 px-1 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer text-center">
                    <span class="text-lg">🧾</span>
                    <span>Invoice</span>
                </a>

                <a href="{{ route('tenant.sales.index') }}" class="w-full py-3 px-1 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer text-center">
                    <span class="text-lg">⏱️</span>
                    <span>History</span>
                </a>

                <a href="{{ route('tenant.settings.index') }}" class="w-full py-3 px-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] flex flex-col items-center gap-1 shadow-md transition active:scale-95 cursor-pointer mt-auto text-center">
                    <span class="text-lg">⚙️</span>
                    <span>Setting</span>
                </a>
            </div>

            <!-- Center: Vibrant Department / Category Touch Tiles & Products -->
            <div class="flex-1 flex flex-col min-h-0 bg-slate-100 dark:bg-slate-900 rounded-2xl p-4 space-y-3 overflow-hidden">
                
                <!-- Quick SKU / Search Bar -->
                <div class="flex items-center gap-2">
                    <input type="text"
                           x-ref="touchSearch"
                           wire:model.live.debounce.250ms="search"
                           placeholder="Type product name, SKU or barcode scan..."
                           class="flex-1 py-2.5 px-4 bg-white dark:bg-slate-800 rounded-xl border-none shadow-xs text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-amber-500">
                    
                    @if ($selectedCategoryId)
                        <button type="button" wire:click="selectCategory(null)" class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-xs font-bold hover:bg-slate-300">
                            ✕ Clear Filter
                        </button>
                    @endif
                </div>

                <!-- Colorful Department Tiles Grid matching Bi_119818936.png -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 shrink-0">
                    @php
                        $deptColors = [
                            ['bg' => 'bg-amber-500', 'hover' => 'hover:bg-amber-600'],
                            ['bg' => 'bg-orange-500', 'hover' => 'hover:bg-orange-600'],
                            ['bg' => 'bg-amber-600', 'hover' => 'hover:bg-amber-700'],
                            ['bg' => 'bg-lime-600', 'hover' => 'hover:bg-lime-700'],
                            ['bg' => 'bg-emerald-600', 'hover' => 'hover:bg-emerald-700'],
                            ['bg' => 'bg-green-600', 'hover' => 'hover:bg-green-700'],
                            ['bg' => 'bg-blue-600', 'hover' => 'hover:bg-blue-700'],
                            ['bg' => 'bg-indigo-600', 'hover' => 'hover:bg-indigo-700'],
                        ];
                    @endphp

                    @foreach ($categories->take(6) as $idx => $cat)
                        @php $cStyle = $deptColors[$idx % count($deptColors)]; @endphp
                        <button type="button"
                                wire:click="selectCategory({{ $cat->id }})"
                                class="{{ $cStyle['bg'] }} {{ $cStyle['hover'] }} text-white p-3.5 rounded-2xl shadow-md text-center flex flex-col items-center justify-center min-h-[70px] transition active:scale-95 cursor-pointer relative overflow-hidden">
                            <span class="text-xs sm:text-sm font-black tracking-tight leading-tight">{{ $cat->name }}</span>
                            @if ($selectedCategoryId === $cat->id)
                                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-white animate-ping"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <!-- Products Touch Grid -->
                <div class="flex-1 min-h-0 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 content-start">
                    @forelse ($products as $product)
                        <button type="button"
                                wire:click="addProductToCart({{ $product->id }})"
                                class="bg-white dark:bg-slate-800 p-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs hover:shadow-lg hover:border-amber-400 flex flex-col justify-between text-left transition active:scale-95 cursor-pointer">
                            <div>
                                <div class="font-extrabold text-xs text-slate-900 dark:text-white truncate">{{ $product->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $product->code ?: 'SKU-'.$product->id }}</div>
                            </div>
                            <div class="flex items-center justify-between mt-2 pt-1 border-t border-slate-100 dark:border-slate-700/60">
                                <span class="text-xs font-black text-amber-600 dark:text-amber-400">{{ $company->formatMoney($product->sale_price) }}</span>
                                <span class="text-[10px] font-bold text-slate-400">{{ (int)$product->current_stock }} pcs</span>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-10 text-center text-slate-400">No products available in this department.</div>
                    @endforelse
                </div>

                <!-- Pagination Footer matching screenshot < 1 2 3 4 5 > -->
                <div class="flex items-center justify-center gap-3 pt-2 border-t border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-500">
                    <button type="button" class="px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300">&lt;</button>
                    <span class="px-2 py-1 rounded-lg bg-amber-500 text-white font-black">1</span>
                    <span class="px-2 py-1">2</span>
                    <span class="px-2 py-1">3</span>
                    <span class="px-2 py-1">4</span>
                    <span class="px-2 py-1">5</span>
                    <button type="button" class="px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300">&gt;</button>
                </div>

            </div>

            <!-- Right: Itemized Receipt Slip matching Bi_119818936.png -->
            <div class="w-full lg:w-96 bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 flex flex-col justify-between shadow-xl border border-slate-200 dark:border-slate-800 min-h-0">
                
                <div class="space-y-3 flex-1 flex flex-col min-h-0">
                    
                    <!-- Receipt Header with Interactive Customer Selector -->
                    <div class="border-b-2 border-dashed border-slate-200 dark:border-slate-700 pb-3 text-[11px] space-y-1.5 text-slate-600 dark:text-slate-300 font-mono">
                        <div class="flex items-center justify-between font-bold">
                            <button type="button"
                                    wire:click="openCustomerSelectModal"
                                    class="text-left font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 flex items-center gap-1 cursor-pointer">
                                <span>Cust: {{ $this->selectedCustomer?->name ?: 'Walk-in Member' }}</span>
                                <span class="text-[10px] text-blue-500 font-sans font-normal">(Change)</span>
                            </button>
                            <span>{{ now()->format('d-m-Y') }}</span>
                        </div>

                        <div class="flex items-center justify-between text-slate-400">
                            <span class="flex items-center gap-1.5">
                                <span>Member: {{ $this->selectedCustomer?->id ? sprintf('MEM-%05d', $this->selectedCustomer->id) : '330024' }}</span>
                                @if ($this->selectedCustomer)
                                    <button type="button" wire:click="clearSelectedCustomer" class="text-rose-500 hover:underline text-[10px] font-sans">[✕]</button>
                                @endif
                            </span>
                            <span>Time: {{ now()->format('H:i') }}</span>
                        </div>
                        <div class="text-slate-400">Inv: #{{ $orderNumber }}</div>
                    </div>

                    <!-- Receipt Items Table Header -->
                    <div class="grid grid-cols-12 gap-1 text-[10px] font-black uppercase text-slate-400 border-b pb-1 font-mono">
                        <span class="col-span-5">Product</span>
                        <span class="col-span-2 text-center">Qty</span>
                        <span class="col-span-2 text-right">Price</span>
                        <span class="col-span-3 text-right">Total</span>
                    </div>

                    <!-- Receipt Items List -->
                    <div class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 font-mono text-xs">
                        @forelse ($items as $idx => $it)
                            @if (!empty($it['product_id']) || !empty($it['name']))
                                <div class="grid grid-cols-12 gap-1 items-center hover:bg-slate-50 dark:hover:bg-slate-800/50 p-1 rounded-lg">
                                    <div class="col-span-5 truncate font-bold text-slate-800 dark:text-white">
                                        {{ $it['name'] ?: 'Item' }}
                                    </div>
                                    <div class="col-span-2 text-center flex items-center justify-center gap-1">
                                        <button type="button" wire:click="decreaseQuantity({{ $idx }})" class="text-slate-400 hover:text-rose-500 font-black">-</button>
                                        <span class="font-black">{{ (int)$it['quantity'] }}</span>
                                        <button type="button" wire:click="increaseQuantity({{ $idx }})" class="text-slate-400 hover:text-blue-500 font-black">+</button>
                                    </div>
                                    <div class="col-span-2 text-right text-slate-500">
                                        @if ($this->canOverridePrice)
                                            <input type="number" min="0" step="0.01" value="{{ $it['price'] }}"
                                                   wire:change="applyPriceOverride({{ $idx }}, $event.target.value)"
                                                   class="w-16 py-0 px-1 text-right text-[11px] font-bold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"
                                                   title="Override unit price">
                                        @else
                                            {{ $company->formatMoney($it['price']) }}
                                        @endif
                                    </div>
                                    <div class="col-span-3 text-right font-black text-slate-900 dark:text-white">
                                        {{ $company->formatMoney((float)$it['quantity'] * (float)$it['price']) }}
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="py-8 text-center text-slate-400 text-xs font-sans">No items added to receipt yet.</div>
                        @endforelse
                    </div>

                    <!-- Receipt Totals -->
                    <div class="border-t-2 border-dashed border-slate-200 dark:border-slate-700 pt-3 space-y-1.5 font-mono text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>subtotal</span>
                            <span>{{ $company->formatMoney($this->subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>tax ({{ $taxPercent }}%)</span>
                            <span>{{ $company->formatMoney($this->taxAmount) }}</span>
                        </div>
                        @if ($this->discount > 0)
                            <div class="flex justify-between text-rose-500 font-bold">
                                <span>discount</span>
                                <span>-{{ $company->formatMoney($this->discount) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-1 border-t">
                            <span>total</span>
                            <span class="text-lg text-emerald-600 dark:text-emerald-400">{{ $company->formatMoney($this->total) }}</span>
                        </div>
                    </div>

                    <!-- Payment Methods Selector for Supermarket Touch -->
                    <div class="pt-3 border-t border-dashed border-slate-200 dark:border-slate-700">
                        <div class="flex items-center justify-between mb-1.5 font-mono text-[10px] uppercase font-bold text-slate-400">
                            <span>Pay Method:</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-extrabold uppercase">{{ $paymentMethod }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            @foreach ($paymentMethods as $pm)
                                <button type="button"
                                        wire:click="$set('paymentMethod', '{{ $pm->code }}')"
                                        @class([
                                            'py-2 px-1 rounded-xl text-[11px] font-mono font-bold text-center transition capitalize cursor-pointer',
                                            'bg-emerald-600 text-white shadow-xs' => $paymentMethod === $pm->code,
                                            'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' => $paymentMethod !== $pm->code,
                                        ])>
                                    {{ $pm->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Dual Action Buttons: Red BACK + Green CHECK OUT matching Bi_119818936.png -->
                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button"
                            wire:click="clearCart"
                            class="py-3.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-rose-500/20 active:scale-95 transition cursor-pointer">
                        BACK / CLEAR
                    </button>

                    <button type="button"
                            wire:click="openCheckoutModal"
                            wire:loading.attr="disabled"
                            class="py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/25 active:scale-95 transition cursor-pointer flex items-center justify-center gap-1.5">
                        <span wire:loading.remove>CHECK OUT</span>
                        <span wire:loading>Processing...</span>
                    </button>
                </div>

            </div>

        </div>

    <!-- ========================================================================= -->
    <!-- LAYOUT 3: SQUARE STAND MODERN REGISTER (Matching PD07328-hardware-stand)   -->
    <!-- ========================================================================= -->
    @elseif ($layout === 'stand')
        <div class="flex-1 flex flex-col lg:flex-row gap-4 min-h-0 overflow-hidden bg-slate-100 dark:bg-slate-950 p-2 sm:p-3 rounded-3xl">
            
            <!-- Left / Center: Stand Tabs & Photo Tiles Grid -->
            <div class="flex-1 flex flex-col min-h-0 bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 overflow-hidden">
                
                <!-- Top Tabs matching PD07328-hardware-stand.png (Favorites | Library | Keypad | Discounts) -->
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2 shrink-0">
                    <div class="flex items-center gap-1 sm:gap-3">
                        <button type="button"
                                wire:click="setActiveStandTab('favorites')"
                                @class([
                                    'px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                    'text-slate-900 dark:text-white border-b-2 border-blue-600 font-black' => $activeStandTab === 'favorites',
                                    'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $activeStandTab !== 'favorites',
                                ])>
                            Favorites
                        </button>

                        <button type="button"
                                wire:click="setActiveStandTab('library')"
                                @class([
                                    'px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                    'text-slate-900 dark:text-white border-b-2 border-blue-600 font-black' => $activeStandTab === 'library',
                                    'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $activeStandTab !== 'library',
                                ])>
                            Library
                        </button>

                        <button type="button"
                                wire:click="setActiveStandTab('keypad')"
                                @class([
                                    'px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                    'text-slate-900 dark:text-white border-b-2 border-blue-600 font-black' => $activeStandTab === 'keypad',
                                    'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $activeStandTab !== 'keypad',
                                ])>
                            Keypad
                        </button>

                        <button type="button"
                                wire:click="setActiveStandTab('discounts')"
                                @class([
                                    'px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                                    'text-slate-900 dark:text-white border-b-2 border-blue-600 font-black' => $activeStandTab === 'discounts',
                                    'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $activeStandTab !== 'discounts',
                                ])>
                            Discounts
                        </button>
                    </div>

                    <!-- Search Input in Library tab -->
                    @if ($activeStandTab === 'library' || $activeStandTab === 'favorites')
                        <input type="text"
                               wire:model.live.debounce.250ms="search"
                               placeholder="Search items..."
                               class="py-1.5 px-3 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 w-36 sm:w-48 text-slate-900 dark:text-white">
                    @endif
                </div>

                <!-- KEYPAD VIEW -->
                @if ($activeStandTab === 'keypad')
                    <div class="flex-1 flex flex-col items-center justify-center max-w-sm mx-auto w-full space-y-4">
                        <div class="w-full text-center py-4 bg-slate-100 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
                            <span class="text-xs text-slate-400 block font-bold">Custom Charge Amount</span>
                            <span class="text-3xl font-black text-slate-900 dark:text-white font-mono">
                                ${{ $keypadAmount ?: '0.00' }}
                            </span>
                        </div>

                        <!-- Numeric Grid -->
                        <div class="grid grid-cols-3 gap-3 w-full">
                            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'C'] as $k)
                                @if ($k === 'C')
                                    <button type="button" wire:click="clearKeypad" class="py-4 rounded-2xl bg-rose-100 text-rose-700 font-black text-lg hover:bg-rose-200 transition active:scale-95">C</button>
                                @else
                                    <button type="button" wire:click="appendKeypad('{{ $k }}')" class="py-4 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-black text-lg transition active:scale-95 shadow-2xs border border-slate-200 dark:border-slate-700">{{ $k }}</button>
                                @endif
                            @endforeach
                        </div>

                        <button type="button" wire:click="addCustomKeypadItem" class="w-full py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-sm shadow-md transition active:scale-98">
                            Add to Sale +
                        </button>
                    </div>

                <!-- DISCOUNTS VIEW -->
                @elseif ($activeStandTab === 'discounts')
                    <div class="space-y-4">
                        <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Preset Quick Discounts</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ([5 => '5% Staff Discount', 10 => '10% Happy Hour', 15 => '15% Member VIP', 20 => '20% Promo Clearance'] as $pct => $dLabel)
                                <button type="button"
                                        wire:click="applyQuickDiscount({{ $pct }}, true)"
                                        class="p-5 rounded-2xl border border-blue-200 dark:border-blue-800/80 bg-blue-50/50 dark:bg-blue-950/40 hover:bg-blue-100 text-blue-900 dark:text-blue-200 text-center font-bold text-xs space-y-1 transition active:scale-95 cursor-pointer shadow-xs">
                                    <span class="text-2xl block font-black text-blue-600 dark:text-blue-400">{{ $pct }}%</span>
                                    <span>{{ $dLabel }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                <!-- FAVORITES & LIBRARY PHOTO GRID (Matching PD07328-hardware-stand.png) -->
                @else
                    <!-- Photo-First Square Tiles Grid -->
                    <div class="flex-1 min-h-0 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-4 gap-3.5 content-start">
                        @forelse ($products as $product)
                            <button type="button"
                                    wire:click="addProductToCart({{ $product->id }})"
                                    class="bg-white dark:bg-slate-800/90 rounded-2xl p-3 border border-slate-200/90 dark:border-slate-700/80 flex flex-col justify-between hover:shadow-md hover:border-blue-500 transition group active:scale-95 cursor-pointer shadow-2xs">
                                
                                <div class="w-full h-28 bg-slate-50 dark:bg-slate-700/50 rounded-xl overflow-hidden mb-2 flex items-center justify-center">
                                    @if ($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                    @else
                                        <x-pos-product-icon :name="$product->name" size="md" />
                                    @endif
                                </div>

                                <div class="w-full text-center">
                                    <div class="font-extrabold text-xs text-slate-800 dark:text-slate-100 truncate">{{ $product->name }}</div>
                                    <div class="text-xs font-black text-blue-600 dark:text-blue-400 mt-1">
                                        {{ $company->formatMoney($product->sale_price) }}
                                    </div>
                                </div>
                            </button>
                        @empty
                            <div class="col-span-full py-12 text-center text-slate-400">No products available in catalog.</div>
                        @endforelse
                    </div>

                    <!-- Bottom Quick Action Tiles matching PD07328-hardware-stand.png -->
                    <div class="grid grid-cols-3 gap-3 pt-2 shrink-0 border-t border-slate-200 dark:border-slate-800">
                        <button type="button"
                                wire:click="setActiveStandTab('discounts')"
                                class="py-3 px-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-2xs">
                            <span>🏷️ Discounts</span>
                        </button>
                        
                        <button type="button"
                                wire:click="openCustomerSelectModal"
                                class="py-3 px-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-2xs">
                            <span>👤 Customer & Rewards</span>
                        </button>

                        <button type="button"
                                wire:click="setActiveStandTab('keypad')"
                                class="py-3 px-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-2xs">
                            <span>🔢 Keypad Entry</span>
                        </button>
                    </div>
                @endif

            </div>

            <!-- Right: Stand Fast-Charge Summary Panel matching PD07328-hardware-stand.png -->
            <div class="w-full lg:w-96 bg-white dark:bg-slate-900 rounded-3xl p-5 flex flex-col justify-between border border-slate-200 dark:border-slate-800 min-h-0 shadow-md">
                
                <div class="space-y-4 flex-1 flex flex-col min-h-0">
                    
                    <!-- Current Sale Header & Customer Selection Chip -->
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <span class="font-black text-sm text-slate-900 dark:text-white">Current sale ({{ count($items) }})</span>
                        
                        <!-- Customer Chip with Selector Trigger -->
                        <div class="flex items-center gap-1">
                            <button type="button"
                                    wire:click="openCustomerSelectModal"
                                    class="px-3.5 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 flex items-center gap-1.5 transition cursor-pointer shadow-2xs">
                                <span>👤 {{ $this->selectedCustomer?->name ?: 'Add Customer' }}</span>
                                <span class="text-slate-400">&gt;</span>
                            </button>

                            @if ($this->selectedCustomer)
                                <button type="button" wire:click="clearSelectedCustomer" class="p-1 text-slate-400 hover:text-rose-500 text-xs font-black" title="Unassign Customer">✕</button>
                            @endif
                        </div>
                    </div>

                    <!-- Itemized Order Rows -->
                    <div class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 text-xs">
                        @forelse ($items as $idx => $it)
                            @if (!empty($it['product_id']) || !empty($it['name']))
                                <div class="flex items-center justify-between py-2 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50">
                                    <div class="space-y-0.5 min-w-0 flex-1 pr-2">
                                        <div class="font-extrabold text-slate-900 dark:text-white truncate">{{ $it['name'] ?: 'Item' }}</div>
                                        <div class="text-[11px] text-slate-400 flex items-center gap-1">
                                            <span>Qty: {{ (int)$it['quantity'] }} &bull;</span>
                                            @if ($this->canOverridePrice)
                                                <span>$</span>
                                                <input type="number" min="0" step="0.01" value="{{ $it['price'] }}"
                                                       wire:change="applyPriceOverride({{ $idx }}, $event.target.value)"
                                                       class="w-14 py-0 px-1 text-[10px] font-bold rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"
                                                       title="Override unit price">
                                                <span>each</span>
                                            @else
                                                <span>{{ $company->formatMoney($it['price']) }} each</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="font-black text-slate-900 dark:text-white">{{ $company->formatMoney((float)$it['quantity'] * (float)$it['price']) }}</div>
                                        <button type="button" wire:click="removeItem({{ $idx }})" class="text-[10px] text-rose-500 hover:underline font-semibold cursor-pointer">Remove</button>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="py-12 text-center text-slate-400 text-xs">Your sale is currently empty. Tap items to add.</div>
                        @endforelse
                    </div>

                    <!-- Subtotal, Tax & Discounts Breakdown -->
                    <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="text-slate-900 dark:text-white font-bold">{{ $company->formatMoney($this->subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax ({{ $taxPercent }}%)</span>
                            <span class="text-slate-900 dark:text-white font-bold">{{ $company->formatMoney($this->taxAmount) }}</span>
                        </div>
                        @if ($this->discount > 0)
                            <div class="flex justify-between text-rose-500 font-bold">
                                <span>Discount</span>
                                <span>-{{ $company->formatMoney($this->discount) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Payment Method Selector for Square Stand -->
                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-1.5 text-xs font-extrabold text-slate-700 dark:text-slate-300">
                            <span>Payment Method</span>
                            <span class="text-blue-600 dark:text-blue-400 font-black capitalize">{{ $paymentMethod }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            @foreach ($paymentMethods as $pm)
                                <button type="button"
                                        wire:click="$set('paymentMethod', '{{ $pm->code }}')"
                                        @class([
                                            'py-2 px-1 rounded-xl text-[11px] font-bold text-center transition capitalize cursor-pointer border',
                                            'bg-blue-600 text-white border-blue-600 shadow-sm' => $paymentMethod === $pm->code,
                                            'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700' => $paymentMethod !== $pm->code,
                                        ])>
                                    {{ $pm->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Prominent Solid Blue Charge Button matching PD07328-hardware-stand.png -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button"
                            wire:click="openCheckoutModal"
                            wire:loading.attr="disabled"
                            class="w-full py-4 rounded-2xl bg-[#006aff] hover:bg-[#0055d6] text-white font-black text-base shadow-xl shadow-blue-500/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                        <span wire:loading.remove>Charge {{ $company->formatMoney($this->total) }} ({{ ucfirst($paymentMethod) }})</span>
                        <span wire:loading>Processing Charge...</span>
                    </button>
                </div>

            </div>

        </div>
    @endif

    <!-- Customer Selection & Search Modal -->
    @include('livewire.tenant.sales.partials.customer-select-modal')

    <!-- Quick Customer Creation Modal -->
    @include('livewire.tenant.sales.partials.quick-customer-modal')

    <!-- POS Checkout & Split Payment Modal -->
    @include('livewire.tenant.sales.partials.checkout-modal')

    <!-- Post-Sale Receipt & Share Popup Modal -->
    @include('livewire.tenant.sales.partials.sale-success-modal')

</div>
