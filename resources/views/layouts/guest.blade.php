@php
    $guestTenantCompany = app()->bound('tenant.company_id')
        ? \App\Models\Company::withoutGlobalScopes()->find(app('tenant.company_id'))
        : null;
    $branding = \App\Models\PlatformBranding::current();
    $guestBrandName = $guestTenantCompany?->trade_name ?: ($guestTenantCompany?->name ?: ($branding?->platform_name ?? config('app.name', 'Smart Inventory')));
    $guestLogoUrl = $guestTenantCompany?->logo ?: $branding?->logo_url;
    $guestFaviconUrl = $guestTenantCompany?->favicon ?: $branding?->favicon_url;
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
    $guestNavLinks = $guestLandingEnabled ? [
        ['label' => __('Features'), 'url' => url('/') . '#features'],
        ['label' => __('Pricing'), 'url' => url('/') . '#pricing'],
        ['label' => __('Contact'), 'url' => url('/') . '#contact'],
    ] : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $guestActiveLang?->code ?? 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $guestForceDark ? 'dark' : '' }}" x-data="{ dark: document.documentElement.classList.contains('dark') }" x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) }); document.documentElement.classList.toggle('dark', dark)">
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
        html { background: #f1f5f9; }
        html.dark { background: #020617; }
        body { margin: 0; min-height: 100vh; background: #f1f5f9; color: #0f172a;
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
<body class="bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen flex flex-col justify-between p-3 sm:p-6 lg:p-10 font-sans antialiased relative overflow-x-hidden selection:bg-brand-lime selection:text-slate-900">
    
    @include('layouts.partials.preloader')

    <!-- Ambient Aurora Canvas Background matching landing page -->
    <div class="fixed inset-0 bg-slate-100 dark:bg-gradient-to-br dark:from-[#06242a] dark:via-[#09353c] dark:to-[#2b4414] -z-20"></div>

    <!-- Soft radial glow orbs (decorative; hidden on phones / reduced-motion) -->
    <div class="auth-ambient fixed top-1/4 -left-20 w-96 h-96 rounded-full bg-teal-400/20 blur-3xl pointer-events-none -z-10"></div>
    <div class="auth-ambient fixed bottom-10 right-0 w-[500px] h-[500px] rounded-full bg-lime-400/15 blur-3xl pointer-events-none -z-10"></div>

    <!-- Top Floating Navigation Bar -->
    <header x-data="{ navOpen: false }"
            class="w-full max-w-6xl mx-auto flex items-center justify-between gap-3 py-3 px-2 sm:px-4 z-30 mb-4 sm:mb-6 relative">

        <!-- Brand Logo (links home when the landing page is live) -->
        <a @if($guestLandingEnabled) href="{{ url('/') }}" @endif
           class="inline-flex items-center gap-2.5 group shrink-0 {{ $guestLandingEnabled ? '' : 'pointer-events-none' }}">
            @if ($guestLogoUrl)
                <img src="{{ $guestLogoUrl }}" alt="{{ $guestBrandName }}" class="h-8 w-auto object-contain">
            @else
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-lime to-emerald-400 p-1 flex items-center justify-center shadow-md shadow-emerald-400/30 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5 text-slate-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round">
                        <path d="M7 17L17 7M11 17L17 11M7 13L13 7" />
                    </svg>
                </div>
            @endif
            <span class="text-sm sm:text-lg font-black tracking-tight text-slate-900 dark:text-white truncate max-w-[9rem] sm:max-w-none">{{ $guestBrandName }}</span>
        </a>

        @if ($guestLandingEnabled)
            {{-- Back to Home (compact, beside the logo on tablet+) --}}
            <a href="{{ url('/') }}" class="group hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 border border-slate-200 dark:border-white/15 text-xs font-bold text-slate-700 dark:text-white transition shrink-0">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                <span>{{ __('Back to Home') }}</span>
            </a>

            <!-- Desktop inline navigation -->
            <nav class="hidden md:flex items-center gap-1 bg-slate-100 dark:bg-white/10 backdrop-blur-md border border-slate-200 dark:border-white/15 rounded-full px-2 py-1">
                @foreach ($guestNavLinks as $link)
                    <a href="{{ $link['url'] }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <!-- Right Quick Actions -->
        <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">

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
                        <span class="text-sm">{{ $guestActiveLang?->flag ?: '🌐' }}</span>
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
                                       'bg-brand-lime/20 text-brand-lime font-bold border border-brand-lime/30' => ($guestActiveLang?->code ?? 'en') === $lang->code,
                                       'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ($guestActiveLang?->code ?? 'en') !== $lang->code,
                                   ])>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ $lang->flag }}</span>
                                        <span>{{ $lang->native_name ?: $lang->name }}</span>
                                    </div>
                                    @if (($guestActiveLang?->code ?? 'en') === $lang->code)
                                        <span class="text-brand-lime font-black text-xs">✓</span>
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
                <span x-show="!dark">🌙</span>
                <span x-show="dark">☀️</span>
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
                    <a href="{{ route('tenant.register') }}" class="text-center px-3 py-2 rounded-xl text-xs font-black text-slate-950 bg-brand-lime hover:bg-brand-lime-dark transition">{{ __('Register') }}</a>
                </div>
            </div>
        @endif
    </header>

    <!-- Main Auth Card Outer Container (Matching Highnote Rounded Hero Card) -->
    <div class="w-full max-w-6xl mx-auto my-auto rounded-[2.5rem] sm:rounded-[3rem] bg-white dark:bg-slate-950/90 backdrop-blur-2xl border border-slate-200 dark:border-white/15 shadow-xl dark:shadow-[0_30px_90px_-15px_rgba(0,0,0,0.5)] p-6 sm:p-10 md:p-12 relative overflow-hidden z-10">
        
        <!-- Vibrant Corner Glows -->
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-gradient-to-bl from-brand-lime/30 to-transparent rounded-full blur-3xl pointer-events-none -z-0"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-gradient-to-tr from-teal-400/20 to-transparent rounded-full blur-3xl pointer-events-none -z-0"></div>

        <!-- Inner Content (Login / Register / Onboarding) -->
        <div class="relative z-10 w-full">
            @php($__flash = collect(['error', 'warning', 'status', 'success'])->first(fn ($k) => session($k)))
            @if ($__flash)
                <div class="mb-4 rounded-xl border px-4 py-3 text-xs font-semibold {{ in_array($__flash, ['error', 'warning']) ? 'border-rose-500/30 bg-rose-500/10 text-rose-300' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' }}">
                    {{ session($__flash) }}
                </div>
            @endif
            {{ $slot }}
        </div>

    </div>

    <!-- Simple Modern Auth Footer -->
    <div class="w-full max-w-6xl mx-auto py-6 px-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-white/70 z-10">
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
