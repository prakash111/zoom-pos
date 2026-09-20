@extends('tenants.store.layout')

@section('title', ($company->name ?? 'Storefront') . ' — ' . __('Online Store'))

@section('content')
    <!-- 1. Hero Promotional Showcase Banner (Tenant Controlled) -->
    @php
        $banner = $company->getStoreBanner();
    @endphp
    @if ($banner['is_active'])
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white p-6 sm:p-12 shadow-xl border border-emerald-800/40">
        <!-- Background decorative ambient circles -->
        <div class="absolute -right-16 -top-16 w-80 h-80 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/4 -bottom-20 w-60 h-60 bg-teal-500/20 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 max-w-2xl space-y-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 backdrop-blur-md uppercase tracking-wider">
                <span>✨</span> {{ $banner['tag'] }}
            </span>

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                {{ $banner['title'] }}
            </h1>

            <p class="text-xs sm:text-sm text-emerald-100/90 font-medium leading-relaxed max-w-xl">
                {{ $banner['subtitle'] }}
            </p>

            <div class="pt-2 flex flex-wrap items-center gap-3">
                <a href="{{ $banner['cta_link'] ?: '#products-section' }}"
                   @if (str_starts_with($banner['cta_link'] ?? '', '#')) x-on:click="scrollToProducts()" @endif
                   class="px-6 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-500/30 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                    <span>{{ $banner['cta_text'] ?: __('Shop Now') }}</span>
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

        @if (!empty($banner['image_url']))
            <div class="hidden sm:block absolute right-0 top-0 bottom-0 w-1/3 opacity-40 mix-blend-screen overflow-hidden pointer-events-none">
                <img src="{{ $banner['image_url'] }}" alt="{{ $banner['title'] }}" class="w-full h-full object-cover">
            </div>
        @endif
    </section>
    @endif

    <!-- 2. "Shop Our Top Categories" Grid (matching store-idea.mp4) -->
    <section id="categories-section" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Shop Our Top Categories') }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                    {{ __('Browse products by curated departments') }}
                </p>
            </div>
            <button type="button"
                    x-on:click="selectedCategory = 'all'; scrollToProducts();"
                    class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 transition cursor-pointer">
                {{ __('View All') }} &rarr;
            </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-4">
            @forelse ($categories as $index => $cat)
                @php
                    $colors = [
                        ['bg' => 'bg-emerald-50 dark:bg-slate-900', 'border' => 'border-emerald-100 dark:border-slate-800', 'text' => 'text-emerald-700 dark:text-emerald-400', 'icon' => '📦'],
                        ['bg' => 'bg-teal-50 dark:bg-slate-900', 'border' => 'border-teal-100 dark:border-slate-800', 'text' => 'text-teal-700 dark:text-teal-400', 'icon' => '🏷️'],
                        ['bg' => 'bg-cyan-50 dark:bg-slate-900', 'border' => 'border-cyan-100 dark:border-slate-800', 'text' => 'text-cyan-700 dark:text-cyan-400', 'icon' => '🛍️'],
                        ['bg' => 'bg-sky-50 dark:bg-slate-900', 'border' => 'border-sky-100 dark:border-slate-800', 'text' => 'text-sky-700 dark:text-sky-400', 'icon' => '✨'],
                    ];
                    $palette = $colors[$index % count($colors)];
                @endphp
                <button type="button"
                        x-on:click="selectedCategory = {{ json_encode($cat->name) }}; scrollToProducts();"
                        class="p-3 sm:p-4 rounded-2xl sm:rounded-3xl border text-left transition-all duration-200 group flex items-center justify-between cursor-pointer hover:shadow-md hover:-translate-y-0.5"
                        :class="selectedCategory === {{ json_encode($cat->name) }} ? 'border-emerald-500 bg-emerald-50 dark:bg-slate-800 shadow-md ring-2 ring-emerald-500/20' : '{{ $palette['bg'] }} {{ $palette['border'] }} hover:bg-white dark:hover:bg-slate-800'">
                    <div class="space-y-1 min-w-0 flex-1">
                        <span class="text-2xl block mb-1">{{ $palette['icon'] }}</span>
                        <h3 class="font-extrabold text-xs sm:text-sm text-slate-800 dark:text-slate-100 group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition truncate">
                            {{ $cat->name }}
                        </h3>
                        <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 block">
                            {{ $cat->products_count }} {{ __('Items') }}
                        </span>
                    </div>
                    <div class="hidden sm:flex w-7 h-7 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700 items-center justify-center text-slate-400 group-hover:text-emerald-600 group-hover:border-emerald-300 transition shrink-0 ml-2">
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
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
            <!-- Left Filter Pills -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1 max-w-full">
                <button type="button"
                        x-on:click="selectedCategory = 'all'"
                        class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition cursor-pointer shrink-0 whitespace-nowrap"
                        :class="selectedCategory === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'">
                    {{ __('All Products') }} ({{ count($products) }})
                </button>
                @foreach ($categories as $cat)
                    <button type="button"
                            x-on:click="selectedCategory = {{ json_encode($cat->name) }}"
                            class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition cursor-pointer shrink-0 whitespace-nowrap"
                            :class="selectedCategory === {{ json_encode($cat->name) }} ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Right Sort Dropdown -->
            <div class="flex items-center gap-3 self-end md:self-auto shrink-0">
                <label class="text-xs font-bold text-slate-400 dark:text-slate-500">{{ __('Sort by:') }}</label>
                <select x-model="sortBy"
                        class="text-xs font-extrabold rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                    <option value="featured">{{ __('Featured') }}</option>
                    <option value="price_low">{{ __('Price: Low to High') }}</option>
                    <option value="price_high">{{ __('Price: High to Low') }}</option>
                    <option value="name_asc">{{ __('Alphabetical: A-Z') }}</option>
                </select>
            </div>
        </div>

        <!-- 4. Product Cards Grid (matching store-idea.mp4) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 mb-4 sm:mb-8">
            @forelse ($products as $product)
                @php
                    $prodPrice = (float)($product->sale_price ?? $product->price ?? 0);
                    $reviewsEnabled = (bool) ($company->enable_product_reviews ?? true);
                    $rating = (float) $product->average_rating;
                    $reviewsCount = (int) $product->reviews_count;
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
                        'average_rating' => $rating,
                        'reviews_count' => $reviewsCount,
                        'reviews_enabled' => $reviewsEnabled,
                    ];
                @endphp
                <div x-show="matchesFilter({{ json_encode($product->name) }}, {{ json_encode($product->category_name ?? '') }}, {{ json_encode($product->code ?? '') }}, {{ $prodPrice }})"
                     class="bg-white dark:bg-slate-900 rounded-2xl sm:rounded-3xl p-3 sm:p-5 shadow-xs hover:shadow-xl border border-slate-200/70 dark:border-slate-800 flex flex-col justify-between transition-all duration-300 group hover:-translate-y-1 relative">
                    
                    <!-- Wishlist Heart Button (Top Right) bound to global store state -->
                    <button type="button"
                            x-on:click.stop="toggleWishlist({{ json_encode($prodData) }})"
                            class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-white/90 dark:bg-slate-800/90 backdrop-blur-xs border border-slate-100 dark:border-slate-700 flex items-center justify-center transition cursor-pointer shadow-xs"
                            :class="isWishlisted({{ $product->id }}) ? 'text-rose-500 fill-rose-500' : 'text-slate-400 dark:text-slate-500 hover:text-rose-500'"
                            title="{{ __('Add to wishlist') }}">
                        <svg class="w-4 h-4 transition-transform group-hover:scale-110" :fill="isWishlisted({{ $product->id }}) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>

                    <!-- Product Image Container -->
                    <div x-on:click="openProductDetail({{ json_encode($prodData) }})"
                         class="w-full aspect-square rounded-2xl bg-slate-50 dark:bg-slate-800/50 overflow-hidden mb-3.5 flex items-center justify-center relative cursor-pointer group-hover:bg-emerald-50/20 transition">
                        @if ($product->image_url)
                            <img src="{{ str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url) }}"
                                 alt="{{ $product->name }}"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="text-4xl text-slate-300 dark:text-slate-600 group-hover:scale-110 transition">
                                🛍️
                            </div>
                        @endif

                        @if ($product->category_name)
                            <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/90 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 shadow-xs backdrop-blur-xs">
                                {{ $product->category_name }}
                            </span>
                        @endif
                    </div>

                    <!-- Details: Price, Name, Description, Star Ratings -->
                    <div class="space-y-1.5 w-full flex-1">
                        <!-- Price & Stock pill -->
                        <div class="flex items-baseline justify-between">
                            <span class="text-base sm:text-lg font-black text-slate-900 dark:text-white font-mono tracking-tight">
                                ${{ number_format($prodPrice, 2) }}
                            </span>
                            @if ((float)$product->current_stock <= 5 && (float)$product->current_stock > 0)
                                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded-md">
                                    {{ (int)$product->current_stock }} {{ __('left') }}
                                </span>
                            @elseif ((float)$product->current_stock > 5)
                                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded-md">
                                    {{ __('In stock') }}
                                </span>
                            @endif
                        </div>

                        <!-- Product Name -->
                        <h3 x-on:click="openProductDetail({{ json_encode($prodData) }})"
                            class="font-extrabold text-xs sm:text-sm text-slate-800 dark:text-slate-100 line-clamp-1 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition cursor-pointer"
                            title="{{ $product->name }}">
                            {{ $product->name }}
                        </h3>

                        @if (trim($product->description ?? ''))
                            <!-- Product Rich Description -->
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                {{ $product->description }}
                            </p>
                        @endif

                        <!-- Product Star Rating (When Reviews Enabled by Tenant) -->
                        @if ($reviewsEnabled)
                            <div class="flex items-center justify-between pt-1 text-xs">
                                <button type="button"
                                        x-on:click.stop="openProductReviews({{ json_encode($prodData) }})"
                                        class="flex items-center gap-1.5 hover:opacity-80 transition cursor-pointer text-left">
                                    <div class="flex items-center text-amber-500 font-black">
                                        ★ {{ $rating > 0 ? number_format($rating, 1) : __('New') }}
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">
                                        ({{ $reviewsCount }})
                                    </span>
                                </button>
                                <button type="button"
                                        x-on:click.stop="openProductReviews({{ json_encode($prodData) }})"
                                        class="text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline cursor-pointer">
                                    {{ __('Rate') }}
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Add To Cart Pill Button -->
                    <div class="w-full pt-3.5">
                        <template x-if="getItemQty({{ $product->id }}) === 0">
                            <button type="button"
                                    x-on:click="addToCart({{ json_encode($prodData) }})"
                                    class="w-full py-2.5 px-3 rounded-2xl bg-emerald-50 dark:bg-slate-800 hover:bg-emerald-600 dark:hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-extrabold text-xs transition-all duration-150 active:scale-95 flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
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
                    <div class="text-sm font-bold text-slate-600 dark:text-slate-300">{{ __('No products currently listed.') }}</div>
                    <p class="text-xs text-slate-400">{{ __('Products uploaded in the tenant POS inventory will appear here automatically.') }}</p>
                </div>
            @endforelse

            @if (count($products) > 0)
                <!-- Dynamic Empty State when filters/search return 0 matches -->
                <div x-show="filteredProductsCount === 0"
                     x-cloak
                     class="col-span-full py-16 px-4 text-center rounded-3xl bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-2xl text-slate-400">
                        🔍
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-extrabold text-slate-800 dark:text-slate-100">
                            {{ __('No products found') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                            <span x-show="search.length > 0">
                                {{ __('No items matching') }} "<span class="font-bold text-slate-700 dark:text-slate-200" x-text="search"></span>"
                            </span>
                            <span x-show="search.length === 0 && selectedCategory !== 'all'">
                                {{ __('No items found in this category.') }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <button type="button"
                                x-on:click="search = ''; selectedCategory = 'all'; priceFilter = 'all';"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ __('Reset Filters') }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- 5. "Services To Help You Shop" Section (matching store-idea.mp4) -->
    <section id="services-section" class="space-y-4 pt-4 sm:pt-6">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ __('Services To Help You Shop') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                {{ __('Everything you need for a comfortable and secure ordering experience') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Card 1: Frequently Asked Questions (Opens Storefront FAQ Modal) -->
            <div @click="openFaqsModal('all')"
                 class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md hover:border-emerald-500/50 dark:hover:border-emerald-500/50 transition-all space-y-3 cursor-pointer group select-none">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    ❓
                </div>
                <h3 class="font-black text-base text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">{{ __('Frequently Asked Questions') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Updates on safe shopping in our stores. Need answers regarding payment, sizes, or warranties? Our staff responds swiftly.') }}
                </p>
                <div class="pt-2 flex items-center justify-between">
                    <button type="button" @click.stop="openFaqsModal('all')" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 flex items-center gap-1 cursor-pointer">
                        <span>{{ __('Ask a Question') }}</span>
                        <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </button>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ count($faqs ?? []) }} {{ __('Answers') }}
                    </span>
                </div>
            </div>

            <!-- Card 2: Online Payment Process (Opens FAQs with Payment topic) -->
            <div @click="openFaqsModal('Payment')"
                 class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md hover:border-teal-500/50 dark:hover:border-teal-500/50 transition-all space-y-3 cursor-pointer group select-none">
                <div class="w-10 h-10 rounded-2xl bg-teal-50 dark:bg-slate-800 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    💳
                </div>
                <h3 class="font-black text-base text-slate-900 dark:text-white group-hover:text-teal-600 dark:group-hover:text-teal-400 transition">{{ __('Online Payment Process') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Updates on safe and swift online payment. We accept Cash on Delivery (COD), Shopcart digital card, PayPal, and credit/debit cards.') }}
                </p>
                <div class="pt-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        {{ __('Verified & Encrypted') }} &check;
                    </span>
                    <span class="text-[11px] font-bold text-teal-600 dark:text-teal-400 group-hover:translate-x-0.5 transition-transform">
                        {{ __('View Details') }} &rarr;
                    </span>
                </div>
            </div>

            <!-- Card 3: Home Delivery Options (Opens FAQs with Delivery topic) -->
            <div @click="openFaqsModal('Delivery')"
                 class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md hover:border-sky-500/50 dark:hover:border-sky-500/50 transition-all space-y-3 cursor-pointer group select-none">
                <div class="w-10 h-10 rounded-2xl bg-sky-50 dark:bg-slate-800 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                    🚚
                </div>
                <h3 class="font-black text-base text-slate-900 dark:text-white group-hover:text-sky-600 dark:group-hover:text-sky-400 transition">{{ __('Home Delivery Options') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Updates on safe home delivery. Orders placed here directly notify the store counter for prompt packaging and driver dispatch.') }}
                </p>
                <div class="pt-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        {{ __('Same-Day Dispatch') }} &check;
                    </span>
                    <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400 group-hover:translate-x-0.5 transition-transform">
                        {{ __('Learn More') }} &rarr;
                    </span>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('modals')
    <!-- 6. Product Detail Modal (matching store-idea.mp4 popup) -->
    <div x-show="detailModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="closeProductDetail()"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-3xl w-full p-5 sm:p-8 overflow-hidden border border-slate-100 dark:border-slate-800 my-auto z-10"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <!-- Close Button -->
            <button type="button"
                    x-on:click="closeProductDetail()"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition z-20 cursor-pointer">
                &times;
            </button>

                <template x-if="modalProduct">
                    <div class="space-y-4">
                        <!-- Modal Tab Header: Overview vs Ratings & Reviews -->
                        <template x-if="enableProductReviews && modalProduct.reviews_enabled !== false">
                            <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-2">
                                <button type="button"
                                        x-on:click="detailModalTab = 'overview'"
                                        class="pb-2 px-3 text-xs sm:text-sm font-extrabold transition-all border-b-2 flex items-center gap-1.5 cursor-pointer"
                                        :class="detailModalTab === 'overview' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                                    <span>📦</span>
                                    <span>{{ __('Overview') }}</span>
                                </button>
                                <button type="button"
                                        x-on:click="detailModalTab = 'reviews'"
                                        class="pb-2 px-3 text-xs sm:text-sm font-extrabold transition-all border-b-2 flex items-center gap-1.5 cursor-pointer"
                                        :class="detailModalTab === 'reviews' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                                    <span>⭐</span>
                                    <span>{{ __('Ratings & Reviews') }}</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-mono font-bold"
                                          x-text="modalProduct.reviews_count || (productReviews ? productReviews.length : 0)"></span>
                                </button>
                            </div>
                        </template>

                        <!-- Tab 1: Product Overview -->
                        <div x-show="detailModalTab === 'overview'">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                                <!-- Left: Large Image & Preview -->
                                <div class="space-y-3">
                                    <div class="w-full aspect-square rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 overflow-hidden flex items-center justify-center">
                                        <template x-if="modalProduct.image_url">
                                            <img :src="modalProduct.image_url" :alt="modalProduct.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!modalProduct.image_url">
                                            <span class="text-6xl">🛍️</span>
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2 text-[11px] text-slate-400 dark:text-slate-500 font-bold justify-center">
                                        <span>🛡️ {{ __('100% Genuine Guaranteed') }}</span>
                                        <span>&bull;</span>
                                        <span>⚡ {{ __('Fast Handling') }}</span>
                                    </div>
                                </div>

                                <!-- Right: Info, Price, Description, Specs, Buy Now -->
                                <div class="space-y-4 flex flex-col justify-between">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 uppercase tracking-wider"
                                                  x-text="modalProduct.category_name"></span>
                                            
                                            <!-- Wishlist Heart in detail modal -->
                                            <button type="button"
                                                    x-on:click="toggleWishlist(modalProduct)"
                                                    class="flex items-center gap-1 text-xs font-bold"
                                                    :class="isWishlisted(modalProduct.id) ? 'text-rose-500' : 'text-slate-400 hover:text-rose-500'">
                                                <svg class="w-4 h-4" :fill="isWishlisted(modalProduct.id) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                                </svg>
                                                <span x-text="isWishlisted(modalProduct.id) ? '{{ __('Saved') }}' : '{{ __('Save') }}'"></span>
                                            </button>
                                        </div>

                                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight" x-text="modalProduct.name"></h2>

                                        <!-- Star Rating + Review count -->
                                        <template x-if="enableProductReviews && modalProduct.reviews_enabled !== false">
                                            <div class="flex items-center gap-2 text-xs">
                                                <button type="button"
                                                        x-on:click="detailModalTab = 'reviews'"
                                                        class="flex items-center text-amber-500 font-black hover:opacity-80 transition cursor-pointer">
                                                    <span>★</span>
                                                    <span class="ml-1" x-text="(modalProduct.average_rating > 0) ? modalProduct.average_rating.toFixed(1) : '{{ __('New') }}'"></span>
                                                </button>
                                                <button type="button"
                                                        x-on:click="detailModalTab = 'reviews'"
                                                        class="text-slate-400 font-medium hover:text-emerald-600 dark:hover:text-emerald-400 transition cursor-pointer">
                                                    (<span x-text="modalProduct.reviews_count || 0"></span> {{ __('customer reviews') }})
                                                </button>
                                                <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                                                <button type="button"
                                                        x-on:click="detailModalTab = 'reviews'; reviewFormOpen = true;"
                                                        class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline cursor-pointer">
                                                    + {{ __('Rate Product') }}
                                                </button>
                                            </div>
                                        </template>

                                        <!-- Price -->
                                        <div class="flex items-baseline gap-2 pt-1">
                                            <span class="text-2xl font-black text-slate-900 dark:text-white font-mono">
                                                $<span x-text="modalProduct.sale_price.toFixed(2)"></span>
                                            </span>
                                            <span class="text-xs text-slate-400 line-through font-mono">
                                                $<span x-text="(modalProduct.sale_price * 1.25).toFixed(2)"></span>
                                            </span>
                                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded-md">
                                                {{ __('20% OFF') }}
                                            </span>
                                        </div>

                                        <!-- Full Description (Only when populated) -->
                                        <template x-if="modalProduct.description && modalProduct.description.trim()">
                                            <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed pt-1" x-text="modalProduct.description"></div>
                                        </template>

                                        <!-- Specifications Key-Value Table -->
                                        <div class="border-t border-slate-100 dark:border-slate-800 pt-3 space-y-1.5 text-xs">
                                            <div class="flex justify-between py-1 border-b border-slate-50 dark:border-slate-800">
                                                <span class="text-slate-400 font-medium">{{ __('SKU / Code:') }}</span>
                                                <span class="font-mono font-bold text-slate-700 dark:text-slate-300" x-text="modalProduct.code || 'N/A'"></span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-slate-50 dark:border-slate-800">
                                                <span class="text-slate-400 font-medium">{{ __('Unit:') }}</span>
                                                <span class="font-bold text-slate-700 dark:text-slate-300" x-text="modalProduct.unit"></span>
                                            </div>
                                            <div class="flex justify-between py-1 border-b border-slate-50 dark:border-slate-800">
                                                <span class="text-slate-400 font-medium">{{ __('Availability:') }}</span>
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                                    {{ __('In Stock') }} (<span x-text="parseInt(modalProduct.current_stock)"></span> {{ __('available') }})
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Scarcity Alert -->
                                        <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-800 rounded-xl p-2.5 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
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
                                            <div class="flex items-center border border-slate-200 dark:border-slate-700 rounded-xl p-1 bg-slate-50 dark:bg-slate-800">
                                                <button type="button"
                                                        x-on:click="if (modalQty > 1) modalQty--"
                                                        class="w-8 h-8 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 font-black text-sm flex items-center justify-center hover:bg-emerald-50 dark:hover:bg-slate-700 transition cursor-pointer">
                                                    -
                                                </button>
                                                <span class="font-bold text-xs px-3 text-slate-800 dark:text-slate-100" x-text="modalQty"></span>
                                                <button type="button"
                                                        x-on:click="modalQty++"
                                                        class="w-8 h-8 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 font-black text-sm flex items-center justify-center hover:bg-emerald-50 dark:hover:bg-slate-700 transition cursor-pointer">
                                                    +
                                                </button>
                                            </div>

                                            <!-- Add to Cart Button -->
                                            <button type="button"
                                                    x-on:click="addToCart(modalProduct, modalQty); closeProductDetail();"
                                                    class="flex-1 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-400 font-extrabold text-xs transition cursor-pointer">
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
                        </div>

                        <!-- Tab 2: Ratings & Reviews Tab -->
                        <div x-show="detailModalTab === 'reviews'" x-cloak class="space-y-6">
                            <!-- Scorecard & Review Action -->
                            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 flex flex-col md:flex-row items-center justify-between gap-6">
                                <!-- Average Score Column -->
                                <div class="flex items-center gap-4 text-center md:text-left">
                                    <div class="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white font-mono tracking-tight"
                                         x-text="(reviewsStats.average_rating > 0) ? reviewsStats.average_rating.toFixed(1) : '0.0'"></div>
                                    <div class="space-y-1">
                                        <div class="flex items-center text-amber-400 text-base">
                                            <template x-for="i in 5" :key="i">
                                                <span :class="i <= Math.round(reviewsStats.average_rating) ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'">★</span>
                                            </template>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 font-bold">
                                            <span x-text="reviewsStats.total_reviews"></span> {{ __('customer reviews') }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Rating Distribution Breakdown -->
                                <div class="flex-1 w-full max-w-xs space-y-1.5 text-xs">
                                    <template x-for="star in [5, 4, 3, 2, 1]" :key="star">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 text-right font-bold text-slate-600 dark:text-slate-400" x-text="star + '★'"></span>
                                            <div class="flex-1 h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                                <div class="h-full bg-amber-400 rounded-full transition-all duration-300"
                                                     :style="'width: ' + (reviewsStats.total_reviews > 0 ? ((reviewsDistribution[star] || 0) / reviewsStats.total_reviews * 100) : 0) + '%'"></div>
                                            </div>
                                            <span class="w-8 text-left text-[11px] font-mono text-slate-400" x-text="reviewsDistribution[star] || 0"></span>
                                        </div>
                                    </template>
                                </div>

                                <!-- Write Review CTA Button -->
                                <div class="shrink-0 w-full md:w-auto">
                                    <button type="button"
                                            x-on:click="reviewFormOpen = !reviewFormOpen"
                                            class="w-full md:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs transition active:scale-95 shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                                        <span>✍️</span>
                                        <span x-text="reviewFormOpen ? '{{ __('Close Form') }}' : '{{ __('Write a Review') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Review Form (Collapsible) -->
                            <div x-show="reviewFormOpen" class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-500/30 shadow-md space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>⭐</span> {{ __('Rate & Review this Product') }}
                                    </h4>
                                    <span class="text-xs text-slate-400 font-medium">{{ __('Honest feedback helps other buyers') }}</span>
                                </div>

                                <!-- Success and Error alerts -->
                                <template x-if="reviewSuccessMsg">
                                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                                        <span x-text="reviewSuccessMsg"></span>
                                        <button type="button" x-on:click="reviewSuccessMsg = ''" class="text-emerald-600 font-bold">&times;</button>
                                    </div>
                                </template>
                                <template x-if="reviewErrorMsg">
                                    <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-xs font-bold text-rose-800 dark:text-rose-300 flex items-center justify-between">
                                        <span x-text="reviewErrorMsg"></span>
                                        <button type="button" x-on:click="reviewErrorMsg = ''" class="text-rose-600 font-bold">&times;</button>
                                    </div>
                                </template>

                                <!-- Rating Star Selector -->
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Your Overall Rating *') }}</label>
                                    <div class="flex items-center gap-1.5">
                                        <template x-for="star in 5" :key="star">
                                            <button type="button"
                                                    x-on:click="setReviewRating(star)"
                                                    class="text-2xl transition-transform hover:scale-125 cursor-pointer select-none focus:outline-none"
                                                    :class="star <= reviewRating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'">
                                                ★
                                            </button>
                                        </template>
                                        <span class="ml-2 text-xs font-bold text-slate-600 dark:text-slate-400"
                                              x-text="{1: '{{ __('1 - Poor') }}', 2: '{{ __('2 - Fair') }}', 3: '{{ __('3 - Good') }}', 4: '{{ __('4 - Very Good') }}', 5: '{{ __('5 - Excellent') }}'}[reviewRating] || ''"></span>
                                    </div>
                                </div>

                                <!-- Name & Email (For Guests or Logged-in) -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Your Name *') }}</label>
                                        <input type="text"
                                               x-model="reviewCustomerName"
                                               :placeholder="customer ? customer.name : '{{ __('e.g. Alex Smith') }}'"
                                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email Address (Optional)') }}</label>
                                        <input type="email"
                                               x-model="reviewCustomerEmail"
                                               :placeholder="customer ? customer.email : '{{ __('e.g. alex@example.com') }}'"
                                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white">
                                    </div>
                                </div>

                                <!-- Review Title -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Review Headline / Title') }}</label>
                                    <input type="text"
                                           x-model="reviewTitle"
                                           placeholder="{{ __('e.g. Excellent build quality, highly recommended!') }}"
                                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white">
                                </div>

                                <!-- Review Comment -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Detailed Review *') }}</label>
                                    <textarea rows="3"
                                              x-model="reviewComment"
                                              placeholder="{{ __('Tell us what you liked or disliked about this product...') }}"
                                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white"></textarea>
                                </div>

                                <!-- Form Actions -->
                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button type="button"
                                            x-on:click="reviewFormOpen = false"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="button"
                                            x-on:click="submitReview()"
                                            :disabled="isSubmittingReview"
                                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs shadow-sm transition active:scale-95 flex items-center gap-2 cursor-pointer">
                                        <template x-if="isSubmittingReview">
                                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </template>
                                        <span x-text="isSubmittingReview ? '{{ __('Submitting...') }}' : '{{ __('Post Review') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Existing Reviews List -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center justify-between">
                                    <span>{{ __('Customer Reviews') }} (<span x-text="productReviews.length"></span>)</span>
                                    <button type="button"
                                            x-on:click="loadProductReviews(modalProduct.id)"
                                            class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold hover:underline cursor-pointer">
                                        ↻ {{ __('Refresh') }}
                                    </button>
                                </h4>

                                <!-- Loading spinner -->
                                <template x-if="isLoadingReviews">
                                    <div class="py-8 text-center text-slate-400 space-y-2">
                                        <div class="animate-spin inline-block w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full"></div>
                                        <div class="text-xs font-medium">{{ __('Loading reviews...') }}</div>
                                    </div>
                                </template>

                                <!-- Empty state -->
                                <template x-if="!isLoadingReviews && productReviews.length === 0">
                                    <div class="py-10 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-dashed border-slate-200 dark:border-slate-700 p-6 space-y-3">
                                        <div class="text-3xl">⭐</div>
                                        <div class="font-extrabold text-slate-800 dark:text-slate-200 text-sm">
                                            {{ __('No reviews yet for this product') }}
                                        </div>
                                        <p class="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                                            {{ __('Be the first customer to rate this product and share your experience with other shoppers!') }}
                                        </p>
                                        <button type="button"
                                                x-on:click="reviewFormOpen = true"
                                                class="mt-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-sm transition active:scale-95 cursor-pointer">
                                            ✍️ {{ __('Write First Review') }}
                                        </button>
                                    </div>
                                </template>

                                <!-- Reviews feed -->
                                <template x-if="!isLoadingReviews && productReviews.length > 0">
                                    <div class="space-y-3">
                                        <template x-for="rev in productReviews" :key="rev.id">
                                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 space-y-2 shadow-2xs">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 font-black text-xs flex items-center justify-center"
                                                             x-text="(rev.customer_name || 'U').charAt(0).toUpperCase()"></div>
                                                        <div>
                                                            <div class="font-extrabold text-xs text-slate-900 dark:text-white flex items-center gap-1.5">
                                                                <span x-text="rev.customer_name"></span>
                                                                <template x-if="rev.is_verified_purchase">
                                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                                        ✓ {{ __('Verified Buyer') }}
                                                                    </span>
                                                                </template>
                                                            </div>
                                                            <div class="text-[10px] text-slate-400" x-text="rev.created_at"></div>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center text-amber-400 text-xs">
                                                        <template x-for="i in 5" :key="i">
                                                            <span :class="i <= rev.rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-700'">★</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <template x-if="rev.title">
                                                    <div class="font-extrabold text-xs text-slate-800 dark:text-slate-200" x-text="rev.title"></div>
                                                </template>
                                                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed" x-text="rev.comment"></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
    </div>
@endsection
