<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app(\App\Services\Localization\LocalizationService::class)->isRtl() ? 'rtl' : 'ltr' }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($company->name ?? 'Storefront') . ' — Online Store')</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS with custom styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes pulse-subtle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .animate-pulse-subtle {
            animation: pulse-subtle 2s infinite ease-in-out;
        }
    </style>
    @yield('head')
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col antialiased selection:bg-emerald-500 selection:text-white"
      x-data="ecommerceStore({
          storeName: {{ json_encode($company->name ?? 'Store') }},
          storePhone: {{ json_encode($company->phone ?? '') }},
          currency: {{ json_encode($company->currency ?? 'USD') }},
          orderEndpoint: '{{ route('tenant.store.order') }}',
          catalogId: '{{ $catalog->id ?? '' }}'
      })">

    <!-- 1. Top Announcement Bar (matching store-idea.mp4) -->
    <div class="bg-emerald-900 text-emerald-100 text-[11px] sm:text-xs py-2 px-4 sm:px-8 border-b border-emerald-800">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <!-- Left: Phone -->
            <div class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <a href="tel:{{ $company->phone ?? '+1 (555) 019-2834' }}" class="hover:text-white transition font-medium">
                    {{ $company->phone ?? '+1 (555) 019-2834' }}
                </a>
            </div>

            <!-- Center: Promo Message -->
            <div class="hidden md:flex items-center gap-2 font-semibold text-emerald-200">
                <span>{{ __('Get 50% Off on Selected Items') }}</span>
                <span class="text-emerald-400">&bull;</span>
                <a href="#products-section" class="text-white underline underline-offset-2 hover:text-emerald-300 font-bold transition">
                    {{ __('Shop Now') }}
                </a>
            </div>

            <!-- Right: Language Dropdown & Location -->
            <div class="flex items-center gap-4">
                <!-- Language Selector -->
                <div class="relative" x-data="{ langOpen: false }">
                    <button type="button"
                            x-on:click="langOpen = !langOpen"
                            class="flex items-center gap-1.5 hover:text-white font-medium focus:outline-hidden cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>
                            @php
                                $activeLocale = app()->getLocale();
                                $activeLang = $languages->firstWhere('code', $activeLocale);
                            @endphp
                            {{ $activeLang ? ($activeLang->native_name ?: $activeLang->name) : strtoupper($activeLocale) }}
                        </span>
                        <svg class="w-3 h-3 text-emerald-300 transition-transform" :class="langOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="langOpen"
                         x-cloak
                         x-on:click.outside="langOpen = false"
                         class="absolute right-0 mt-2 w-40 bg-white text-slate-800 rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 text-xs font-semibold">
                        @foreach ($languages as $lang)
                            <a href="{{ route('locale.switch', ['locale' => $lang->code]) }}"
                               class="flex items-center justify-between px-3.5 py-2 hover:bg-emerald-50 hover:text-emerald-700 transition {{ app()->getLocale() === $lang->code ? 'bg-emerald-50/80 font-bold text-emerald-700' : '' }}">
                                <span>{{ $lang->native_name ?: $lang->name }}</span>
                                @if (app()->getLocale() === $lang->code)
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Location -->
                <div class="hidden sm:flex items-center gap-1.5 text-emerald-200">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{{ $company->city ? $company->city . ($company->state ? ', ' . $company->state : '') : __('Location') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Main Shopcart Header (matching store-idea.mp4) -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-3.5 flex items-center justify-between gap-4">
            
            <!-- Store Branding & Logo -->
            <a href="{{ url('/') }}" class="flex items-center gap-3 shrink-0 group">
                @if ($company->logo)
                    <img src="{{ str_starts_with($company->logo, 'http') ? $company->logo : asset($company->logo) }}"
                         alt="{{ $company->name }}"
                         class="w-10 h-10 rounded-2xl object-cover border border-slate-100 shadow-xs group-hover:scale-105 transition">
                @else
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-black text-base flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-105 transition">
                        {{ strtoupper(substr($company->name ?? 'S', 0, 1)) }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight leading-none group-hover:text-emerald-600 transition">
                            {{ $company->name ?? 'Shopcart' }}
                        </span>
                        <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            {{ __('Verified') }}
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400 font-medium leading-none mt-1 block">
                        {{ __('Online Storefront') }}
                    </span>
                </div>
            </a>

            <!-- Categories Dropdown Trigger -->
            <div class="hidden lg:block relative" x-data="{ catMenuOpen: false }">
                <button type="button"
                        x-on:click="catMenuOpen = !catMenuOpen"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/50 text-slate-700 hover:text-emerald-700 text-xs font-bold transition">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                    <span>{{ __('Categories') }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="catMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Category list popup -->
                <div x-show="catMenuOpen"
                     x-cloak
                     x-on:click.outside="catMenuOpen = false"
                     class="absolute left-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 z-50 space-y-1">
                    <button type="button"
                            x-on:click="selectedCategory = 'all'; catMenuOpen = false; scrollToProducts();"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-emerald-50 hover:text-emerald-700 transition"
                            :class="selectedCategory === 'all' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700'">
                        <span>{{ __('All Categories') }}</span>
                        <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-extrabold">{{ count($products) }}</span>
                    </button>
                    @foreach ($categories as $cat)
                        <button type="button"
                                x-on:click="selectedCategory = {{ json_encode($cat->name) }}; catMenuOpen = false; scrollToProducts();"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-emerald-50 hover:text-emerald-700 transition"
                                :class="selectedCategory === {{ json_encode($cat->name) }} ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700'">
                            <span class="truncate">{{ $cat->name }}</span>
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-extrabold">{{ $cat->products_count }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Quick Navigation Links (Deals, What's New, Delivery) -->
            <nav class="hidden md:flex items-center gap-6 text-xs font-bold text-slate-600">
                <a href="#products-section" x-on:click="selectedCategory = 'all'; scrollToProducts();" class="hover:text-emerald-600 transition">
                    {{ __('Deals') }}
                </a>
                <a href="#products-section" x-on:click="sortBy = 'newest'; scrollToProducts();" class="hover:text-emerald-600 transition">
                    {{ __("What's New") }}
                </a>
                <a href="#services-section" class="hover:text-emerald-600 transition">
                    {{ __('Delivery') }}
                </a>
            </nav>

            <!-- Search Bar with Live Real-time Filtering -->
            <div class="flex-1 max-w-xs sm:max-w-sm relative">
                <input type="text"
                       x-model="search"
                       placeholder="{{ __('Search Product...') }}"
                       class="w-full pl-10 pr-8 py-2 bg-slate-100 hover:bg-slate-50 focus:bg-white rounded-2xl border-none text-xs sm:text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500 shadow-inner transition">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="button"
                        x-show="search.length > 0"
                        x-on:click="search = ''"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    &times;
                </button>
            </div>

            <!-- Right Actions: Account & Cart Trigger -->
            <div class="flex items-center gap-3">
                <!-- Account / Login -->
                <a href="{{ route('tenant.login') }}"
                   class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl text-slate-700 hover:text-emerald-600 hover:bg-slate-50 text-xs font-bold transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>{{ __('Account') }}</span>
                </a>

                <!-- Cart Button -->
                <button type="button"
                        x-on:click="cartDrawerOpen = true"
                        class="relative px-3.5 sm:px-4 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold text-xs sm:text-sm flex items-center gap-2 shadow-md shadow-emerald-600/20 transition cursor-pointer">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="hidden sm:inline">{{ __('Cart') }}</span>
                    <span x-show="cartTotalCount > 0"
                          x-text="cartTotalCount"
                          class="px-2 py-0.5 rounded-full bg-white text-emerald-700 font-black text-xs shadow-xs animate-pulse-subtle">
                    </span>
                    <span x-show="cartTotalPrice > 0" class="hidden sm:inline font-mono border-l border-emerald-500/60 pl-2">
                        $<span x-text="cartTotalPrice.toFixed(2)"></span>
                    </span>
                </button>
            </div>

        </div>
    </header>

    <!-- Main Dynamic Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-8 py-6 space-y-10">
        @yield('content')
    </main>

    <!-- 3. Slide-Over Shopping Cart & Checkout Drawer (matching store-idea.mp4) -->
    <div x-show="cartDrawerOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-hidden"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
             x-on:click="cartDrawerOpen = false"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10 rtl:pl-0 rtl:pr-10 rtl:left-0 rtl:right-auto">
            <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full rtl:-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full rtl:-translate-x-full">
                
                <!-- Drawer Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-base text-slate-900 leading-tight">{{ __('Your Shopping Cart') }}</h3>
                            <span class="text-xs text-slate-400 font-medium">
                                <span x-text="cartTotalCount"></span> {{ __('items selected') }}
                            </span>
                        </div>
                    </div>
                    <button type="button"
                            x-on:click="cartDrawerOpen = false"
                            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center font-bold text-lg transition cursor-pointer">
                        &times;
                    </button>
                </div>

                <!-- Cart Items Scrollable List -->
                <div class="flex-1 overflow-y-auto p-5 space-y-3">
                    <template x-for="item in cart" :key="item.id">
                        <div class="flex items-center justify-between bg-slate-50 hover:bg-emerald-50/40 p-3 rounded-2xl border border-slate-100 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 overflow-hidden shrink-0 flex items-center justify-center">
                                    <template x-if="item.image">
                                        <img :src="item.image" :alt="item.name" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!item.image">
                                        <span class="text-xs font-bold text-slate-400">📦</span>
                                    </template>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-xs sm:text-sm text-slate-800 truncate" x-text="item.name"></h4>
                                    <div class="text-xs text-emerald-600 font-extrabold mt-0.5">
                                        $<span x-text="item.price.toFixed(2)"></span> &bull; <span class="text-slate-400">Total:</span> $<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stepper & Remove -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button"
                                        x-on:click="decreaseQty(item.id)"
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 flex items-center justify-center font-black text-xs hover:bg-emerald-50 transition cursor-pointer">
                                    -
                                </button>
                                <span class="font-extrabold text-xs min-w-4 text-center" x-text="item.quantity"></span>
                                <button type="button"
                                        x-on:click="increaseQty(item.id)"
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 flex items-center justify-center font-black text-xs hover:bg-emerald-50 transition cursor-pointer">
                                    +
                                </button>
                                <button type="button"
                                        x-on:click="removeItem(item.id)"
                                        class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center text-xs transition cursor-pointer"
                                        title="{{ __('Remove item') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <template x-if="cart.length === 0">
                        <div class="py-20 text-center text-slate-400 space-y-3">
                            <div class="w-16 h-16 mx-auto rounded-3xl bg-slate-100 flex items-center justify-center text-2xl text-slate-400">
                                🛒
                            </div>
                            <div class="text-sm font-bold text-slate-700">{{ __('Your cart is currently empty') }}</div>
                            <p class="text-xs text-slate-400 max-w-xs mx-auto">
                                {{ __('Browse our products and add your favorite items to start checkout.') }}
                            </p>
                        </div>
                    </template>
                </div>

                <!-- Checkout Form & Totals Accordion -->
                <div class="p-5 border-t border-slate-100 bg-slate-50/50 space-y-4">
                    
                    <template x-if="cart.length > 0">
                        <div class="space-y-3">
                            <!-- Toggle Delivery Details Form -->
                            <div class="space-y-2">
                                <label class="block text-[11px] font-extrabold text-slate-700 uppercase tracking-wider">
                                    {{ __('Customer & Delivery Details') }}
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text"
                                           x-model="customerName"
                                           placeholder="{{ __('Full Name *') }}"
                                           class="w-full rounded-xl border-slate-200 bg-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2">
                                    <input type="tel"
                                           x-model="customerPhone"
                                           placeholder="{{ __('Phone / WhatsApp *') }}"
                                           class="w-full rounded-xl border-slate-200 bg-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text"
                                           x-model="deliveryAddress"
                                           placeholder="{{ __('Street Address') }}"
                                           class="w-full rounded-xl border-slate-200 bg-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2">
                                    <input type="text"
                                           x-model="deliveryCity"
                                           placeholder="{{ __('City') }}"
                                           class="w-full rounded-xl border-slate-200 bg-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2">
                                </div>

                                <!-- Payment Method Radio -->
                                <div class="pt-1">
                                    <label class="block text-[11px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                        {{ __('Payment Method') }}
                                    </label>
                                    <div class="grid grid-cols-3 gap-1.5 text-center">
                                        <label class="px-2 py-2 rounded-xl border cursor-pointer text-xs font-bold transition flex flex-col items-center gap-1"
                                               :class="paymentMethod === 'cod' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'">
                                            <input type="radio" value="cod" x-model="paymentMethod" class="sr-only">
                                            <span>💵 COD</span>
                                            <span class="text-[9px] text-slate-400 font-normal">{{ __('Pay on delivery') }}</span>
                                        </label>
                                        <label class="px-2 py-2 rounded-xl border cursor-pointer text-xs font-bold transition flex flex-col items-center gap-1"
                                               :class="paymentMethod === 'counter' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'">
                                            <input type="radio" value="counter" x-model="paymentMethod" class="sr-only">
                                            <span>🏪 Store</span>
                                            <span class="text-[9px] text-slate-400 font-normal">{{ __('Pay at counter') }}</span>
                                        </label>
                                        <label class="px-2 py-2 rounded-xl border cursor-pointer text-xs font-bold transition flex flex-col items-center gap-1"
                                               :class="paymentMethod === 'online' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'">
                                            <input type="radio" value="online" x-model="paymentMethod" class="sr-only">
                                            <span>💳 Online</span>
                                            <span class="text-[9px] text-slate-400 font-normal">{{ __('Card / Net') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Totals Breakdown -->
                            <div class="space-y-1.5 pt-2 border-t border-slate-200 text-xs">
                                <div class="flex justify-between text-slate-500">
                                    <span>{{ __('Subtotal:') }}</span>
                                    <span class="font-mono font-bold text-slate-800">$<span x-text="cartTotalPrice.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-slate-500">
                                    <span>{{ __('Estimated Delivery:') }}</span>
                                    <span class="font-bold text-emerald-600">{{ __('FREE') }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-1 border-t border-slate-200">
                                    <span>{{ __('Total Order Amount:') }}</span>
                                    <span class="text-base text-emerald-600 font-mono font-black">$<span x-text="cartTotalPrice.toFixed(2)"></span></span>
                                </div>
                            </div>

                            <!-- Order CTAs: Place Order & WhatsApp -->
                            <div class="space-y-2 pt-2">
                                <button type="button"
                                        x-on:click="submitOrderToStore()"
                                        :disabled="isSubmitting || cart.length === 0"
                                        class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-600/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                                    <template x-if="isSubmitting">
                                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                    </template>
                                    <span x-text="isSubmitting ? '{{ __('Processing...') }}' : '{{ __('Place Order Now') }}'"></span>
                                </button>

                                <button type="button"
                                        x-on:click="orderViaWhatsApp()"
                                        :disabled="cart.length === 0"
                                        class="w-full py-3 rounded-2xl bg-[#25D366] hover:bg-[#1EBE5D] disabled:opacity-40 text-white font-extrabold text-xs tracking-wide shadow-sm active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                                    </svg>
                                    <span>{{ __('Order via WhatsApp') }}</span>
                                </button>
                            </div>
                        </div>
                    </template>

                </div>

            </div>
        </div>
    </div>

    <!-- 4. Order Confirmation Modal (matching store-idea.mp4) -->
    <div x-show="orderAcceptedModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs"></div>

        <div class="relative bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-full shadow-2xl text-center space-y-4 border border-slate-100"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-90 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <!-- Animated Green Checkmark -->
            <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div class="space-y-1">
                <h3 class="text-xl font-black text-slate-900 tracking-tight">
                    {{ __('Your order has been accepted!') }}
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    {{ __('Transaction ID:') }}
                    <span class="font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md" x-text="confirmedSaleNumber"></span>
                </p>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed">
                {{ __('Thank you for your order! We have recorded your items and our team is preparing your package.') }}
            </p>

            <button type="button"
                    x-on:click="orderAcceptedModalOpen = false"
                    class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold text-xs tracking-wide shadow-md transition cursor-pointer">
                {{ __('Continue Shopping') }}
            </button>
        </div>
    </div>

    <!-- 5. Store Footer -->
    <footer class="bg-white border-t border-slate-200 mt-20 pt-12 pb-8 px-4 sm:px-8">
        <div class="max-w-7xl mx-auto space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Brand summary -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2.5">
                        @if ($company->logo)
                            <img src="{{ str_starts_with($company->logo, 'http') ? $company->logo : asset($company->logo) }}"
                                 alt="{{ $company->name }}" class="w-8 h-8 rounded-xl object-cover">
                        @else
                            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-black text-sm flex items-center justify-center">
                                {{ strtoupper(substr($company->name ?? 'S', 0, 1)) }}
                            </div>
                        @endif
                        <span class="font-extrabold text-base text-slate-900">{{ $company->name }}</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        {{ __('Your trusted digital store for quality items, fast home delivery, and verified checkout.') }}
                    </p>
                </div>

                <!-- Department / Categories -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 uppercase tracking-wider text-[11px]">{{ __('Department') }}</h5>
                    <ul class="space-y-1.5 text-slate-500 font-medium">
                        @foreach ($categories->take(4) as $cat)
                            <li>
                                <a href="#products-section" x-on:click="selectedCategory = {{ json_encode($cat->name) }}; scrollToProducts();" class="hover:text-emerald-600 transition">
                                    {{ $cat->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Help -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 uppercase tracking-wider text-[11px]">{{ __('Help & Support') }}</h5>
                    <ul class="space-y-1.5 text-slate-500 font-medium">
                        <li><a href="#services-section" class="hover:text-emerald-600 transition">{{ __('Delivery Terms') }}</a></li>
                        <li><a href="#services-section" class="hover:text-emerald-600 transition">{{ __('Order Tracking') }}</a></li>
                        <li><a href="#services-section" class="hover:text-emerald-600 transition">{{ __('Returns Policy') }}</a></li>
                    </ul>
                </div>

                <!-- Contact Details -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 uppercase tracking-wider text-[11px]">{{ __('Contact Store') }}</h5>
                    <div class="space-y-1.5 text-slate-500">
                        @if ($company->phone)
                            <div>📞 <a href="tel:{{ $company->phone }}" class="hover:text-emerald-600 font-bold">{{ $company->phone }}</a></div>
                        @endif
                        @if ($company->email)
                            <div>✉️ <a href="mailto:{{ $company->email }}" class="hover:text-emerald-600 font-bold">{{ $company->email }}</a></div>
                        @endif
                        @if ($company->address)
                            <div>📍 {{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright & Safe Payment Badges -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <div>
                    &copy; {{ date('Y') }} {{ $company->name }}. {{ __('All rights reserved. Powered by ZoomPOS.') }}
                </div>
                <div class="flex items-center gap-3 font-semibold text-[11px]">
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-600">🛡️ 100% Secure Checkout</span>
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-600">🚚 Fast Dispatch</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Alpine Store Core Script -->
    <script>
        function ecommerceStore(config) {
            return {
                storeName: config.storeName || 'Store',
                storePhone: config.storePhone || '',
                currency: config.currency || 'USD',
                orderEndpoint: config.orderEndpoint || '/store/order',
                catalogId: config.catalogId || '',

                // State
                search: '',
                selectedCategory: 'all',
                priceFilter: 'all',
                sortBy: 'featured',
                cartDrawerOpen: false,
                orderAcceptedModalOpen: false,
                isSubmitting: false,
                confirmedSaleNumber: '',

                // Selected product modal state
                detailModalOpen: false,
                modalProduct: null,
                modalQty: 1,

                // Checkout fields
                customerName: '',
                customerPhone: '',
                deliveryAddress: '',
                deliveryCity: '',
                paymentMethod: 'cod',
                cart: [],

                init() {
                    try {
                        const saved = localStorage.getItem('pos_store_cart_' + (config.catalogId || 'general'));
                        if (saved) {
                            this.cart = JSON.parse(saved);
                        }
                    } catch (e) {}

                    this.$watch('cart', (value) => {
                        try {
                            localStorage.setItem('pos_store_cart_' + (config.catalogId || 'general'), JSON.stringify(value));
                        } catch (e) {}
                    });
                },

                get cartTotalCount() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },

                get cartTotalPrice() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },

                openProductDetail(product) {
                    this.modalProduct = product;
                    this.modalQty = 1;
                    this.detailModalOpen = true;
                },

                closeProductDetail() {
                    this.detailModalOpen = false;
                    this.modalProduct = null;
                },

                addToCart(product, qty = 1) {
                    const price = parseFloat(product.sale_price || product.price || 0);
                    const existing = this.cart.find(i => i.id === product.id);
                    if (existing) {
                        existing.quantity += qty;
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            price: price,
                            image: product.image_url || '',
                            quantity: qty
                        });
                    }
                },

                buyNow(product) {
                    this.addToCart(product, this.modalQty);
                    this.closeProductDetail();
                    this.cartDrawerOpen = true;
                },

                getItemQty(productId) {
                    const item = this.cart.find(i => i.id === productId);
                    return item ? item.quantity : 0;
                },

                increaseQty(id) {
                    const item = this.cart.find(i => i.id === id);
                    if (item) item.quantity++;
                },

                decreaseQty(id) {
                    const item = this.cart.find(i => i.id === id);
                    if (item) {
                        item.quantity--;
                        if (item.quantity <= 0) {
                            this.removeItem(id);
                        }
                    }
                },

                removeItem(id) {
                    this.cart = this.cart.filter(i => i.id !== id);
                },

                scrollToProducts() {
                    const el = document.getElementById('products-section');
                    if (el) el.scrollIntoView({ behavior: 'smooth' });
                },

                matchesFilter(name, category, code, price) {
                    if (this.selectedCategory !== 'all' && category !== this.selectedCategory) {
                        return false;
                    }
                    if (this.search && this.search.trim().length > 0) {
                        const q = this.search.toLowerCase();
                        const matchName = name && name.toLowerCase().includes(q);
                        const matchCode = code && code.toLowerCase().includes(q);
                        const matchCat = category && category.toLowerCase().includes(q);
                        if (!matchName && !matchCode && !matchCat) return false;
                    }
                    return true;
                },

                async submitOrderToStore() {
                    if (this.cart.length === 0) return;
                    if (!this.customerName.trim()) {
                        alert('{{ __('Please enter your full name.') }}');
                        return;
                    }
                    if (!this.customerPhone.trim()) {
                        alert('{{ __('Please enter your phone number.') }}');
                        return;
                    }

                    this.isSubmitting = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const response = await fetch(this.orderEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || ''
                            },
                            body: JSON.stringify({
                                customer_name: this.customerName,
                                customer_phone: this.customerPhone,
                                address: this.deliveryAddress,
                                city: this.deliveryCity,
                                payment_method: this.paymentMethod,
                                items: this.cart
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.confirmedSaleNumber = data.sale_number || 'WEB-' + Date.now();
                            this.cart = [];
                            this.cartDrawerOpen = false;
                            this.orderAcceptedModalOpen = true;
                        } else {
                            alert(data.message || 'Error placing order. Please try again.');
                        }
                    } catch (err) {
                        console.error('Order submission error:', err);
                        alert('Connection error while placing order.');
                    } finally {
                        this.isSubmitting = false;
                    }
                },

                async orderViaWhatsApp() {
                    if (this.cart.length === 0) return;
                    
                    // First register order in POS
                    try {
                        await this.submitOrderToStore();
                    } catch (e) {}

                    // Build formatted WhatsApp message
                    let itemsSummary = '';
                    this.cart.forEach(item => {
                        itemsSummary += `• ${item.name} (x${item.quantity}) - $${(item.price * item.quantity).toFixed(2)}\n`;
                    });

                    const clientName = this.customerName.trim() || 'Guest Customer';
                    const clientPhone = this.customerPhone.trim() ? `\n*Phone:* ${this.customerPhone.trim()}` : '';
                    const clientAddr = this.deliveryAddress.trim() ? `\n*Address:* ${this.deliveryAddress.trim()}, ${this.deliveryCity.trim()}` : '';

                    const message = `🛒 *NEW ORDER FROM STORE*\n`
                        + `*Store:* ${this.storeName}\n`
                        + `*Customer:* ${clientName}${clientPhone}${clientAddr}\n`
                        + `*Payment Method:* ${this.paymentMethod.toUpperCase()}\n\n`
                        + `*Order Items:*\n${itemsSummary}\n`
                        + `----------------------------\n`
                        + `*Total:* $${this.cartTotalPrice.toFixed(2)} ${this.currency}\n`
                        + `----------------------------\n`
                        + `Please confirm order delivery. Thank you!`;

                    const sanitizedPhone = (this.storePhone || '').replace(/[^0-9]/g, '');
                    const waUrl = sanitizedPhone
                        ? `https://wa.me/${sanitizedPhone}?text=${encodeURIComponent(message)}`
                        : `https://api.whatsapp.com/send?text=${encodeURIComponent(message)}`;

                    window.open(waUrl, '_blank');
                }
            };
        }
    </script>
</body>
</html>
