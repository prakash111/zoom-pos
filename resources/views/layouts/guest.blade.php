@php
    $guestTenantCompany = app()->bound('tenant.company_id')
        ? \App\Models\Company::withoutGlobalScopes()->find(app('tenant.company_id'))
        : null;
    $branding = \App\Models\PlatformBranding::current();
    $dynamicLogo = \App\Models\DynamicSetting::get('platform_logo_url');
    $dynamicName = \App\Models\DynamicSetting::get('platform_brand_name');
    $guestBrandName = $guestTenantCompany?->trade_name ?: ($guestTenantCompany?->name ?: ($dynamicName ?: ($branding?->platform_name ?? config('app.name', 'Smart Inventory'))));
    $guestLogoUrl = $guestTenantCompany?->getLogoUrl() ?: ($dynamicLogo ?: $branding?->getLogoPublicUrl());
    $guestFaviconUrl = $guestTenantCompany?->favicon ?: ($branding?->getFaviconPublicUrl() ?: $branding?->favicon_url);
    $guestLocService = app(\App\Services\Localization\LocalizationService::class);
    $guestActiveLang = $guestLocService->getActiveLanguage();
    $guestLanguages = $guestLocService->getActiveLanguages();
    $isRtl = $guestLocService->isRtl();

    // Same source of truth as LandingPageController: the branding column, not
    // the (never-written) `landing_page_enabled` global-setting key.
    $guestLandingEnabled = (bool) ($branding?->landing_page_enabled ?? false);
    // Auth screens follow the selected landing theme: light for theme_fast,
    // dark for the four legacy (dark-committed) themes.
    $guestForceDark = setting('landing_page_theme', 'theme_fast') !== 'theme_fast';
    $guestIsLogin = request()->routeIs('tenant.login', 'superadmin.login');
    $guestIsRegister = request()->routeIs('tenant.register');
    $guestAuthBannerEnabled = (bool) \App\Models\DynamicSetting::get('show_auth_banner', true);
    $guestNavLinks = $guestLandingEnabled ? [
        ['label' => __('Features'), 'url' => url('/') . '#features'],
        ['label' => __('Pricing'), 'url' => url('/') . '#pricing'],
        ['label' => __('Contact'), 'url' => url('/') . '#contact'],
    ] : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $guestActiveLang?->code ?? 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $guestForceDark ? 'dark' : '' }}" x-data="{ dark: document.documentElement.classList.contains('dark') }" x-init="$watch('dark', v => { try { localStorage.setItem('theme', v ? 'dark' : 'light') } catch (e) {}; document.documentElement.classList.toggle('dark', v) }); document.documentElement.classList.toggle('dark', dark)">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $guestBrandName }} &middot; Authentication</title>
    @if ($guestFaviconUrl)
        <link rel="icon" href="{{ $guestFaviconUrl }}">
    @endif

    {{-- Set the saved theme before CSS loads, preventing a light/dark flash. --}}
    <script>try{document.documentElement.classList.toggle('dark',localStorage.getItem('theme')==='dark')}catch(e){}</script>
    @if ($guestForceDark)<script>document.documentElement.classList.add('dark')</script>@endif

    <style>
        :root {
            --color-brand-emerald: {{ $branding->landing_primary_color ?: '#10b981' }};
            --color-brand-lime: {{ $branding->landing_accent_color ?: '#d7f24e' }};
            --color-brand-teal: {{ $branding->primary_color ?: '#0c5966' }};
        }
        html { background: #f3f8ff; }
        html.dark { background: #020617; }
        body { margin: 0; min-height: 100vh; background: #f3f8ff; color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ui-sans-serif, system-ui, Helvetica, Arial, sans-serif; }
        html.dark body { background: #020617; color: #f1f5f9; }
        [x-cloak] { display: none !important; }
    </style>

    {{-- Scoped auth stylesheet only. The full app.css / app.js bundle and the
         Google Fonts request are NOT loaded on the sign-in screens; Alpine
         comes from @livewireScripts. --}}
    @vite(['resources/css/auth.css'])
    @livewireStyles
</head>
<body class="auth-page {{ $guestIsLogin ? 'auth-page--login' : ($guestIsRegister ? 'auth-page--register' : 'auth-page--standard') }} {{ !$guestAuthBannerEnabled ? 'auth-page--solo' : '' }} font-sans antialiased">
    @include('layouts.partials.preloader')

    <!-- Top Floating Navigation Bar -->
    <header x-data="{ navOpen: false }"
            class="auth-header flex items-center justify-between gap-3 relative z-30">

        <!-- Brand Logo (links home when the landing page is live) -->
        <a @if($guestLandingEnabled) href="{{ url('/') }}" @endif
           class="auth-header-brand inline-flex shrink-0">
            @include('auth.partials.brand', ['brandName' => $guestBrandName, 'logoUrl' => $guestLogoUrl])
        </a>

        @if ($guestLandingEnabled)
            {{-- Back to Home (compact, beside the logo on tablet+) --}}
            <a href="{{ url('/') }}" class="auth-home-link group hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 border border-slate-200 dark:border-white/15 text-xs font-bold text-slate-700 dark:text-white transition shrink-0">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                <span>{{ __('Back to Home') }}</span>
            </a>

            <!-- Desktop inline navigation -->
            <nav class="auth-header-nav hidden md:flex items-center gap-1 bg-slate-100 dark:bg-white/10 backdrop-blur-md border border-slate-200 dark:border-white/15 rounded-full px-2 py-1">
                @foreach ($guestNavLinks as $link)
                    <a href="{{ $link['url'] }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <!-- Right Quick Actions -->
        <div class="auth-header-actions flex items-center gap-2 sm:gap-2.5 shrink-0">

            @if(request()->routeIs('tenant.login'))
                <a href="https://web.zoomnearby.com" target="_blank" rel="noopener noreferrer"
                   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 text-xs font-bold text-sky-700 dark:text-sky-300 transition shrink-0"
                   title="{{ __('Try Flutter Web Tenant Demo') }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                    <span>{{ __('Flutter Web Demo') }}</span>
                    <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif

            {{-- Mobile navigation toggle --}}
            @if (!empty($guestNavLinks))
                <button type="button" x-on:click="navOpen = !navOpen" :aria-expanded="navOpen.toString()"
                        class="md:hidden p-2.5 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 border border-slate-200 dark:border-white/15 text-slate-700 dark:text-slate-200 transition"
                        title="{{ __('Menu') }}">
                    <svg x-show="!navOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="navOpen" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            @endif

            <!-- Language Switcher Dropdown (Auth) -->
            @if ($guestLanguages->isNotEmpty())
                <div class="relative" x-data="{ openAuthLang: false }">
                    <button type="button"
                            x-on:click="openAuthLang = !openAuthLang"
                            x-on:click.outside="openAuthLang = false"
                            class="p-2 sm:px-3 sm:py-2 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 text-xs font-bold transition border border-slate-200 dark:border-white/15 flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="{{ __('Switch Language') }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18Z"/></svg>
                        <span class="hidden sm:inline uppercase text-[11px] font-mono font-bold">{{ $guestActiveLang?->code ?? 'EN' }}</span>
                        <svg class="w-3 h-3 text-slate-400 hidden sm:inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>

                    <div x-show="openAuthLang"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="transform opacity-0 scale-95 translate-y-1"
                         x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="transform opacity-0 scale-95 translate-y-1"
                         class="absolute right-0 mt-2 w-52 rounded-2xl bg-white dark:bg-slate-950/95 backdrop-blur-2xl border border-slate-200 dark:border-white/15 shadow-2xl p-2 z-50 space-y-0.5">
                        <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            {{ __('Select Language') }}
                        </div>
                        <div class="max-h-56 overflow-y-auto no-scrollbar space-y-0.5">
                            @foreach ($guestLanguages as $lang)
                                <a href="{{ route('locale.switch', $lang->code) }}"
                                   @class([
                                       'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition',
                                       'bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold border border-blue-200 dark:border-blue-800' => ($guestActiveLang?->code ?? 'en') === $lang->code,
                                       'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ($guestActiveLang?->code ?? 'en') !== $lang->code,
                                   ])>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ $lang->flag }}</span>
                                        <span>{{ $lang->native_name ?: $lang->name }}</span>
                                    </div>
                                    @if (($guestActiveLang?->code ?? 'en') === $lang->code)
                                        <span class="text-blue-600 dark:text-blue-300 font-black text-xs">✓</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <button type="button" x-on:click="dark = !dark"
                    class="p-2.5 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 backdrop-blur-md border border-slate-200 dark:border-white/15 text-slate-700 dark:text-slate-200 text-xs font-semibold transition"
                    title="{{ __('Toggle Theme') }}">
                <svg x-show="!dark" class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.2A9 9 0 0 1 10.8 3 9 9 0 1 0 21 13.2Z"/></svg>
                <svg x-show="dark" x-cloak class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.4 1.4m11.2 11.2L19 19M5 19l1.4-1.4M17.6 6.4 19 5"/></svg>
            </button>
        </div>

        {{-- Mobile navigation dropdown --}}
        @if (!empty($guestNavLinks))
            <div x-show="navOpen" x-cloak x-on:click.outside="navOpen = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="md:hidden absolute top-full left-2 right-2 mt-2 rounded-2xl bg-white dark:bg-slate-950/95 backdrop-blur-2xl border border-slate-200 dark:border-white/15 shadow-2xl p-2 z-50 flex flex-col gap-1">
                <a href="{{ url('/') }}" x-on:click="navOpen = false"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-700 dark:text-white bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    {{ __('Back to Home') }}
                </a>
                @foreach ($guestNavLinks as $link)
                    <a href="{{ $link['url'] }}" x-on:click="navOpen = false"
                       class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ $link['label'] }}</a>
                @endforeach
                <div class="mt-1 pt-2 border-t border-slate-200 dark:border-white/10 grid grid-cols-2 gap-1.5">
                    <a href="{{ route('tenant.login') }}" class="text-center px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 transition">{{ __('Sign in') }}</a>
                    <a href="{{ route('tenant.register') }}" class="text-center px-3 py-2 rounded-xl text-xs font-black text-white bg-blue-600 hover:bg-blue-700 transition">{{ __('Register') }}</a>
                </div>
            </div>
        @endif
    </header>

    <!-- Main Auth Content Area -->
    <main class="auth-main relative z-10">
        @php($__flash = collect(['error', 'warning', 'status', 'success'])->first(fn ($k) => session($k)))
        @if ($__flash)
            <div role="status" class="auth-flash mb-4 rounded-xl border px-4 py-3 text-xs font-semibold {{ in_array($__flash, ['error', 'warning']) ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' }}">
                {{ session($__flash) }}
            </div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Simple Modern Auth Footer -->
    <div class="auth-footer py-6 px-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-white/70 z-10">
        <div class="flex items-center gap-2">
            <span>&copy; {{ now()->year }} {{ $guestBrandName }}.</span>
            <span>{{ __('All rights reserved.') }}</span>
        </div>
        @if($guestLandingEnabled)
            <div class="flex items-center gap-4 text-slate-600 dark:text-white/80 font-medium">
                <a href="{{ url('/') }}#pricing" class="hover:text-brand-lime transition">{{ __('Pricing Plans') }}</a>
                <span>&middot;</span>
                <a href="{{ url('/') }}#contact" class="hover:text-brand-lime transition">{{ __('Support & Contact') }}</a>
            </div>
        @endif
    </div>

    @livewireScripts
</body>
</html>
