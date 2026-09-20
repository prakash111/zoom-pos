@php
    $categories = $categories ?? collect();
    $products = $products ?? collect();
    $languages = $languages ?? \App\Models\Language::query()->where('is_active', true)->orderBy('name')->get();
@endphp
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

    <!-- Tailwind CSS with custom styling & dark mode support -->
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

    <!-- Theme Initialization Script (matches Laravel dark/light pattern) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('pos_store_theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- Razorpay Checkout JS SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <!-- Stripe Checkout JS SDK -->
    <script src="https://js.stripe.com/v3/"></script>

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
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col antialiased selection:bg-emerald-500 selection:text-white transition-colors duration-200"
      x-data="ecommerceStore({
          storeName: {{ json_encode($company->name ?? 'Store') }},
          storePhone: {{ json_encode($company->phone ?? '') }},
          currency: {{ json_encode($company->currency ?? 'USD') }},
          orderEndpoint: '{{ route('tenant.store.order') }}',
          catalogId: '{{ $catalog->id ?? '' }}',
          companySlug: '{{ $company->slug ?? '' }}',
          companyId: '{{ $company->id ?? '' }}',
          enabledPaymentMethods: {{ json_encode($company->getStorefrontPaymentMethods()) }},
          enableGoogleLogin: {{ json_encode((bool) ($company->enable_google_login ?? false)) }},
          enableProductReviews: {{ json_encode((bool) ($company->enable_product_reviews ?? true)) }},
          googleAuthUrl: '{{ route('tenant.store.auth.redirect', ['provider' => 'google']) }}',
          couponValidateUrl: '{{ route('tenant.store.coupon.validate') }}',
          accountUrl: '{{ route('tenant.store.account') }}',
          initialProducts: {{ json_encode(($products ?? collect())->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'category' => $p->category_name ?? '', 'code' => $p->code ?? '', 'price' => (float)($p->sale_price ?? $p->price ?? 0)])) }}
      })">

    <!-- 1. Top Announcement Bar (matching store-idea.mp4) -->
    <div class="bg-emerald-900 dark:bg-slate-900 text-emerald-100 dark:text-slate-300 text-[11px] sm:text-xs py-2 px-4 sm:px-8 border-b border-emerald-800 dark:border-slate-800 transition-colors">
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
            <div class="hidden md:flex items-center gap-2 font-semibold text-emerald-200 dark:text-emerald-400">
                <span>{{ __('Get 50% Off on Selected Items') }}</span>
                <span class="text-emerald-400">&bull;</span>
                <a href="#products-section" class="text-white underline underline-offset-2 hover:text-emerald-300 font-bold transition">
                    {{ __('Shop Now') }}
                </a>
            </div>

            <!-- Right: Dark Mode Toggle, Language Dropdown & Location -->
            <div class="flex items-center gap-3 sm:gap-4">
                
                <!-- Dark / Light Mode Switcher -->
                <button type="button"
                        x-on:click="toggleDarkMode()"
                        class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-emerald-800/80 dark:bg-slate-800 hover:bg-emerald-700 dark:hover:bg-slate-700 text-white text-[11px] font-bold transition cursor-pointer"
                        :title="isDark ? '{{ __('Switch to Light Mode') }}' : '{{ __('Switch to Dark Mode') }}'">
                    <template x-if="!isDark">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <span class="hidden sm:inline">{{ __('Dark') }}</span>
                        </span>
                    </template>
                    <template x-if="isDark">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="hidden sm:inline">{{ __('Light') }}</span>
                        </span>
                    </template>
                </button>

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
                         class="absolute right-0 mt-2 w-40 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 rounded-xl shadow-xl border border-slate-100 dark:border-slate-800 py-1.5 z-50 text-xs font-semibold">
                        @foreach ($languages as $lang)
                            <a href="{{ route('locale.switch', ['locale' => $lang->code]) }}"
                               class="flex items-center justify-between px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 transition {{ app()->getLocale() === $lang->code ? 'bg-emerald-50/80 dark:bg-slate-800/80 font-bold text-emerald-700 dark:text-emerald-400' : '' }}">
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
                <div class="hidden sm:flex items-center gap-1.5 text-emerald-200 dark:text-slate-400">
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
    <header class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-xs transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-3.5 flex items-center justify-between gap-4">
            
            <!-- Store Branding & Logo -->
            <a href="{{ url('/') }}" class="flex items-center gap-3 shrink-0 group">
                @if ($company->logo)
                    <img src="{{ str_starts_with($company->logo, 'http') ? $company->logo : asset($company->logo) }}"
                         alt="{{ $company->name }}"
                         class="w-10 h-10 rounded-2xl object-cover border border-slate-100 dark:border-slate-800 shadow-xs group-hover:scale-105 transition">
                @else
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-black text-base flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-105 transition">
                        {{ strtoupper(substr($company->name ?? 'S', 0, 1)) }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight leading-none group-hover:text-emerald-600 transition">
                            {{ $company->name ?? 'Shopcart' }}
                        </span>
                        <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300">
                            {{ __('Verified') }}
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 font-medium leading-none mt-1 block">
                        {{ __('Online Storefront') }}
                    </span>
                </div>
            </a>

            <!-- Categories Dropdown Trigger -->
            <div class="hidden lg:block relative" x-data="{ catMenuOpen: false }">
                <button type="button"
                        x-on:click="catMenuOpen = !catMenuOpen"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-400 text-xs font-bold transition cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                     class="absolute left-0 mt-2 w-64 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-800 p-2 z-50 space-y-1">
                    <button type="button"
                            x-on:click="selectedCategory = 'all'; catMenuOpen = false; scrollToProducts();"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 transition cursor-pointer"
                            :class="selectedCategory === 'all' ? 'bg-emerald-50 dark:bg-slate-800 text-emerald-700 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300'">
                        <span>{{ __('All Categories') }}</span>
                        <span class="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-full font-extrabold">{{ count($products) }}</span>
                    </button>
                    @foreach ($categories as $cat)
                        <button type="button"
                                x-on:click="selectedCategory = {{ json_encode($cat->name) }}; catMenuOpen = false; scrollToProducts();"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 transition cursor-pointer"
                                :class="selectedCategory === {{ json_encode($cat->name) }} ? 'bg-emerald-50 dark:bg-slate-800 text-emerald-700 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300'">
                            <span class="truncate">{{ $cat->name }}</span>
                            <span class="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-full font-extrabold">{{ $cat->products_count }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Quick Navigation Links (Deals, What's New, Delivery) -->
            <nav class="hidden md:flex items-center gap-6 text-xs font-bold text-slate-600 dark:text-slate-300">
                <a href="#products-section" x-on:click="selectedCategory = 'all'; scrollToProducts();" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                    {{ __('Deals') }}
                </a>
                <a href="#products-section" x-on:click="sortBy = 'newest'; scrollToProducts();" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                    {{ __("What's New") }}
                </a>
                <a href="#services-section" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                    {{ __('Delivery') }}
                </a>
            </nav>

            <!-- Desktop Search Bar with Live Real-time Filtering -->
            <div class="hidden md:block flex-1 max-w-sm mx-4 relative">
                <input type="text"
                       x-model="search"
                       x-on:input="if (search.trim().length > 0) scrollToProducts()"
                       placeholder="{{ __('Search Product...') }}"
                       class="w-full pl-10 pr-8 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 focus:bg-white dark:focus:bg-slate-800 rounded-2xl border-none text-xs sm:text-sm font-medium text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-emerald-500 shadow-inner transition">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="button"
                        x-show="search.length > 0"
                        x-on:click="search = ''"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                    &times;
                </button>
            </div>

            <!-- Right Actions: Wishlist, Customer Account & Cart Trigger -->
            <div class="flex items-center gap-2 sm:gap-3">

                <!-- Wishlist Button -->
                <button type="button"
                        x-on:click="openWishlistModal()"
                        class="relative p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:text-rose-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                        title="{{ __('My Wishlist') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span x-show="wishlistItems.length > 0"
                          x-text="wishlistItems.length"
                          class="absolute -top-1 -right-1 px-1.5 py-0.2 rounded-full bg-rose-500 text-white font-black text-[10px] shadow-xs">
                    </span>
                </button>

                <!-- Customer Account Portal Trigger -->
                <div class="relative" x-data="{ accountMenuOpen: false }">
                    <!-- If Logged In -->
                    <template x-if="customer">
                        <div>
                            <button type="button"
                                    x-on:click="accountMenuOpen = !accountMenuOpen"
                                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-slate-100 transition cursor-pointer">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-extrabold uppercase">
                                    <span x-text="(customer.name || 'C').charAt(0)"></span>
                                </div>
                                <span class="hidden sm:inline max-w-[90px] truncate" x-text="customer.name"></span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Account Dropdown Menu -->
                            <div x-show="accountMenuOpen"
                                 x-cloak
                                 x-on:click.outside="accountMenuOpen = false"
                                 class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-800 py-1.5 z-50 text-xs space-y-1">
                                <div class="px-3.5 py-2 border-b border-slate-100 dark:border-slate-800">
                                    <div class="font-extrabold text-slate-900 dark:text-white truncate" x-text="customer.name"></div>
                                    <div class="text-[10px] text-slate-400 truncate" x-text="customer.email || customer.phone"></div>
                                </div>
                                <button type="button"
                                        x-on:click="accountMenuOpen = false; openProfileModal()"
                                        class="w-full text-left px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                                    <span>👤</span> {{ __('My Profile') }}
                                </button>
                                <button type="button"
                                        x-on:click="accountMenuOpen = false; openOrdersModal()"
                                        class="w-full text-left px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 flex items-center justify-between font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                                    <div class="flex items-center gap-2">
                                        <span>📦</span> {{ __('My Orders') }}
                                    </div>
                                    <span class="text-[10px] bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-extrabold px-1.5 py-0.5 rounded-full" x-text="ordersList.length"></span>
                                </button>
                                <button type="button"
                                        x-on:click="accountMenuOpen = false; openAddressesModal()"
                                        class="w-full text-left px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                                    <span>📍</span> {{ __('Saved Addresses') }}
                                </button>
                                <button type="button"
                                        x-on:click="accountMenuOpen = false; openWishlistModal()"
                                        class="w-full text-left px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                                    <span>❤️</span> {{ __('My Wishlist') }}
                                </button>
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-1">
                                    <button type="button"
                                            x-on:click="accountMenuOpen = false; logoutCustomer()"
                                            class="w-full text-left px-3.5 py-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2 font-bold transition cursor-pointer">
                                        <span>🚪</span> {{ __('Sign Out') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- If Not Logged In -->
                    <template x-if="!customer">
                        <button type="button"
                                x-on:click="openAuthModal('login')"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-bold transition cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="hidden sm:inline">{{ __('Sign In') }}</span>
                        </button>
                    </template>
                </div>

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
                        $<span x-text="cartFinalPrice.toFixed(2)"></span>
                    </span>
                </button>
            </div>

        </div>

        <!-- Dedicated Mobile Search Bar -->
        <div class="md:hidden px-4 pb-3 pt-0">
            <div class="relative w-full">
                <input type="text"
                       x-model="search"
                       x-on:input="if (search.trim().length > 0) scrollToProducts()"
                       placeholder="{{ __('Search Product...') }}"
                       class="w-full pl-10 pr-9 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 focus:bg-white dark:focus:bg-slate-800 rounded-2xl border border-transparent focus:border-emerald-500 text-xs sm:text-sm font-medium text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-emerald-500/20 shadow-inner transition">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="button"
                        x-show="search.length > 0"
                        x-on:click="search = ''"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer text-base">
                    &times;
                </button>
            </div>
        </div>
    </header>

    <!-- Main Dynamic Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-8 py-6 space-y-8 sm:space-y-10">
        @yield('content')
    </main>

    @yield('modals')

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

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10 rtl:pl-0 rtl:pr-6 sm:rtl:pr-10 rtl:left-0 rtl:right-auto">
            <div class="w-screen max-w-md sm:max-w-lg bg-white dark:bg-slate-900 shadow-2xl flex flex-col h-full max-h-[100dvh] text-slate-900 dark:text-slate-100 overflow-hidden"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full rtl:-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full rtl:-translate-x-full">
                
                <!-- Drawer Header (Fixed with Safe Area padding) -->
                <div class="px-4 sm:px-5 py-3.5 sm:py-4 pt-[max(1rem,env(safe-area-inset-top))] border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-800/80 backdrop-blur-md shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-tight">{{ __('Your Shopping Cart') }}</h3>
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-medium">
                                <span x-text="cartTotalCount"></span> {{ __('items selected') }}
                            </span>
                        </div>
                    </div>
                    <button type="button"
                            x-on:click="cartDrawerOpen = false"
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer"
                            aria-label="Close cart">
                        &times;
                    </button>
                </div>

                <!-- Scrollable Body (Items + Customer Info + Payment + Coupon) -->
                <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-4">
                    <!-- Cart Items List -->
                    <div class="space-y-2.5">
                        <template x-for="item in cart" :key="item.id">
                            <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/60 hover:bg-emerald-50/40 dark:hover:bg-slate-800 p-2.5 sm:p-3 rounded-2xl border border-slate-100 dark:border-slate-800 transition">
                                <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-700 overflow-hidden shrink-0 flex items-center justify-center">
                                        <template x-if="item.image">
                                            <img :src="item.image" :alt="item.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!item.image">
                                            <span class="text-xs font-bold text-slate-400">📦</span>
                                        </template>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200 truncate" x-text="item.name"></h4>
                                        <div class="text-[11px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-extrabold mt-0.5">
                                            $<span x-text="item.price.toFixed(2)"></span> &bull; <span class="text-slate-400">{{ __('Total:') }}</span> $<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stepper & Remove -->
                                <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                                    <div class="flex items-center border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 overflow-hidden shadow-2xs">
                                        <button type="button"
                                                x-on:click="decreaseQty(item.id)"
                                                class="w-6 h-6 sm:w-7 sm:h-7 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-emerald-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer text-xs font-black select-none">
                                            &minus;
                                        </button>
                                        <span class="px-1.5 sm:px-2 font-mono font-extrabold text-xs text-slate-800 dark:text-slate-200 text-center select-none min-w-[1.25rem] sm:min-w-[1.5rem]" x-text="item.quantity"></span>
                                        <button type="button"
                                                x-on:click="increaseQty(item.id)"
                                                class="w-6 h-6 sm:w-7 sm:h-7 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-emerald-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer text-xs font-black select-none">
                                            +
                                        </button>
                                    </div>
                                    <button type="button"
                                            x-on:click="removeItem(item.id)"
                                            class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center justify-center text-xs transition cursor-pointer"
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
                            <div class="py-16 text-center text-slate-400 space-y-3">
                                <div class="w-16 h-16 mx-auto rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-2xl text-slate-400">
                                    🛒
                                </div>
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('Your cart is currently empty') }}</div>
                                <p class="text-xs text-slate-400 max-w-xs mx-auto">
                                    {{ __('Browse our products and add your favorite items to start checkout.') }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- Checkout Form inside scrollable area -->
                    <template x-if="cart.length > 0">
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <!-- Delivery Details Form -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                        {{ __('Customer & Delivery Details') }}
                                    </label>
                                    <!-- Use Saved Address Pill if Logged in -->
                                    <template x-if="customer && savedAddresses.length > 0">
                                        <button type="button"
                                                x-on:click="openAddressesModal()"
                                                class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                            📍 {{ __('Saved Addresses') }} (<span x-text="savedAddresses.length"></span>)
                                        </button>
                                    </template>
                                </div>

                                <!-- Saved Address quick picker -->
                                <template x-if="customer && savedAddresses.length > 0">
                                    <div class="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                                        <template x-for="addr in savedAddresses" :key="addr.id">
                                            <button type="button"
                                                    x-on:click="selectAddressForCheckout(addr)"
                                                    class="shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer"
                                                    :class="deliveryAddress === addr.street_address ? 'bg-emerald-100 dark:bg-emerald-900/60 border-emerald-500 text-emerald-800 dark:text-emerald-300' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'">
                                                <span x-text="addr.type || 'Address'"></span>: <span x-text="addr.city || addr.street_address"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <!-- Row 1: Full Name * | Phone / WhatsApp * -->
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text"
                                           x-model="customerName"
                                           placeholder="{{ __('Full Name *') }}"
                                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                                    <input type="tel"
                                           x-model="customerPhone"
                                           placeholder="{{ __('Phone / WhatsApp *') }}"
                                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                                </div>
                                <!-- Row 2: Email Address (Optional) | City * -->
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="email"
                                           x-model="customerEmail"
                                           placeholder="{{ __('Email Address (Optional)') }}"
                                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                                    <input type="text"
                                           x-model="deliveryCity"
                                           placeholder="{{ __('City *') }}"
                                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                                </div>
                                <!-- Row 3: Street Address * -->
                                <div class="w-full">
                                    <input type="text"
                                           x-model="deliveryAddress"
                                           placeholder="{{ __('Street Address *') }}"
                                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                                </div>

                                <!-- Payment Method Radio Grid (Dynamic Tenant Gateways) -->
                                <div class="pt-1.5">
                                    <label class="block text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                                        <span>{{ __('Payment Method') }}</span>
                                        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">🔒 {{ __('Secure Payment') }}</span>
                                    </label>
                                    <div class="space-y-2 text-left">
                                        <template x-for="method in paymentMethods" :key="method.id">
                                            <label class="p-3 rounded-2xl border cursor-pointer text-xs font-bold transition flex items-center justify-between gap-3 relative select-none hover:shadow-xs"
                                                   :class="paymentMethod === method.id ? 'border-emerald-500 bg-emerald-50/90 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-200 ring-2 ring-emerald-500/20 shadow-xs' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600'">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <input type="radio" :value="method.id" x-model="paymentMethod" class="sr-only">
                                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-base"
                                                         :class="paymentMethod === method.id ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                                                        <span x-text="getMethodIcon(method.id)"></span>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-extrabold text-xs sm:text-sm leading-snug text-slate-900 dark:text-white" x-text="method.name"></div>
                                                        <div class="text-[11px] text-slate-400 dark:text-slate-400 leading-tight mt-0.5" x-text="method.instructions || ''"></div>
                                                    </div>
                                                </div>
                                                <div class="shrink-0">
                                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition"
                                                         :class="paymentMethod === method.id ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700'">
                                                        <template x-if="paymentMethod === method.id">
                                                            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </template>
                                                    </div>
                                                </div>
                                            </label>
                                        </template>
                                    </div>

                                    <!-- Specific Gateway Dynamic Info -->
                                    <template x-if="getSelectedPaymentMethod() && getSelectedPaymentMethod().upi_id">
                                        <div class="mt-2 p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200 space-y-2">
                                            <div class="font-extrabold flex items-center justify-between">
                                                <span>📱 {{ __('Merchant UPI ID:') }}</span>
                                                <span class="font-mono bg-white dark:bg-slate-900 px-2.5 py-1 rounded-lg border border-amber-300 dark:border-amber-700 select-all font-bold text-amber-700 dark:text-amber-300" x-text="getSelectedPaymentMethod().upi_id"></span>
                                            </div>
                                            <p class="text-[11px] text-amber-800 dark:text-amber-300">{{ __('Pay using Google Pay, PhonePe, Paytm, BHIM, or any UPI app.') }}</p>
                                            <div class="pt-1 flex items-center gap-2">
                                                <a :href="'upi://pay?pa=' + encodeURIComponent(getSelectedPaymentMethod().upi_id) + '&pn=' + encodeURIComponent(storeName) + '&am=' + cartFinalPrice.toFixed(2) + '&cu=' + encodeURIComponent(currency) + '&tn=Order'"
                                                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-[11px] shadow-sm transition active:scale-95">
                                                    <span>📲</span>
                                                    <span>{{ __('Open UPI App to Pay') }}</span>
                                                </a>
                                                <button type="button"
                                                        x-on:click="navigator.clipboard.writeText(getSelectedPaymentMethod().upi_id); alert('{{ __('UPI ID copied to clipboard!') }}');"
                                                        class="px-2.5 py-1.5 rounded-xl border border-amber-300 dark:border-amber-700 bg-white dark:bg-slate-900 text-[11px] font-bold text-amber-800 dark:text-amber-200 hover:bg-amber-100 transition">
                                                    📋 {{ __('Copy ID') }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="paymentMethod === 'stripe' || paymentMethod === 'card' || paymentMethod === 'shopcart_card'">
                                        <div class="mt-2 p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                                <span>{{ __('Credit / Debit Card Checkout') }}</span>
                                                <span class="text-emerald-600 font-mono text-[10px]">🔒 256-bit SSL</span>
                                            </div>
                                            <input type="text"
                                                   placeholder="•••• •••• •••• 4242"
                                                   value="4242 •••• •••• 4242"
                                                   class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-1.5 px-2.5 font-mono text-xs">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" placeholder="MM/YY" value="12/28" class="rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-1.5 px-2.5 font-mono text-xs">
                                                <input type="text" placeholder="CVV" value="888" class="rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 py-1.5 px-2.5 font-mono text-xs">
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="paymentMethod === 'razorpay'">
                                        <div class="mt-2 p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-[11px] text-blue-900 dark:text-blue-200 flex items-center justify-between">
                                            <span>⚡ {{ __('Instant UPI, Cards & NetBanking via Razorpay') }}</span>
                                            <span class="font-bold text-blue-600 dark:text-blue-400 underline">{{ __('Secure') }}</span>
                                        </div>
                                    </template>

                                    <template x-if="paymentMethod === 'paypal'">
                                        <div class="mt-2 p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-[11px] text-sky-800 dark:text-sky-300 flex items-center justify-between">
                                            <span>🅿️ {{ __('PayPal express checkout will verify on order placement.') }}</span>
                                            <span class="font-bold underline">{{ __('Active') }}</span>
                                        </div>
                                    </template>
                                </div>

                                <!-- Coupon Discount Input -->
                                <div class="pt-1.5 space-y-1.5">
                                    <div class="flex gap-2">
                                        <input type="text"
                                               x-model="couponCode"
                                               x-on:keydown.enter.prevent="applyCoupon()"
                                               placeholder="{{ __('Coupon Code (e.g. WELCOME10)') }}"
                                               class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3 uppercase">
                                        <button type="button"
                                                x-on:click="applyCoupon()"
                                                class="px-4 py-2 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 text-white text-xs font-bold transition cursor-pointer shrink-0 active:scale-95">
                                            {{ __('Apply') }}
                                        </button>
                                    </div>

                                    <!-- Coupon Inline Error Message -->
                                    <template x-if="couponError">
                                        <div class="flex items-center justify-between px-2.5 py-1.5 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 rounded-xl text-[11px] text-rose-800 dark:text-rose-300 font-bold animate-in fade-in">
                                            <span x-text="couponError"></span>
                                            <button type="button" x-on:click="couponError = ''" class="text-rose-500 hover:text-rose-700 font-bold ml-2 cursor-pointer">&times;</button>
                                        </div>
                                    </template>

                                    <!-- Coupon Inline Success Message -->
                                    <template x-if="appliedCoupon">
                                        <div class="flex items-center justify-between px-2.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded-xl text-[11px] text-emerald-800 dark:text-emerald-300 font-bold animate-in fade-in">
                                            <span>🎉 {{ __('Code') }} <strong x-text="appliedCoupon.code"></strong> {{ __('applied') }}: -$<span x-text="appliedCoupon.amount.toFixed(2)"></span></span>
                                            <button type="button" x-on:click="removeCoupon()" class="text-rose-500 hover:text-rose-700 font-bold ml-2 cursor-pointer">&times; {{ __('Remove') }}</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Sticky Bottom Cart Footer (Totals + CTAs + Safe Area Inset) -->
                <template x-if="cart.length > 0">
                    <div class="shrink-0 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 sm:px-5 py-3 sm:py-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl space-y-2.5">
                        <!-- Totals Breakdown -->
                        <div class="space-y-1 text-xs">
                            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                <span class="font-medium">{{ __('Subtotal:') }}</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200 text-right shrink-0 whitespace-nowrap">$<span x-text="cartTotalPrice.toFixed(2)"></span></span>
                            </div>
                            <template x-if="appliedCoupon">
                                <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                                    <span class="font-medium">{{ __('Discount:') }}</span>
                                    <span class="font-mono font-bold text-right shrink-0 whitespace-nowrap">-$<span x-text="appliedCoupon.amount.toFixed(2)"></span></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                <span class="font-medium">{{ __('Estimated Delivery:') }}</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-right shrink-0 uppercase tracking-wide">{{ __('FREE') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm font-extrabold text-slate-900 dark:text-white pt-1.5 border-t border-slate-100 dark:border-slate-800">
                                <span>{{ __('Total Order Amount:') }}</span>
                                <span class="text-base text-emerald-600 dark:text-emerald-400 font-mono font-black text-right shrink-0 whitespace-nowrap">$<span x-text="cartFinalPrice.toFixed(2)"></span></span>
                            </div>
                        </div>

                        <!-- Order CTAs: Place Order & WhatsApp -->
                        <div class="space-y-2 pt-1">
                            <button type="button"
                                    x-on:click="submitOrderToStore()"
                                    :disabled="isSubmitting || cart.length === 0"
                                    class="w-full py-3 sm:py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-600/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <span x-text="checkoutButtonText"></span>
                            </button>

                            <button type="button"
                                    x-on:click="orderViaWhatsApp()"
                                    :disabled="cart.length === 0"
                                    class="w-full py-2.5 sm:py-3 rounded-2xl bg-[#25D366] hover:bg-[#1EBE5D] disabled:opacity-40 text-white font-extrabold text-xs tracking-wide shadow-sm active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
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

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-sm w-full shadow-2xl text-center space-y-4 border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-90 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <!-- Animated Green Checkmark -->
            <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div class="space-y-1">
                <h3 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Your order has been accepted!') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                    {{ __('Transaction ID:') }}
                    <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 px-2 py-0.5 rounded-md" x-text="confirmedSaleNumber"></span>
                </p>
                <div class="pt-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black"
                          :class="isOrderPaid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300 dark:border-amber-700'">
                        <span x-text="isOrderPaid ? '✓ {{ __('Payment Confirmed Online') }}' : '⏱ {{ __('Pay on Delivery / Cash') }}'"></span>
                    </span>
                </div>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ __('Thank you for your order! We have recorded your items and our team is preparing your package.') }}
            </p>

            <template x-if="confirmedTrackingCode">
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs space-y-1 text-center">
                    <div class="text-[10.5px] text-slate-400 font-bold uppercase tracking-wider">{{ __('Live Order Tracking Code') }}</div>
                    <div class="font-mono font-black text-sm text-emerald-600 dark:text-emerald-400" x-text="confirmedTrackingCode"></div>
                    <a :href="confirmedTrackingUrl" class="inline-block pt-1 text-xs font-extrabold text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('Open Live Tracking Stepper') }} →
                    </a>
                </div>
            </template>

            <div class="space-y-2 pt-2">
                <button type="button"
                        x-on:click="orderAcceptedModalOpen = false; if(customer) openOrdersModal();"
                        class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold text-xs tracking-wide shadow-md transition cursor-pointer">
                    <span x-text="customer ? '{{ __('View Order in My Account') }}' : '{{ __('Continue Shopping') }}'"></span>
                </button>
                <template x-if="customer">
                    <button type="button"
                            x-on:click="orderAcceptedModalOpen = false"
                            class="w-full py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
                        {{ __('Continue Shopping') }}
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- 4.5 Customer Account Verification Modal (OTP Gate before Order Placement) -->
    <div x-show="verificationModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="verificationModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="verificationModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <!-- Modal Header -->
            <div class="text-center space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-2xl shadow-inner">
                    🔐
                </div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Verify Your Account') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="verificationMessage || '{{ __('Please enter the 6-digit verification code sent to your email or phone number to confirm your order.') }}'">
                </p>
                
                <!-- Channel Badges -->
                <template x-if="verificationChannels && verificationChannels.length > 0">
                    <div class="flex items-center justify-center gap-2 pt-1 flex-wrap">
                        <template x-for="ch in verificationChannels" :key="ch">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center gap-1 border border-slate-200 dark:border-slate-700">
                                <span x-text="ch === 'email' ? '✉️' : (ch === 'whatsapp' ? '🟢' : '💬')"></span>
                                <span x-text="ch"></span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Error Banner -->
            <template x-if="verificationError">
                <div class="p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
                    <span class="text-sm shrink-0">⚠️</span>
                    <span x-text="verificationError"></span>
                </div>
            </template>

            <!-- Resend Success Banner -->
            <template x-if="resendSuccessMessage">
                <div class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2">
                    <span class="text-sm shrink-0">✓</span>
                    <span x-text="resendSuccessMessage"></span>
                </div>
            </template>

            <!-- Verification Code Form -->
            <form x-on:submit.prevent="verifyAndSubmitOrder()" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 text-center uppercase tracking-wider">
                        {{ __('Enter 6-Digit OTP Code') }}
                    </label>
                    <input type="text"
                           x-model="verificationCode"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           placeholder="••••••"
                           class="w-full text-center text-2xl font-mono font-black tracking-widest py-3 px-4 rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-emerald-500 focus:ring-0 transition"
                           required
                           autofocus>
                    <div class="text-[11px] text-slate-400 text-center mt-1.5">
                        {{ __('Code is valid for 10 minutes.') }}
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <button type="submit"
                            :disabled="isVerifying || isSubmitting"
                            class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-600/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                        <template x-if="isVerifying || isSubmitting">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </template>
                        <span>{{ __('Verify & Place Order') }}</span>
                    </button>

                    <div class="flex items-center justify-between text-xs pt-2">
                        <button type="button"
                                x-on:click="resendVerificationCode()"
                                :disabled="isResendingVerification"
                                class="text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 font-bold transition flex items-center gap-1 cursor-pointer disabled:opacity-50">
                            <span x-show="!isResendingVerification">🔄 {{ __('Resend Code') }}</span>
                            <span x-show="isResendingVerification">⏳ {{ __('Resending...') }}</span>
                        </button>

                        <button type="button"
                                x-on:click="verificationModalOpen = false"
                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. Customer Authentication Modal (Login & Register) -->
    <div x-show="authModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="authModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="authModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <!-- Tabs: Sign In / Register -->
            <div class="flex border-b border-slate-200 dark:border-slate-800">
                <button type="button"
                        x-on:click="authMode = 'login'; authError = ''"
                        class="flex-1 pb-3 text-center text-sm font-extrabold transition cursor-pointer border-b-2"
                        :class="authMode === 'login' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-600'">
                    {{ __('Sign In') }}
                </button>
                <button type="button"
                        x-on:click="authMode = 'register'; authError = ''"
                        class="flex-1 pb-3 text-center text-sm font-extrabold transition cursor-pointer border-b-2"
                        :class="authMode === 'register' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-600'">
                    {{ __('Create Account') }}
                </button>
            </div>

            <!-- Error Banner -->
            <template x-if="authError">
                <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-xs font-semibold" x-text="authError"></div>
            </template>

            <!-- Sign In Form -->
            <form x-show="authMode === 'login'" x-on:submit.prevent="submitLogin()" class="space-y-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email or Phone Number') }}</label>
                    <input type="text"
                           x-model="loginIdentifier"
                           required
                           placeholder="you@example.com or +1555..."
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2.5 px-3">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Password') }}</label>
                    <input type="password"
                           x-model="loginPassword"
                           required
                           placeholder="••••••••"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2.5 px-3">
                </div>
                <button type="submit"
                        :disabled="isAuthLoading"
                        class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs tracking-wide shadow-md transition cursor-pointer">
                    <span x-text="isAuthLoading ? '{{ __('Authenticating...') }}' : '{{ __('Sign In') }}'"></span>
                </button>
            </form>

            <!-- Register Form -->
            <form x-show="authMode === 'register'" x-on:submit.prevent="submitRegister()" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Full Name *') }}</label>
                    <input type="text" x-model="regName" required placeholder="{{ __('Full Name *') }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email Address') }}</label>
                        <input type="email" x-model="regEmail" placeholder="{{ __('Email Address') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone Number') }}</label>
                        <input type="tel" x-model="regPhone" placeholder="{{ __('Phone Number') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Password (min 6 characters) *') }}</label>
                    <input type="password" x-model="regPassword" required minlength="6" placeholder="••••••••"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('City') }}</label>
                        <input type="text" x-model="regCity" placeholder="{{ __('City') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Street Address') }}</label>
                        <input type="text" x-model="regAddress" placeholder="{{ __('Street Address') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                    </div>
                </div>
                <button type="submit"
                        :disabled="isAuthLoading"
                        class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs tracking-wide shadow-md transition cursor-pointer">
                    <span x-text="isAuthLoading ? '{{ __('Creating Account...') }}' : '{{ __('Create Account') }}'"></span>
                </button>
            </form>

            <!-- Google Social Login Option -->
            <template x-if="enableGoogleLogin">
                <div class="space-y-3 pt-2">
                    <div class="relative flex items-center justify-center">
                        <div class="border-t border-slate-200 dark:border-slate-800 w-full"></div>
                        <span class="bg-white dark:bg-slate-900 px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider relative z-10">{{ __('Or continue with') }}</span>
                    </div>

                    <a :href="googleAuthUrl"
                       class="w-full py-2.5 px-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-2.5 transition active:scale-98 shadow-2xs">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>{{ __('Sign In with Google') }}</span>
                    </a>
                </div>
            </template>
        </div>
    </div>

    <!-- 6. Customer Profile Modal -->
    <div x-show="profileModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="profileModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-4 border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="profileModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ __('My Profile') }}</h3>

            <template x-if="profileMessage">
                <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold" x-text="profileMessage"></div>
            </template>

            <form x-on:submit.prevent="updateCustomerProfile()" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Full Name') }}</label>
                    <input type="text" x-model="profileForm.name" required
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone Number') }}</label>
                    <input type="tel" x-model="profileForm.phone"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Default City') }}</label>
                    <input type="text" x-model="profileForm.city"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Default Address') }}</label>
                    <input type="text" x-model="profileForm.address"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 py-2 px-3">
                </div>
                <button type="submit"
                        class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs tracking-wide shadow-md transition cursor-pointer">
                    {{ __('Save Changes') }}
                </button>
            </form>
        </div>
    </div>

    <!-- 7. Customer Order History Modal -->
    <div x-show="ordersModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="ordersModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-xl w-full shadow-2xl space-y-4 max-h-[85vh] flex flex-col border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="ordersModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ __('My Past Orders') }}</h3>
                <p class="text-xs text-slate-400">{{ __('Track and review your purchases at this store.') }}</p>
            </div>

            <!-- Orders List -->
            <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                <template x-for="order in ordersList" :key="order.id">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-mono font-bold text-xs text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-md" x-text="order.sale_number"></span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize"
                                  :class="order.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                  x-text="order.status || 'pending'"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                            <div>
                                <span class="text-slate-400 text-[10px]" x-text="order.created_at ? new Date(order.created_at).toLocaleDateString() : ''"></span>
                                <span class="mx-1">&bull;</span>
                                <span class="capitalize font-semibold" x-text="order.payment_method || 'COD'"></span>
                            </div>
                            <div class="font-black text-slate-900 dark:text-white font-mono text-sm">
                                $<span x-text="parseFloat(order.total || order.net_amount || 0).toFixed(2)"></span>
                            </div>
                        </div>
                        <template x-if="order.delivery_address">
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                📍 <span x-text="order.delivery_address"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="ordersList.length === 0">
                    <div class="py-12 text-center text-slate-400 space-y-2">
                        <div class="text-3xl">📦</div>
                        <div class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('No orders yet.') }}</div>
                        <p class="text-xs">{{ __('Orders placed on this store will appear here with live tracking.') }}</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- 8. Customer Saved Addresses Modal -->
    <div x-show="addressesModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="addressesModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-4 max-h-[85vh] flex flex-col border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="addressesModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Saved Delivery Addresses') }}</h3>
                <p class="text-xs text-slate-400">{{ __('Manage your delivery locations for faster checkout.') }}</p>
            </div>

            <!-- List -->
            <div class="space-y-2 flex-1 overflow-y-auto pr-1">
                <template x-for="addr in savedAddresses" :key="addr.id">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between">
                        <div class="space-y-0.5 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-extrabold text-xs text-slate-900 dark:text-white capitalize" x-text="addr.type || 'Home'"></span>
                                <template x-if="addr.is_default">
                                    <span class="text-[9px] font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 px-1.5 py-0.2 rounded-md">{{ __('Default') }}</span>
                                </template>
                            </div>
                            <div class="text-xs text-slate-600 dark:text-slate-300 truncate" x-text="addr.street_address"></div>
                            <div class="text-[10px] text-slate-400" x-text="addr.city ? addr.city + (addr.state ? ', ' + addr.state : '') : ''"></div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button"
                                    x-on:click="selectAddressForCheckout(addr); addressesModalOpen = false; cartDrawerOpen = true;"
                                    class="px-2.5 py-1 rounded-xl bg-emerald-600 text-white font-bold text-[10px] hover:bg-emerald-700 transition cursor-pointer">
                                {{ __('Use') }}
                            </button>
                            <button type="button"
                                    x-on:click="deleteAddress(addr.id)"
                                    class="p-1 rounded-xl text-slate-400 hover:text-rose-600 transition cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Add New Address Toggle / Form -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800" x-data="{ adding: false }">
                <button type="button"
                        x-show="!adding"
                        x-on:click="adding = true"
                        class="w-full py-2.5 rounded-2xl border border-dashed border-emerald-500 text-emerald-600 font-bold text-xs hover:bg-emerald-50 dark:hover:bg-slate-800 transition cursor-pointer">
                    + {{ __('Add New Address') }}
                </button>
                <form x-show="adding" x-on:submit.prevent="saveNewAddress(); adding = false;" class="space-y-2 text-xs">
                    <input type="text" x-model="newAddrStreet" placeholder="{{ __('Street Address *') }}" required
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" x-model="newAddrCity" placeholder="{{ __('City *') }}" required
                               class="rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3">
                        <input type="text" x-model="newAddrType" placeholder="{{ __('Type (Home/Office)') }}"
                               class="rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 py-2 bg-emerald-600 text-white rounded-xl font-bold cursor-pointer">{{ __('Save Address') }}</button>
                        <button type="button" x-on:click="adding = false" class="px-3 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold cursor-pointer">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 9. Customer Wishlist Modal / Drawer -->
    <div x-show="wishlistModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="wishlistModalOpen = false"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-4 max-h-[85vh] flex flex-col border border-slate-100 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="wishlistModalOpen = false"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>❤️</span> {{ __('My Wishlist') }}
                </h3>
                <p class="text-xs text-slate-400">{{ __('Saved items to purchase later.') }}</p>
            </div>

            <div class="space-y-3 flex-1 overflow-y-auto pr-1">
                <template x-for="item in wishlistItems" :key="item.id">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between gap-3">
                        <div class="w-12 h-12 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-700 overflow-hidden shrink-0 flex items-center justify-center">
                            <template x-if="item.image_url">
                                <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!item.image_url">
                                <span class="text-lg">🛍️</span>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-extrabold text-xs text-slate-900 dark:text-white truncate" x-text="item.name"></h4>
                            <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">$<span x-text="parseFloat(item.sale_price || item.price || 0).toFixed(2)"></span></div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button"
                                    x-on:click="addToCart(item); removeFromWishlist(item.id);"
                                    class="px-2.5 py-1.5 rounded-xl bg-emerald-600 text-white font-extrabold text-[10px] hover:bg-emerald-700 transition cursor-pointer">
                                {{ __('+ Cart') }}
                            </button>
                            <button type="button"
                                    x-on:click="removeFromWishlist(item.id)"
                                    class="p-1 rounded-xl text-slate-400 hover:text-rose-500 transition cursor-pointer">
                                &times;
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="wishlistItems.length === 0">
                    <div class="py-12 text-center text-slate-400 space-y-2">
                        <div class="text-3xl">🤍</div>
                        <div class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('Wishlist is empty.') }}</div>
                        <p class="text-xs">{{ __('Click the heart icon on any product card to save it here.') }}</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- 10. Storefront FAQ Interactive Modal (matching read/faqs.png) -->
    <div x-show="faqsModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="closeFaqsModal()"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl space-y-5 max-h-[90vh] flex flex-col border border-slate-100 dark:border-slate-800 z-10"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <button type="button"
                    x-on:click="closeFaqsModal()"
                    class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-lg transition cursor-pointer">
                &times;
            </button>

            <!-- Header -->
            <div class="space-y-1.5 pr-8">
                <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    <span>❓</span>
                    <span>{{ __('Help Center & Store Guidance') }}</span>
                </div>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ __('Frequently Asked Questions') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Answers provided directly by store staff regarding payments, order dispatch, returns, and store policies.') }}
                </p>
            </div>

            <!-- Search Filter -->
            <div class="relative">
                <input type="text"
                       x-model="faqsSearch"
                       placeholder="{{ __('Search questions or keywords (e.g. delivery, COD, returns)...') }}"
                       class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/80 text-xs sm:text-sm pl-10 pr-4 py-2.5 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 transition">
                <span class="absolute left-3.5 top-3 text-slate-400 text-sm">🔍</span>
                <button type="button"
                        x-show="faqsSearch"
                        x-on:click="faqsSearch = ''"
                        class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer font-bold">
                    &times;
                </button>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar shrink-0 text-xs">
                <button type="button"
                        x-on:click="faqsCategory = 'all'"
                        :class="faqsCategory === 'all' ? 'bg-emerald-600 text-white font-extrabold shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3.5 py-1.5 rounded-xl transition shrink-0 cursor-pointer">
                    {{ __('All Topics') }}
                </button>
                <template x-for="cat in faqsCategories" :key="cat">
                    <button type="button"
                            x-on:click="faqsCategory = cat"
                            :class="faqsCategory === cat ? 'bg-emerald-600 text-white font-extrabold shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold hover:bg-slate-200 dark:hover:bg-slate-700'"
                            class="px-3.5 py-1.5 rounded-xl transition shrink-0 capitalize cursor-pointer"
                            x-text="cat">
                    </button>
                </template>
            </div>

            <!-- FAQ Accordion List -->
            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                <template x-for="faq in filteredFaqs" :key="faq.id">
                    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-800/60 overflow-hidden transition-colors shadow-2xs">
                        <button type="button"
                                x-on:click="toggleFaq(faq.id)"
                                class="w-full p-4 text-left flex items-center justify-between gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-800 transition cursor-pointer">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 shrink-0" x-text="faq.category || 'General'"></span>
                                <span class="font-extrabold text-xs sm:text-sm text-slate-900 dark:text-white leading-snug" x-text="faq.question"></span>
                            </div>
                            <span class="text-slate-400 font-bold text-sm shrink-0 transition-transform duration-200"
                                  :class="faqsExpandedId === faq.id ? 'rotate-180 text-emerald-600' : ''">
                                ▼
                            </span>
                        </button>
                        <div x-show="faqsExpandedId === faq.id"
                             x-collapse
                             class="px-4 pb-4 pt-1 text-xs text-slate-600 dark:text-slate-300 leading-relaxed border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/30">
                            <p x-text="faq.answer" class="whitespace-pre-line"></p>
                        </div>
                    </div>
                </template>

                <template x-if="filteredFaqs.length === 0">
                    <div class="py-12 text-center text-slate-400 space-y-2">
                        <div class="text-3xl">🔍</div>
                        <div class="font-bold text-sm text-slate-700 dark:text-slate-300">{{ __('No matching FAQs found') }}</div>
                        <p class="text-xs">{{ __('Try different search keywords or select another topic.') }}</p>
                    </div>
                </template>
            </div>

            <!-- Footer: Quick Store Contact -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <span class="text-slate-500 dark:text-slate-400">{{ __('Still have questions?') }}</span>
                <div class="flex items-center gap-2">
                    @if ($company->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $company->phone) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold hover:bg-emerald-100 transition">
                            <span>💬</span>
                            <span>{{ __('WhatsApp Us') }}</span>
                        </a>
                        <a href="tel:{{ $company->phone }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 transition">
                            <span>📞</span>
                            <span>{{ __('Call Store') }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 11. Store Footer -->
    <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 mt-8 sm:mt-16 pt-10 sm:pt-12 pb-8 px-4 sm:px-8 transition-colors">
        <div class="max-w-7xl mx-auto space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 sm:gap-8">
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
                        <span class="font-extrabold text-base text-slate-900 dark:text-white">{{ $company->name }}</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Your trusted digital store for quality items, fast home delivery, and verified checkout.') }}
                    </p>
                </div>

                <!-- Department / Categories -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">{{ __('Department') }}</h5>
                    <ul class="space-y-1.5 text-slate-500 dark:text-slate-400 font-medium">
                        @foreach ($categories->take(4) as $cat)
                            <li>
                                <a href="#products-section" x-on:click="selectedCategory = {{ json_encode($cat->name) }}; scrollToProducts();" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                                    {{ $cat->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Help -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">{{ __('Help & Support') }}</h5>
                    <ul class="space-y-1.5 text-slate-500 dark:text-slate-400 font-medium">
                        <li><a href="#services-section" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ __('Delivery Terms') }}</a></li>
                        <li><a href="#services-section" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ __('Order Tracking') }}</a></li>
                        <li><a href="#services-section" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ __('Returns Policy') }}</a></li>
                    </ul>
                </div>

                <!-- Contact Details -->
                <div class="space-y-2.5 text-xs">
                    <h5 class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">{{ __('Contact Store') }}</h5>
                    <div class="space-y-1.5 text-slate-500 dark:text-slate-400">
                        @if ($company->phone)
                            <div>📞 <a href="tel:{{ $company->phone }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 font-bold">{{ $company->phone }}</a></div>
                        @endif
                        @if ($company->email)
                            <div>✉️ <a href="mailto:{{ $company->email }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 font-bold">{{ $company->email }}</a></div>
                        @endif
                        @if ($company->address)
                            <div>📍 {{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright & Safe Payment Badges -->
            <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <div>
                    &copy; {{ date('Y') }} {{ $company->name }}. {{ __('All rights reserved. Powered by ZoomPOS.') }}
                </div>
                <div class="flex items-center gap-3 font-semibold text-[11px]">
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">🛡️ {{ __('100% Secure Checkout') }}</span>
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">🚚 {{ __('Fast Dispatch') }}</span>
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
                companySlug: config.companySlug || '',
                companyId: config.companyId || '',
                productsList: config.initialProducts || [],

                // Dark mode state
                isDark: document.documentElement.classList.contains('dark'),

                // Catalog & Filter state
                search: '',
                selectedCategory: 'all',
                priceFilter: 'all',
                sortBy: 'featured',
                cartDrawerOpen: false,
                orderAcceptedModalOpen: false,
                isSubmitting: false,
                confirmedSaleNumber: '',
                isOrderPaid: false,

                get filteredProductsCount() {
                    return this.productsList.filter(p => this.matchesFilter(p.name, p.category, p.code, p.price)).length;
                },

                // Selected product modal state
                detailModalOpen: false,
                detailModalTab: 'overview',
                modalProduct: null,
                modalQty: 1,

                // Reviews state & toggles
                enableProductReviews: config.enableProductReviews !== false,
                productReviews: [],
                reviewsStats: { average_rating: 5.0, total_reviews: 0 },
                reviewsDistribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 },
                isLoadingReviews: false,
                reviewFormOpen: false,
                isSubmittingReview: false,
                reviewRating: 5,
                reviewTitle: '',
                reviewComment: '',
                reviewCustomerName: '',
                reviewCustomerEmail: '',
                reviewSuccessMsg: '',
                reviewErrorMsg: '',

                // Coupon feedback messages
                couponError: '',
                couponSuccess: '',

                // Customer Verification state (OTP Gate)
                verificationModalOpen: false,
                verificationCode: '',
                verificationError: '',
                verificationMessage: '',
                verificationChannels: [],
                verificationCustomerId: null,
                isVerifying: false,
                isResendingVerification: false,
                resendSuccessMessage: '',

                // Customer Auth & Portal state
                authModalOpen: false,
                authMode: 'login',
                isAuthLoading: false,
                authError: '',
                loginIdentifier: '',
                loginPassword: '',
                regName: '',
                regEmail: '',
                regPhone: '',
                regPassword: '',
                regCity: '',
                regAddress: '',

                customerToken: localStorage.getItem('pos_store_customer_token') || '',
                customer: null,

                profileModalOpen: false,
                profileForm: { name: '', phone: '', city: '', address: '' },
                profileMessage: '',

                ordersModalOpen: false,
                ordersList: [],

                addressesModalOpen: false,
                savedAddresses: [],
                newAddrStreet: '',
                newAddrCity: '',
                newAddrType: 'home',

                wishlistModalOpen: false,
                wishlistItems: [],

                // Storefront FAQ state & methods
                faqsModalOpen: false,
                faqsSearch: '',
                faqsCategory: 'all',
                faqsExpandedId: null,
                faqsList: @json($faqs ?? []),

                get faqsCategories() {
                    const cats = new Set();
                    (this.faqsList || []).forEach(f => {
                        if (f.category) cats.add(f.category);
                    });
                    return Array.from(cats);
                },

                get filteredFaqs() {
                    const q = (this.faqsSearch || '').toLowerCase().trim();
                    const cat = this.faqsCategory;
                    return (this.faqsList || []).filter(f => {
                        const matchesCat = cat === 'all' || (f.category && f.category.toLowerCase() === cat.toLowerCase());
                        const matchesQuery = !q || (f.question && f.question.toLowerCase().includes(q)) || (f.answer && f.answer.toLowerCase().includes(q));
                        return matchesCat && matchesQuery;
                    });
                },

                openFaqsModal(cat = 'all') {
                    this.faqsCategory = cat;
                    this.faqsModalOpen = true;
                    if (!this.faqsList || this.faqsList.length === 0) {
                        this.loadFaqs();
                    }
                },

                closeFaqsModal() {
                    this.faqsModalOpen = false;
                },

                toggleFaq(id) {
                    this.faqsExpandedId = this.faqsExpandedId === id ? null : id;
                },

                async loadFaqs() {
                    try {
                        const storeParam = this.companySlug ? '?store=' + encodeURIComponent(this.companySlug) : '';
                        const res = await fetch('/store/api/faqs' + storeParam);
                        if (res.ok) {
                            const data = await res.json();
                            if (data.success && Array.isArray(data.faqs)) {
                                this.faqsList = data.faqs;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load FAQs', e);
                    }
                },

                // Storefront payment methods & config
                paymentMethods: config.enabledPaymentMethods || [],
                paymentMethod: (config.enabledPaymentMethods && config.enabledPaymentMethods.length > 0) ? config.enabledPaymentMethods[0].id : 'cod',
                enableGoogleLogin: config.enableGoogleLogin || false,
                googleAuthUrl: config.googleAuthUrl || '/store/auth/google/redirect',
                couponValidateUrl: config.couponValidateUrl || '/store/coupons/validate',
                accountUrl: config.accountUrl || '/store/account',
                confirmedTrackingCode: '',
                confirmedTrackingUrl: '',

                // Checkout fields
                customerName: '',
                customerPhone: '',
                customerEmail: '',
                deliveryAddress: '',
                deliveryCity: '',
                couponCode: '',
                appliedCoupon: null,
                cart: [],

                getMethodIcon(id) {
                    const icons = {
                        cod: '💵',
                        store_pickup: '🏪',
                        razorpay: '⚡',
                        stripe: '💳',
                        paypal: '🅿️',
                        upi: '📱'
                    };
                    return icons[id] || '💳';
                },

                getSelectedPaymentMethod() {
                    return (this.paymentMethods || []).find(m => m.id === this.paymentMethod) || null;
                },

                init() {
                    // Initialize theme
                    this.isDark = document.documentElement.classList.contains('dark');

                    // Initialize cart from localStorage
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

                    // Initialize customer from localStorage
                    try {
                        const savedCust = localStorage.getItem('pos_store_customer_data');
                        if (savedCust) {
                            this.customer = JSON.parse(savedCust);
                            this.prefillCustomerDetails(this.customer);
                        }
                    } catch (e) {}

                    // Fetch live customer details, orders, addresses, and wishlist if token present
                    if (this.customerToken) {
                        this.loadCustomerProfile();
                        this.loadOrders();
                        this.loadAddresses();
                        this.loadWishlist();
                    }

                    // Always fetch latest enabled payment methods for store
                    this.loadPaymentMethods();

                    // Open FAQs modal if requested by hash or query param
                    if (window.location.search.includes('open_faqs=1') || window.location.hash === '#faqs') {
                        this.openFaqsModal();
                    }
                },

                async loadPaymentMethods() {
                    try {
                        const storeParam = this.companySlug ? '?store=' + encodeURIComponent(this.companySlug) : '';
                        const res = await fetch('/store/payment-methods' + storeParam);
                        if (res.ok) {
                            const data = await res.json();
                            if (data.success && Array.isArray(data.enabled_methods) && data.enabled_methods.length > 0) {
                                this.paymentMethods = data.enabled_methods;
                                if (!this.paymentMethods.some(m => m.id === this.paymentMethod)) {
                                    this.paymentMethod = this.paymentMethods[0].id;
                                }
                            }
                        }
                    } catch (e) {}
                },

                toggleDarkMode() {
                    this.isDark = !this.isDark;
                    if (this.isDark) {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('pos_store_theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('pos_store_theme', 'light');
                    }
                },

                prefillCustomerDetails(c) {
                    if (!c) return;
                    if (!this.customerName && c.name) this.customerName = c.name;
                    if (!this.customerPhone && c.phone) this.customerPhone = c.phone;
                    if (!this.customerEmail && c.email) this.customerEmail = c.email;
                    if (!this.deliveryCity && c.city) this.deliveryCity = c.city;
                    if (!this.deliveryAddress && c.address) this.deliveryAddress = c.address;
                    this.profileForm = {
                        name: c.name || '',
                        phone: c.phone || '',
                        city: c.city || '',
                        address: c.address || '',
                        date_of_birth: c.date_of_birth || '',
                        gender: c.gender || 'Male',
                        avatar_url: c.avatar_url || ''
                    };
                },

                get cartTotalCount() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },

                get cartTotalPrice() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },

                get cartFinalPrice() {
                    const discount = this.appliedCoupon ? this.appliedCoupon.amount : 0;
                    return Math.max(0, this.cartTotalPrice - discount);
                },

                get checkoutButtonText() {
                    if (this.isSubmitting) return '{{ __('Processing...') }}';
                    const amount = '$' + this.cartFinalPrice.toFixed(2);
                    switch (this.paymentMethod) {
                        case 'razorpay':
                            return `{{ __('Pay') }} ${amount} {{ __('via Razorpay') }}`;
                        case 'stripe':
                        case 'card':
                        case 'shopcart_card':
                            return `{{ __('Pay') }} ${amount} {{ __('with Card') }}`;
                        case 'paypal':
                            return `{{ __('Pay') }} ${amount} {{ __('via PayPal') }}`;
                        case 'upi':
                            return `{{ __('Pay') }} ${amount} {{ __('via UPI') }}`;
                        case 'store_pickup':
                            return `{{ __('Place Order (Store Pickup)') }}`;
                        case 'cod':
                        default:
                            return `{{ __('Place Order (Cash on Delivery)') }}`;
                    }
                },

                async applyCoupon() {
                    this.couponError = '';
                    this.couponSuccess = '';
                    const code = (this.couponCode || '').trim().toUpperCase();
                    if (!code) {
                        this.couponError = '{{ __('Please enter a coupon code.') }}';
                        return;
                    }
                    if (this.cartTotalPrice <= 0) {
                        this.couponError = '{{ __('Please add items to your cart before applying a coupon.') }}';
                        return;
                    }
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                        if (token) headers['X-CSRF-TOKEN'] = token;
                        if (this.customerToken) headers['X-Customer-Token'] = this.customerToken;

                        const url = (this.couponValidateUrl || '/store/coupons/validate') + '?store=' + encodeURIComponent(this.companySlug || '');
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify({
                                code: code,
                                subtotal: this.cartTotalPrice,
                                store_slug: this.companySlug || null,
                                store: this.companySlug || null,
                                company_id: this.companyId || null,
                                customer_id: this.customer?.id || null,
                                phone: this.customerPhone || null,
                                email: this.customerEmail || null,
                                auth_token: this.customerToken || null
                            })
                        });
                        const data = await res.json();
                        if (res.ok && (data.valid || data.success)) {
                            this.appliedCoupon = {
                                code: data.code,
                                amount: parseFloat(data.discount_amount || 0),
                                discount_type: data.discount_type || 'fixed'
                            };
                            this.couponSuccess = data.message || `{{ __('Coupon applied successfully!') }}`;
                            this.couponCode = '';
                        } else {
                            this.couponError = data.message || '{{ __('Invalid or inapplicable coupon code.') }}';
                        }
                    } catch (e) {
                        console.error('Coupon validation error:', e);
                        this.couponError = '{{ __('Unable to validate coupon at this time.') }}';
                    }
                },

                removeCoupon() {
                    this.appliedCoupon = null;
                    this.couponError = '';
                    this.couponSuccess = '';
                },

                selectAddressForCheckout(addr) {
                    if (addr.street_address) this.deliveryAddress = addr.street_address;
                    if (addr.city) this.deliveryCity = addr.city;
                    if (addr.phone && !this.customerPhone) this.customerPhone = addr.phone;
                    if (addr.name && !this.customerName) this.customerName = addr.name;
                },

                openProductDetail(product, initialTab = 'overview') {
                    this.modalProduct = product;
                    this.modalQty = 1;
                    this.detailModalTab = initialTab;
                    this.reviewSuccessMsg = '';
                    this.reviewErrorMsg = '';
                    this.reviewFormOpen = false;
                    this.detailModalOpen = true;

                    if (this.enableProductReviews && product && product.id) {
                        this.loadProductReviews(product.id);
                    }
                },

                openProductReviews(product) {
                    this.openProductDetail(product, 'reviews');
                },

                closeProductDetail() {
                    this.detailModalOpen = false;
                    this.modalProduct = null;
                    this.detailModalTab = 'overview';
                    this.reviewSuccessMsg = '';
                    this.reviewErrorMsg = '';
                    this.reviewFormOpen = false;
                },

                setReviewRating(stars) {
                    this.reviewRating = Math.max(1, Math.min(5, parseInt(stars) || 5));
                },

                async loadProductReviews(productId) {
                    if (!productId) return;
                    this.isLoadingReviews = true;
                    try {
                        const storeParam = this.companySlug ? '?store=' + encodeURIComponent(this.companySlug) : '';
                        const res = await fetch('/store/products/' + productId + '/reviews' + storeParam);
                        if (res.ok) {
                            const data = await res.json();
                            if (data.success) {
                                this.productReviews = data.reviews || [];
                                this.reviewsStats = {
                                    average_rating: parseFloat(data.average_rating || 0),
                                    total_reviews: parseInt(data.total_reviews || 0)
                                };
                                this.reviewsDistribution = data.rating_distribution || { 5:0, 4:0, 3:0, 2:0, 1:0 };
                                if (this.modalProduct && this.modalProduct.id === productId) {
                                    this.modalProduct.average_rating = this.reviewsStats.average_rating;
                                    this.modalProduct.reviews_count = this.reviewsStats.total_reviews;
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load product reviews:', e);
                    } finally {
                        this.isLoadingReviews = false;
                    }
                },

                async submitReview() {
                    if (!this.modalProduct || !this.modalProduct.id) return;

                    if (!this.reviewComment || this.reviewComment.trim().length < 3) {
                        this.reviewErrorMsg = '{{ __('Please write a review comment (minimum 3 characters).') }}';
                        return;
                    }

                    const name = (this.reviewCustomerName || '').trim() || (this.customer?.name || '').trim();
                    if (!this.customerToken && !name) {
                        this.reviewErrorMsg = '{{ __('Please provide your name.') }}';
                        return;
                    }

                    this.isSubmittingReview = true;
                    this.reviewErrorMsg = '';
                    this.reviewSuccessMsg = '';

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                        if (token) headers['X-CSRF-TOKEN'] = token;
                        if (this.customerToken) headers['X-Customer-Token'] = this.customerToken;

                        const storeParam = this.companySlug ? '?store=' + encodeURIComponent(this.companySlug) : '';
                        const res = await fetch('/store/products/' + this.modalProduct.id + '/reviews' + storeParam, {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify({
                                rating: this.reviewRating,
                                title: (this.reviewTitle || '').trim() || null,
                                comment: this.reviewComment.trim(),
                                customer_name: name || null,
                                customer_email: (this.reviewCustomerEmail || '').trim() || (this.customer?.email || null),
                                auth_token: this.customerToken || null,
                                company_id: this.companyId || null,
                                store: this.companySlug || null
                            })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.reviewSuccessMsg = data.message || '{{ __('Thank you! Your review has been submitted.') }}';
                            this.reviewComment = '';
                            this.reviewTitle = '';
                            this.reviewFormOpen = false;
                            await this.loadProductReviews(this.modalProduct.id);
                        } else {
                            this.reviewErrorMsg = data.message || '{{ __('Failed to submit review. Please try again.') }}';
                        }
                    } catch (e) {
                        console.error('Error submitting review:', e);
                        this.reviewErrorMsg = '{{ __('Network error while submitting review.') }}';
                    } finally {
                        this.isSubmittingReview = false;
                    }
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
                    this.$nextTick(() => {
                        const el = document.getElementById('products-section');
                        if (el) {
                            const yOffset = -90; // offset for sticky header
                            const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset;
                            window.scrollTo({ top: y, behavior: 'smooth' });
                        }
                    });
                },

                matchesFilter(name, category, code, price) {
                    if (this.selectedCategory !== 'all' && category !== this.selectedCategory) {
                        return false;
                    }
                    if (this.search && this.search.trim().length > 0) {
                        const q = this.search.toLowerCase().trim();
                        const matchName = name && name.toLowerCase().includes(q);
                        const matchCode = code && code.toLowerCase().includes(q);
                        const matchCat = category && category.toLowerCase().includes(q);
                        if (!matchName && !matchCode && !matchCat) return false;
                    }
                    return true;
                },

                // --- Customer Auth & API helpers ---
                authHeaders() {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        'X-Store-ID': this.companySlug || this.companyId || ''
                    };
                    if (this.customerToken) {
                        headers['Authorization'] = 'Bearer ' + this.customerToken;
                        headers['X-Customer-Token'] = this.customerToken;
                    }
                    return headers;
                },

                openAuthModal(mode = 'login') {
                    this.authMode = mode;
                    this.authError = '';
                    this.authModalOpen = true;
                },

                async submitLogin() {
                    this.isAuthLoading = true;
                    this.authError = '';
                    try {
                        const res = await fetch('/api/storefront/customer/login?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: this.authHeaders(),
                            body: JSON.stringify({
                                login: this.loginIdentifier.trim(),
                                password: this.loginPassword
                            })
                        });
                        const data = await res.json();
                        if (data.success && data.token) {
                            this.customerToken = data.token;
                            this.customer = data.customer;
                            localStorage.setItem('pos_store_customer_token', data.token);
                            localStorage.setItem('pos_store_customer_data', JSON.stringify(data.customer));
                            this.prefillCustomerDetails(data.customer);
                            this.authModalOpen = false;
                            this.loadOrders();
                            this.loadAddresses();
                            this.loadWishlist();
                        } else {
                            this.authError = data.message || 'Login failed. Please check credentials.';
                        }
                    } catch (e) {
                        this.authError = 'Network error during login.';
                    } finally {
                        this.isAuthLoading = false;
                    }
                },

                async submitRegister() {
                    this.isAuthLoading = true;
                    this.authError = '';
                    try {
                        const res = await fetch('/api/storefront/customer/register?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: this.authHeaders(),
                            body: JSON.stringify({
                                name: this.regName.trim(),
                                email: this.regEmail.trim() || null,
                                phone: this.regPhone.trim() || null,
                                password: this.regPassword,
                                address: this.regAddress.trim() || null,
                                city: this.regCity.trim() || null
                            })
                        });
                        const data = await res.json();
                        if (data.success && data.token) {
                            this.customerToken = data.token;
                            this.customer = data.customer;
                            localStorage.setItem('pos_store_customer_token', data.token);
                            localStorage.setItem('pos_store_customer_data', JSON.stringify(data.customer));
                            this.prefillCustomerDetails(data.customer);
                            this.authModalOpen = false;
                            this.loadOrders();
                            this.loadAddresses();
                            this.loadWishlist();
                        } else {
                            this.authError = data.message || 'Registration failed. Please try again.';
                        }
                    } catch (e) {
                        this.authError = 'Network error during registration.';
                    } finally {
                        this.isAuthLoading = false;
                    }
                },

                async logoutCustomer() {
                    try {
                        await fetch('/api/storefront/customer/logout?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: this.authHeaders()
                        });
                    } catch (e) {}
                    this.customerToken = '';
                    this.customer = null;
                    localStorage.removeItem('pos_store_customer_token');
                    localStorage.removeItem('pos_store_customer_data');
                    this.ordersList = [];
                    this.savedAddresses = [];
                    this.wishlistItems = [];
                },

                openProfileModal() {
                    this.profileMessage = '';
                    if (this.customer) {
                        this.profileForm = {
                            name: this.customer.name || '',
                            phone: this.customer.phone || '',
                            city: this.customer.city || '',
                            address: this.customer.address || ''
                        };
                    }
                    this.profileModalOpen = true;
                },

                async loadCustomerProfile() {
                    if (!this.customerToken) return;
                    try {
                        const res = await fetch('/api/storefront/customer/profile?store=' + encodeURIComponent(this.companySlug || ''), {
                            headers: this.authHeaders()
                        });
                        const data = await res.json();
                        if (data.success && data.customer) {
                            this.customer = data.customer;
                            localStorage.setItem('pos_store_customer_data', JSON.stringify(data.customer));
                            this.prefillCustomerDetails(data.customer);
                        }
                    } catch (e) {}
                },

                async updateCustomerProfile() {
                    try {
                        const res = await fetch('/api/storefront/customer/profile?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'PUT',
                            headers: this.authHeaders(),
                            body: JSON.stringify(this.profileForm)
                        });
                        const data = await res.json();
                        if (data.success && data.customer) {
                            this.customer = data.customer;
                            localStorage.setItem('pos_store_customer_data', JSON.stringify(data.customer));
                            this.prefillCustomerDetails(data.customer);
                            this.profileMessage = 'Profile updated successfully!';
                            setTimeout(() => { this.profileMessage = ''; }, 3000);
                        }
                    } catch (e) {}
                },

                openOrdersModal() {
                    this.loadOrders();
                    this.ordersModalOpen = true;
                },

                async loadOrders() {
                    if (!this.customerToken) return;
                    try {
                        const res = await fetch('/api/storefront/customer/orders?store=' + encodeURIComponent(this.companySlug || ''), {
                            headers: this.authHeaders()
                        });
                        const data = await res.json();
                        if (data.success && data.orders) {
                            this.ordersList = data.orders.data || data.orders;
                        }
                    } catch (e) {}
                },

                openAddressesModal() {
                    this.loadAddresses();
                    this.addressesModalOpen = true;
                },

                async loadAddresses() {
                    if (!this.customerToken) return;
                    try {
                        const res = await fetch('/api/storefront/customer/addresses?store=' + encodeURIComponent(this.companySlug || ''), {
                            headers: this.authHeaders()
                        });
                        const data = await res.json();
                        if (data.success && data.addresses) {
                            this.savedAddresses = data.addresses;
                        }
                    } catch (e) {}
                },

                async saveNewAddress() {
                    if (!this.newAddrStreet.trim()) return;
                    try {
                        const res = await fetch('/api/storefront/customer/addresses?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: this.authHeaders(),
                            body: JSON.stringify({
                                street_address: this.newAddrStreet.trim(),
                                city: this.newAddrCity.trim(),
                                type: this.newAddrType || 'home'
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.newAddrStreet = '';
                            this.newAddrCity = '';
                            this.loadAddresses();
                        }
                    } catch (e) {}
                },

                async deleteAddress(id) {
                    try {
                        await fetch('/api/storefront/customer/addresses/' + id + '?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'DELETE',
                            headers: this.authHeaders()
                        });
                        this.loadAddresses();
                    } catch (e) {}
                },

                openWishlistModal() {
                    this.loadWishlist();
                    this.wishlistModalOpen = true;
                },

                async loadWishlist() {
                    if (!this.customerToken) return;
                    try {
                        const res = await fetch('/api/storefront/customer/wishlist?store=' + encodeURIComponent(this.companySlug || ''), {
                            headers: this.authHeaders()
                        });
                        const data = await res.json();
                        if (data.success && data.wishlist) {
                            this.wishlistItems = data.wishlist;
                        }
                    } catch (e) {}
                },

                isWishlisted(productId) {
                    return this.wishlistItems.some(i => i.id === productId);
                },

                async toggleWishlist(product) {
                    if (!this.customerToken) {
                        this.openAuthModal('login');
                        return;
                    }
                    const pId = product.id;
                    const exists = this.isWishlisted(pId);
                    if (exists) {
                        this.wishlistItems = this.wishlistItems.filter(i => i.id !== pId);
                    } else {
                        this.wishlistItems.push(product);
                    }
                    try {
                        await fetch('/api/storefront/customer/wishlist/toggle?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: this.authHeaders(),
                            body: JSON.stringify({ product_id: pId })
                        });
                    } catch (e) {}
                },

                async removeFromWishlist(productId) {
                    this.wishlistItems = this.wishlistItems.filter(i => i.id !== productId);
                    try {
                        await fetch('/api/storefront/customer/wishlist/' + productId + '?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'DELETE',
                            headers: this.authHeaders()
                        });
                    } catch (e) {}
                },

                // --- Order submission ---
                async submitOrderToStore(codeOverride = null) {
                    if (this.cart.length === 0) return;

                    if (!this.customerName.trim()) {
                        alert('{{ __('Please enter your full name.') }}');
                        return;
                    }
                    if (!this.customerPhone.trim()) {
                        alert('{{ __('Please enter your phone number.') }}');
                        return;
                    }
                    if (!this.deliveryCity.trim()) {
                        alert('{{ __('Please enter your city.') }}');
                        return;
                    }
                    if (!this.deliveryAddress.trim()) {
                        alert('{{ __('Please enter your delivery street address.') }}');
                        return;
                    }

                    this.isSubmitting = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const headers = this.authHeaders();
                        if (token) headers['X-CSRF-TOKEN'] = token;

                        const discount = this.appliedCoupon ? this.appliedCoupon.amount : 0;
                        const couponCode = this.appliedCoupon ? this.appliedCoupon.code : null;
                        const vCode = codeOverride || this.verificationCode || null;

                        const response = await fetch(this.orderEndpoint + '?store=' + encodeURIComponent(this.companySlug || ''), {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify({
                                customer_name: this.customerName.trim(),
                                customer_phone: this.customerPhone.trim(),
                                customer_email: this.customerEmail.trim() || null,
                                delivery_address: this.deliveryAddress.trim(),
                                address: this.deliveryAddress.trim(),
                                city: this.deliveryCity.trim(),
                                payment_method: this.paymentMethod,
                                discount: discount,
                                coupon_code: couponCode,
                                auth_token: this.customerToken || null,
                                company_id: this.companyId || null,
                                verification_code: vCode ? vCode.trim() : null,
                                items: this.cart
                            })
                        });

                        const data = await response.json();
                        if (response.status === 401 || data.auth_required) {
                            this.authModalOpen = true;
                            this.authError = data.message || '{{ __('Please sign in to place your order.') }}';
                            this.isSubmitting = false;
                            return;
                        }

                        if (data.verification_required) {
                            this.verificationModalOpen = true;
                            this.verificationMessage = data.message || '{{ __('Account verification required before placing your order. A 6-digit verification code has been sent directly to your email / phone.') }}';
                            this.verificationChannels = data.channels || [];
                            this.verificationCustomerId = data.customer_id || this.verificationCustomerId;
                            if (data.verification_failed) {
                                this.verificationError = data.message || '{{ __('Invalid or expired verification code. Please try again.') }}';
                            } else {
                                this.verificationError = '';
                            }
                            this.isSubmitting = false;
                            return;
                        }

                        if (data.success) {
                            this.verificationModalOpen = false;
                            this.verificationCode = '';
                            this.verificationError = '';

                            if (data.gateway_data && data.gateway_data.gateway === 'razorpay' && data.gateway_data.requires_online_action) {
                                this.handleRazorpayPayment(data);
                                return;
                            }
                            if (data.gateway_data && data.gateway_data.gateway === 'stripe' && data.gateway_data.requires_online_action) {
                                this.handleStripePayment(data);
                                return;
                            }

                            this.finishOrderAccepted(data);
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

                async verifyAndSubmitOrder() {
                    if (!this.verificationCode || !this.verificationCode.trim()) {
                        this.verificationError = '{{ __('Please enter the 6-digit verification code.') }}';
                        return;
                    }
                    this.verificationError = '';
                    this.isVerifying = true;
                    try {
                        await this.submitOrderToStore(this.verificationCode.trim());
                    } finally {
                        this.isVerifying = false;
                    }
                },

                async resendVerificationCode() {
                    this.isResendingVerification = true;
                    this.verificationError = '';
                    this.resendSuccessMessage = '';
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                        if (token) headers['X-CSRF-TOKEN'] = token;

                        const storeParam = this.companySlug ? '?store=' + encodeURIComponent(this.companySlug) : '';
                        const res = await fetch('/store/auth/send-verification' + storeParam, {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify({
                                company_id: this.companyId,
                                customer_id: this.verificationCustomerId,
                                phone: this.customerPhone.trim(),
                                email: this.customerEmail.trim() || null,
                                store: this.companySlug
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.resendSuccessMessage = data.message || '{{ __('Verification code resent successfully.') }}';
                            if (data.channels) this.verificationChannels = data.channels;
                        } else {
                            this.verificationError = data.message || '{{ __('Could not resend verification code.') }}';
                        }
                    } catch (e) {
                        console.error('Error resending verification code:', e);
                        this.verificationError = '{{ __('Network error while resending verification code.') }}';
                    } finally {
                        this.isResendingVerification = false;
                    }
                },

                handleRazorpayPayment(orderData) {
                    const gw = orderData.gateway_data;
                    if (typeof Razorpay === 'undefined') {
                        alert('{{ __('Payment gateway SDK is loading. Your order was created as pending.') }}');
                        this.finishOrderAccepted(orderData, false);
                        return;
                    }

                    const self = this;
                    const options = {
                        key: gw.key,
                        amount: gw.amount,
                        currency: gw.currency || 'INR',
                        name: gw.store_name || this.storeName || 'Online Store',
                        description: 'Order #' + (orderData.sale_number || gw.sale_number),
                        prefill: {
                            name: self.customerName.trim(),
                            email: self.customerEmail.trim(),
                            contact: self.customerPhone.trim()
                        },
                        theme: {
                            color: '#059669'
                        },
                        handler: async function (response) {
                            self.isSubmitting = true;
                            try {
                                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                                if (token) headers['X-CSRF-TOKEN'] = token;
                                if (self.customerToken) headers['X-Customer-Token'] = self.customerToken;

                                const verifyUrl = '/store/payment/verify?store=' + encodeURIComponent(self.companySlug || '');
                                const verifyRes = await fetch(verifyUrl, {
                                    method: 'POST',
                                    headers: headers,
                                    body: JSON.stringify({
                                        order_id: orderData.order_id || gw.sale_id,
                                        sale_number: orderData.sale_number || gw.sale_number,
                                        payment_method: 'razorpay',
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature
                                    })
                                });

                                const verifyData = await verifyRes.json();
                                if (verifyRes.ok && verifyData.success) {
                                    self.finishOrderAccepted(orderData, true);
                                } else {
                                    alert(verifyData.message || 'Payment recorded. Verification will be checked by store.');
                                    self.finishOrderAccepted(orderData, true);
                                }
                            } catch (err) {
                                console.error('Payment verification error:', err);
                                self.finishOrderAccepted(orderData, true);
                            } finally {
                                self.isSubmitting = false;
                            }
                        },
                        modal: {
                            ondismiss: function() {
                                alert('{{ __('Payment window closed. Your order was created as pending. You can settle it anytime.') }}');
                                self.finishOrderAccepted(orderData, false);
                            }
                        }
                    };

                    if (gw.razorpay_order_id && typeof gw.razorpay_order_id === 'string' && gw.razorpay_order_id.trim() !== '') {
                        options.order_id = gw.razorpay_order_id.trim();
                    }

                    try {
                        const rzp = new Razorpay(options);
                        rzp.open();
                    } catch (rzpErr) {
                        console.error('Razorpay popup error:', rzpErr);
                        self.finishOrderAccepted(orderData, false);
                    }
                },

                handleStripePayment(orderData) {
                    const gw = orderData.gateway_data;
                    if (gw.checkout_url) {
                        window.location.href = gw.checkout_url;
                        return;
                    }
                    this.verifyStripePayment(orderData);
                },

                async verifyStripePayment(orderData) {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                        if (token) headers['X-CSRF-TOKEN'] = token;
                        if (this.customerToken) headers['X-Customer-Token'] = this.customerToken;

                        const verifyUrl = '/store/payment/verify?store=' + encodeURIComponent(this.companySlug || '');
                        const verifyRes = await fetch(verifyUrl, {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify({
                                order_id: orderData.order_id,
                                sale_number: orderData.sale_number,
                                payment_method: 'stripe',
                                stripe_payment_id: 'ch_' + Math.random().toString(36).substr(2, 9)
                            })
                        });
                        const verifyData = await verifyRes.json();
                        this.finishOrderAccepted(orderData, verifyData.success ?? true);
                    } catch (e) {
                        this.finishOrderAccepted(orderData, false);
                    }
                },

                finishOrderAccepted(data, isPaid = null) {
                    this.confirmedSaleNumber = data.sale_number || 'WEB-' + Date.now();
                    this.confirmedTrackingCode = data.tracking_code || '';
                    this.confirmedTrackingUrl = data.tracking_url || ('/store/track/' + (data.tracking_code || ''));
                    this.isOrderPaid = (isPaid !== null) ? isPaid : (this.paymentMethod !== 'cod' && this.paymentMethod !== 'store_pickup');
                    this.cart = [];
                    this.appliedCoupon = null;
                    this.cartDrawerOpen = false;
                    this.orderAcceptedModalOpen = true;
                    if (this.customerToken) {
                        this.loadOrders();
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
                    const clientEmail = this.customerEmail.trim() ? `\n*Email:* ${this.customerEmail.trim()}` : '';
                    const clientAddr = this.deliveryAddress.trim() ? `\n*Address:* ${this.deliveryAddress.trim()}, ${this.deliveryCity.trim()}` : '';

                    const message = `🛒 *NEW ORDER FROM STORE*\n`
                        + `*Store:* ${this.storeName}\n`
                        + `*Customer:* ${clientName}${clientPhone}${clientEmail}${clientAddr}\n`
                        + `*Payment Method:* ${this.paymentMethod.toUpperCase()}\n\n`
                        + `*Order Items:*\n${itemsSummary}\n`
                        + `----------------------------\n`
                        + `*Total:* $${this.cartFinalPrice.toFixed(2)} ${this.currency}\n`
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
