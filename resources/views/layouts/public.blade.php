@php
    $publicBranding = $branding ?? \App\Models\PlatformBranding::current();
    $publicHeaderMenu = \App\Models\MenuItem::getMenu('header');
    $publicFooterCol1 = \App\Models\MenuItem::getMenu('footer_col_1');
    $publicFooterCol2 = \App\Models\MenuItem::getMenu('footer_col_2');
    $publicFooterPages = $footerPages ?? \App\Models\Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get();
    $publicLocService = app(\App\Services\Localization\LocalizationService::class);
    $publicActiveLang = $publicLocService->getActiveLanguage();
    $publicLanguages = $publicLocService->getActiveLanguages();
    $isRtl = $publicLocService->isRtl();

    // The fast theme ships a ~9 KB-gzip scoped stylesheet; the four legacy
    // themes still need the full app.css for their richer markup.
    $publicTheme = $appearance['theme'] ?? setting('landing_page_theme', 'theme_fast');
    $publicCssBundle = $publicTheme === 'theme_fast' ? 'resources/css/public.css' : 'resources/css/app.css';
    // Only the fast theme has a light palette; the four legacy themes are
    // dark-committed, so pin them to dark regardless of the viewer toggle.
    $publicForceDark = $publicTheme !== 'theme_fast';
@endphp
<!DOCTYPE html>
<html lang="{{ $publicActiveLang?->code ?? 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth {{ $publicForceDark ? 'dark' : '' }}" x-data="{ dark: document.documentElement.classList.contains('dark') }" x-init="$watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $publicBranding->platform_name)</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    @if ($publicBranding->favicon_url)
        <link rel="icon" href="{{ $publicBranding->favicon_url }}">
    @endif

    {{-- Set the saved theme before CSS is requested, preventing a light/dark flash. --}}
    <script>try{document.documentElement.classList.toggle('dark',localStorage.getItem('theme')==='dark')}catch(e){}</script>
    @if ($publicForceDark)<script>document.documentElement.classList.add('dark')</script>@endif

    {{-- Critical above-the-fold CSS: paints the page background and sticky
         header before the (render-blocking but small) public stylesheet
         arrives, so there is no flash of unstyled header. --}}
    <style>
        :root {
            --color-brand-emerald: {{ $publicBranding->landing_primary_color ?: '#10b981' }};
            --color-brand-lime: {{ $publicBranding->landing_accent_color ?: '#d7f24e' }};
            --color-brand-teal: {{ $publicBranding->primary_color ?: '#0c5966' }};
        }
        html { background: #f8fafc; }
        html.dark { background: #020617; }
        body { margin: 0; min-height: 100vh; background: #f8fafc; color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ui-sans-serif, system-ui, Helvetica, Arial, sans-serif; }
        html.dark body { background: #020617; color: #f1f5f9; }
        [x-cloak] { display: none !important; }
    </style>

    {{-- Public stylesheet (scoped ~9 KB gzip for the fast theme, full app.css
         for legacy themes) + an Alpine-only runtime, both deferred. Livewire
         is not loaded on the marketing site. --}}
    @vite([$publicCssBundle, 'resources/js/public-navigation.js'])
</head>
<body class="public-site bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 font-sans antialiased min-h-screen selection:bg-brand-lime selection:text-slate-900">

    <!-- Global Floating / Sticky Navbar -->
    <header x-data="{ mobileOpen: false }"
            x-effect="document.body.classList.toggle('overflow-hidden', mobileOpen)"
            class="sticky top-0 z-50 bg-white/85 dark:bg-slate-950/85 backdrop-blur-md border-b border-slate-200 dark:border-white/10">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-16 sm:h-[72px] flex items-center justify-between gap-3">
            <!-- Brand Logo -->
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 min-w-0 group">
                @if ($publicBranding->logo_url)
                    <img src="{{ $publicBranding->logo_url }}" alt="{{ $publicBranding->platform_name }}" class="h-8 w-auto object-contain" decoding="async" fetchpriority="high">
                @else
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-lime via-emerald-400 to-teal-500 p-0.5 shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform flex items-center justify-center">
                        <div class="w-full h-full bg-white dark:bg-slate-950 rounded-[10px] flex items-center justify-center text-brand-lime font-black text-lg">
                            <svg class="w-5 h-5 text-brand-lime" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <path d="M7 17L17 7M11 17L17 11M7 13L13 7" />
                            </svg>
                        </div>
                    </div>
                @endif
                <span class="text-sm sm:text-lg font-black tracking-tight text-slate-900 dark:text-white truncate max-w-[12rem] sm:max-w-none">{{ $publicBranding->platform_name }}</span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden lg:flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/40 backdrop-blur-md px-4 py-1.5 rounded-full border border-slate-200 dark:border-white/10">
                @if(!empty($publicHeaderMenu))
                    @foreach($publicHeaderMenu as $item)
                        @php
                            $itemUrl = is_array($item) ? $item['url'] : $item->url;
                            $itemTitle = is_array($item) ? $item['title'] : $item->title;
                            $itemTarget = is_array($item) ? ($item['target'] ?? '_self') : ($item->target ?? '_self');
                            if (str_starts_with($itemUrl, '#')) {
                                $itemUrl = request()->is('/') ? $itemUrl : url('/' . $itemUrl);
                            } elseif (!str_starts_with($itemUrl, 'http://') && !str_starts_with($itemUrl, 'https://') && !str_starts_with($itemUrl, '/')) {
                                $itemUrl = url($itemUrl);
                            }
                        @endphp
                        <a href="{{ $itemUrl }}"
                           target="{{ $itemTarget }}"
                           class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">
                            {{ $itemTitle }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ url('/') }}#showcase" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Platform') }}</a>
                    <a href="{{ url('/') }}#features" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Products') }}</a>
                    <a href="{{ url('/') }}#solutions" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Solutions') }}</a>
                    <a href="{{ url('/') }}#pricing" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Pricing') }}</a>
                    @if ($publicBranding->isSectionEnabled('downloads') && $publicBranding->hasAnyDownloadLink())
                        <a href="{{ url('/') }}#download" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Download') }}</a>
                    @endif
                    @if ($publicBranding->isSectionEnabled('faq'))
                        <a href="{{ url('/') }}#faq" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('FAQ') }}</a>
                    @endif
                    <a href="{{ url('/') }}#about" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Company') }}</a>
                    <a href="{{ url('/') }}#contact" class="px-3.5 py-2.5 rounded-full text-xs font-semibold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Contact') }}</a>
                @endif
            </nav>

            <!-- Right Actions -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">

                <!-- Language Switcher Dropdown (Desktop) -->
                @if ($publicLanguages->isNotEmpty())
                    <div class="relative hidden md:block" x-data="{ openLang: false }">
                        <button type="button"
                                x-on:click="openLang = !openLang"
                                x-on:click.outside="openLang = false"
                                class="px-3 py-2 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 text-xs font-bold transition border border-slate-200 dark:border-white/10 flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                title="{{ __('Switch Language') }}">
                            <span class="text-sm">{{ $publicActiveLang?->flag ?: '🌐' }}</span>
                            <span class="uppercase text-[11px] font-mono font-bold">{{ $publicActiveLang?->code ?? 'EN' }}</span>
                            <svg class="w-3 h-3 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div x-show="openLang"
                             x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="transform opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="transform opacity-0 scale-95 translate-y-1"
                             class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-950/95 backdrop-blur-2xl border border-slate-200 dark:border-white/15 shadow-2xl p-2 z-50 space-y-1">
                            <div class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ __('Select Language') }}
                            </div>
                            <div class="max-h-64 overflow-y-auto no-scrollbar space-y-0.5">
                                @foreach ($publicLanguages as $lang)
                                    <a href="{{ route('locale.switch', $lang->code) }}"
                                       @class([
                                           'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition',
                                           'bg-brand-lime/20 text-brand-lime font-bold border border-brand-lime/30' => ($publicActiveLang?->code ?? 'en') === $lang->code,
                                           'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ($publicActiveLang?->code ?? 'en') !== $lang->code,
                                       ])>
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">{{ $lang->flag }}</span>
                                            <span>{{ $lang->native_name ?: $lang->name }}</span>
                                        </div>
                                        @if (($publicActiveLang?->code ?? 'en') === $lang->code)
                                            <span class="text-brand-lime font-black text-xs">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Dark / Light Theme Toggle -->
                <button type="button" x-on:click="dark = !dark"
                        class="hidden md:inline-flex p-2 rounded-full bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 text-xs font-semibold transition border border-slate-200 dark:border-white/10"
                        title="{{ __('Toggle Theme') }}">
                    <span x-show="!dark">🌙</span>
                    <span x-show="dark">☀️</span>
                </button>

                <a href="{{ route('tenant.login') }}" class="hidden sm:inline-block px-4 py-2 rounded-full text-xs font-bold text-slate-600 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition">
                    {{ __('Sign in') }}
                </a>
                <a href="{{ route('tenant.register') }}" class="hidden sm:inline-flex px-5 py-2.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-sm font-black shadow-lg shadow-brand-lime/20 transition active:scale-95">
                    {{ __('Start Free Trial') }}
                </a>

                <!-- Mobile Menu Toggle -->
                <button type="button" x-on:click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" class="lg:hidden w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-white border border-slate-200 dark:border-white/10" title="{{ __('Menu') }}">
                    <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <!-- Mobile Nav Drawer -->
        <div x-show="mobileOpen"
             x-cloak
             x-transition:enter="transition transform ease-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition transform ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             x-on:keydown.escape.window="mobileOpen = false"
             class="lg:hidden fixed top-16 sm:top-[72px] right-0 bottom-0 w-full sm:w-96 border-t sm:border-l border-slate-200 dark:border-white/10 px-4 py-4 flex flex-col gap-1 bg-white dark:bg-slate-950 shadow-2xl overflow-y-auto overscroll-contain">
            <div class="flex items-center justify-between px-1 pb-3 mb-2 border-b border-slate-200 dark:border-white/10">
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Navigation') }}</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $publicBranding->platform_name }}</div>
                </div>
                <button type="button" x-on:click="dark = !dark" class="h-9 px-3 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-white/10">
                    <span x-show="!dark">🌙 {{ __('Dark') }}</span>
                    <span x-show="dark">☀️ {{ __('Light') }}</span>
                </button>
            </div>
            @if(!empty($publicHeaderMenu))
                @foreach($publicHeaderMenu as $item)
                    @php
                        $itemUrl = is_array($item) ? $item['url'] : $item->url;
                        $itemTitle = is_array($item) ? $item['title'] : $item->title;
                        $itemTarget = is_array($item) ? ($item['target'] ?? '_self') : ($item->target ?? '_self');
                        if (str_starts_with($itemUrl, '#')) {
                            $itemUrl = request()->is('/') ? $itemUrl : url('/' . $itemUrl);
                        } elseif (!str_starts_with($itemUrl, 'http://') && !str_starts_with($itemUrl, 'https://') && !str_starts_with($itemUrl, '/')) {
                            $itemUrl = url($itemUrl);
                        }
                    @endphp
                    <a href="{{ $itemUrl }}"
                       target="{{ $itemTarget }}"
                       x-on:click="mobileOpen = false"
                       class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">
                        {{ $itemTitle }}
                    </a>
                @endforeach
            @else
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#showcase" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Platform') }}</a>
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#features" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Products') }}</a>
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#solutions" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Solutions') }}</a>
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#pricing" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Pricing') }}</a>
                @if ($publicBranding->isSectionEnabled('downloads') && $publicBranding->hasAnyDownloadLink())
                    <a x-on:click="mobileOpen = false" href="{{ url('/') }}#download" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Download') }}</a>
                @endif
                @if ($publicBranding->isSectionEnabled('faq'))
                    <a x-on:click="mobileOpen = false" href="{{ url('/') }}#faq" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('FAQ') }}</a>
                @endif
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#about" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Company') }}</a>
                <a x-on:click="mobileOpen = false" href="{{ url('/') }}#contact" class="px-4 py-3 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-50 dark:bg-white/[0.04] hover:bg-slate-100 dark:hover:bg-white/10 transition">{{ __('Contact') }}</a>
            @endif

            <!-- Mobile Language Switcher -->
            @if ($publicLanguages->isNotEmpty())
                <div class="mt-2 pt-4 border-t border-slate-200 dark:border-white/10">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 px-1">{{ __('Choose Language') }}</div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach ($publicLanguages as $lang)
                            <a href="{{ route('locale.switch', $lang->code) }}"
                               @class([
                                   'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold transition',
                                   'bg-brand-lime/20 text-brand-lime border border-brand-lime/30' => ($publicActiveLang?->code ?? 'en') === $lang->code,
                                   'bg-slate-50 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ($publicActiveLang?->code ?? 'en') !== $lang->code,
                               ])>
                                <span>{{ $lang->flag }}</span>
                                <span class="truncate">{{ $lang->native_name ?: $lang->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sticky bottom-0 mt-auto pt-4 pb-[max(0px,env(safe-area-inset-bottom))] border-t border-slate-200 dark:border-white/10 flex gap-2 bg-white dark:bg-slate-950">
                <a href="{{ route('tenant.login') }}" class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-200 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 transition">{{ __('Sign in') }}</a>
                <a href="{{ route('tenant.register') }}" class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-black text-slate-950 bg-brand-lime hover:bg-brand-lime-dark transition">{{ __('Start Free Trial') }}</a>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main>
        @yield('content')
    </main>

    <!-- Site Footer -->
    <footer class="border-t border-slate-200 dark:border-white/10 bg-white dark:bg-slate-950 text-slate-500 dark:text-slate-400">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-8 lg:gap-12">
                <div class="col-span-2 lg:col-span-1 pr-4">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                        @if ($publicBranding->logo_url)
                            <img src="{{ $publicBranding->logo_url }}" alt="{{ $publicBranding->platform_name }}" class="h-8 w-auto object-contain" loading="lazy" decoding="async">
                        @else
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-lime to-emerald-400 p-0.5 flex items-center justify-center">
                                <div class="w-full h-full bg-white dark:bg-slate-950 rounded-[6px] flex items-center justify-center text-brand-lime font-black text-sm">
                                    <svg class="w-4 h-4 text-brand-lime" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <path d="M7 17L17 7M11 17L17 11M7 13L13 7" />
                                    </svg>
                                </div>
                            </div>
                        @endif
                        <span class="text-base font-black tracking-tight text-slate-900 dark:text-white">{{ $publicBranding->platform_name }}</span>
                    </a>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('The all-in-one cloud POS, smart inventory, dining table KOT, and automated financial ledgers platform built for fast-moving retail stores, supermarkets, and restaurants.') }}
                    </p>
                    <!-- Social Links -->
                    <div class="mt-6 flex items-center gap-2.5">
                        <a href="#" aria-label="X / Twitter" class="w-8 h-8 rounded-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-brand-lime hover:border-brand-lime/40 transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.9 2H22l-7.6 8.7L23 22h-6.9l-5.4-6.9L4.4 22H1.3l8.1-9.3L1 2h7.1l4.9 6.4Zm-1.2 18h1.9L7.4 4h-2Z"/></svg>
                        </a>
                        <a href="#" aria-label="LinkedIn" class="w-8 h-8 rounded-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-brand-lime hover:border-brand-lime/40 transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5M3 9h4v12H3zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.34-.02-3.06-1.87-3.06-1.87 0-2.16 1.46-2.16 2.96V21h-4Z"/></svg>
                        </a>
                        <a href="#" aria-label="GitHub" class="w-8 h-8 rounded-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-brand-lime hover:border-brand-lime/40 transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                        </a>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-200 mb-3">{{ __('Quick Links') }}</h4>
                    <ul class="space-y-2.5 text-xs">
                        @if(!empty($publicFooterCol1))
                            @foreach($publicFooterCol1 as $item)
                                @php
                                    $itemUrl = is_array($item) ? $item['url'] : $item->url;
                                    $itemTitle = is_array($item) ? $item['title'] : $item->title;
                                    $itemTarget = is_array($item) ? ($item['target'] ?? '_self') : ($item->target ?? '_self');
                                    if (str_starts_with($itemUrl, '#')) {
                                        $itemUrl = request()->is('/') ? $itemUrl : url('/' . $itemUrl);
                                    } elseif (!str_starts_with($itemUrl, 'http://') && !str_starts_with($itemUrl, 'https://') && !str_starts_with($itemUrl, '/')) {
                                        $itemUrl = url($itemUrl);
                                    }
                                @endphp
                                <li>
                                    <a href="{{ $itemUrl }}" target="{{ $itemTarget }}" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">
                                        {{ $itemTitle }}
                                    </a>
                                </li>
                            @endforeach
                        @else
                            <li><a href="{{ url('/') }}#features" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Smart Inventory & Stock') }}</a></li>
                            <li><a href="{{ url('/') }}#features" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Retail POS & Checkout') }}</a></li>
                            <li><a href="{{ url('/') }}#features" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Restaurant & Dining KOT') }}</a></li>
                            <li><a href="{{ url('/') }}#features" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Financials & Invoicing') }}</a></li>
                        @endif
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-200 mb-3">{{ __('Solutions') }}</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="{{ url('/') }}#solutions" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Retail & Supermarkets') }}</a></li>
                        <li><a href="{{ url('/') }}#solutions" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Restaurants & Cafes') }}</a></li>
                        <li><a href="{{ url('/') }}#solutions" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Multi-Store Franchises') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-200 mb-3">{{ __('Support') }}</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="{{ url('/') }}#contact" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Contact Support') }}</a></li>
                        @if ($publicBranding->support_email)
                            <li><a href="mailto:{{ $publicBranding->support_email }}" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ $publicBranding->support_email }}</a></li>
                        @endif
                        <li><a href="{{ route('tenant.login') }}" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ __('Customer Login') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-200 mb-3">{{ __('Legal & Company') }}</h4>
                    <ul class="space-y-2.5 text-xs">
                        @if(!empty($publicFooterCol2))
                            @foreach($publicFooterCol2 as $item)
                                @php
                                    $itemUrl = is_array($item) ? $item['url'] : $item->url;
                                    $itemTitle = is_array($item) ? $item['title'] : $item->title;
                                    $itemTarget = is_array($item) ? ($item['target'] ?? '_self') : ($item->target ?? '_self');
                                    if (str_starts_with($itemUrl, '#')) {
                                        $itemUrl = request()->is('/') ? $itemUrl : url('/' . $itemUrl);
                                    } elseif (!str_starts_with($itemUrl, 'http://') && !str_starts_with($itemUrl, 'https://') && !str_starts_with($itemUrl, '/')) {
                                        $itemUrl = url($itemUrl);
                                    }
                                @endphp
                                <li>
                                    <a href="{{ $itemUrl }}" target="{{ $itemTarget }}" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">
                                        {{ $itemTitle }}
                                    </a>
                                </li>
                            @endforeach
                        @else
                            @forelse ($publicFooterPages as $fp)
                                <li><a href="{{ route('pages.show', $fp->slug) }}" class="text-slate-500 dark:text-slate-400 hover:text-brand-lime transition">{{ $fp->title }}</a></li>
                            @empty
                                <li class="text-slate-500">{{ __('Terms & Privacy Policy') }}</li>
                            @endforelse
                        @endif
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-200 dark:border-white/10 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div class="flex flex-wrap items-center gap-4">
                    <span>&copy; {{ now()->year }} {{ $publicBranding->platform_name }}. {{ __('All rights reserved.') }}</span>
                    @if ($publicLanguages->isNotEmpty())
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400">
                            <span>🌐 {{ __('Language') }}:</span>
                            <div class="relative inline-block" x-data="{ openFootLang: false }">
                                <button type="button" x-on:click="openFootLang = !openFootLang" x-on:click.outside="openFootLang = false" class="text-brand-lime font-bold hover:underline flex items-center gap-1 cursor-pointer">
                                    <span>{{ $publicActiveLang?->flag }} {{ $publicActiveLang?->native_name ?: $publicActiveLang?->name ?: 'English' }}</span>
                                    <span>▾</span>
                                </button>
                                <div x-show="openFootLang" x-cloak x-transition class="absolute bottom-full left-0 mb-2 w-52 rounded-2xl bg-white dark:bg-slate-950/95 backdrop-blur-2xl border border-slate-200 dark:border-white/15 shadow-2xl p-2 z-50 space-y-0.5">
                                    <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Choose Language') }}</div>
                                    <div class="max-h-48 overflow-y-auto no-scrollbar space-y-0.5">
                                        @foreach ($publicLanguages as $lang)
                                            <a href="{{ route('locale.switch', $lang->code) }}"
                                               @class([
                                                   'flex items-center justify-between px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                                   'bg-brand-lime/20 text-brand-lime font-bold' => ($publicActiveLang?->code ?? 'en') === $lang->code,
                                                   'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ($publicActiveLang?->code ?? 'en') !== $lang->code,
                                               ])>
                                                <span class="flex items-center gap-1.5">
                                                    <span>{{ $lang->flag }}</span>
                                                    <span>{{ $lang->native_name ?: $lang->name }}</span>
                                                </span>
                                                @if (($publicActiveLang?->code ?? 'en') === $lang->code)
                                                    <span class="text-brand-lime font-bold">✓</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-5 text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5">⚡ {{ __('High Performance') }}</span>
                    <span class="inline-flex items-center gap-1.5">🔒 {{ __('Bank-Grade Security') }}</span>
                    <span class="inline-flex items-center gap-1.5">🌐 {{ __('Global Multi-Currency') }}</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global Toast Feedback -->
    <div x-data="{ show: false, message: '' }"
         x-on:toast.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 4000)"
         x-show="show" x-cloak x-transition
         class="fixed bottom-5 inset-x-0 sm:inset-x-auto sm:right-5 z-50 flex justify-center sm:justify-end px-4 sm:px-0">
        <div class="flex items-center gap-2.5 px-5 py-3.5 rounded-2xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-sm font-bold shadow-2xl max-w-sm">
            <span>✅</span>
            <span x-text="message"></span>
        </div>
    </div>

</body>
</html>
