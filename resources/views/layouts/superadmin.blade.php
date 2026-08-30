<!DOCTYPE html>
<html lang="en" x-data="{ 
    dark: localStorage.getItem('theme') === 'dark',
    sidebarOpen: false
}" x-init="
    $watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) });
    document.documentElement.classList.toggle('dark', dark)
">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Super Admin' }} — {{ config('app.name', 'Smart Inventory & Sales') }}</title>
    @php
        $branding = \App\Models\PlatformBranding::current();
        $saSidebarColor = $branding->superadmin_sidebar_color ?: '#4338ca';
        $saPrimaryColor = $branding->primary_color ?: '#4f46e5';
    @endphp
    @if ($branding?->favicon_url)
        <link rel="icon" href="{{ $branding->favicon_url }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700;1,800&family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/libs/nprogress.css') }}">
    <script src="{{ asset('assets/libs/nprogress.js') }}"></script>
    @if (request()->routeIs('superadmin.pages.create', 'superadmin.pages.edit'))
        <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>
        <script src="{{ asset('assets/libs/tinymce/tinymce-theme-handler.js') }}"></script>
    @endif
    @if (request()->routeIs('superadmin.menus.*'))
        <script src="{{ asset('assets/libs/sortable.min.js') }}"></script>
    @endif
    <script src="{{ asset('assets/libs/turbo.min.js') }}" data-turbo-track="reload"></script>
    <meta name="turbo-cache-control" content="no-preview">
    @livewireStyles
    <script>window.platformAppearanceDefaults = @json(appearance_defaults());</script>

    <!-- Inline Loading Bar Styling -->
    <style>
        #nprogress .bar {
            background: #2563eb !important;
            height: 3px !important;
            z-index: 99999 !important;
        }
        #nprogress .peg {
            box-shadow: 0 0 10px #2563eb, 0 0 5px #2563eb !important;
        }
    </style>
    <script>
        (function() {
            try {
                var defaults = window.platformAppearanceDefaults || {};
                var raw = localStorage.getItem('sa_dock_nav_state');
                var saved = raw ? JSON.parse(raw) : null;
                if (saved && String(saved.defaultVersion || '') !== String(defaults.version || '')) saved = null;
                var pos = (saved && saved.position) ? saved.position : (defaults.position || 'left');
                var mode = (saved && saved.mode) ? saved.mode : (defaults.mode || 'docked');
                var layout = (saved && saved.layout) ? saved.layout : (defaults.layout || 'slim');
                var theme = (saved && saved.theme) ? saved.theme : 'violet';
                var sticky = (saved && typeof saved.sticky !== 'undefined') ? saved.sticky : (localStorage.getItem('nav_sticky') === 'true');
                var navTextColor = (saved && saved.navTextColor) ? saved.navTextColor : (defaults.navTextColor || '#ffffff');
                var navTextActive = (saved && saved.navTextActiveColor) ? saved.navTextActiveColor : (defaults.navTextActiveColor || '#60a5fa');
                document.documentElement.setAttribute('data-dock-pos', pos);
                document.documentElement.setAttribute('data-dock-mode', mode);
                document.documentElement.setAttribute('data-nav-layout', layout);
                document.documentElement.setAttribute('data-nav-theme', theme);
                document.documentElement.setAttribute('data-nav-sticky', sticky ? 'true' : 'false');
                document.documentElement.style.setProperty('--nav-item-color', navTextColor);
                document.documentElement.style.setProperty('--nav-item-active-color', navTextActive);
            } catch(e) {}
        })();
    </script>
    <style>
        :root {
            --sa-sidebar-color: {{ $saSidebarColor }};
            --sa-primary-color: {{ $saPrimaryColor }};
            --nav-item-color: #ffffff;
            --nav-item-active-color: #60a5fa;
        }
        .dockable-nav-item:not([aria-selected="true"]) span,
        .top-nav-item:not(.active) span {
            color: var(--nav-item-color);
        }
        .dockable-nav-item[aria-selected="true"] span,
        .dockable-nav-item.active span,
        .top-nav-item.active span {
            color: var(--nav-item-active-color) !important;
            font-weight: 700;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Dockable & Category Menu CSS Specifications (Android-Style Scroll-Snap) */
        .dockable-nav-container,
        .pos-categories-row,
        .tab-scroll-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
        }
        .dockable-nav-container::-webkit-scrollbar,
        .pos-categories-row::-webkit-scrollbar,
        .tab-scroll-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari */
        }
        
        /* Vertical rotated text matching tenant POS rail design */
        .vertical-rail-label {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            text-orientation: mixed;
            letter-spacing: 0.05em;
        }

        /* Anti-flicker initial dock layout rules */
        html[data-dock-pos="left"]:not([data-nav-layout="macos-dock"]):not([data-nav-layout="speed-dial"]) .app-main-frame { flex-direction: row; }
        html[data-dock-pos="right"]:not([data-nav-layout="macos-dock"]):not([data-nav-layout="speed-dial"]) .app-main-frame { flex-direction: row-reverse; }
        html[data-dock-pos="top"]:not([data-nav-layout="macos-dock"]):not([data-nav-layout="speed-dial"]) .app-main-frame { flex-direction: column; }
        html[data-dock-pos="bottom"]:not([data-nav-layout="macos-dock"]):not([data-nav-layout="speed-dial"]) .app-main-frame { flex-direction: column-reverse; }
        html[data-dock-pos="floating"] .app-main-frame,
        html[data-nav-layout="macos-dock"] .app-main-frame,
        html[data-nav-layout="speed-dial"] .app-main-frame { flex-direction: column; position: relative; }

        /* Dynamic full-width and margin reset rules when docked Top, Bottom or Floating */
        html[data-dock-pos="top"] .main-content-pane,
        html[data-dock-pos="bottom"] .main-content-pane,
        html[data-dock-pos="floating"] .main-content-pane,
        html[data-dock-mode="floating"] .main-content-pane {
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>
</head>
<body class="bg-[#1a1f37] dark:bg-[#0c101d] text-slate-900 dark:text-slate-100 min-h-screen p-2 sm:p-4 md:p-6 antialiased selection:bg-indigo-500 selection:text-white">

    @include('layouts.partials.preloader')

    <!-- Main Outer Container with Draggable & Dockable Layout Binding -->
    <div x-data="dockableNav('sa_dock_nav_state', 'left')"
         :class="{
             'flex-row': position === 'left' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-row-reverse': position === 'right' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col': position === 'top' || layout === 'macos-dock' || layout === 'speed-dial',
             'flex-col-reverse': position === 'bottom' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col relative': position === 'floating' || layout === 'macos-dock' || layout === 'speed-dial'
         }"
         class="app-main-frame bg-slate-100 dark:bg-slate-900/90 rounded-[2.5rem] shadow-2xl border border-slate-800/30 dark:border-slate-800 flex overflow-hidden min-h-[calc(100vh-2rem)] md:min-h-[calc(100vh-3rem)] transition-all duration-300 relative w-full">
        
        <!-- Snap Dock Guides (Visible only while dragging menu) -->
        <div x-show="isDragging" x-cloak class="fixed inset-0 z-50 pointer-events-none transition-all duration-200">
            <div :class="snapZone === 'left' ? 'bg-indigo-600/50 border-indigo-400 scale-100 shadow-2xl shadow-indigo-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute left-2 top-2 bottom-2 w-24 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬅️ {{ __('Dock Left') }}</span>
            </div>
            <div :class="snapZone === 'right' ? 'bg-indigo-600/50 border-indigo-400 scale-100 shadow-2xl shadow-indigo-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute right-2 top-2 bottom-2 w-24 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">➡️ {{ __('Dock Right') }}</span>
            </div>
            <div :class="snapZone === 'top' ? 'bg-indigo-600/50 border-indigo-400 scale-100 shadow-2xl shadow-indigo-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute top-2 left-28 right-28 h-20 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬆️ {{ __('Dock Top') }}</span>
            </div>
            <div :class="snapZone === 'bottom' ? 'bg-indigo-600/50 border-indigo-400 scale-100 shadow-2xl shadow-indigo-500/50' : 'bg-white/5 border-white/20'"
                 class="absolute bottom-2 left-28 right-28 h-20 rounded-3xl border-2 border-dashed transition-all flex items-center justify-center">
                <span class="text-xs font-black text-white bg-slate-950/80 px-2.5 py-1 rounded-full border border-white/20 shadow">⬇️ {{ __('Dock Bottom') }}</span>
            </div>
        </div>

        @php
            $isHome = request()->routeIs('superadmin.dashboard');
            $isTenants = request()->routeIs('superadmin.tenants.*');
            $isPlans = request()->routeIs('superadmin.plans.*') || request()->routeIs('superadmin.payment-gateways.*');
            $isCodes = request()->routeIs('superadmin.activation-codes.*');
            $isSettings = request()->routeIs('superadmin.settings.*') || request()->routeIs('superadmin.smtp.*') || request()->routeIs('superadmin.branding.*') || request()->routeIs('superadmin.tax.*') || request()->routeIs('superadmin.backups.*') || request()->routeIs('superadmin.system.*') || request()->routeIs('superadmin.audit.*') || request()->routeIs('superadmin.pages.*') || request()->routeIs('superadmin.languages.*') || request()->routeIs('superadmin.menus.*');
        @endphp
        
        <!-- ==========================================
             LAYOUT 1 & 2: SLIM RAIL & EXPANDED SIDEBAR
             ========================================== -->
        <aside x-ref="dockNavEl"
               x-show="layout === 'slim' || layout === 'expanded'"
               :style="position === 'floating' ? `left: ${x}px; top: ${y}px; position: fixed; z-index: 50;` : (theme === 'violet' ? 'background-color: var(--sa-sidebar-color);' : '')"
               :class="{
                   // Theme Styles:
                   'bg-indigo-900/95 text-white shadow-2xl shadow-indigo-950/50': theme === 'violet' && position === 'floating',
                   'bg-slate-900/80 backdrop-blur-2xl border border-white/20 text-white shadow-2xl': theme === 'glass',
                   'bg-slate-950 border border-slate-700/80 text-slate-100 shadow-2xl rounded-xl font-mono': theme === 'enterprise',
                   'bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-300/40 dark:border-slate-700/50 shadow-[8px_8px_16px_rgba(0,0,0,0.12),-8px_-8px_16px_rgba(255,255,255,0.7)] dark:shadow-[8px_8px_16px_rgba(0,0,0,0.6),-8px_-8px_16px_rgba(255,255,255,0.04)]': theme === 'neumorphic',
                   
                   // Slim Rail Dimensions:
                   'w-14 sm:w-16 md:w-20 shrink-0 flex flex-col items-center justify-between py-4 px-1 sm:px-2 z-20 shadow-xl': layout === 'slim' && (position === 'left' || position === 'right'),
                   'w-full shrink-0 flex flex-row items-center justify-between py-2 px-3 sm:px-5 z-20 shadow-lg min-h-[3.5rem]': layout === 'slim' && (position === 'top' || position === 'bottom'),
                   
                   // Expanded Sidebar Dimensions:
                   'w-64 sm:w-72 md:w-80 shrink-0 flex flex-col justify-between p-4 z-20 shadow-2xl overflow-y-auto no-scrollbar': layout === 'expanded' && (position === 'left' || position === 'right'),
                   'w-full shrink-0 flex flex-row items-center justify-between py-2 px-3 sm:px-5 z-20 shadow-lg overflow-x-auto no-scrollbar min-h-[3.75rem]': layout === 'expanded' && (position === 'top' || position === 'bottom'),
                   
                   // Floating:
                   'w-auto max-w-[95vw] flex flex-row sm:flex-col items-center justify-between gap-3 p-3 rounded-3xl shadow-2xl backdrop-blur-xl border border-white/20': position === 'floating',

                   // Sticky Viewport Pinning:
                   'sticky top-0 z-40': sticky && position !== 'floating' && position !== 'bottom',
                   'sticky bottom-0 z-40': sticky && position === 'bottom'
               }"
               class="select-none transition-all duration-300">
            
            <!-- Top Grab Handle & Quick Actions Header -->
            <div :class="{
                     'w-full pb-2 border-b border-white/10 mb-3 flex items-center justify-between': layout === 'expanded' && (position === 'left' || position === 'right'),
                     'w-full pb-2 flex items-center justify-between': layout === 'slim' && (position === 'left' || position === 'right'),
                     'shrink-0 flex items-center gap-2.5 sm:gap-3.5': position === 'top' || position === 'bottom',
                     'shrink-0 flex items-center gap-2': position === 'floating'
                 }">
                
                <!-- Expanded Brand Header (When in Expanded Sidebar layout) -->
                <div x-show="layout === 'expanded' || position === 'top' || position === 'bottom'" class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                    @if ($branding?->logo_url)
                        <img src="{{ $branding->logo_url }}" alt="{{ $branding->platform_name }}" class="w-7 h-7 sm:w-8 sm:h-8 object-contain rounded-xl bg-white/10 p-1 border border-white/15 shrink-0">
                    @else
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-white/20 text-white flex items-center justify-center font-black text-xs sm:text-sm shrink-0">
                            🛡️
                        </div>
                    @endif
                    <div class="truncate" :class="{ 'hidden sm:block': position === 'top' || position === 'bottom' }">
                        <div class="font-black text-xs text-white leading-tight truncate">
                            {{ $branding?->platform_name ?? 'SuperAdmin' }}
                        </div>
                        <div class="text-[9px] text-white/60 font-bold uppercase tracking-wider">{{ __("Control Center") }}</div>
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

                    <!-- Quick Dropdown Popover -->
                    <div x-show="showQuickMenu"
                         x-cloak
                         @click.outside="showQuickMenu = false"
                         class="absolute z-50 mt-2 py-2 w-56 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-white/20 shadow-2xl text-xs text-white space-y-1 font-semibold"
                         :class="position === 'right' ? 'right-0' : (position === 'bottom' ? 'bottom-full mb-2 left-0' : 'left-0 top-full')">
                        <div class="px-3 py-1 text-[10px] uppercase font-black tracking-wider text-slate-400 border-b border-white/10 flex items-center justify-between">
                            <span>{{ __('Quick Dock') }}</span>
                            <span class="text-indigo-400 capitalize" x-text="position"></span>
                        </div>
                        <button type="button" @click="setPosition('left')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'left' && mode === 'docked') ? 'text-indigo-400 font-bold' : ''">
                            <span>⬅️ {{ __('Dock Left') }}</span>
                            <span x-show="position === 'left' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('right')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'right' && mode === 'docked') ? 'text-indigo-400 font-bold' : ''">
                            <span>➡️ {{ __('Dock Right') }}</span>
                            <span x-show="position === 'right' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('top')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'top' && mode === 'docked') ? 'text-indigo-400 font-bold' : ''">
                            <span>⬆️ {{ __('Dock Top') }}</span>
                            <span x-show="position === 'top' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setPosition('bottom')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="(position === 'bottom' && mode === 'docked') ? 'text-indigo-400 font-bold' : ''">
                            <span>⬇️ {{ __('Dock Bottom') }}</span>
                            <span x-show="position === 'bottom' && mode === 'docked'">✓</span>
                        </button>
                        <button type="button" @click="setMode('floating')" class="w-full px-3 py-1.5 text-left hover:bg-white/15 flex items-center justify-between transition cursor-pointer" :class="mode === 'floating' ? 'text-indigo-400 font-bold' : ''">
                            <span>🪟 {{ __('Free Floating') }}</span>
                            <span x-show="mode === 'floating'">✓</span>
                        </button>
                        <div class="border-t border-white/10 pt-1">
                            <button type="button" @click="toggleCustomizerModal()" class="w-full px-3 py-1.5 text-left hover:bg-indigo-600/30 text-indigo-300 flex items-center gap-1.5 transition cursor-pointer">
                                <span>🎨 {{ __('Appearance & Layout Settings') }}</span>
                            </button>
                            <button type="button" @click="resetAll()" class="w-full px-3 py-1.5 text-left hover:bg-rose-500/20 text-rose-300 flex items-center gap-1.5 transition cursor-pointer">
                                <span>🔄 {{ __('Reset Menu Defaults') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Navigation Links Container (Slim Mode) -->
            <div x-show="layout === 'slim'"
                 class="dockable-nav-container tab-scroll-container"
                 :class="{
                     'w-full flex flex-col items-center gap-4 sm:gap-5 my-auto': position === 'left' || position === 'right',
                     'flex-1 flex flex-row items-center justify-center gap-1.5 sm:gap-2.5 overflow-x-auto no-scrollbar mx-2 py-1': position === 'top' || position === 'bottom',
                     'flex flex-row sm:flex-col items-center gap-2': position === 'floating'
                 }">
                
                <!-- 1. Home -->
                <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isHome ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-indigo-900 shadow-xl font-extrabold' => $isHome,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isHome,
                   ])
                   title="{{ __('Dashboard') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[11px] sm:text-xs' : 'text-xs whitespace-nowrap font-bold'">{{ __('Home') }}</span>
                </a>

                <!-- 2. Tenants -->
                <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isTenants ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-indigo-900 shadow-xl font-extrabold' => $isTenants,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isTenants,
                   ])
                   title="{{ __('Tenants & Stores') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Tenants') }}</span>
                </a>

                <!-- 3. Plans -->
                <a wire:navigate.hover href="{{ route('superadmin.plans.index') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isPlans ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-indigo-900 shadow-xl font-extrabold' => $isPlans,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isPlans,
                   ])
                   title="{{ __('Subscription & Billing') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Billing & Plans') }}</span>
                </a>

                <!-- 4. Activation Codes -->
                <a wire:navigate.hover href="{{ route('superadmin.activation-codes.index') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isCodes ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-indigo-900 shadow-xl font-extrabold' => $isCodes,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isCodes,
                   ])
                   title="{{ __('Redeem License') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Licensing') }}</span>
                </a>

                <!-- 5. Settings -->
                <a wire:navigate.hover href="{{ route('superadmin.settings.index') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isSettings ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-indigo-900 shadow-xl font-extrabold' => $isSettings,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isSettings,
                   ])
                   title="{{ __('Settings') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Settings') }}</span>
                </a>
            </div>

            <!-- Navigation Links (Expanded Layout with Categorized Groups) -->
            <div x-show="layout === 'expanded'"
                 :class="{
                     'w-full space-y-4 my-2 flex-1 overflow-y-auto no-scrollbar': position === 'left' || position === 'right',
                     'flex-1 flex flex-row items-center justify-center gap-2 sm:gap-3 overflow-x-auto no-scrollbar mx-2 py-1': position === 'top' || position === 'bottom',
                     'w-full flex flex-col gap-2 p-2': position === 'floating'
                 }">
                
                <!-- Category 1: Overview -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Overview') }}
                    </div>
                    <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-indigo-900 shadow-md' => $isHome,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isHome,
                       ])
                       title="{{ __('Dashboard & Stats') }}">
                        <span class="text-base shrink-0">📊</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('Dashboard & Stats') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('System metrics & active stores') }}</div>
                        </div>
                    </a>
                </div>

                <!-- Category 2: Tenants -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Tenants & Stores') }}
                    </div>
                    <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-indigo-900 shadow-md' => $isTenants,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isTenants,
                       ])
                       title="{{ __('All Tenant Stores') }}">
                        <span class="text-base shrink-0">🏬</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('All Tenant Stores') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Provisioning & domains') }}</div>
                        </div>
                    </a>
                </div>

                <!-- Category 3: Subscriptions -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Billing & Licensing') }}
                    </div>
                    <a wire:navigate.hover href="{{ route('superadmin.plans.index') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-indigo-900 shadow-md' => $isPlans,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isPlans,
                       ])
                       title="{{ __('Plans & Pricing') }}">
                        <span class="text-base shrink-0">💳</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('Plans & Pricing') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Subscription tiers & limits') }}</div>
                        </div>
                    </a>

                    <a wire:navigate.hover href="{{ route('superadmin.activation-codes.index') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-indigo-900 shadow-md' => $isCodes,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isCodes,
                       ])
                       title="{{ __('License Generator') }}">
                        <span class="text-base shrink-0">🔑</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('License Generator') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Offline key redemption') }}</div>
                        </div>
                    </a>
                </div>

                <!-- Category 4: Settings -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('System & Config') }}
                    </div>
                    <a wire:navigate.hover href="{{ route('superadmin.settings.index') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-indigo-900 shadow-md' => $isSettings,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isSettings,
                       ])
                       title="{{ __('Platform Settings') }}">
                        <span class="text-base shrink-0">⚙️</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('Platform Settings') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('SMTP, branding & backups') }}</div>
                        </div>
                    </a>
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
                        title="{{ __('Open Full Control Center Menu') }}">
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
             :class="position === 'top' ? 'top-4 sm:top-6' : 'bottom-4 sm:bottom-6'"
             class="dockable-nav-container tab-scroll-container fixed left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-full backdrop-blur-2xl border flex items-center gap-2 sm:gap-3.5 shadow-2xl select-none transition-all duration-300 max-w-[95vw] overflow-x-auto"
             :class="{
                 'bg-indigo-950/85 border-indigo-500/40 text-white shadow-indigo-500/30': theme === 'violet',
                 'bg-slate-900/70 border-white/25 text-white backdrop-blur-3xl shadow-2xl': theme === 'glass',
                 'bg-slate-950 border-slate-700 text-slate-100 rounded-2xl': theme === 'enterprise',
                 'bg-slate-200/90 dark:bg-slate-850/90 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 shadow-[8px_8px_16px_rgba(0,0,0,0.2),-8px_-8px_16px_rgba(255,255,255,0.8)]': theme === 'neumorphic'
             }">
            
            <!-- Grab Handle -->
            <button type="button" @click="toggleQuickMenu()" class="p-1.5 rounded-full hover:bg-white/20 transition text-white/60 hover:text-white shrink-0" title="{{ __('Dock Options') }}">
                ⋮⋮
            </button>

            <!-- 1. Home -->
            <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isHome ? 'true' : 'false' }}"
               title="{{ __('Dashboard') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-indigo-600 text-white font-bold ring-2 ring-indigo-400' => $isHome,
                    'bg-white/15 text-white hover:bg-white/30' => !$isHome,
                ])>
                    📊
                </div>
                @if ($isHome)
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 mt-1"></span>
                @endif
            </a>

            <!-- 2. Tenants -->
            <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isTenants ? 'true' : 'false' }}"
               title="{{ __('Tenants & Stores') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-indigo-600 text-white font-bold ring-2 ring-indigo-400' => $isTenants,
                    'bg-white/15 text-white hover:bg-white/30' => !$isTenants,
                ])>
                    🏬
                </div>
            </a>

            <!-- 3. Plans -->
            <a wire:navigate.hover href="{{ route('superadmin.plans.index') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isPlans ? 'true' : 'false' }}"
               title="{{ __('Subscription Plans') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-indigo-600 text-white font-bold ring-2 ring-indigo-400' => $isPlans,
                    'bg-white/15 text-white hover:bg-white/30' => !$isPlans,
                ])>
                    💳
                </div>
            </a>

            <!-- 4. Codes -->
            <a wire:navigate.hover href="{{ route('superadmin.activation-codes.index') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isCodes ? 'true' : 'false' }}"
               title="{{ __('Licensing') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-indigo-600 text-white font-bold ring-2 ring-indigo-400' => $isCodes,
                    'bg-white/15 text-white hover:bg-white/30' => !$isCodes,
                ])>
                    🔑
                </div>
            </a>

            <!-- 5. Settings -->
            <a wire:navigate.hover href="{{ route('superadmin.settings.index') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isSettings ? 'true' : 'false' }}"
               title="{{ __('System Settings') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-indigo-600 text-white font-bold ring-2 ring-indigo-400' => $isSettings,
                    'bg-white/15 text-white hover:bg-white/30' => !$isSettings,
                ])>
                    ⚙️
                </div>
            </a>

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
                    class="w-14 h-14 rounded-full bg-gradient-to-tr from-indigo-600 to-purple-600 text-white shadow-2xl flex items-center justify-center text-xl font-black active:scale-95 transition-transform hover:shadow-indigo-500/50 cursor-pointer border-2 border-white/30">
                <span x-show="!speedDialOpen">🧭</span>
                <span x-show="speedDialOpen" class="text-2xl leading-none">&times;</span>
            </button>

            <!-- Speed Dial Expanded Tray -->
            <div x-show="speedDialOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-90"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-90"
                 class="flex flex-col items-end gap-2.5 p-3 rounded-3xl bg-slate-900/90 backdrop-blur-2xl border border-white/20 shadow-2xl text-xs font-bold text-white">
                
                <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                    <span>{{ __('Overview') }}</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center">📊</span>
                </a>

                <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                    <span>{{ __('Stores') }}</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center">🏬</span>
                </a>

                <a wire:navigate.hover href="{{ route('superadmin.plans.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                    <span>{{ __('Plans') }}</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center">💳</span>
                </a>

                <a wire:navigate.hover href="{{ route('superadmin.settings.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                    <span>{{ __('Settings') }}</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center">⚙️</span>
                </a>

                <div class="w-full border-t border-white/10 my-1"></div>

                <button type="button" @click="toggleCustomizerModal()" class="w-full flex items-center justify-between px-3 py-1.5 rounded-xl hover:bg-indigo-600/30 text-indigo-300 transition cursor-pointer">
                    <span>{{ __('Layout & Theme') }}</span>
                    <span>🎨</span>
                </button>
            </div>
        </div>

        <!-- Slide-out Drawer with All Superadmin Modules -->
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
                        @if ($branding?->logo_url)
                            <img src="{{ $branding->logo_url }}" alt="{{ $branding->platform_name }}" class="w-10 h-10 object-contain rounded-2xl bg-slate-50 dark:bg-slate-800 p-1 border border-slate-200 dark:border-slate-700 shadow-md">
                        @else
                            <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black text-sm shadow-md">
                                🛡️
                            </div>
                        @endif
                        <div>
                            <div class="font-extrabold text-sm text-slate-900 dark:text-white leading-tight">
                                {{ $branding?->platform_name ?? config('app.name', 'Super Admin') }}
                            </div>
                            <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Super Admin Center
                            </div>
                        </div>
                    </div>

                    <button type="button" x-on:click="sidebarOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center font-bold text-base transition cursor-pointer">
                        &times;
                    </button>
                </div>

                <!-- Structured Toggle Menu List Items -->
                <nav class="space-y-5 text-xs font-semibold">
                    
                    <!-- 1. Tenants & Accounts -->
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-2 px-3">{{ __('Tenants & Stores') }}</div>
                        <div class="space-y-1">
                            <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    📊
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Overview & Metrics') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Active stores & system stats') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🏢
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('All Tenants') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Provisioning, domains & status') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.tenants.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    ➕
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Create New Store') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Manual onboarding wizard') }}</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- 2. Subscriptions & Licensing -->
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Subscriptions & Billing') }}</div>
                        <div class="space-y-1">
                            <a wire:navigate.hover href="{{ route('superadmin.plans.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    💳
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Subscription Plans') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Pricing, limits & trial periods') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.activation-codes.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🔑
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Activation License Keys') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Offline license generator & redemption') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.payment-gateways.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    💰
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Payment Gateways') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __("Stripe, PayPal, Mollie, Razorpay") }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.tax.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🏛️
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Tax Reference System') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __("HSN / SAC codes & VAT presets") }}</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- 3. Platform & System -->
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Platform & Maintenance') }}</div>
                        <div class="space-y-1">
                            <a wire:navigate.hover href="{{ route('superadmin.branding.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🎨
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('White-label Branding') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Logos, platform title & colors') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.pages.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    📄
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Custom Pages') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Public content pages, TinyMCE editor') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.menus.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🧭
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Navigation Menus') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Header & footer drag-and-drop builder') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.smtp.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    ✉️
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Global SMTP Server') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Email dispatch & from address') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.languages.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🌐
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Languages & Translations') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Multi-language JSON file editor') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.backups.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    💾
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Automated Backups') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Database snapshots & S3 storage') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.system.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    🛠️
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Maintenance & Health') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Cache, logs & system metrics') }}</div>
                                </div>
                            </a>

                            <a wire:navigate.hover href="{{ route('superadmin.audit.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition group">
                                <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                                    📋
                                </span>
                                <div>
                                    <div class="font-bold">{{ __('Global Audit Trail') }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal">{{ __('Security & compliance logs') }}</div>
                                </div>
                            </a>
                        </div>
                    </div>

                </nav>
            </div>

            <!-- Footer inside Drawer -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <form method="POST" action="{{ route('superadmin.logout') }}">
                    @csrf
                    <button type="submit" class="font-bold text-rose-600 hover:underline flex items-center gap-1.5 cursor-pointer">
                        <span>🚪 {{ __('Sign Out') }}</span>
                    </button>
                </form>
                <span class="text-slate-400 text-[11px]">v2.5 SaaS Platform</span>
            </div>
        </div>

        <!-- Backdrop overlay when drawer is open -->
        <div x-show="sidebarOpen"
             x-cloak
             x-on:click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs transition-opacity"></div>

        <!-- Main Content Wrapper -->
        <div class="main-content-pane flex-1 flex flex-col min-w-0 bg-slate-50 dark:bg-slate-950 min-h-0 overflow-y-auto w-full transition-all duration-300"
             :class="{
                 'pb-24 sm:pb-28': layout === 'macos-dock',
                 'w-full max-w-none ml-0 mr-0': position === 'top' || position === 'bottom' || position === 'floating' || layout === 'macos-dock' || layout === 'speed-dial',
                 'flex-1': position === 'left' || position === 'right'
             }">
            
            <!-- Top App Header Bar -->
            <header class="h-16 sm:h-20 px-4 sm:px-8 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between shrink-0 bg-white/70 dark:bg-slate-900/60 backdrop-blur-md sticky top-0 z-30">
                
                <!-- Left: Title & Platform Badge -->
                <div class="flex items-center gap-3">
                    <button type="button"
                            x-on:click="sidebarOpen = !sidebarOpen"
                            class="p-2 -ml-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 md:hidden cursor-pointer"
                            title="Menu">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>

                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                                {{ $title ?? 'Super Admin' }}
                            </h2>
                            <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                🛡️ Platform Admin
                            </span>
                        </div>
                        <p class="hidden sm:block text-[11px] text-slate-400 font-medium">
                            Multi-Tenant Control Center & System Administration
                        </p>
                    </div>
                </div>

                <!-- Right Header Actions (Fullscreen, Theme Toggle, Profile Menu) -->
                <div class="flex items-center gap-2.5 sm:gap-3">

                    <!-- Fullscreen Toggle Button -->
                    <button type="button"
                            x-on:click="$store.fullscreen.toggle()"
                            class="px-2.5 sm:px-3 py-2 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="Toggle Fullscreen">
                        <template x-if="!$store.fullscreen.active">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                <span class="hidden md:inline">{{ __("Fullscreen") }}</span>
                            </span>
                        </template>
                        <template x-if="$store.fullscreen.active">
                            <span class="flex items-center gap-1 text-amber-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                <span class="hidden md:inline">{{ __("Exit") }}</span>
                            </span>
                        </template>
                    </button>

                    <!-- Multi-Language Switcher Dropdown -->
                    @php
                        $saLocService = app(\App\Services\Localization\LocalizationService::class);
                        $saActiveLang = $saLocService->getActiveLanguage();
                        $saAllLangs = $saLocService->getActiveLanguages();
                    @endphp
                    <div x-data="{ openLang: false }" class="relative">
                        <button type="button"
                                x-on:click="openLang = !openLang"
                                x-on:click.outside="openLang = false"
                                class="px-2.5 sm:px-3 py-2 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                title="Switch Language">
                            <span class="text-sm">{{ $saActiveLang?->flag ?: '🌐' }}</span>
                            <span class="hidden md:inline uppercase text-[11px] font-mono">{{ $saActiveLang?->code ?? 'EN' }}</span>
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
                             class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-900 shadow-xl border border-slate-200 dark:border-slate-800 p-2 z-50 space-y-1">
                            <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Select Platform Language
                            </div>
                            <div class="max-h-60 overflow-y-auto no-scrollbar space-y-0.5">
                                @foreach ($saAllLangs as $lang)
                                    <a wire:navigate.hover href="{{ route('locale.switch', $lang->code) }}"
                                       @class([
                                            'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition',
                                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-bold' => ($saActiveLang?->code ?? 'en') === $lang->code,
                                            'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' => ($saActiveLang?->code ?? 'en') !== $lang->code,
                                       ])>
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">{{ $lang->flag }}</span>
                                            <span>{{ $lang->name }}</span>
                                        </div>
                                        @if (($saActiveLang?->code ?? 'en') === $lang->code)
                                            <span class="text-indigo-600 dark:text-indigo-400 font-bold text-xs">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                            <div class="pt-1 border-t border-slate-100 dark:border-slate-800">
                                <a wire:navigate.hover href="{{ route('superadmin.languages.index') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50">
                                    <span>🌐 Manage All Languages</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Theme Toggle -->
                    <button type="button"
                            x-on:click="dark = !dark"
                            class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition active:scale-95 cursor-pointer shadow-2xs"
                            title="Toggle Dark/Light Mode">
                        <span x-show="!dark" class="text-sm">🌙</span>
                        <span x-show="dark" class="text-sm">☀️</span>
                    </button>

                    <!-- Superadmin User Profile Dropdown (Streamlined) -->
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button"
                                x-on:click="open = !open"
                                class="flex items-center gap-2 p-1.5 sm:px-3 sm:py-2 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer shadow-2xs">
                            <div class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs font-black shadow-sm">
                                🛡️
                            </div>
                            <span class="hidden md:inline text-xs font-bold text-slate-800 dark:text-slate-200">
                                {{ auth('platform_web')->user()?->name ?? 'Platform Admin' }}
                            </span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-800 p-2 z-30 space-y-1 text-xs">
                            
                            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800">
                                <div class="font-extrabold text-slate-900 dark:text-white">{{ auth('platform_web')->user()?->name ?? __('Administrator') }}</div>
                                <div class="text-[11px] text-slate-400 truncate">{{ auth('platform_web')->user()?->email }}</div>
                            </div>

                            <a wire:navigate.hover href="{{ route('tenant.login') }}" class="flex items-center gap-2 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition font-medium">
                                <span>🏪</span>
                                <span>{{ __('Tenant Store Portal') }}</span>
                            </a>

                            <button type="button" @click="resetAll(); open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:text-indigo-600 transition text-left cursor-pointer font-medium">
                                <span>🔄</span>
                                <span>{{ __('Reset Menu Position') }}</span>
                            </button>

                            <div class="border-t border-slate-100 dark:border-slate-800 pt-1">
                                <form method="POST" action="{{ route('superadmin.logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 font-bold transition text-left cursor-pointer">
                                        <span>🚪</span>
                                        <span>{{ __('Sign Out') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </header>

            <!-- Main Dynamic View Container with Smooth Transition -->
            <main class="flex-1 p-3 sm:p-6 md:p-8 w-full max-w-none" id="main-app-content">
                <div class="w-full max-w-none">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </main>

        </div>

        <!-- Appearance & Layout Customizer Modal (Inside x-data scope) -->
        @include('layouts.partials.nav-customizer-modal')

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

    @livewireScripts
    @stack('scripts')
</body>
</html>
