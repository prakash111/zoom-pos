<!DOCTYPE html>
<html lang="en" x-data="{ 
    dark: localStorage.getItem('theme') === 'dark',
    sidebarOpen: false
}" x-init="
    $watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) });
    document.documentElement.classList.toggle('dark', dark)
">
<head>
    {{-- $tenantCompany, $themeClasses, $uiAccentColorHex and every $can*/$is*
         permission and active-route flag used in this layout are computed
         once per request by TenantNavigationComposer (registered in
         AppServiceProvider::boot()), not recomputed inline here. --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $uiAccentColorHex ?? '#2563eb' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        // Per-user dock position, server-persisted, seeds first paint before
        // localStorage (anti-flicker) and Alpine take over.
        $dockPosition = auth()->user()?->dock_position ?: 'left';
    @endphp
    <title>{{ $title ?? 'POS & Store Manager' }} — {{ auth()->user()?->company?->name ?? config('app.name') }}</title>
    @if (auth()->user()?->company?->favicon)
        <link rel="icon" href="{{ auth()->user()->company->favicon }}">
    @endif
    <link rel="manifest" href="{{ route('tenant.pwa.manifest') }}" crossorigin="use-credentials">
    <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- ApexCharts now loads lazily via Vite (resources/js/charts-loader.js),
         only when the reports dashboard's own DOM marker is present, instead
         of a route-gated raw <script> tag. --}}
    @livewireStyles
    <script>window.platformAppearanceDefaults = @json(appearance_defaults());</script>
    {{-- Loaded here (not pushed from the page that uses it, e.g. Settings >
         Navigation Menu) because a @push('scripts')'d <script> only resolves
         at @stack('scripts') near the end of body — by the time an Alpine
         x-init runs (as soon as this element is processed, typically well
         before the rest of the page has finished loading), Sortable would
         still be undefined and drag-and-drop would silently no-op forever,
         with no retry once the library does finish loading. Same
         early-in-<head> placement the superadmin layout already uses for
         its own (working) SortableJS menu builder. --}}
    <script src="{{ asset('assets/libs/sortable.min.js') }}"></script>
    <script src="{{ asset('assets/js/tenant-navigation-builder.js') }}"></script>

    {{-- Cloak/nprogress/font-family/dockable-nav/theme-utility CSS used to be
         duplicated here as an inline <style> block, re-sent and re-parsed on
         every single page load instead of being cached like the rest of the
         stylesheet. None of it embeds a per-request value, so it now lives in
         resources/css/app.css and is compiled + cached once by Vite. Only the
         next <style> block below is still inline, because it genuinely does
         need a per-request PHP value ($uiAccentColorHex, the tenant's own
         branded accent color) baked into the CSS custom property values. --}}
    <script>
        (function() {
            try {
                var raw = localStorage.getItem('tenant_dock_nav_state');
                var saved = raw ? JSON.parse(raw) : null;
                // Fall back to the server-persisted position (per user, survives a
                // cleared localStorage / a fresh device) instead of a hardcoded default.
                var pos = (saved && saved.position) ? saved.position : @json($dockPosition);
                var mode = (saved && saved.mode) ? saved.mode : 'docked';
                var layout = (saved && saved.layout) ? saved.layout : 'slim';
                var theme = (saved && saved.theme) ? saved.theme : 'violet';
                var sticky = (saved && typeof saved.sticky !== 'undefined') ? saved.sticky : (localStorage.getItem('nav_sticky') === 'true');
                var accent = localStorage.getItem('ui_accent_color') || (saved && saved.uiAccentColor) || '{{ $uiAccentColorHex }}';
                var navTextColor = (saved && saved.navTextColor) ? saved.navTextColor : (localStorage.getItem('nav_text_color') || '#ffffff');
                var navTextActive = (saved && saved.navTextActiveColor) ? saved.navTextActiveColor : (localStorage.getItem('nav_text_active_color') || '#60a5fa');

                document.documentElement.setAttribute('data-dock-pos', pos);
                document.documentElement.setAttribute('data-dock-mode', mode);
                document.documentElement.setAttribute('data-nav-layout', layout);
                document.documentElement.setAttribute('data-nav-theme', theme);
                document.documentElement.setAttribute('data-nav-sticky', sticky ? 'true' : 'false');

                if (accent) {
                    document.documentElement.style.setProperty('--color-primary', accent);
                }
                document.documentElement.style.setProperty('--nav-item-color', navTextColor);
                document.documentElement.style.setProperty('--nav-item-active-color', navTextActive);
            } catch(e) {}
        })();
    </script>
    <style>
        :root {
            --color-primary: {{ $uiAccentColorHex }};
            --color-primary-hover: {{ $uiAccentColorHex }};
            --color-primary-light: rgba(37, 99, 235, 0.12);
            --color-primary-gradient: linear-gradient(135deg, {{ $uiAccentColorHex }}, #1e293b);
            --nav-item-color: #ffffff;
            --nav-item-active-color: #60a5fa;
        }
    </style>
<body class="bg-[#1a1f37] dark:bg-[#0c101d] text-slate-900 dark:text-slate-100 antialiased selection:bg-blue-500 selection:text-white {{ $isPosScreen ? 'h-screen overflow-hidden p-0 sm:p-2' : 'min-h-screen p-2 sm:p-4 md:p-6' }}">

    @include('layouts.partials.preloader')

    <!-- Impersonation Banner -->
    @if (session('impersonator_id'))
        <div class="mb-3 bg-amber-400 text-amber-950 text-xs sm:text-sm px-5 py-2.5 rounded-2xl flex items-center justify-between shadow-md">
            <span class="font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                {{ __("Viewing as") }} {{ auth()->user()->name }} ({{ auth()->user()->email }})
            </span>
            <form method="POST" action="{{ route('tenant.impersonate.stop') }}">
                @csrf
                <button type="submit" class="font-bold underline hover:opacity-80">{{ __("Return to my account") }}</button>
            </form>
        </div>
    @endif

    {{-- Same note as <head>: all of these are supplied by
         TenantNavigationComposer now, computed once per request. --}}

    <!-- Main Outer Container with Draggable & Dockable Layout Binding -->
    <div x-data="dockableNav('tenant_dock_nav_state', @js($dockPosition), '{{ $isRestaurant ? 'restaurant' : 'general' }}', '{{ route('tenant.preferences.dock-position') }}')"
         :class="{
             'flex-row': position === 'left' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-row-reverse': position === 'right' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col': position === 'top' || layout === 'macos-dock' || layout === 'speed-dial',
             'flex-col-reverse': position === 'bottom' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col relative': position === 'floating' || layout === 'macos-dock' || layout === 'speed-dial'
         }"
         class="app-main-frame bg-slate-100 dark:bg-slate-900/90 rounded-[2.5rem] shadow-2xl border border-slate-800/30 dark:border-slate-800 flex overflow-hidden {{ $isPosScreen ? 'h-full max-h-screen rounded-none sm:rounded-[2rem]' : 'min-h-[calc(100vh-2rem)] md:min-h-[calc(100vh-3rem)]' }} transition-all duration-300 relative w-full">

        {{-- The nav chrome below (drag guides, rail/sidebar, macOS dock, speed-dial,
             slide-out drawer & its backdrop) is wrapped in @persist so wire:navigate
             keeps this exact DOM subtree — and its already-initialized dockableNav()
             Alpine state — alive across page swaps instead of tearing it down and
             re-mounting it on every click. Without this, Livewire's navigate.js
             fully replaces <body> on each visit (nothing survives unless persisted),
             forcing Alpine to destroy and re-hydrate this component from scratch and
             replay its `transition-all duration-300` classes, which is what produced
             the visible sidebar flicker/layout-jump on every navigation. --}}
        @persist('tenant-nav-shell')
        <!-- Snap Dock Guides (Visible only while dragging menu) -->
        <div x-show="isDragging" x-cloak class="fixed inset-0 z-50 pointer-events-none transition-all duration-200">
            <div :class="snapZone === 'left' ? 'bg-blue-600/50 border-blue-400 scale-100 shadow-2xl shadow-blue-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute left-2 top-2 bottom-2 w-24 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬅️ {{ __('Dock Left') }}</span>
            </div>
            <div :class="snapZone === 'right' ? 'bg-blue-600/50 border-blue-400 scale-100 shadow-2xl shadow-blue-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute right-2 top-2 bottom-2 w-24 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">➡️ {{ __('Dock Right') }}</span>
            </div>
            <div :class="snapZone === 'top' ? 'bg-blue-600/50 border-blue-400 scale-100 shadow-2xl shadow-blue-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute top-2 left-28 right-28 h-20 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬆️ {{ __('Dock Top') }}</span>
            </div>
            <div :class="snapZone === 'bottom' ? 'bg-blue-600/50 border-blue-400 scale-100 shadow-2xl shadow-blue-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute bottom-2 left-28 right-28 h-20 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬇️ {{ __('Dock Bottom') }}</span>
            </div>
        </div>

        <!-- ==========================================
             LAYOUT 1 & 2: SLIM RAIL & EXPANDED SIDEBAR
             ========================================== -->
        <aside x-ref="dockNavEl"
               x-show="layout === 'slim' || layout === 'expanded'"
               :style="(position === 'floating' ? `left: ${x}px; top: ${y}px; position: fixed; z-index: 50;` : '') + (customBg ? `background: ${customBg} !important;` : '')"
               :class="{
                   // Visual Themes:
                   '{{ $themeClasses['bg_primary'] }} text-white shadow-2xl': theme === 'violet',
                   'bg-slate-900/80 backdrop-blur-2xl border border-white/20 text-white shadow-2xl': theme === 'glass',
                   'bg-slate-950 border border-slate-700/80 text-slate-100 shadow-2xl rounded-xl font-mono': theme === 'enterprise',
                   'bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-300/40 dark:border-slate-700/50 shadow-[8px_8px_16px_rgba(0,0,0,0.12),-8px_-8px_16px_rgba(255,255,255,0.7)] dark:shadow-[8px_8px_16px_rgba(0,0,0,0.6),-8px_-8px_16px_rgba(255,255,255,0.04)]': theme === 'neumorphic',
                   
                   // Slim Rail:
                   'w-14 sm:w-16 md:w-20 shrink-0 flex flex-col items-center justify-between py-4 px-1 sm:px-2 z-20 shadow-xl': layout === 'slim' && (position === 'left' || position === 'right'),
                   'w-full shrink-0 flex flex-row items-center justify-between py-2 px-3 sm:px-5 z-20 shadow-lg min-h-[3.5rem]': layout === 'slim' && (position === 'top' || position === 'bottom'),
                   
                   // Expanded Sidebar:
                   'w-64 sm:w-72 md:w-80 shrink-0 flex flex-col justify-between p-4 z-20 shadow-2xl overflow-y-auto no-scrollbar': layout === 'expanded' && (position === 'left' || position === 'right'),
                   'w-full shrink-0 flex flex-row items-center justify-between py-2 px-3 sm:px-5 z-20 shadow-lg overflow-x-auto no-scrollbar min-h-[3.75rem]': layout === 'expanded' && (position === 'top' || position === 'bottom'),
                   
                   // Floating:
                   'w-auto max-w-[95vw] flex flex-row sm:flex-col items-center justify-between gap-3 p-3 rounded-3xl shadow-2xl backdrop-blur-xl border border-white/20': position === 'floating',

                   // Sticky Viewport Pinning:
                   'sticky top-0 z-40': sticky && position !== 'floating' && position !== 'bottom',
                   'sticky bottom-0 z-40': sticky && position === 'bottom'
               }"
               class="hidden lg:flex select-none transition-all duration-300">
            
            <!-- Top / Start: Drag Grab Handle & Brand Header -->
            <div :class="{
                      'w-full pb-2 border-b border-white/10 mb-3 flex items-center justify-between': layout === 'expanded' && (position === 'left' || position === 'right'),
                      'w-full pb-2 flex items-center justify-between': layout === 'slim' && (position === 'left' || position === 'right'),
                      'shrink-0 flex items-center gap-2.5 sm:gap-3.5': position === 'top' || position === 'bottom',
                      'shrink-0 flex items-center gap-2': position === 'floating'
                  }">
                
                <!-- Expanded Brand Header -->
                <div x-show="layout === 'expanded' || position === 'top' || position === 'bottom'" class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                    @if (auth()->user()?->company?->logo)
                        <img src="{{ auth()->user()->company->logo }}" alt="{{ auth()->user()->company->name }}" class="w-7 h-7 sm:w-8 sm:h-8 object-contain rounded-xl bg-white/10 p-1 border border-white/15 shrink-0">
                    @else
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-white/20 text-white flex items-center justify-center font-black text-xs sm:text-sm shrink-0">
                            {{ substr(auth()->user()?->company?->name ?? 'Z', 0, 1) }}
                        </div>
                    @endif
                    <div class="truncate" :class="{ 'hidden sm:block': position === 'top' || position === 'bottom' }">
                        <div class="font-black text-xs text-white leading-tight truncate">
                            {{ auth()->user()?->company?->name ?? config('app.name') }}
                        </div>
                        <div class="text-[9px] text-white/60 font-bold uppercase tracking-wider">
                            {{ $isRestaurant ? 'Restaurant' : 'POS' }}
                        </div>
                    </div>
                </div>

                <!-- Grab Handle & Quick Popover Trigger -->
                <div class="relative group/handle flex items-center justify-center shrink-0"
                     :class="{ 'mx-auto': layout === 'slim' && (position === 'left' || position === 'right') }"
                     data-drag-handle>
                    <div class="cursor-grab active:cursor-grabbing p-1.5 rounded-xl bg-white/10 hover:bg-white/25 text-white transition flex items-center justify-center shadow-xs"
                         title="{{ __('Drag to reposition or click for Menu Settings') }}"
                         @mousedown="startDrag($event)"
                         @touchstart="startDrag($event)"
                         @click.stop="toggleQuickMenu()">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="9" cy="6" r="1.75" /><circle cx="15" cy="6" r="1.75" />
                            <circle cx="9" cy="12" r="1.75" /><circle cx="15" cy="12" r="1.75" />
                            <circle cx="9" cy="18" r="1.75" /><circle cx="15" cy="18" r="1.75" />
                        </svg>
                    </div>

                    <!-- Quick Dock Options Dropdown Popover -->
                    <div x-show="showQuickMenu"
                         x-cloak
                         @click.outside="showQuickMenu = false"
                         class="absolute z-50 mt-2 py-2 w-56 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-white/20 shadow-2xl text-xs text-white space-y-1 font-semibold"
                         :class="position === 'right' ? 'right-0' : (position === 'bottom' ? 'bottom-full mb-2 left-0' : 'left-0 top-full')">
                        <div class="px-3 py-1 text-[10px] uppercase font-black tracking-wider text-slate-400 border-b border-white/10 flex items-center justify-between">
                            <span>{{ __('Quick Dock') }}</span>
                            <span class="text-blue-400 capitalize" x-text="position"></span>
                        </div>
                        <button type="button" @click="setPosition('left')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'left' && mode === 'docked') ? 'text-blue-400 font-bold' : ''">
                            <span>⬅️ {{ __('Dock Left') }}</span>
                            <span x-show="position === 'left' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('right')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'right' && mode === 'docked') ? 'text-blue-400 font-bold' : ''">
                            <span>➡️ {{ __('Dock Right') }}</span>
                            <span x-show="position === 'right' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('top')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'top' && mode === 'docked') ? 'text-blue-400 font-bold' : ''">
                            <span>⬆️ {{ __('Dock Top') }}</span>
                            <span x-show="position === 'top' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('bottom')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'bottom' && mode === 'docked') ? 'text-blue-400 font-bold' : ''">
                            <span>⬇️ {{ __('Dock Bottom') }}</span>
                            <span x-show="position === 'bottom' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setMode('floating')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="mode === 'floating' ? 'text-blue-400 font-bold' : ''">
                            <span>🪟 {{ __('Free Floating') }}</span>
                            <span x-show="mode === 'floating'">✓</span>
                        </button>
                        <div class="border-t border-white/10 pt-1">
                            <button type="button" @click="toggleCustomizerModal()" class="w-full px-3 py-1.5 text-left hover:bg-blue-600/30 text-blue-300 flex items-center gap-1.5 transition cursor-pointer">
                                <span>🎨 {{ __('Appearance & Layout Settings') }}</span>
                            </button>
                            <button type="button" @click="resetAll()" class="w-full px-3 py-1.5 text-left hover:bg-rose-500/20 text-rose-300 flex items-center gap-1.5 transition cursor-pointer">
                                <span>🔄 {{ __('Reset Menu Defaults') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Navigation Items Container (Slim Mode) -->
            <div x-show="layout === 'slim'"
                 class="relative flex items-center"
                 :class="{
                     'w-full flex-col my-auto': position === 'left' || position === 'right',
                     'flex-1 flex flex-row mx-1 sm:mx-2 min-w-0': position === 'top' || position === 'bottom',
                     'flex-row sm:flex-col': position === 'floating'
                 }">

                <div data-dock-scroll-container
                     x-ref="scrollNavContainer"
                     class="dockable-nav-container tab-scroll-container"
                     :class="{
                         'w-full flex flex-col items-center gap-4 sm:gap-5 my-auto': position === 'left' || position === 'right',
                         'flex-1 flex flex-row items-center justify-start sm:justify-center gap-1.5 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 scroll-smooth': position === 'top' || position === 'bottom',
                         'flex flex-row sm:flex-col items-center gap-2': position === 'floating'
                     }">
                
                <x-nav.rail-item :route="route('tenant.dashboard')" :active="$isHome" item-key="home" variant="white" label-size="text-[11px] sm:text-xs" label="{{ __('Home') }}">
                    <x-ui.icon name="home" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                </x-nav.rail-item>

                @if ($isRestaurant)
                    @if ($canPos)
                        <x-nav.rail-item :route="route('tenant.restaurant.pos')" :active="$isRestaurantPos" item-key="restaurant_pos" variant="lime" title="{{ __('Food & Restaurant POS') }}" label="{{ __('Food POS') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🍽️</span>
                        </x-nav.rail-item>

                        <x-nav.rail-item :route="route('tenant.restaurant.tables')" :active="$isTables" item-key="tables" title="{{ __('Tables & Floor Plan') }}" label="{{ __('Tables') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🪑</span>
                        </x-nav.rail-item>

                        <x-nav.rail-item :route="route('tenant.restaurant.kds')" :active="$isKds" item-key="kds" title="{{ __('Kitchen Display System') }}" label="{{ __('Kitchen') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🍳</span>
                        </x-nav.rail-item>
                    @endif

                    @if ($canSales)
                        <x-nav.rail-item :route="route('tenant.sales.index')" :active="$isTransaction" item-key="sales" title="{{ __('Dining & Sales History') }}" label="{{ __('Dining History') }}">
                            <x-ui.icon name="receipt" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif
                @else
                    @if ($canPos)
                        <x-nav.rail-item :route="route('tenant.sales.create')" :active="$isCasier" item-key="pos" label="{{ __('Retail POS') }}">
                            <x-ui.icon name="cart" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif

                    @if ($canSales)
                        <x-nav.rail-item :route="route('tenant.sales.index')" :active="$isTransaction" item-key="sales" title="{{ __('Invoices & Sales') }}" label="{{ __('Invoices') }}">
                            <x-ui.icon name="receipt" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif

                    @if ($canQuotes)
                        <x-nav.rail-item :route="route('tenant.quotes.index')" :active="$isQuotes" item-key="quotes" title="{{ __('Quotations & Proposals') }}" label="{{ __('Quotations') }}">
                            <x-ui.icon name="document" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif

                    @if ($canLeads)
                        <x-nav.rail-item :route="route('tenant.leads.index')" :active="$isLeads" item-key="lead_management" title="{{ __('Lead Management') }}" label="{{ __('Leads') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🎯</span>
                        </x-nav.rail-item>
                    @endif

                    @if ($canConsignments)
                        <x-nav.rail-item :route="route('tenant.consignments.index')" :active="$isConsignments" title="{{ __('Consignments') }}" label="{{ __('Consign') }}">
                            <x-ui.icon name="box" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif

                    @if ($canServiceOrders)
                        <x-nav.rail-item :route="route('tenant.service-orders.index')" :active="$isServiceOrders" title="{{ __('Service Orders & Warranty Repairs') }}" label="{{ __('Repairs / OS') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </x-nav.rail-item>
                    @endif

                    @if ($canProducts)
                        <x-nav.rail-item :route="route('tenant.products.index')" :active="$isProducts" item-key="products" title="{{ __('Products & Stock') }}" label="{{ __('Products') }}">
                            <x-ui.icon name="box" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif

                    @if ($canCustomers)
                        <x-nav.rail-item :route="route('tenant.customers.index')" :active="$isCustomers" item-key="customers" title="{{ __('Customers & CRM') }}" label="{{ __('Customers') }}">
                            <x-ui.icon name="users" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                        </x-nav.rail-item>
                    @endif
                @endif

                @if ($canCashRegister)
                    <x-nav.rail-item :route="route('tenant.financials.cash_register')" :active="$isCashRegister" item-key="register" title="{{ __('Cash Register') }}" label="{{ __('Register') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🗄️</span>
                    </x-nav.rail-item>
                @endif

                @if ($canReports)
                    <x-nav.rail-item :route="route('tenant.reports.index')" :active="$isReports" item-key="reports" title="{{ __('Reports & Analytics') }}" label="{{ __('Reports') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">📊</span>
                    </x-nav.rail-item>
                @endif

                @if ($canTargets)
                    <x-nav.rail-item :route="route('tenant.sales-targets.index')" :active="$isSalesTargets" title="{{ __('Sales Targets & Goals') }}" label="{{ __('Targets') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🎯</span>
                    </x-nav.rail-item>
                @endif

                @if ($canSettings)
                    <x-nav.rail-item :route="route('tenant.settings.index')" :active="$isSettings" item-key="settings" label="{{ __('Settings') }}">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </x-nav.rail-item>
                @endif

                <x-nav.rail-item :route="route('tenant.billing.index')" :active="$isBilling" item-key="billing" title="{{ __('Subscription & Billing') }}" label="{{ __('Billing & Plans') }}">
                    <x-ui.icon name="credit-card" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                </x-nav.rail-item>
                </div>
            </div>

            <!-- Navigation Links (Expanded Mode) -->
            <div x-show="layout === 'expanded'"
                 :class="{
                     'w-full space-y-4 my-2 flex-1 overflow-y-auto no-scrollbar': position === 'left' || position === 'right',
                     'flex-1 flex flex-row items-center justify-center gap-2 sm:gap-3 overflow-x-auto no-scrollbar mx-2 py-1': position === 'top' || position === 'bottom',
                     'w-full flex flex-col gap-2 p-2': position === 'floating'
                 }">
                
                <!-- Category 1: Overview -->
                <div x-show="isItemVisible('home')" :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Store Overview') }}
                    </div>
                    <x-nav.expanded-item :route="route('tenant.dashboard')" :active="$isHome" item-key="home" title="{{ __('Dashboard') }}" subtitle="{{ __('Sales summary & metrics') }}">
                        <span class="text-base shrink-0">📊</span>
                    </x-nav.expanded-item>
                </div>

                <!-- Category 2: POS & Operations -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Sales & POS') }}
                    </div>
                    @if ($isRestaurant)
                        @if ($canPos)
                            <x-nav.expanded-item :route="route('tenant.restaurant.pos')" :active="$isRestaurantPos" item-key="restaurant_pos" variant="lime" title="{{ __('Restaurant POS') }}" subtitle="{{ __('Dine-In, Takeaway, KOT') }}">
                                <span class="text-base shrink-0">🍽️</span>
                            </x-nav.expanded-item>
                            <x-nav.expanded-item :route="route('tenant.restaurant.tables')" :active="$isTables" item-key="tables" title="{{ __('Tables & Floor') }}" subtitle="{{ __('Floor plan & live seats') }}">
                                <span class="text-base shrink-0">🪑</span>
                            </x-nav.expanded-item>
                            <x-nav.expanded-item :route="route('tenant.restaurant.kds')" :active="$isKds" item-key="kds" title="{{ __('Kitchen Display (KDS)') }}" subtitle="{{ __('Order preparation queue') }}">
                                <span class="text-base shrink-0">🍳</span>
                            </x-nav.expanded-item>
                        @endif
                        @if ($canSales)
                            <x-nav.expanded-item :route="route('tenant.sales.index')" :active="$isTransaction" item-key="sales" title="{{ __('Dining History') }}" subtitle="{{ __('Orders & thermal receipts') }}">
                                <span class="text-base shrink-0">🧾</span>
                            </x-nav.expanded-item>
                        @endif
                    @else
                        @if ($canPos)
                            <x-nav.expanded-item :route="route('tenant.sales.create')" :active="$isCasier" item-key="pos" title="{{ __('Cashier POS') }}" subtitle="{{ __('Barcode & Touch Sales') }}">
                                <span class="text-base shrink-0">🛒</span>
                            </x-nav.expanded-item>
                        @endif
                        @if ($canSales)
                            <x-nav.expanded-item :route="route('tenant.sales.index')" :active="$isTransaction" item-key="sales" title="{{ __('Sales & Invoices') }}" subtitle="{{ __('Invoices, returns & receipts') }}">
                                <span class="text-base shrink-0">🧾</span>
                            </x-nav.expanded-item>
                        @endif
                        @if ($canQuotes)
                            <x-nav.expanded-item :route="route('tenant.quotes.index')" :active="$isQuotes" item-key="quotes" title="{{ __('Quotations') }}" subtitle="{{ __('Quotes, proposals & estimates') }}">
                                <span class="text-base shrink-0">📑</span>
                            </x-nav.expanded-item>
                        @endif
                        @if ($canLeads)
                            <x-nav.expanded-item :route="route('tenant.leads.index')" :active="$isLeads" item-key="lead_management" title="{{ __('Lead Management') }}" subtitle="{{ __('Pipeline, follow-ups & auto-sync CRM') }}">
                                <span class="text-base shrink-0">🎯</span>
                            </x-nav.expanded-item>
                        @endif
                        @if ($canCustomers)
                            <x-nav.expanded-item :route="route('tenant.customers.index')" :active="$isCustomers" item-key="customers" title="{{ __('Customers & CRM') }}" subtitle="{{ __('Profiles, history & loyalty') }}">
                                <span class="text-base shrink-0">👥</span>
                            </x-nav.expanded-item>
                        @endif
                    @endif
                </div>

                @if ($canFinance)
                    <!-- Category 3: Financial Management -->
                    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                            {{ __('Financial Management') }}
                        </div>
                        <x-nav.expanded-item :route="route('tenant.financials.cash_register')" :active="$isCashRegister" item-key="register" title="{{ __('Cash Register') }}" subtitle="{{ __('Shifts, float & cash balancing') }}">
                            <span class="text-base shrink-0">🗄️</span>
                        </x-nav.expanded-item>
                        <x-nav.expanded-item :route="route('tenant.financials.receivables')" :active="$isReceivables" item-key="receivables" title="{{ __('Accounts Receivable') }}" subtitle="{{ __('Customer credit & unpaid bills') }}">
                            <span class="text-base shrink-0">📈</span>
                        </x-nav.expanded-item>
                        <x-nav.expanded-item :route="route('tenant.financials.payables')" :active="$isPayables" item-key="receivables" title="{{ __('Accounts Payable') }}" subtitle="{{ __('Supplier bills & expenses') }}">
                            <span class="text-base shrink-0">📉</span>
                        </x-nav.expanded-item>

                        @if ($canReports)
                            <x-nav.expanded-item :route="route('tenant.reports.index')" :active="$isReports" item-key="reports" title="{{ __('Reports & Analytics') }}" subtitle="{{ __('Sales, commissions & aging') }}">
                                <span class="text-base shrink-0">📊</span>
                            </x-nav.expanded-item>
                        @endif
                    </div>
                @endif

                <!-- Category 4: Catalog & Inventory -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Products & Inventory') }}
                    </div>
                    @if ($canProducts)
                        <x-nav.expanded-item :route="route('tenant.products.index')" :active="$isProducts" item-key="products" title="{{ __('Products & Stock') }}" subtitle="{{ __('Catalog, prices & alerts') }}">
                            <span class="text-base shrink-0">📦</span>
                        </x-nav.expanded-item>
                    @endif
                    @if ($canCategories)
                        <x-nav.expanded-item :route="route('tenant.categories.index')" :active="$isCategories" item-key="categories" title="{{ __('Categories') }}" subtitle="{{ __('Departments & tax rates') }}">
                            <span class="text-base shrink-0">🏷️</span>
                        </x-nav.expanded-item>
                    @endif
                    @if ($canCatalog)
                        <x-nav.expanded-item :route="route('tenant.catalog.index')" :active="$isCatalog" item-key="products" title="{{ __('Online Catalog') }}" subtitle="{{ __('Digital catalog & WhatsApp share') }}">
                            <span class="text-base shrink-0">🌐</span>
                        </x-nav.expanded-item>
                    @endif
                </div>

                @include('layouts.partials.vertical-nav-expanded')

                <!-- Category 5: Administration & Settings -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Administration & Settings') }}
                    </div>
                    @if ($canSettings)
                        <x-nav.expanded-item :route="route('tenant.settings.index')" :active="$isSettings" item-key="settings" title="{{ __('Store Settings') }}" subtitle="{{ __('Profile, printer, tax & domain') }}">
                            <span class="text-base shrink-0">⚙️</span>
                        </x-nav.expanded-item>
                    @endif
                    <x-nav.expanded-item :route="route('tenant.billing.index')" :active="$isBilling" item-key="billing" title="{{ __('Subscription & Billing') }}" subtitle="{{ __('Plan details & invoices') }}">
                        <span class="text-base shrink-0">💳</span>
                    </x-nav.expanded-item>
                    @if ($canUsers)
                        <x-nav.expanded-item :route="route('tenant.users.index')" :active="$isUsers" title="{{ __('Users & Permissions') }}" subtitle="{{ __('Staff accounts & access matrix') }}">
                            <span class="text-base shrink-0">🛡️</span>
                        </x-nav.expanded-item>
                    @endif
                </div>

            </div>

            <!-- Bottom / End: Slide-out Toggle Menu & Appearance Trigger -->
            <div :class="{
                     'w-full border-t border-white/10 mt-2 pt-2 flex items-center justify-between gap-2 shrink-0': layout === 'expanded' && (position === 'left' || position === 'right'),
                     'w-full pt-2 flex items-center justify-between gap-2 shrink-0': layout === 'slim' && (position === 'left' || position === 'right'),
                     'shrink-0 flex items-center gap-2 py-1': position === 'top' || position === 'bottom' || position === 'floating'
                 }">
                
                <button type="button"
                        @click="toggleCustomizerModal()"
                        class="p-2 rounded-2xl bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition active:scale-90 shadow-sm cursor-pointer"
                        title="{{ __('Appearance & Layout Settings') }}">
                    <span class="text-sm">🎨</span>
                </button>

                <button type="button"
                        x-on:click="sidebarOpen = !sidebarOpen"
                        class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition active:scale-90 shadow-md cursor-pointer"
                        title="{{ __('Open Full Store Menu') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- ==========================================
             LAYOUT 3: MACOS-STYLE FLOATING ISLAND DOCK
             ========================================== -->
        <nav x-show="layout === 'macos-dock'"
             x-cloak
             :style="customBg ? ('background: ' + customBg + ' !important;') : ''"
             :class="position === 'top' ? 'top-4 sm:top-6' : 'bottom-4 sm:bottom-6'"
             class="dockable-nav-container tab-scroll-container fixed left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-full backdrop-blur-2xl border flex items-center gap-2 sm:gap-3.5 shadow-2xl select-none transition-all duration-300 max-w-[95vw] overflow-x-auto"
             :class="{
                 '{{ $themeClasses['bg_primary'] }} border-blue-400/40 text-white shadow-blue-500/30': theme === 'violet',
                 'bg-slate-900/70 border-white/25 text-white backdrop-blur-3xl shadow-2xl': theme === 'glass',
                 'bg-slate-950 border-slate-700 text-slate-100 rounded-2xl': theme === 'enterprise',
                 'bg-slate-200/90 dark:bg-slate-850/90 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 shadow-[8px_8px_16px_rgba(0,0,0,0.2),-8px_-8px_16px_rgba(255,255,255,0.8)]': theme === 'neumorphic'
             }">
            
            <!-- Grab Handle -->
            <button type="button" @click="toggleQuickMenu()" class="p-1.5 rounded-full hover:bg-white/20 transition text-white/60 hover:text-white shrink-0" title="{{ __('Dock Options') }}">
                ⋮⋮
            </button>

            <!-- 1. Home -->
            <x-nav.pill-item :route="route('tenant.dashboard')" :active="$isHome" title="{{ __('Dashboard') }}">📊</x-nav.pill-item>

            <!-- 2. POS Option -->
            @if ($isRestaurant)
                @if ($canPos)
                    <x-nav.pill-item :route="route('tenant.restaurant.pos')" :active="$isRestaurantPos" item-key="restaurant_pos" variant="lime" title="{{ __('Restaurant POS') }}">🍽️</x-nav.pill-item>
                @endif
            @else
                @if ($canPos)
                    <x-nav.pill-item :route="route('tenant.sales.create')" :active="$isCasier" item-key="pos" title="{{ __('Retail POS') }}">🛒</x-nav.pill-item>
                @endif
            @endif

            <!-- 3. Sales & Invoices -->
            @if ($canSales)
                <x-nav.pill-item :route="route('tenant.sales.index')" :active="$isTransaction" item-key="sales" title="{{ __('Invoices & Sales') }}">🧾</x-nav.pill-item>
            @endif

            <!-- 4. Quotations (General Mode) -->
            @if (!$isRestaurant && $canQuotes)
                <x-nav.pill-item :route="route('tenant.quotes.index')" :active="$isQuotes" item-key="quotes" title="{{ __('Quotations & Proposals') }}">📑</x-nav.pill-item>
            @endif

            @if ($canLeads)
                <x-nav.pill-item :route="route('tenant.leads.index')" :active="$isLeads" item-key="lead_management" title="{{ __('Lead Management') }}">🎯</x-nav.pill-item>
            @endif

            <!-- 5. Products -->
            @if ($canProducts)
                <x-nav.pill-item :route="route('tenant.products.index')" :active="$isProducts" item-key="products" title="{{ __('Products & Catalog') }}">📦</x-nav.pill-item>
            @endif

            <!-- 6. Cash Register -->
            @if ($canFinance)
                <x-nav.pill-item :route="route('tenant.financials.cash_register')" :active="$isCashRegister" item-key="register" title="{{ __('Cash Register') }}">🗄️</x-nav.pill-item>
            @endif

            <!-- 7. Reports -->
            @if ($canReports)
                <x-nav.pill-item :route="route('tenant.reports.index')" :active="$isReports" item-key="reports" title="{{ __('Reports & Analytics') }}">📊</x-nav.pill-item>
            @endif

            <!-- 8. Settings -->
            @if ($canSettings)
                <x-nav.pill-item :route="route('tenant.settings.index')" :active="$isSettings" item-key="settings" title="{{ __('Store Settings') }}">⚙️</x-nav.pill-item>
            @endif

            <!-- Divider -->
            <div class="w-px h-6 bg-white/20 my-auto shrink-0"></div>

            <!-- Customizer Button -->
            <button type="button" @click="toggleCustomizerModal()" class="w-9 h-9 rounded-2xl bg-white/15 hover:bg-white/30 text-white flex items-center justify-center text-base hover:scale-125 transition-transform duration-200 origin-bottom shadow-sm cursor-pointer shrink-0" title="{{ __('Menu Appearance') }}">
                🎨
            </button>

            <!-- Full Drawer Menu -->
            <button type="button" x-on:click="sidebarOpen = true" class="w-9 h-9 rounded-2xl bg-white/15 hover:bg-white/30 text-white flex items-center justify-center text-base hover:scale-125 transition-transform duration-200 origin-bottom shadow-sm cursor-pointer shrink-0" title="{{ __('Full Menu') }}">
                📋
            </button>
        </nav>

        <!-- ==========================================
             LAYOUT 4: COMPACT SPEED-DIAL BUBBLE
             ========================================== -->
        <div x-show="layout === 'speed-dial'"
             x-cloak
             class="fixed bottom-6 right-6 z-50 flex flex-col-reverse items-end gap-3 select-none">
            
            <!-- Primary Bubble Button -->
            <button type="button"
                    @click="toggleSpeedDial()"
                    class="w-14 h-14 rounded-full {{ $themeClasses['bg_primary'] }} text-white shadow-2xl flex items-center justify-center text-xl font-black active:scale-95 transition-transform hover:shadow-blue-500/50 cursor-pointer border-2 border-white/30">
                <span x-show="!speedDialOpen">🧭</span>
                <span x-show="speedDialOpen" class="text-2xl leading-none">&times;</span>
            </button>

            <!-- Speed Dial Expanded Tray -->
            <div x-show="speedDialOpen"
                 x-cloak
                 :style="customBg ? ('background: ' + customBg + ' !important;') : ''"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-90"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-90"
                 class="flex flex-col items-end gap-2.5 p-3 rounded-3xl bg-slate-900/90 backdrop-blur-2xl border border-white/20 shadow-2xl text-xs font-bold text-white">
                
                <x-nav.speed-dial-item :route="route('tenant.dashboard')" label="{{ __('Overview') }}">📊</x-nav.speed-dial-item>

                @if ($isRestaurant)
                    @if ($canPos)
                        <x-nav.speed-dial-item :route="route('tenant.restaurant.pos')" item-key="restaurant_pos" variant="lime" label="{{ __('Restaurant POS') }}">🍽️</x-nav.speed-dial-item>
                    @endif
                @else
                    @if ($canPos)
                        <x-nav.speed-dial-item :route="route('tenant.sales.create')" item-key="pos" label="{{ __('Cashier POS') }}">🛒</x-nav.speed-dial-item>
                    @endif
                @endif

                @if ($canSales)
                    <x-nav.speed-dial-item :route="route('tenant.sales.index')" item-key="sales" label="{{ __('Invoices & Sales') }}">🧾</x-nav.speed-dial-item>
                @endif

                @if (!$isRestaurant && $canQuotes)
                    <x-nav.speed-dial-item :route="route('tenant.quotes.index')" item-key="quotes" label="{{ __('Quotations') }}">📑</x-nav.speed-dial-item>
                @endif

                @if ($canLeads)
                    <x-nav.speed-dial-item :route="route('tenant.leads.index')" item-key="lead_management" label="{{ __('Lead Management') }}">🎯</x-nav.speed-dial-item>
                @endif

                @if ($canFinance)
                    <x-nav.speed-dial-item :route="route('tenant.financials.cash_register')" item-key="register" label="{{ __('Cash Register') }}">🗄️</x-nav.speed-dial-item>
                @endif

                @if ($canReports)
                    <x-nav.speed-dial-item :route="route('tenant.reports.index')" item-key="reports" label="{{ __('Reports') }}">📊</x-nav.speed-dial-item>
                @endif

                @if ($canSettings)
                    <x-nav.speed-dial-item :route="route('tenant.settings.index')" item-key="settings" label="{{ __('Settings') }}">⚙️</x-nav.speed-dial-item>
                @endif

                <div class="w-full border-t border-white/10 my-1"></div>

                <button type="button" @click="toggleCustomizerModal()" class="w-full flex items-center justify-between px-3 py-1.5 rounded-xl hover:bg-blue-600/30 text-blue-300 transition cursor-pointer">
                    <span>{{ __('Layout & Theme') }}</span>
                    <span>🎨</span>
                </button>
            </div>
        </div>

        <!-- Slide-out Drawer with All Store Modules & Navigation Items -->
        <div x-show="sidebarOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 z-50 w-80 bg-white dark:bg-slate-900 shadow-2xl p-6 flex flex-col justify-between border-r border-slate-200 dark:border-slate-800">
            
            <div class="flex-1 overflow-y-auto pr-1">
                <!-- Header inside toggle menu -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                    <div class="flex items-center gap-3">
                        @if (auth()->user()?->company?->logo)
                            <img src="{{ auth()->user()->company->logo }}" alt="{{ auth()->user()->company->name }}" class="w-10 h-10 object-contain rounded-2xl bg-slate-50 dark:bg-slate-800 p-1 border border-slate-200 dark:border-slate-700 shadow-md">
                        @else
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shadow-md">
                                {{ substr(auth()->user()?->company?->name ?? 'Z', 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-extrabold text-sm text-slate-900 dark:text-white leading-tight">
                                {{ auth()->user()?->company?->name ?? config('app.name') }}
                            </div>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online POS & Store
                            </div>
                        </div>
                    </div>

                    <button type="button" x-on:click="sidebarOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-base transition">
                        &times;
                    </button>
                </div>

                <!-- Structured Toggle Menu List Items (Strictly Mode Isolated) -->
                <nav id="tenant-drawer-nav" class="space-y-5 text-xs font-semibold">
                    
                    @if ($isRestaurant)
                        <!-- RESTAURANT MODE DRAWER ITEMS -->
                        @if ($canPos)
                            <div data-section-key="restaurant_operations">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-lime-600 dark:text-lime-400 mb-2 px-3">{{ __('Restaurant Operations') }}</div>
                                <div class="space-y-1">
                                    <x-nav.drawer-item item-key="restaurant_pos" :route="route('tenant.restaurant.pos')" hover="lime" highlighted title="{{ __('Restaurant POS Terminal') }}" subtitle="{{ __('Dine-In, Takeaway & Delivery') }}">🍽️</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="floor_plan" :route="route('tenant.restaurant.tables')" hover="lime" title="{{ __('Floor Plan & Tables') }}" subtitle="{{ __('Live Table Status & QR Menus') }}">🪑</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="kitchen_display" :route="route('tenant.restaurant.kds')" hover="lime" title="{{ __('Kitchen Display (KDS)') }}" subtitle="{{ __('Live KOT preparation queue') }}">🍳</x-nav.drawer-item>
                                </div>
                            </div>
                        @endif

                        <div data-section-key="orders_cash">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Orders & Cash') }}</div>
                            <div class="space-y-1">
                                @if ($canSales)
                                    <x-nav.drawer-item item-key="dining_history" :route="route('tenant.sales.index')" title="{{ __('Dining & Sales History') }}" subtitle="{{ __('Thermal receipts & order history') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                    </x-nav.drawer-item>
                                @endif


                                @if ($canFinance)
                                    <x-nav.drawer-item item-key="cash_register" :route="route('tenant.financials.cash_register')" hover="lime" title="{{ __('Cash Register') }}" subtitle="{{ __('Opening, closing, cash withdrawals') }}">🗄️</x-nav.drawer-item>
                                @endif
                            </div>
                        </div>

                        @if ($canFinance)
                            <div data-section-key="financial_management">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Financial Management') }}</div>
                                <div class="space-y-1">
                                    <x-nav.drawer-item item-key="due_receivables" :route="route('tenant.financials.receivables')" title="{{ __('Accounts Receivable') }}" subtitle="{{ __('Customer credit & unpaid bills') }}">📈</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="accounts_receivable" :route="route('tenant.financials.receivables')" title="{{ __('Accounts Receivable') }}" subtitle="{{ __('Customer credit & unpaid bills') }}">📈</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="payables" :route="route('tenant.financials.payables')" title="{{ __('Accounts Payable') }}" subtitle="{{ __('Supplier bills & food purchases') }}">📉</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="accounts_payable" :route="route('tenant.financials.payables')" title="{{ __('Accounts Payable') }}" subtitle="{{ __('Supplier bills & food purchases') }}">📉</x-nav.drawer-item>

                                    @if ($canReports)
                                        <x-nav.drawer-item item-key="reports" :route="route('tenant.reports.index')" title="{{ __('Reports') }}" subtitle="{{ __('Sales, commissions & aging') }}">📊</x-nav.drawer-item>
                                        <x-nav.drawer-item item-key="analytics" :route="route('tenant.reports.index')" title="{{ __('Analytics') }}" subtitle="{{ __('Sales trends & performance charts') }}">📈</x-nav.drawer-item>
                                        <x-nav.drawer-item item-key="reports_analytics" :route="route('tenant.reports.index')" title="{{ __('Reports & Analytics') }}" subtitle="{{ __('Sales, commissions & aging') }}">📊</x-nav.drawer-item>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div data-section-key="kitchen_menu_catalog">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Kitchen Menu & Catalog') }}</div>
                            <div class="space-y-1">
                                @if ($canProducts)
                                    <x-nav.drawer-item item-key="menu_dishes" :route="route('tenant.products.index')" title="{{ __('Menu Dishes & Stock') }}" subtitle="{{ __('Dishes, ingredients & pricing') }}">📦</x-nav.drawer-item>
                                @endif

                                @if ($canCategories)
                                    <x-nav.drawer-item item-key="categories" :route="route('tenant.categories.index')" title="{{ __('Categories') }}" subtitle="{{ __('Menu sections & tax rates') }}">🏷️</x-nav.drawer-item>
                                @endif

                                <x-nav.drawer-item item-key="brands" :route="route('tenant.brands.index')" title="{{ __('Brands & Modifiers') }}" subtitle="{{ __('Product brands & food options') }}">✨</x-nav.drawer-item>

                                @if ($canUnits)
                                    <x-nav.drawer-item item-key="units" :route="route('tenant.units.index')" title="{{ __('Units of Measure') }}" subtitle="{{ __('Portions, kg, litres & grams') }}">⚖️</x-nav.drawer-item>
                                @endif

                                @if ($canSuppliers)
                                    <x-nav.drawer-item item-key="suppliers" :route="route('tenant.suppliers.index')" title="{{ __('Food Suppliers') }}" subtitle="{{ __('Vendor contacts & purchasing') }}">🚚</x-nav.drawer-item>
                                @endif

                                @if ($canCatalog)
                                    <x-nav.drawer-item item-key="catalog" :route="route('tenant.catalog.index')" title="{{ __('Online QR Menu') }}" subtitle="{{ __('Digital QR menu & WhatsApp store') }}">🌐</x-nav.drawer-item>
                                @endif

                                @if ($canCustomers)
                                    <x-nav.drawer-item item-key="guest_directory" :route="route('tenant.customers.index')" title="{{ __('Guest Directory') }}" subtitle="{{ __('Customer history & contact list') }}">👥</x-nav.drawer-item>
                                @endif
                            </div>
                        </div>

                    @elseif ($isAllModulesDemo || (! $isPharmacy && ! $isSalon && ! $isRepair)
                        && ! in_array(strtoupper((string) (auth()->user()?->company?->business_type
                            ?? auth()->user()?->company?->store_type ?? '')), ['SALON', 'PHARMACY', 'RESTAURANT', 'REPAIR', 'REPAIRS'], true))
                        <!-- GENERAL RETAIL DRAWER ITEMS -->
                        <div data-section-key="cashier_sales">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-2 px-3">{{ __('Cashier & Sales') }}</div>
                            <div class="space-y-1">
                                @if ($canPos)
                                    <x-nav.drawer-item item-key="pos" :route="route('tenant.sales.create')" highlighted title="{{ __('Cashier POS Terminal') }}" subtitle="{{ __('Fast barcode scan & cash checkout') }}">🛒</x-nav.drawer-item>
                                @endif

                                @if ($canProducts)
                                    <x-nav.drawer-item item-key="barcode_printing" :route="route('tenant.products.index')" title="{{ __('Barcode & Label Printing') }}" subtitle="{{ __('Print product labels & barcodes') }}">🏷️</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="batch_tracking" :route="route('tenant.products.index')" title="{{ __('Batch & Expiry Tracking') }}" subtitle="{{ __('Track lots, batches & expirations') }}">📦</x-nav.drawer-item>
                                @endif

                                @if ($canSales)
                                    <x-nav.drawer-item item-key="sales" :route="route('tenant.sales.index')" title="{{ __('Sales & Invoices') }}" subtitle="{{ __('History, print receipts & refunds') }}">🧾</x-nav.drawer-item>
                                @endif

                                @if ($canQuotes)
                                    <x-nav.drawer-item item-key="quotations" :route="route('tenant.quotes.index')" title="{{ __('Quotations & Proposals') }}" subtitle="{{ __('Quotes, estimates & 1-click sales conversion') }}">📑</x-nav.drawer-item>
                                @endif

                                @if ($canConsignments)
                                    <x-nav.drawer-item item-key="consignments" :route="route('tenant.consignments.index')" title="{{ __('Consignments') }}" subtitle="{{ __('Dispatch and consignment tracking') }}">🚚</x-nav.drawer-item>
                                @endif

                                @if ($canCustomers)
                                    <x-nav.drawer-item item-key="customers" :route="route('tenant.customers.index')" title="{{ __('Customers & CRM') }}" subtitle="{{ __('Customer directory & loyalty points') }}">👥</x-nav.drawer-item>
                                @endif
                            </div>
                        </div>

                        @if ($canFinance)
                            <div data-section-key="financial_management">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Financial Management') }}</div>
                                <div class="space-y-1">
                                    <x-nav.drawer-item item-key="cash_register" :route="route('tenant.financials.cash_register')" title="{{ __('Cash Register') }}" subtitle="{{ __('Opening, closing, cash withdrawals') }}">🗄️</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="due_receivables" :route="route('tenant.financials.receivables')" title="{{ __('Accounts Receivable') }}" subtitle="{{ __('Customer credit & pending payments') }}">📈</x-nav.drawer-item>
                                    <x-nav.drawer-item item-key="payables" :route="route('tenant.financials.payables')" title="{{ __('Accounts Payable') }}" subtitle="{{ __('Supplier bills & purchase dues') }}">📉</x-nav.drawer-item>

                                    @if ($canReports)
                                        <x-nav.drawer-item item-key="reports" :route="route('tenant.reports.index')" title="{{ __('Reports') }}" subtitle="{{ __('Sales, commissions & aging') }}">📊</x-nav.drawer-item>
                                        <x-nav.drawer-item item-key="analytics" :route="route('tenant.reports.index')" title="{{ __('Analytics') }}" subtitle="{{ __('Sales trends & performance charts') }}">📈</x-nav.drawer-item>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div data-section-key="products_inventory">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Products & Inventory') }}</div>
                            <div class="space-y-1">
                                @if ($canProducts)
                                    <x-nav.drawer-item item-key="inventory" :route="route('tenant.products.index')" title="{{ __('All Products') }}" subtitle="{{ __('SKUs, pricing & inventory levels') }}">📦</x-nav.drawer-item>
                                @endif

                                @if ($canCategories)
                                    <x-nav.drawer-item item-key="categories" :route="route('tenant.categories.index')" title="{{ __('Categories') }}" subtitle="{{ __('Tax rates & category hierarchy') }}">🏷️</x-nav.drawer-item>
                                @endif

                                <x-nav.drawer-item item-key="brands" :route="route('tenant.brands.index')" title="{{ __('Brands & Manufacturers') }}" subtitle="{{ __('Brand names & supplier labels') }}">✨</x-nav.drawer-item>

                                @if ($canUnits)
                                    <x-nav.drawer-item item-key="units" :route="route('tenant.units.index')" title="{{ __('Units of Measure') }}" subtitle="{{ __('Pieces, kg, box, packs & liters') }}">⚖️</x-nav.drawer-item>
                                @endif

                                @if ($canSuppliers)
                                    <x-nav.drawer-item item-key="suppliers" :route="route('tenant.suppliers.index')" title="{{ __('Suppliers & Vendors') }}" subtitle="{{ __('Vendor directory & purchasing') }}">🚚</x-nav.drawer-item>
                                @endif

                                @if ($canCatalog)
                                    <x-nav.drawer-item item-key="catalog" :route="route('tenant.catalog.index')" title="{{ __('Online Digital Catalog') }}" subtitle="{{ __('Shareable web catalog & WhatsApp store') }}">🌐</x-nav.drawer-item>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Lead Management is an optional extension, but when it
                         is enabled it must be visible for every vertical and
                         for the all-modules enterprise workspace. --}}
                    @if ($canLeads)
                        <div data-section-key="lead_ops">
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-2 px-3">{{ __('Lead Operations') }}</div>
                            <div class="space-y-1">
                                <x-nav.drawer-item item-key="lead_management" :route="route('tenant.leads.index')" title="{{ __('Lead Management') }}" subtitle="{{ __('Pipeline, follow-ups & auto-sync CRM') }}">🎯</x-nav.drawer-item>
                            </div>
                        </div>
                    @endif

                    @include('layouts.partials.vertical-nav-drawer')

                    <!-- Administration & Settings -->
                    <div data-section-key="administration">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Administration & Settings') }}</div>
                        <div class="space-y-1">
                            <x-nav.drawer-link item-key="subscription" :route="route('tenant.billing.index')" dot="blue" bold :title="__('Subscription & Billing')" :badge="__('Invoices')" badge-color="blue" />

                            @if ($canSettings)
                                <x-nav.drawer-link item-key="settings" :route="route('tenant.settings.index')" :title="__('Store Settings')" />
                                <div class="pl-8 space-y-1 nav-children-container" data-parent-key="settings">
                                    <x-nav.drawer-link item-key="settings_mode" :route="route('tenant.settings.mode')" :title="__('Store Operating Mode')" />
                                    <x-nav.drawer-link item-key="settings_profile" :route="route('tenant.settings.profile')" :title="__('Store Profile & Branding')" />
                                    <x-nav.drawer-link item-key="settings_receipts" :route="route('tenant.settings.receipts')" :title="__('Receipt Prefixes & Bank Terms')" />
                                    <x-nav.drawer-link item-key="settings_financial" :route="route('tenant.settings.financial')" :title="__('Financial & Currency')" />
                                    <x-nav.drawer-link item-key="settings_taxes" :route="route('tenant.settings.taxes')" :title="__('Taxes & Compliance')" />
                                    <x-nav.drawer-link item-key="settings_api" :route="route('tenant.settings.integrations')" :title="__('API & Integrations')" />
                                    <x-nav.drawer-link item-key="settings_navigation" :route="route('tenant.settings.navigation')" :title="__('Navigation Menu')" />
                                </div>
                            @endif

                            <x-nav.drawer-link item-key="languages" :route="route('tenant.languages.index')" dot="emerald" :title="__('Languages & Translations')" :badge="__('Multi-Lang')" badge-color="emerald" />

                            @if ($canUsers)
                                <x-nav.drawer-link item-key="staff" :route="route('tenant.users.index')" :title="__('Users & Permissions')" />
                            @endif

                            <x-nav.drawer-link item-key="devices" :route="route('tenant.devices.index')" :title="__('Terminals & Devices')" />
                        </div>
                    </div>

                </nav>

{{-- Applies the canonical navigation hierarchy to the
                     permission-gated drawer markup, then turns every item
                     with children into an accessible accordion. The saved
                     parent_id/level contract is shared with Flutter. --}}
                <script>
                    (function () {
                        let navConfig = @json($tenantNavConfig ?? ['sections' => [], 'items' => [], 'tree' => []]);
                        const expandedByKey = new Map();
                        const toggleLabel = @json(__('Toggle submenu'));

                        function parentKeyFor(meta) {
                            return meta?.parent_id ?? meta?.parent ?? null;
                        }

                        function childrenContainerFor(navEl, parentEl) {
                            const parentKey = parentEl.getAttribute('data-item-key');
                            let container = navEl.querySelector(
                                '.nav-children-container[data-parent-key="' + CSS.escape(parentKey) + '"]'
                            );
                            if (!container) {
                                container = document.createElement('div');
                                container.className = 'pl-8 space-y-1 nav-children-container';
                                container.setAttribute('data-parent-key', parentKey);
                                parentEl.after(container);
                            } else {
                                container.classList.remove('pl-4');
                                container.classList.add('pl-8');
                            }

                            return container;
                        }

                        function removeAccordionControls(navEl) {
                            navEl.querySelectorAll('.nav-accordion-toggle').forEach((toggle) => {
                                const key = toggle.getAttribute('data-parent-key');
                                expandedByKey.set(key, toggle.getAttribute('aria-expanded') === 'true');
                                toggle.remove();
                            });
                            navEl.querySelectorAll('.nav-children-container').forEach((container) => {
                                container.hidden = false;
                            });
                            navEl.querySelectorAll('[data-nav-has-children]').forEach((parent) => {
                                parent.removeAttribute('data-nav-has-children');
                                parent.removeAttribute('aria-expanded');
                                parent.removeAttribute('aria-controls');
                            });
                        }

                        function linkMatchesLocation(link) {
                            try {
                                const candidate = new URL(link.href, window.location.href);
                                const current = new URL(window.location.href);
                                return candidate.pathname === current.pathname
                                    && candidate.search === current.search
                                    && candidate.hash === current.hash;
                            } catch (error) {
                                return false;
                            }
                        }

                        function setupAccordions(navEl) {
                            const containers = Array.from(navEl.querySelectorAll('.nav-children-container'));
                            containers.forEach((container) => {
                                const parentKey = container.getAttribute('data-parent-key');
                                const parentEl = navEl.querySelector(
                                    '[data-item-key="' + CSS.escape(parentKey) + '"]'
                                );
                                const hasChildren = Boolean(
                                    container.querySelector(':scope > [data-item-key]')
                                );

                                if (!parentEl || !hasChildren) {
                                    container.hidden = true;
                                    return;
                                }

                                const controlId = 'tenant-nav-children-' + parentKey.replace(/[^a-zA-Z0-9_-]/g, '-');
                                const containsCurrentLink = Array.from(
                                    container.querySelectorAll('a[href]')
                                ).some(linkMatchesLocation);
                                const parentIsCurrent = linkMatchesLocation(parentEl);
                                const expanded = expandedByKey.has(parentKey)
                                    ? expandedByKey.get(parentKey)
                                    : (containsCurrentLink || parentIsCurrent);

                                container.id = controlId;
                                container.hidden = !expanded;
                                parentEl.setAttribute('data-nav-has-children', 'true');
                                parentEl.setAttribute('aria-expanded', String(expanded));
                                parentEl.setAttribute('aria-controls', controlId);

                                const toggle = document.createElement('span');
                                toggle.className = 'nav-accordion-toggle ml-auto shrink-0 inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200';
                                toggle.setAttribute('role', 'button');
                                toggle.setAttribute('tabindex', '0');
                                toggle.setAttribute('data-parent-key', parentKey);
                                toggle.setAttribute('aria-label', toggleLabel);
                                toggle.setAttribute('aria-controls', controlId);
                                toggle.setAttribute('aria-expanded', String(expanded));
                                toggle.innerHTML = '<svg aria-hidden="true" class="h-3.5 w-3.5 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';

                                const update = (nextExpanded) => {
                                    expandedByKey.set(parentKey, nextExpanded);
                                    container.hidden = !nextExpanded;
                                    parentEl.setAttribute('aria-expanded', String(nextExpanded));
                                    toggle.setAttribute('aria-expanded', String(nextExpanded));
                                    const icon = toggle.querySelector('svg');
                                    if (icon) icon.style.transform = nextExpanded ? 'rotate(90deg)' : '';
                                };
                                const activate = (event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    update(toggle.getAttribute('aria-expanded') !== 'true');
                                };

                                toggle.addEventListener('click', activate);
                                toggle.addEventListener('keydown', (event) => {
                                    if (event.key === 'Enter' || event.key === ' ') activate(event);
                                });
                                parentEl.appendChild(toggle);
                                update(Boolean(expanded));
                            });
                        }

                        function applyTenantNavOrder() {
                            const navEl = document.getElementById('tenant-drawer-nav');
                            if (!navEl) return;
                            removeAccordionControls(navEl);

                            const sectionOrder = {};
                            const sectionTitles = {};
                            (navConfig.sections || []).forEach((section) => {
                                sectionOrder[section.key] = section.order;
                                if (String(section.custom_title || '').trim()) {
                                    sectionTitles[section.key] = String(section.custom_title).trim();
                                }
                            });
                            const itemMeta = {};
                            (navConfig.items || []).forEach((item) => {
                                itemMeta[item.key] = item;
                            });

                            const sectionsByKey = {};
                            navEl.querySelectorAll(':scope > [data-section-key]').forEach((section) => {
                                const sectionKey = section.getAttribute('data-section-key');
                                sectionsByKey[sectionKey] = section;
                                const heading = section.querySelector(':scope > div:first-child');
                                if (heading && sectionTitles[sectionKey]) {
                                    heading.textContent = sectionTitles[sectionKey];
                                }
                            });

                            // Apply parent placement before sorting. A missing
                            // parent (for example, one hidden by permissions)
                            // promotes the child to its configured section root.
                            Array.from(navEl.querySelectorAll('[data-item-key]')).forEach((itemEl) => {
                                const key = itemEl.getAttribute('data-item-key');
                                const meta = itemMeta[key];
                                if (!meta) return;

                                itemEl.setAttribute('data-nav-level', String(Math.max(0, Math.min(2, Number(meta.level) || 0))));
                                const parentKey = parentKeyFor(meta);
                                if (parentKey) {
                                    const parentEl = navEl.querySelector(
                                        '[data-item-key="' + CSS.escape(parentKey) + '"]'
                                    );
                                    if (parentEl && parentEl !== itemEl) {
                                        const container = childrenContainerFor(navEl, parentEl);
                                        if (itemEl.parentElement !== container) container.appendChild(itemEl);
                                        return;
                                    }
                                }

                                const currentSection = itemEl.closest('[data-section-key]');
                                const targetSectionKey = meta.section
                                    || currentSection?.getAttribute('data-section-key');
                                const targetSection = targetSectionKey
                                    ? sectionsByKey[targetSectionKey]
                                    : null;
                                const targetList = targetSection?.querySelector(':scope > .space-y-1');
                                if (targetList && targetList !== itemEl.parentElement) {
                                    targetList.appendChild(itemEl);
                                }
                            });

                            function sortByOrder(list) {
                                Array.from(list.children)
                                    .filter((element) => element.hasAttribute('data-item-key'))
                                    .sort((a, b) => {
                                        const first = itemMeta[a.getAttribute('data-item-key')]?.order
                                            ?? Number.MAX_SAFE_INTEGER;
                                        const second = itemMeta[b.getAttribute('data-item-key')]?.order
                                            ?? Number.MAX_SAFE_INTEGER;
                                        return first - second;
                                    })
                                    .forEach((element) => list.appendChild(element));
                            }

                            Object.values(sectionsByKey).forEach((section) => {
                                const list = section.querySelector(':scope > .space-y-1');
                                if (list) sortByOrder(list);
                            });
                            navEl.querySelectorAll('.nav-children-container').forEach(sortByOrder);

                            // A child container is a sibling of its parent
                            // link. Reattach it after sorting so nested
                            // branches move as one visual accordion tree.
                            navEl.querySelectorAll('.nav-children-container').forEach((container) => {
                                const parentKey = container.getAttribute('data-parent-key');
                                const parentEl = navEl.querySelector(
                                    '[data-item-key="' + CSS.escape(parentKey) + '"]'
                                );
                                if (parentEl) parentEl.after(container);
                            });

                            Array.from(navEl.querySelectorAll(':scope > [data-section-key]'))
                                .sort((a, b) => {
                                    const first = sectionOrder[a.getAttribute('data-section-key')]
                                        ?? Number.MAX_SAFE_INTEGER;
                                    const second = sectionOrder[b.getAttribute('data-section-key')]
                                        ?? Number.MAX_SAFE_INTEGER;
                                    return first - second;
                                })
                                .forEach((section) => navEl.appendChild(section));

                            setupAccordions(navEl);
                        }

                        document.addEventListener('DOMContentLoaded', applyTenantNavOrder);
                        document.addEventListener('livewire:navigated', applyTenantNavOrder);
                        window.addEventListener('tenant-navigation-updated', (event) => {
                            if (event.detail?.nav) navConfig = event.detail.nav;
                            applyTenantNavOrder();
                        });
                    })();
                </script>
            </div>

            <!-- Sticky account footer: sign-out lives in the drawer, not the app bar. -->
            <div class="mt-auto pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <button type="button" x-on:click="dark = !dark" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1.5 font-bold transition">
                    <span x-show="!dark">🌙 Dark Mode</span>
                    <span x-show="dark">☀️ Light Mode</span>
                </button>

                <div class="mt-auto border-t border-slate-200 dark:border-slate-800 p-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-slate-200 dark:border-slate-700">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 2)) }}
                        </div>
                        <div class="text-xs min-w-0 flex-1">
                            <p class="font-bold text-slate-800 dark:text-slate-200 truncate leading-tight">{{ auth()->user()?->name }}</p>
                            <p class="text-slate-400 truncate leading-normal">{{ auth()->user()?->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0 m-0">
                        @csrf
                        <button type="submit" title="{{ __('Sign Out') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold whitespace-nowrap transition-colors">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>{{ __('Sign Out') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backdrop overlay for slide-out drawer -->
        <div x-show="sidebarOpen"
             x-cloak
             x-on:click="sidebarOpen = false"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40"></div>
        @endpersist

        <!-- Main Body Area -->
        <div class="main-content-pane flex-1 min-w-0 w-full flex flex-col overflow-hidden transition-all duration-300"
             :class="{
                 'pb-24 sm:pb-28': layout === 'macos-dock',
                 'w-full max-w-none ml-0 mr-0': position === 'top' || position === 'bottom' || position === 'floating' || layout === 'macos-dock' || layout === 'speed-dial',
                 'flex-1': position === 'left' || position === 'right'
             }">
            
            <!-- Top Header Bar -->
            <header class="relative z-30 px-3 sm:px-6 py-2 sm:py-3.5 flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md shrink-0">
                
                <div class="flex items-center gap-3">
                    <button type="button"
                            x-on:click="sidebarOpen = true"
                            class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-blue-50 hover:text-blue-600 transition"
                            title="Toggle Menu">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>

                    <div>
                        <h1 class="text-base font-black text-slate-800 dark:text-slate-100 tracking-tight">{{ $title ?? 'Dashboard' }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3 sm:gap-4">
                    <!-- Quick action to POS based on active mode -->
                    @if ($isRestaurant)
                        @unless(request()->routeIs('tenant.restaurant.pos'))
                            <a wire:navigate.hover x-show="isItemVisible('restaurant_pos')" href="{{ route('tenant.restaurant.pos') }}"
                               class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#a3e635] text-slate-950 hover:bg-lime-500 font-black text-xs transition shadow-md shadow-lime-500/10">
                                <span>🍽️ Food POS</span>
                            </a>
                        @endunless
                    @else
                        @unless(request()->routeIs('tenant.sales.create'))
                            <a wire:navigate.hover x-show="isItemVisible('pos')" href="{{ route('tenant.sales.create') }}"
                               class="hidden sm:inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 hover:bg-blue-600 hover:text-white font-bold text-xs transition shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                <span>{{ __("Casier POS") }}</span>
                            </a>
                        @endunless
                    @endif

                    <livewire:tenant.desktop-sync-status />

                    <!-- Server-driven notification bell; opens the SDUI alerts feed. -->
                    @php
                        $notificationUnreadCount = app(\App\Services\NotificationAlertService::class)
                            ->unreadCount($tenantCompany?->id ?? auth('web')->user()?->company_id);
                    @endphp
                    <div
                        x-data="{ count: @js($notificationUnreadCount) }"
                        x-on:sdui-unread-count.window="count = Number($event.detail?.count || 0)"
                        class="relative"
                    >
                        <button
                            type="button"
                            x-on:click="$dispatch('open-sdui-sheet', { endpoint: @js(route('tenant.notifications.feed')) })"
                            class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-blue-50 hover:text-blue-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-blue-950/60 dark:hover:text-blue-300"
                            title="{{ __('System Alerts & Reminders') }}"
                            aria-label="{{ __('Open notifications') }}"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span
                                x-show="count > 0"
                                x-text="count > 99 ? '99+' : count"
                                class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full border-2 border-white bg-rose-500 px-1 py-0.5 text-center text-[9px] font-black leading-none text-white dark:border-slate-900"
                            ></span>
                        </button>
                    </div>

                    <!-- Multi-Language Switcher Dropdown -->
                    @php
                        $locService = app(\App\Services\Localization\LocalizationService::class);
                        $activeLang = $locService->getActiveLanguage();
                        $allLangs = $locService->getActiveLanguages();
                    @endphp
                    <div x-data="{ openLang: false }" class="relative">
                        <button type="button"
                                x-on:click="openLang = !openLang"
                                x-on:click.outside="openLang = false"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center gap-1.5 text-xs font-bold cursor-pointer"
                                title="Switch Language">
                            <span class="text-sm">{{ $activeLang?->flag ?: '🌐' }}</span>
                            <span class="hidden md:inline uppercase text-[11px] font-mono">{{ $activeLang?->code ?? 'EN' }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div x-show="openLang"
                             x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-52 rounded-2xl bg-white dark:bg-slate-900 shadow-xl border border-slate-200 dark:border-slate-800 p-2 z-50 space-y-1">
                            <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Select Language
                            </div>
                            <div class="max-h-60 overflow-y-auto no-scrollbar space-y-0.5">
                                @foreach ($allLangs as $lang)
                                    <a wire:navigate.hover href="{{ route('locale.switch', $lang->code) }}"
                                       @class([
                                            'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition',
                                            'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-bold' => ($activeLang?->code ?? 'en') === $lang->code,
                                            'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' => ($activeLang?->code ?? 'en') !== $lang->code,
                                       ])>
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">{{ $lang->flag }}</span>
                                            <span>{{ $lang->name }}</span>
                                        </div>
                                        @if (($activeLang?->code ?? 'en') === $lang->code)
                                            <span class="text-blue-600 dark:text-blue-400 font-bold text-xs">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                            @if (auth('web')->check())
                                <div class="pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <a wire:navigate.hover href="{{ route('tenant.languages.index') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/50">
                                        <span>✏️ Customize Translations</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Appearance & Menu Layout Customizer Trigger -->
                    <button type="button"
                            @click="toggleCustomizerModal()"
                            class="hidden sm:flex px-2.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-xs font-bold transition items-center gap-1.5 cursor-pointer shadow-2xs border border-blue-200/50 dark:border-blue-800/50"
                            title="{{ __('Navigation & Appearance Settings') }}">
                        <span class="text-sm">🎨</span>
                        <span class="hidden lg:inline text-[11px] font-bold uppercase">{{ __('Layout') }}</span>
                    </button>

                    <!-- Dark/Light Mode Toggle -->
                    <button type="button"
                            x-on:click="dark = !dark"
                            class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-blue-600 transition"
                            title="Toggle Theme">
                        <span x-show="!dark" class="flex items-center gap-1.5 text-xs font-semibold">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </span>
                        <span x-show="dark" class="flex items-center gap-1.5 text-xs font-semibold">
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9h-1m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </span>
                    </button>

                    <!-- User identity; sign-out is intentionally in the drawer footer. -->
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-sm">
                            {{ substr(auth()->user()?->name ?? 'U', 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 leading-none">{{ auth()->user()?->name }}</div>
                            <div class="text-[10px] text-slate-400 capitalize mt-0.5">{{ auth()->user()?->role }}</div>
                        </div>

                    </div>
                </div>
            </header>

            <!-- Main Dynamic View Container with Smooth Transition -->
            <livewire:tenant.system-alarm-banner />
            <main class="flex-1 w-full max-w-none transition-all duration-200 {{ $isPosScreen ? 'p-1.5 sm:p-2.5 overflow-hidden flex flex-col min-h-0 h-full' : 'p-3 sm:p-5 md:p-6 overflow-y-auto' }}" id="main-app-content">
                <div class="w-full max-w-none {{ $isPosScreen ? 'flex-1 min-h-0 flex flex-col overflow-hidden h-full' : '' }}">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </main>
        </div>

        <!-- Appearance & Layout Customizer Modal (Inside x-data scope) -->
        @persist('tenant-nav-customizer')
        @include('layouts.partials.nav-customizer-modal')
        @endpersist

    </div>

    <!-- Shared invoice and quotation preview modal -->
    @include('layouts.partials.document-print-preview-modal')

    <!-- Shared Server-Driven UI renderer for notifications and document dispatch. -->
    @include('layouts.partials.sdui-bottom-sheet')

    <!-- Resume Fullscreen Nudge -->
    <div x-data
         x-show="$store.fullscreen.showResumePill"
         x-cloak
         class="fixed bottom-5 right-5 z-[9999] flex items-center gap-2 bg-slate-900 dark:bg-slate-800 text-white rounded-2xl shadow-2xl px-4 py-2.5 text-xs font-bold">
        <button type="button" x-on:click="$store.fullscreen.resume()" class="flex items-center gap-1.5 hover:text-blue-300 transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
            <span>{{ __("Resume Fullscreen") }}</span>
        </button>
        <button type="button" x-on:click="$store.fullscreen.dismiss()" class="text-slate-400 hover:text-white transition cursor-pointer" title="Dismiss">&times;</button>
    </div>

    <!-- Install/update controls only appear when the browser reports an actionable PWA event. -->
    <div x-data
         x-show="$store.pwa.canInstall || $store.pwa.updateAvailable"
         x-cloak
         class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[9998] flex items-center gap-2 rounded-2xl border border-slate-700/70 bg-slate-950/95 px-3 py-2 text-white shadow-2xl">
        <button type="button"
                x-show="$store.pwa.canInstall"
                x-on:click="$store.pwa.install()"
                class="flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold hover:bg-blue-500 transition">
            <span aria-hidden="true">⬇</span>
            <span>{{ __('Install App') }}</span>
        </button>
        <button type="button"
                x-show="$store.pwa.updateAvailable"
                x-on:click="$store.pwa.applyUpdate()"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold hover:bg-emerald-500 transition">
            <span aria-hidden="true">↻</span>
            <span>{{ __('Update App') }}</span>
        </button>
        <button type="button"
                x-on:click="$store.pwa.dismiss()"
                class="rounded-lg px-2 py-1 text-slate-400 hover:text-white transition"
                aria-label="{{ __('Dismiss') }}">&times;</button>
    </div>

    <!-- Global Dynamic Flash Toast Notifications with Slide-Down Physics & Auto-Dismiss Progress Bar -->
    <div x-data="{
             toasts: [],
             add(type, message) {
                 const id = Date.now() + Math.random();
                 this.toasts.push({ id, type: type || 'info', message });
                 setTimeout(() => this.remove(id), 4500);
             },
             remove(id) {
                 this.toasts = this.toasts.filter(t => t.id !== id);
             }
         }"
         x-on:notify.window="add($event.detail[0]?.type || $event.detail.type, $event.detail[0]?.message || $event.detail.message)"
         x-on:toast.window="add($event.detail[0]?.type || $event.detail.type || 'info', $event.detail[0]?.message || $event.detail.message)"
         class="fixed top-5 right-5 z-[99999] flex flex-col gap-3 max-w-sm w-full pointer-events-none px-3 sm:px-0">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="-translate-y-4 opacity-0 scale-95"
                 x-transition:enter-end="translate-y-0 opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-90 -translate-y-2"
                 class="pointer-events-auto relative overflow-hidden flex flex-col p-4 rounded-2xl shadow-2xl backdrop-blur-md text-xs font-bold transition-all duration-300 border"
                 :class="{
                     'bg-rose-600/95 dark:bg-rose-700/95 text-white border-rose-400/40 shadow-rose-500/25': t.type === 'error',
                     'bg-emerald-600/95 dark:bg-emerald-700/95 text-white border-emerald-400/40 shadow-emerald-500/25': t.type === 'success',
                     'bg-amber-500/95 dark:bg-amber-600/95 text-white border-amber-300/40 shadow-amber-500/25': t.type === 'warning',
                     'bg-indigo-600/95 dark:bg-indigo-700/95 text-white border-indigo-400/40 shadow-indigo-500/25': t.type === 'info'
                 }">
                <div class="flex items-start gap-3">
                    <span class="text-base shrink-0" x-text="t.type === 'error' ? '⚠️' : (t.type === 'success' ? '✓' : (t.type === 'warning' ? '⚡' : 'ℹ️'))"></span>
                    <div class="flex-1 text-xs font-bold leading-relaxed pr-1" x-text="t.message"></div>
                    <button type="button" @click="remove(t.id)" class="text-white/80 hover:text-white font-black text-sm shrink-0 leading-none cursor-pointer">&times;</button>
                </div>
                <!-- Auto-Dismiss Progress Bar -->
                <div class="h-1 bg-black/20 dark:bg-white/20 rounded-full overflow-hidden mt-2.5 w-full">
                    <div class="h-full bg-white/90 rounded-full toast-progress-bar"></div>
                </div>
            </div>
        </template>
    </div>

    @if (request()->routeIs('tenant.settings.*', 'tenant.quotes.create', 'tenant.quotes.edit'))
        <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>
        <script src="{{ asset('assets/libs/tinymce/tinymce-theme-handler.js') }}"></script>
    @endif
    @livewireScripts
    @stack('scripts')
</body>
</html>
