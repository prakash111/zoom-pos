@extends('tenants.store.layout')

@section('title', ($company->name ?? 'Storefront') . ' — ' . __('Online Store'))

@section('content')
    <!-- 1. Hero Promotional Showcase Banner (matching store-idea.mp4) -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white p-6 sm:p-12 shadow-xl border border-emerald-800/40">
        <!-- Background decorative ambient circles -->
        <div class="absolute -right-16 -top-16 w-80 h-80 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/4 -bottom-20 w-60 h-60 bg-teal-500/20 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 max-w-2xl space-y-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 backdrop-blur-md uppercase tracking-wider">
                <span>✨</span> {{ __('Special Store Deals') }}
            </span>

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                {{ __('Grab Up To 50% Off On Selected Products') }}
            </h1>

            <p class="text-xs sm:text-sm text-emerald-100/90 font-medium leading-relaxed max-w-xl">
                {{ __('Explore our curated selection of high-quality products. Enjoy seamless online shopping, instant order synchronization with our store POS, and direct WhatsApp updates.') }}
            </p>

            <div class="pt-2 flex flex-wrap items-center gap-3">
                <a href="#products-section"
                   x-on:click="scrollToProducts()"
                   class="px-6 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-500/30 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                    <span>{{ __('Shop Now') }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>

                <a href="#categories-section"
                   class="px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs sm:text-sm backdrop-blur-xs transition">
                    {{ __('Explore Categories') }}
                </a>
            </div>

            <!-- Showcase Value Badges -->
            <div class="pt-4 flex flex-wrap items-center gap-4 text-[11px] font-bold text-emerald-300">
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-400">⭐</span> 4.9/5 {{ __('Rating') }}
                </div>
                <div>&bull;</div>
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-400">🚚</span> {{ __('Fast Dispatch') }}
                </div>
                <div>&bull;</div>
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-400">🔒</span> {{ __('Verified Merchant') }}
                </div>
            </div>
        </div>
    </section>

    <!-- 2. "Shop Our Top Categories" Grid (matching store-idea.mp4) -->
    <section id="categories-section" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    {{ __('Shop Our Top Categories') }}
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    {{ __('Browse products by curated departments') }}
                </p>
            </div>
            <button type="button"
                    x-on:click="selectedCategory = 'all'; scrollToProducts();"
                    class="text-xs font-bold text-emerald-600 hover:text-emerald-700 transition">
                {{ __('View All') }} &rarr;
            </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @forelse ($categories as $index => $cat)
                @php
                    $colors = [
                        ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-100', 'text' => 'text-emerald-700', 'icon' => '📦'],
                        ['bg' => 'bg-teal-50', 'border' => 'border-teal-100', 'text' => 'text-teal-700', 'icon' => '🏷️'],
                        ['bg' => 'bg-cyan-50', 'border' => 'border-cyan-100', 'text' => 'text-cyan-700', 'icon' => '🛍️'],
                        ['bg' => 'bg-sky-50', 'border' => 'border-sky-100', 'text' => 'text-sky-700', 'icon' => '✨'],
                    ];
                    $palette = $colors[$index % count($colors)];
                @endphp
                <button type="button"
                        x-on:click="selectedCategory = {{ json_encode($cat->name) }}; scrollToProducts();"
                        class="p-4 rounded-3xl border text-left transition-all duration-200 group flex items-center justify-between cursor-pointer hover:shadow-md hover:-translate-y-0.5"
                        :class="selectedCategory === {{ json_encode($cat->name) }} ? 'border-emerald-500 bg-emerald-50 shadow-md ring-2 ring-emerald-500/20' : '{{ $palette['bg'] }} {{ $palette['border'] }} hover:bg-white'">
                    <div class="space-y-1">
                        <span class="text-2xl block mb-1">{{ $palette['icon'] }}</span>
                        <h3 class="font-extrabold text-xs sm:text-sm text-slate-800 group-hover:text-emerald-700 transition">
                            {{ $cat->name }}
                        </h3>
                        <span class="text-[11px] font-bold text-slate-400 block">
                            {{ $cat->products_count }} {{ __('Items') }}
                        </span>
                    </div>
                    <div class="w-7 h-7 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400 group-hover:text-emerald-600 group-hover:border-emerald-300 transition">
                        &rarr;
                    </div>
                </button>
            @empty
                <div class="col-span-full py-8 text-center text-xs text-slate-400">
                    {{ __('No specific categories listed. All products available below.') }}
                </div>
            @endforelse
        </div>
    </section>

    <!-- 3. Filter & Sort Bar (matching store-idea.mp4) -->
    <section id="products-section" class="space-y-4 pt-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <!-- Left Filter Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        x-on:click="selectedCategory = 'all'"
                        class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition cursor-pointer"
                        :class="selectedCategory === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'">
                    {{ __('All Products') }} ({{ count($products) }})
                </button>
                @foreach ($categories as $cat)
                    <button type="button"
                            x-on:click="selectedCategory = {{ json_encode($cat->name) }}"
                            class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition cursor-pointer"
                            :class="selectedCategory === {{ json_encode($cat->name) }} ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Right Sort Dropdown -->
            <div class="flex items-center gap-3 self-end md:self-auto shrink-0">
                <label class="text-xs font-bold text-slate-400">{{ __('Sort by:') }}</label>
                <select x-model="sortBy"
                        class="text-xs font-extrabold rounded-xl border-slate-200 bg-white py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                    <option value="featured">{{ __('Featured') }}</option>
                    <option value="price_low">{{ __('Price: Low to High') }}</option>
                    <option value="price_high">{{ __('Price: High to Low') }}</option>
                    <option value="name_asc">{{ __('Alphabetical: A-Z') }}</option>
                </select>
            </div>
        </div>

        <!-- 4. Product Cards Grid (matching store-idea.mp4) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @forelse ($products as $product)
                @php
                    $prodPrice = (float)($product->sale_price ?? $product->price ?? 0);
                    $prodData = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $prodPrice,
                        'sale_price' => $prodPrice,
                        'code' => $product->code,
                        'barcode' => $product->barcode,
                        'category_name' => $product->category_name ?? 'General',
                        'brand_name' => $product->brand_name ?? '',
                        'unit' => $product->unit ?? 'pcs',
                        'current_stock' => (float)$product->current_stock,
                        'description' => $product->description ?? '',
                        'image_url' => $product->image_url ? (str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url)) : '',
                    ];
                @endphp
                <div x-show="matchesFilter({{ json_encode($product->name) }}, {{ json_encode($product->category_name ?? '') }}, {{ json_encode($product->code ?? '') }}, {{ $prodPrice }})"
                     x-data="{ isFavorite: false }"
                     class="bg-white rounded-3xl p-4 sm:p-5 shadow-xs hover:shadow-xl border border-slate-200/70 flex flex-col justify-between transition-all duration-300 group hover:-translate-y-1 relative">
                    
                    <!-- Wishlist Heart Button (Top Right) -->
                    <button type="button"
                            x-on:click.stop="isFavorite = !isFavorite"
                            class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-white/90 backdrop-blur-xs border border-slate-100 flex items-center justify-center text-slate-400 hover:text-rose-500 shadow-xs transition cursor-pointer"
                            :class="isFavorite ? 'text-rose-500 fill-rose-500' : ''"
                            title="{{ __('Add to wishlist') }}">
                        <svg class="w-4 h-4 transition-transform group-hover:scale-110" :fill="isFavorite ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>

                    <!-- Product Image Container -->
                    <div x-on:click="openProductDetail({{ json_encode($prodData) }})"
                         class="w-full aspect-square rounded-2xl bg-slate-50 overflow-hidden mb-3.5 flex items-center justify-center relative cursor-pointer group-hover:bg-emerald-50/20 transition">
                        @if ($product->image_url)
                            <img src="{{ str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url) }}"
                                 alt="{{ $product->name }}"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="text-4xl text-slate-300 group-hover:scale-110 transition">
                                🛍️
                            </div>
                        @endif

                        @if ($product->category_name)
                            <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/90 text-slate-600 shadow-xs backdrop-blur-xs">
                                {{ $product->category_name }}
                            </span>
                        @endif
                    </div>

                    <!-- Details: Price, Name, Description, 5 Green Stars -->
                    <div class="space-y-1.5 w-full flex-1">
                        <!-- Price & Stock pill -->
                        <div class="flex items-baseline justify-between">
                            <span class="text-base sm:text-lg font-black text-slate-900 font-mono tracking-tight">
                                ${{ number_format($prodPrice, 2) }}
                            </span>
                            @if ((float)$product->current_stock <= 5 && (float)$product->current_stock > 0)
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded-md">
                                    {{ (int)$product->current_stock }} {{ __('left') }}
                                </span>
                            @elseif ((float)$product->current_stock > 5)
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-md">
                                    {{ __('In stock') }}
                                </span>
                            @endif
                        </div>

                        <!-- Product Name -->
                        <h3 x-on:click="openProductDetail({{ json_encode($prodData) }})"
                            class="font-extrabold text-xs sm:text-sm text-slate-800 line-clamp-1 group-hover:text-emerald-600 transition cursor-pointer"
                            title="{{ $product->name }}">
                            {{ $product->name }}
                        </h3>

                        @if (trim($product->description ?? ''))
                            <!-- Product Rich Description -->
                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                {{ $product->description }}
                            </p>
                        @endif

                        <!-- 5 Green Stars Rating (★★★★★) with count (121) from store-idea.mp4 -->
                        <div class="flex items-center gap-1.5 pt-1">
                            <div class="flex items-center text-emerald-500 text-xs">
                                ★★★★★
                            </div>
                            <span class="text-[10px] font-bold text-slate-400">
                                ({{ 85 + ($product->id % 60) }})
                            </span>
                        </div>
                    </div>

                    <!-- Add To Cart Pill Button -->
                    <div class="w-full pt-3.5">
                        <template x-if="getItemQty({{ $product->id }}) === 0">
                            <button type="button"
                                    x-on:click="addToCart({{ json_encode($prodData) }})"
                                    class="w-full py-2.5 px-3 rounded-2xl bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white font-extrabold text-xs transition-all duration-150 active:scale-95 flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>{{ __('Add to Cart') }}</span>
                            </button>
                        </template>

                        <template x-if="getItemQty({{ $product->id }}) > 0">
                            <div class="flex items-center justify-between bg-emerald-600 text-white rounded-2xl p-1 shadow-md shadow-emerald-600/20">
                                <button type="button"
                                        x-on:click="decreaseQty({{ $product->id }})"
                                        class="w-7 h-7 rounded-xl bg-white/20 hover:bg-white/30 text-white flex items-center justify-center font-black text-sm active:scale-90 transition cursor-pointer">
                                    -
                                </button>
                                <span class="font-black text-xs px-2" x-text="getItemQty({{ $product->id }})"></span>
                                <button type="button"
                                        x-on:click="increaseQty({{ $product->id }})"
                                        class="w-7 h-7 rounded-xl bg-white/20 hover:bg-white/30 text-white flex items-center justify-center font-black text-sm active:scale-90 transition cursor-pointer">
                                    +
                                </button>
                            </div>
                        </template>
                    </div>

                </div>
            @empty
                <div class="col-span-full py-16 text-center text-slate-400 space-y-2">
                    <div class="text-3xl">🛍️</div>
                    <div class="text-sm font-bold text-slate-600">{{ __('No products currently listed.') }}</div>
                    <p class="text-xs text-slate-400">{{ __('Products uploaded in the tenant POS inventory will appear here automatically.') }}</p>
                </div>
            @endforelse
        </div>
    </section>

    <!-- 5. "Services To Help You Shop" Section (matching store-idea.mp4) -->
    <section id="services-section" class="space-y-4 pt-8">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                {{ __('Services To Help You Shop') }}
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
                {{ __('Everything you need for a comfortable and secure ordering experience') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Card 1: Frequently Asked Questions -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    ❓
                </div>
                <h3 class="font-black text-base text-slate-900">{{ __('Frequently Asked Questions') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    {{ __('Updates on safe shopping in our stores. Need answers regarding payment, sizes, or warranties? Our staff responds swiftly.') }}
                </p>
                <div class="pt-2">
                    <a href="tel:{{ $company->phone }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
                        {{ __('Ask a Question') }} &rarr;
                    </a>
                </div>
            </div>

            <!-- Card 2: Online Payment Process -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
                <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl">
                    💳
                </div>
                <h3 class="font-black text-base text-slate-900">{{ __('Online Payment Process') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    {{ __('Updates on safe and swift online payment. We accept Cash on Delivery (COD), in-store payment, and major digital cards.') }}
                </p>
                <div class="pt-2">
                    <span class="text-xs font-bold text-emerald-600">
                        {{ __('Verified & Encrypted') }} &check;
                    </span>
                </div>
            </div>

            <!-- Card 3: Home Delivery Options -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl">
                    🚚
                </div>
                <h3 class="font-black text-base text-slate-900">{{ __('Home Delivery Options') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    {{ __('Updates on safe home delivery. Orders placed here directly notify the store counter for prompt packaging and driver dispatch.') }}
                </p>
                <div class="pt-2">
                    <span class="text-xs font-bold text-emerald-600">
                        {{ __('Same-Day Dispatch') }} &check;
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Product Detail Modal (matching store-idea.mp4 popup) -->
    <div x-show="detailModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="closeProductDetail()"></div>

        <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-6">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-3xl w-full p-6 sm:p-8 overflow-hidden border border-slate-100"
                 x-transition:enter="transition transform ease-out duration-300"
                 x-transition:enter-start="scale-95 opacity-0"
                 x-transition:enter-end="scale-100 opacity-100">
                
                <!-- Close Button -->
                <button type="button"
                        x-on:click="closeProductDetail()"
                        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center font-bold text-lg transition z-20 cursor-pointer">
                    &times;
                </button>

                <template x-if="modalProduct">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                        <!-- Left: Large Image & Preview -->
                        <div class="space-y-3">
                            <div class="w-full aspect-square rounded-2xl bg-slate-50 border border-slate-100 overflow-hidden flex items-center justify-center">
                                <template x-if="modalProduct.image_url">
                                    <img :src="modalProduct.image_url" :alt="modalProduct.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!modalProduct.image_url">
                                    <span class="text-6xl">🛍️</span>
                                </template>
                            </div>

                            <div class="flex items-center gap-2 text-[11px] text-slate-400 font-bold justify-center">
                                <span>🛡️ {{ __('100% Genuine Guaranteed') }}</span>
                                <span>&bull;</span>
                                <span>⚡ {{ __('Fast Handling') }}</span>
                            </div>
                        </div>

                        <!-- Right: Info, Price, Description, Specs, Buy Now -->
                        <div class="space-y-4 flex flex-col justify-between">
                            <div class="space-y-2">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider"
                                      x-text="modalProduct.category_name"></span>

                                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight" x-text="modalProduct.name"></h2>

                                <!-- 5 Stars + Review count -->
                                <div class="flex items-center gap-2 text-xs">
                                    <div class="text-emerald-500 font-bold">★★★★★</div>
                                    <span class="text-slate-400 font-medium">({{ __('121 customer reviews') }})</span>
                                </div>

                                <!-- Price -->
                                <div class="flex items-baseline gap-2 pt-1">
                                    <span class="text-2xl font-black text-slate-900 font-mono">
                                        $<span x-text="modalProduct.sale_price.toFixed(2)"></span>
                                    </span>
                                    <span class="text-xs text-slate-400 line-through font-mono">
                                        $<span x-text="(modalProduct.sale_price * 1.25).toFixed(2)"></span>
                                    </span>
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-md">
                                        {{ __('20% OFF') }}
                                    </span>
                                </div>

                                <!-- Full Description (Only when populated) -->
                                <template x-if="modalProduct.description && modalProduct.description.trim()">
                                    <div class="text-xs text-slate-600 leading-relaxed pt-1" x-text="modalProduct.description"></div>
                                </template>

                                <!-- Specifications Key-Value Table -->
                                <div class="border-t border-slate-100 pt-3 space-y-1.5 text-xs">
                                    <div class="flex justify-between py-1 border-b border-slate-50">
                                        <span class="text-slate-400 font-medium">{{ __('SKU / Code:') }}</span>
                                        <span class="font-mono font-bold text-slate-700" x-text="modalProduct.code || 'N/A'"></span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-50">
                                        <span class="text-slate-400 font-medium">{{ __('Unit:') }}</span>
                                        <span class="font-bold text-slate-700" x-text="modalProduct.unit"></span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-50">
                                        <span class="text-slate-400 font-medium">{{ __('Availability:') }}</span>
                                        <span class="font-bold text-emerald-600">
                                            {{ __('In Stock') }} (<span x-text="parseInt(modalProduct.current_stock)"></span> {{ __('available') }})
                                        </span>
                                    </div>
                                </div>

                                <!-- Scarcity Alert -->
                                <div class="bg-amber-50 border border-amber-200/60 rounded-xl p-2.5 text-xs text-amber-800 flex items-center gap-2">
                                    <span>🔥</span>
                                    <span class="font-semibold">
                                        {{ __('Only') }} <span class="font-bold" x-text="parseInt(modalProduct.current_stock)"></span> {{ __("Items Left! Don't miss it") }}
                                    </span>
                                </div>
                            </div>

                            <!-- Stepper & Action Buttons -->
                            <div class="space-y-3 pt-3">
                                <div class="flex items-center gap-3">
                                    <!-- Stepper -->
                                    <div class="flex items-center border border-slate-200 rounded-xl p-1 bg-slate-50">
                                        <button type="button"
                                                x-on:click="if (modalQty > 1) modalQty--"
                                                class="w-8 h-8 rounded-lg bg-white text-slate-700 font-black text-sm flex items-center justify-center hover:bg-emerald-50 transition cursor-pointer">
                                            -
                                        </button>
                                        <span class="font-bold text-xs px-3" x-text="modalQty"></span>
                                        <button type="button"
                                                x-on:click="modalQty++"
                                                class="w-8 h-8 rounded-lg bg-white text-slate-700 font-black text-sm flex items-center justify-center hover:bg-emerald-50 transition cursor-pointer">
                                            +
                                        </button>
                                    </div>

                                    <!-- Add to Cart Button -->
                                    <button type="button"
                                            x-on:click="addToCart(modalProduct, modalQty); closeProductDetail();"
                                            class="flex-1 py-3 rounded-xl bg-slate-100 hover:bg-emerald-50 text-slate-800 hover:text-emerald-700 font-extrabold text-xs transition cursor-pointer">
                                        {{ __('Add to Cart') }}
                                    </button>
                                </div>

                                <!-- Buy Now Button -->
                                <button type="button"
                                        x-on:click="buyNow(modalProduct)"
                                        class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs tracking-wide shadow-lg shadow-emerald-600/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>{{ __('Buy Now') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </div>
@endsection
