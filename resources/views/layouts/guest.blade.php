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
@endphp
<!DOCTYPE html>
<html lang="{{ $guestActiveLang?->code ?? 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" x-data="{ dark: localStorage.getItem('theme') === 'dark' }" x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) }); document.documentElement.classList.toggle('dark', dark)">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $guestBrandName }} &middot; Authentication</title>
    @if ($guestFaviconUrl)
        <link rel="icon" href="{{ $guestFaviconUrl }}">
    @endif

    <!-- Google Fonts: Plus Jakarta Sans, Inter, JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700;1,800&family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root {
            --color-brand-emerald: {{ $branding->landing_primary_color ?: '#10b981' }};
            --color-brand-lime: {{ $branding->landing_accent_color ?: '#d7f24e' }};
            --color-brand-teal: {{ $branding->primary_color ?: '#0c5966' }};
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between p-3 sm:p-6 lg:p-10 font-sans antialiased relative overflow-x-hidden selection:bg-brand-lime selection:text-slate-900">
    
    @include('layouts.partials.preloader')

    <!-- Ambient Aurora Canvas Background matching landing page -->
    <div class="fixed inset-0 bg-gradient-to-br from-[#0c5966] via-[#10707e] to-[#6da734] dark:from-[#06242a] dark:via-[#09353c] dark:to-[#2b4414] -z-20"></div>

    <!-- Soft radial glow orbs -->
    <div class="fixed top-1/4 -left-20 w-96 h-96 rounded-full bg-teal-400/20 blur-[120px] pointer-events-none -z-10 animate-pulse-glow"></div>
    <div class="fixed bottom-10 right-0 w-[500px] h-[500px] rounded-full bg-lime-400/20 blur-[130px] pointer-events-none -z-10 animate-pulse-glow" style="animation-delay: 2s;"></div>

    <!-- Top Floating Navigation Bar -->
    <div class="w-full max-w-6xl mx-auto flex items-center justify-between py-3 px-2 sm:px-4 z-20 mb-4 sm:mb-6">
        {{-- Top Return Navigation Link --}}
        @if(setting('landing_page_enabled', true))
            <a href="{{ url('/') }}" class="group inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/15 text-xs sm:text-sm font-bold text-white transition active:scale-95 shadow-md">
                <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>{{ __('Back to Home') }}</span>
            </a>
        @else
            <div class="w-10"><!-- Spacer when landing page is disabled --></div>
        @endif

        <!-- Center Brand Logo linking back to Home -->
        <a @if(setting('landing_page_enabled', true)) href="{{ url('/') }}" @endif class="inline-flex items-center gap-2.5 group">
            @if ($guestLogoUrl)
                <img src="{{ $guestLogoUrl }}" alt="{{ $guestBrandName }}" class="h-8 w-auto object-contain">
            @else
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-lime to-emerald-400 p-1 flex items-center justify-center shadow-md shadow-emerald-400/30 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5 text-slate-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round">
                        <path d="M7 17L17 7M11 17L17 11M7 13L13 7" />
                    </svg>
                </div>
            @endif
            <span class="text-base sm:text-lg font-black tracking-tight text-white hidden sm:inline">{{ $guestBrandName }}</span>
        </a>

        <!-- Right Quick Actions -->
        <div class="flex items-center gap-2 sm:gap-2.5">
            
            <!-- Language Switcher Dropdown (Auth) -->
            @if ($guestLanguages->isNotEmpty())
                <div class="relative" x-data="{ openAuthLang: false }">
                    <button type="button"
                            x-on:click="openAuthLang = !openAuthLang"
                            x-on:click.outside="openAuthLang = false"
                            class="p-2 sm:px-3 sm:py-2 rounded-full bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-bold transition border border-white/15 flex items-center gap-1.5 cursor-pointer shadow-2xs"
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
                         class="absolute right-0 mt-2 w-52 rounded-2xl bg-slate-950/95 backdrop-blur-2xl border border-white/15 shadow-2xl p-2 z-50 space-y-0.5">
                        <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            {{ __('Select Language') }}
                        </div>
                        <div class="max-h-56 overflow-y-auto no-scrollbar space-y-0.5">
                            @foreach ($guestLanguages as $lang)
                                <a href="{{ route('locale.switch', $lang->code) }}"
                                   @class([
                                       'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition',
                                       'bg-brand-lime/20 text-brand-lime font-bold border border-brand-lime/30' => ($guestActiveLang?->code ?? 'en') === $lang->code,
                                       'text-slate-300 hover:bg-white/10 hover:text-white' => ($guestActiveLang?->code ?? 'en') !== $lang->code,
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
                    class="p-2.5 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/15 text-slate-200 text-xs font-semibold transition"
                    title="{{ __('Toggle Theme') }}">
                <span x-show="!dark">🌙</span>
                <span x-show="dark">☀️</span>
            </button>
            @if(setting('landing_page_enabled', true))
                <a href="{{ url('/') }}#features" class="hidden md:inline-flex px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/15 text-xs font-bold text-slate-200 transition">
                    {{ __('Platform Features') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Main Auth Card Outer Container (Matching Highnote Rounded Hero Card) -->
    <div class="w-full max-w-6xl mx-auto my-auto rounded-[2.5rem] sm:rounded-[3rem] bg-slate-950/90 backdrop-blur-2xl border border-white/15 shadow-[0_30px_90px_-15px_rgba(0,0,0,0.5)] p-6 sm:p-10 md:p-12 relative overflow-hidden z-10">
        
        <!-- Vibrant Corner Glows -->
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-gradient-to-bl from-brand-lime/30 to-transparent rounded-full blur-3xl pointer-events-none -z-0"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-gradient-to-tr from-teal-400/20 to-transparent rounded-full blur-3xl pointer-events-none -z-0"></div>

        <!-- Inner Content (Login / Register / Onboarding) -->
        <div class="relative z-10 w-full">
            {{ $slot }}
        </div>

    </div>

    <!-- Simple Modern Auth Footer -->
    <div class="w-full max-w-6xl mx-auto py-6 px-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-white/70 z-10">
        <div class="flex items-center gap-2">
            <span>&copy; {{ now()->year }} {{ $guestBrandName }}.</span>
            <span>{{ __('All rights reserved.') }}</span>
        </div>
        @if(setting('landing_page_enabled', true))
            <div class="flex items-center gap-4 text-white/80 font-medium">
                <a href="{{ url('/') }}#pricing" class="hover:text-brand-lime transition">{{ __('Pricing Plans') }}</a>
                <span>&middot;</span>
                <a href="{{ url('/') }}#contact" class="hover:text-brand-lime transition">{{ __('Support & Contact') }}</a>
            </div>
        @endif
    </div>

    @livewireScripts
</body>
</html>
