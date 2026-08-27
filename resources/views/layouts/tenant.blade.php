<!DOCTYPE html>
<html lang="en" x-data="{ 
    dark: localStorage.getItem('theme') === 'dark',
    sidebarOpen: false
}" x-init="
    $watch('dark', v => { localStorage.setItem('theme', v ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', v) });
    document.documentElement.classList.toggle('dark', dark)
">
<head>
    @php
        $tenantCompany = auth()->user()?->company ?? new \App\Models\Company;
        $themeClasses = $tenantCompany->getThemeColorClasses();
        $uiAccentColorHex = $tenantCompany->primary_color ?: ($themeClasses['hex'] ?? '#2563eb');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $uiAccentColorHex ?? '#2563eb' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{{ $title ?? 'POS & Store Manager' }} — {{ auth()->user()?->company?->name ?? config('app.name') }}</title>
    @if (auth()->user()?->company?->favicon)
        <link rel="icon" href="{{ auth()->user()->company->favicon }}">
    @endif
    <link rel="manifest" href="{{ route('tenant.pwa.manifest') }}" crossorigin="use-credentials">
    <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700;1,800&family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (request()->routeIs('tenant.reports.*'))
        <script src="{{ asset('assets/libs/apexcharts.min.js') }}"></script>
    @endif
    @livewireStyles
    <script>window.platformAppearanceDefaults = @json(appearance_defaults());</script>

    <!-- Inline Loading Bar & Cloak Styling -->
    <style>
        [x-cloak] { display: none !important; }
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
                var raw = localStorage.getItem('tenant_dock_nav_state');
                var saved = raw ? JSON.parse(raw) : null;
                var pos = (saved && saved.position) ? saved.position : 'left';
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

        .bg-theme-primary { background-color: var(--color-primary) !important; }
        .bg-theme-primary-hover:hover { background-color: var(--color-primary-hover) !important; }
        .bg-theme-primary-light { background-color: var(--color-primary-light) !important; }
        .text-theme-primary { color: var(--color-primary) !important; }
        .border-theme-primary { border-color: var(--color-primary) !important; }
        .from-theme-primary { --tw-gradient-from: var(--color-primary) var(--tw-gradient-from-position); --tw-gradient-to: var(--color-primary-hover) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
        .btn-theme-primary { background-color: var(--color-primary) !important; color: #ffffff !important; }
        .btn-theme-primary:hover { background-color: var(--color-primary-hover) !important; }

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
        
        /* Vertical rotated text matching pos.png */
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
@php
    $isPosScreen = request()->routeIs('tenant.sales.create') || request()->routeIs('tenant.restaurant.pos');
@endphp
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

    @php
        $tenantCompany = auth()->user()?->company ?? new \App\Models\Company;
        $themeClasses = $tenantCompany->getThemeColorClasses();
        $isRestaurant = auth()->user()?->company?->isRestaurantMode();
        $user = auth()->user();
        $permChecker = app(\App\Services\Auth\PermissionChecker::class);
        $canQuotes = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'quotes', 'view');
        $canSales = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'sales', 'view');
        $canConsignments = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'consignments', 'view');
        $canServiceOrders = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'service_orders', 'view');
        $canPos = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'pos', 'create');
        $canProducts = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'products', 'view');
        $canCategories = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'categories', 'view');
        $canUnits = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'units', 'view');
        $canSuppliers = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'suppliers', 'view');
        $canCustomers = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'customers', 'view');
        $canCatalog = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'catalog', 'view');
        $canCashRegister = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'cash_register', 'view');
        $canFinance = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'finance', 'view');
        $canReports = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'reports', 'view');
        $canTargets = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'targets', 'view');
        $canSettings = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'settings', 'view');
        $canUsers = ! $user || $user->isPrivilegedRole() || $permChecker->allows($user, 'users', 'view');

        $isHome = request()->routeIs('tenant.dashboard');
        $isQuotes = request()->routeIs('tenant.quotes.*') || request()->routeIs('tenant.quotations.*');
        $isConsignments = request()->routeIs('tenant.consignments.*');
        $isServiceOrders = request()->routeIs('tenant.service-orders.*');
        $isSalesTargets = request()->routeIs('tenant.sales-targets.*');
        $isTransaction = request()->routeIs('tenant.sales.index') || request()->routeIs('tenant.sales.show');
        $isCasier = request()->routeIs('tenant.sales.create');
        $isRestaurantPos = request()->routeIs('tenant.restaurant.pos');
        $isTables = request()->routeIs('tenant.restaurant.tables');
        $isKds = request()->routeIs('tenant.restaurant.kds');
        $isProducts = request()->routeIs('tenant.products.*');
        $isCategories = request()->routeIs('tenant.categories.*');
        $isBrands = request()->routeIs('tenant.brands.*');
        $isUnits = request()->routeIs('tenant.units.*');
        $isSuppliers = request()->routeIs('tenant.suppliers.*');
        $isCustomers = request()->routeIs('tenant.customers.*');
        $isCatalog = request()->routeIs('tenant.catalog.*');
        $isCashRegister = request()->routeIs('tenant.financials.cash_register');
        $isReceivables = request()->routeIs('tenant.financials.receivables');
        $isPayables = request()->routeIs('tenant.financials.payables');
        $isFinancials = $isCashRegister || $isReceivables || $isPayables;
        $isReports = request()->routeIs('tenant.reports.*') || $isSalesTargets;
        $isSettings = request()->routeIs('tenant.settings.*');
        $isLanguages = request()->routeIs('tenant.languages.*');
        $isUsers = request()->routeIs('tenant.users.*');
        $isDevices = request()->routeIs('tenant.devices.*');
        $isBilling = request()->routeIs('tenant.billing.*') || request()->routeIs('tenant.activate');
    @endphp

    <!-- Main Outer Container with Draggable & Dockable Layout Binding -->
    <div x-data="dockableNav('tenant_dock_nav_state', 'left', '{{ $isRestaurant ? 'restaurant' : 'general' }}')"
         :class="{
             'flex-row': position === 'left' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-row-reverse': position === 'right' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col': position === 'top' || layout === 'macos-dock' || layout === 'speed-dial',
             'flex-col-reverse': position === 'bottom' && layout !== 'macos-dock' && layout !== 'speed-dial',
             'flex-col relative': position === 'floating' || layout === 'macos-dock' || layout === 'speed-dial'
         }"
         class="app-main-frame bg-slate-100 dark:bg-slate-900/90 rounded-[2.5rem] shadow-2xl border border-slate-800/30 dark:border-slate-800 flex overflow-hidden {{ $isPosScreen ? 'h-full max-h-screen rounded-none sm:rounded-[2rem]' : 'min-h-[calc(100vh-2rem)] md:min-h-[calc(100vh-3rem)]' }} transition-all duration-300 relative w-full">

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
                
                <!-- 1. Home -->
                <a x-show="isItemVisible('home')" wire:navigate.hover href="{{ route('tenant.dashboard') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isHome ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-blue-600 shadow-xl font-extrabold' => $isHome,
                       'text-white/80 hover:text-white hover:bg-white/20 font-medium' => !$isHome,
                   ])
                   title="{{ __('Home') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[11px] sm:text-xs' : 'text-xs whitespace-nowrap font-bold'">{{ __('Home') }}</span>
                </a>

                @if ($isRestaurant)
                    @if ($canPos)
                        <!-- 2. Restaurant POS -->
                        <a x-show="isItemVisible('restaurant_pos')" wire:navigate.hover href="{{ route('tenant.restaurant.pos') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isRestaurantPos ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-[#a3e635] text-slate-950 shadow-xl font-extrabold' => $isRestaurantPos,
                               'text-lime-200 hover:text-white hover:bg-white/20 font-medium' => !$isRestaurantPos,
                           ])
                           title="{{ __('Food & Restaurant POS') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🍽️</span>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Food POS') }}</span>
                        </a>

                        <!-- 3. Tables -->
                        <a x-show="isItemVisible('tables')" wire:navigate.hover href="{{ route('tenant.restaurant.tables') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isTables ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isTables,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isTables,
                           ])
                           title="{{ __('Tables & Floor Plan') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🪑</span>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Tables') }}</span>
                        </a>

                        <!-- 4. KDS -->
                        <a x-show="isItemVisible('kds')" wire:navigate.hover href="{{ route('tenant.restaurant.kds') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isKds ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isKds,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isKds,
                           ])
                           title="{{ __('Kitchen Display System') }}">
                            <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🍳</span>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Kitchen') }}</span>
                        </a>
                    @endif

                    @if ($canSales)
                        <!-- Dining History -->
                        <a x-show="isItemVisible('sales')" wire:navigate.hover href="{{ route('tenant.sales.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isTransaction ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isTransaction,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isTransaction,
                           ])
                           title="{{ __('Dining & Sales History') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Dining History') }}</span>
                        </a>
                    @endif
                @else
                    @if ($canPos)
                        <!-- 2. Retail Casier (POS) -->
                        <a x-show="isItemVisible('pos')" wire:navigate.hover href="{{ route('tenant.sales.create') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isCasier ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isCasier,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isCasier,
                           ])
                           title="{{ __('Retail POS') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Retail POS') }}</span>
                        </a>
                    @endif

                    @if ($canSales)
                        <!-- 3. Transaction (Sales & Invoices) -->
                        <a x-show="isItemVisible('sales')" wire:navigate.hover href="{{ route('tenant.sales.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isTransaction ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isTransaction,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isTransaction,
                           ])
                           title="{{ __('Invoices & Sales') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Invoices') }}</span>
                        </a>
                    @endif

                    @if ($canQuotes)
                        <!-- 4. Quotations & Proposals -->
                        <a x-show="isItemVisible('quotes')" wire:navigate.hover href="{{ route('tenant.quotes.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isQuotes ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isQuotes,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isQuotes,
                           ])
                           title="{{ __('Quotations & Proposals') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Quotations') }}</span>
                        </a>
                    @endif

                    @if ($canConsignments)
                        <!-- Consignments -->
                        <a wire:navigate.hover href="{{ route('tenant.consignments.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isConsignments ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isConsignments,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isConsignments,
                           ])
                           title="{{ __('Consignments') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Consign') }}</span>
                        </a>
                    @endif

                    @if ($canServiceOrders)
                        <!-- Service Orders & Warranty Repairs -->
                        <a wire:navigate.hover href="{{ route('tenant.service-orders.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isServiceOrders ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isServiceOrders,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isServiceOrders,
                           ])
                           title="{{ __('Service Orders & Warranty Repairs') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Repairs / OS') }}</span>
                        </a>
                    @endif

                    @if ($canProducts)
                        <!-- Products -->
                        <a x-show="isItemVisible('products')" wire:navigate.hover href="{{ route('tenant.products.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isProducts ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isProducts,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isProducts,
                           ])
                           title="{{ __('Products & Stock') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Products') }}</span>
                        </a>
                    @endif

                    @if ($canCustomers)
                        <!-- Customers -->
                        <a x-show="isItemVisible('customers')" wire:navigate.hover href="{{ route('tenant.customers.index') }}"
                           class="dockable-nav-item"
                           aria-selected="{{ $isCustomers ? 'true' : 'false' }}"
                           :class="{
                               'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                               'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                               'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                           }"
                           @class([
                               'transition-all group duration-200 cursor-pointer',
                               'bg-white text-blue-600 shadow-xl font-extrabold' => $isCustomers,
                               'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isCustomers,
                           ])
                           title="{{ __('Customers & CRM') }}">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Customers') }}</span>
                        </a>
                    @endif
                @endif

                @if ($canCashRegister)
                    <!-- Cash Register -->
                    <a x-show="isItemVisible('register')" wire:navigate.hover href="{{ route('tenant.financials.cash_register') }}"
                       class="dockable-nav-item"
                       aria-selected="{{ $isCashRegister ? 'true' : 'false' }}"
                       :class="{
                           'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                           'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                           'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                       }"
                       @class([
                           'transition-all group duration-200 cursor-pointer',
                           'bg-white text-blue-600 shadow-xl font-extrabold' => $isCashRegister,
                           'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isCashRegister,
                       ])
                       title="{{ __('Cash Register') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🗄️</span>
                        <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Register') }}</span>
                    </a>
                @endif

                @if ($canReports)
                    <!-- Reports -->
                    <a x-show="isItemVisible('reports')" wire:navigate.hover href="{{ route('tenant.reports.index') }}"
                       class="dockable-nav-item"
                       aria-selected="{{ $isReports ? 'true' : 'false' }}"
                       :class="{
                           'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                           'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                           'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                       }"
                       @class([
                           'transition-all group duration-200 cursor-pointer',
                           'bg-white text-blue-600 shadow-xl font-extrabold' => $isReports,
                           'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isReports,
                       ])
                       title="{{ __('Reports & Analytics') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">📊</span>
                        <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Reports') }}</span>
                    </a>
                @endif

                @if ($canTargets)
                    <!-- Sales Targets & Goals -->
                    <a wire:navigate.hover href="{{ route('tenant.sales-targets.index') }}"
                       class="dockable-nav-item"
                       aria-selected="{{ $isSalesTargets ? 'true' : 'false' }}"
                       :class="{
                           'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                           'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                           'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                       }"
                       @class([
                           'transition-all group duration-200 cursor-pointer',
                           'bg-white text-blue-600 shadow-xl font-extrabold' => $isSalesTargets,
                           'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isSalesTargets,
                       ])
                       title="{{ __('Sales Targets & Goals') }}">
                        <span class="text-xl group-hover:scale-110 transition-transform shrink-0">🎯</span>
                        <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Targets') }}</span>
                    </a>
                @endif

                @if ($canSettings)
                    <!-- Settings -->
                    <a x-show="isItemVisible('settings')" wire:navigate.hover href="{{ route('tenant.settings.index') }}"
                       class="dockable-nav-item"
                       aria-selected="{{ $isSettings ? 'true' : 'false' }}"
                       :class="{
                           'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                           'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                           'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                       }"
                       @class([
                           'transition-all group duration-200 cursor-pointer',
                           'bg-white text-blue-600 shadow-xl font-extrabold' => $isSettings,
                           'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isSettings,
                       ])
                       title="{{ __('Settings') }}">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Settings') }}</span>
                    </a>
                @endif

                <!-- Billing -->
                <a x-show="isItemVisible('billing')" wire:navigate.hover href="{{ route('tenant.billing.index') }}"
                   class="dockable-nav-item"
                   aria-selected="{{ $isBilling ? 'true' : 'false' }}"
                   :class="{
                       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
                       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
                       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
                   }"
                   @class([
                       'transition-all group duration-200 cursor-pointer',
                       'bg-white text-blue-600 shadow-xl font-extrabold' => $isBilling,
                       'text-blue-100 hover:text-white hover:bg-white/20 font-medium' => !$isBilling,
                   ])
                   title="{{ __('Subscription & Billing') }}">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Billing & Plans') }}</span>
                </a>
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
                    <a x-show="isItemVisible('home')" wire:navigate.hover href="{{ route('tenant.dashboard') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-blue-700 shadow-md' => $isHome,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isHome,
                       ])
                       title="{{ __('Dashboard') }}">
                        <span class="text-base shrink-0">📊</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('Dashboard') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Sales summary & metrics') }}</div>
                        </div>
                    </a>
                </div>

                <!-- Category 2: POS & Operations -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Sales & POS') }}
                    </div>
                    @if ($isRestaurant)
                        @if ($canPos)
                            <a wire:navigate.hover x-show="isItemVisible('restaurant_pos')" href="{{ route('tenant.restaurant.pos') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-[#a3e635] text-slate-950 shadow-md' => $isRestaurantPos,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isRestaurantPos,
                               ])
                               title="{{ __('Restaurant POS') }}">
                                <span class="text-base shrink-0">🍽️</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Restaurant POS') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Dine-In, Takeaway, KOT') }}</div>
                                </div>
                            </a>
                            <a wire:navigate.hover x-show="isItemVisible('tables')" href="{{ route('tenant.restaurant.tables') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isTables,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isTables,
                               ])
                               title="{{ __('Tables & Floor') }}">
                                <span class="text-base shrink-0">🪑</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Tables & Floor') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Floor plan & live seats') }}</div>
                                </div>
                            </a>
                            <a wire:navigate.hover x-show="isItemVisible('kds')" href="{{ route('tenant.restaurant.kds') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isKds,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isKds,
                               ])
                               title="{{ __('Kitchen Display (KDS)') }}">
                                <span class="text-base shrink-0">🍳</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Kitchen Display (KDS)') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Order preparation queue') }}</div>
                                </div>
                            </a>
                        @endif
                        @if ($canSales)
                            <a wire:navigate.hover x-show="isItemVisible('sales')" href="{{ route('tenant.sales.index') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isTransaction,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isTransaction,
                               ])
                               title="{{ __('Dining History') }}">
                                <span class="text-base shrink-0">🧾</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Dining History') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Orders & thermal receipts') }}</div>
                                </div>
                            </a>
                        @endif
                    @else
                        @if ($canPos)
                            <a wire:navigate.hover x-show="isItemVisible('pos')" href="{{ route('tenant.sales.create') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isCasier,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isCasier,
                               ])
                               title="{{ __('Cashier POS') }}">
                                <span class="text-base shrink-0">🛒</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Cashier POS') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Barcode & Touch Sales') }}</div>
                                </div>
                            </a>
                        @endif
                        @if ($canSales)
                            <a wire:navigate.hover x-show="isItemVisible('sales')" href="{{ route('tenant.sales.index') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isTransaction,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isTransaction,
                               ])
                               title="{{ __('Sales & Invoices') }}">
                                <span class="text-base shrink-0">🧾</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Sales & Invoices') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Invoices, returns & receipts') }}</div>
                                </div>
                            </a>
                        @endif
                        @if ($canQuotes)
                            <a wire:navigate.hover x-show="isItemVisible('quotes')" href="{{ route('tenant.quotes.index') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isQuotes,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isQuotes,
                               ])
                               title="{{ __('Quotations') }}">
                                <span class="text-base shrink-0">📑</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Quotations') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Quotes, proposals & estimates') }}</div>
                                </div>
                            </a>
                        @endif
                        @if ($canCustomers)
                            <a wire:navigate.hover x-show="isItemVisible('customers')" href="{{ route('tenant.customers.index') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isCustomers,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isCustomers,
                               ])
                               title="{{ __('Customers & CRM') }}">
                                <span class="text-base shrink-0">👥</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Customers & CRM') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Profiles, history & loyalty') }}</div>
                                </div>
                            </a>
                        @endif
                    @endif
                </div>

                @if ($canFinance)
                    <!-- Category 3: Financial Management -->
                    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                            {{ __('Financial Management') }}
                        </div>
                        <a wire:navigate.hover x-show="isItemVisible('register')" href="{{ route('tenant.financials.cash_register') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isCashRegister,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isCashRegister,
                           ])
                           title="{{ __('Cash Register') }}">
                            <span class="text-base shrink-0">🗄️</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Cash Register') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Shifts, float & cash balancing') }}</div>
                            </div>
                        </a>
                        <a wire:navigate.hover x-show="isItemVisible('receivables')" href="{{ route('tenant.financials.receivables') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isReceivables,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isReceivables,
                           ])
                           title="{{ __('Accounts Receivable') }}">
                            <span class="text-base shrink-0">📈</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Accounts Receivable') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Customer credit & unpaid bills') }}</div>
                            </div>
                        </a>
                        <a wire:navigate.hover x-show="isItemVisible('receivables')" href="{{ route('tenant.financials.payables') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isPayables,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isPayables,
                           ])
                           title="{{ __('Accounts Payable') }}">
                            <span class="text-base shrink-0">📉</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Accounts Payable') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Supplier bills & expenses') }}</div>
                            </div>
                        </a>

                        @if ($canReports)
                            <a wire:navigate.hover x-show="isItemVisible('reports')" href="{{ route('tenant.reports.index') }}"
                               :class="{
                                   'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                                   'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                                   'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                               }"
                               @class([
                                   'bg-white text-blue-700 shadow-md' => $isReports,
                                   'text-white/80 hover:text-white hover:bg-white/15' => !$isReports,
                               ])
                               title="{{ __('Reports & Analytics') }}">
                                <span class="text-base shrink-0">📊</span>
                                <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                    <div class="text-xs truncate">{{ __('Reports & Analytics') }}</div>
                                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Sales, commissions & aging') }}</div>
                                </div>
                            </a>
                        @endif
                    </div>
                @endif

                <!-- Category 4: Catalog & Inventory -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Products & Inventory') }}
                    </div>
                    @if ($canProducts)
                        <a wire:navigate.hover x-show="isItemVisible('products')" href="{{ route('tenant.products.index') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isProducts,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isProducts,
                           ])
                           title="{{ __('Products & Stock') }}">
                            <span class="text-base shrink-0">📦</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Products & Stock') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Catalog, prices & alerts') }}</div>
                            </div>
                        </a>
                    @endif
                    @if ($canCategories)
                        <a wire:navigate.hover x-show="isItemVisible('categories')" href="{{ route('tenant.categories.index') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isCategories,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isCategories,
                           ])
                           title="{{ __('Categories') }}">
                            <span class="text-base shrink-0">🏷️</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Categories') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Departments & tax rates') }}</div>
                            </div>
                        </a>
                    @endif
                    @if ($canCatalog)
                        <a wire:navigate.hover x-show="isItemVisible('products')" href="{{ route('tenant.catalog.index') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isCatalog,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isCatalog,
                           ])
                           title="{{ __('Online Catalog') }}">
                            <span class="text-base shrink-0">🌐</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Online Catalog') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Digital catalog & WhatsApp share') }}</div>
                            </div>
                        </a>
                    @endif
                </div>

                <!-- Category 5: Administration & Settings -->
                <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
                    <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
                        {{ __('Administration & Settings') }}
                    </div>
                    @if ($canSettings)
                        <a wire:navigate.hover x-show="isItemVisible('settings')" href="{{ route('tenant.settings.index') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isSettings,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isSettings,
                           ])
                           title="{{ __('Store Settings') }}">
                            <span class="text-base shrink-0">⚙️</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Store Settings') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Profile, printer, tax & domain') }}</div>
                            </div>
                        </a>
                    @endif
                    <a wire:navigate.hover x-show="isItemVisible('billing')" href="{{ route('tenant.billing.index') }}"
                       :class="{
                           'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                           'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                           'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                       }"
                       @class([
                           'bg-white text-blue-700 shadow-md' => $isBilling,
                           'text-white/80 hover:text-white hover:bg-white/15' => !$isBilling,
                       ])
                       title="{{ __('Subscription & Billing') }}">
                        <span class="text-base shrink-0">💳</span>
                        <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                            <div class="text-xs truncate">{{ __('Subscription & Billing') }}</div>
                            <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Plan details & invoices') }}</div>
                        </div>
                    </a>
                    @if ($canUsers)
                        <a wire:navigate.hover href="{{ route('tenant.users.index') }}"
                           :class="{
                               'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
                               'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
                               'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
                           }"
                           @class([
                               'bg-white text-blue-700 shadow-md' => $isUsers,
                               'text-white/80 hover:text-white hover:bg-white/15' => !$isUsers,
                           ])
                           title="{{ __('Users & Permissions') }}">
                            <span class="text-base shrink-0">🛡️</span>
                            <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
                                <div class="text-xs truncate">{{ __('Users & Permissions') }}</div>
                                <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ __('Staff accounts & access matrix') }}</div>
                            </div>
                        </a>
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
            <a wire:navigate.hover href="{{ route('tenant.dashboard') }}"
               class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
               aria-selected="{{ $isHome ? 'true' : 'false' }}"
               title="{{ __('Dashboard') }}">
                <div @class([
                    'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                    'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isHome,
                    'bg-white/15 text-white hover:bg-white/30' => !$isHome,
                ])>
                    📊
                </div>
            </a>

            <!-- 2. POS Option -->
            @if ($isRestaurant)
                @if ($canPos)
                    <a wire:navigate.hover x-show="isItemVisible('restaurant_pos')" href="{{ route('tenant.restaurant.pos') }}"
                       class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                       aria-selected="{{ $isRestaurantPos ? 'true' : 'false' }}"
                       title="{{ __('Restaurant POS') }}">
                        <div @class([
                            'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                            'bg-[#a3e635] text-slate-950 font-bold ring-2 ring-lime-400' => $isRestaurantPos,
                            'bg-white/15 text-white hover:bg-white/30' => !$isRestaurantPos,
                        ])>
                            🍽️
                        </div>
                    </a>
                @endif
            @else
                @if ($canPos)
                    <a wire:navigate.hover x-show="isItemVisible('pos')" href="{{ route('tenant.sales.create') }}"
                       class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                       aria-selected="{{ $isCasier ? 'true' : 'false' }}"
                       title="{{ __('Retail POS') }}">
                        <div @class([
                            'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                            'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isCasier,
                            'bg-white/15 text-white hover:bg-white/30' => !$isCasier,
                        ])>
                            🛒
                        </div>
                    </a>
                @endif
            @endif

            <!-- 3. Sales & Invoices -->
            @if ($canSales)
                <a wire:navigate.hover x-show="isItemVisible('sales')" href="{{ route('tenant.sales.index') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isTransaction ? 'true' : 'false' }}"
                   title="{{ __('Invoices & Sales') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isTransaction,
                        'bg-white/15 text-white hover:bg-white/30' => !$isTransaction,
                    ])>
                        🧾
                    </div>
                </a>
            @endif

            <!-- 4. Quotations (General Mode) -->
            @if (!$isRestaurant && $canQuotes)
                <a wire:navigate.hover x-show="isItemVisible('quotes')" href="{{ route('tenant.quotes.index') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isQuotes ? 'true' : 'false' }}"
                   title="{{ __('Quotations & Proposals') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isQuotes,
                        'bg-white/15 text-white hover:bg-white/30' => !$isQuotes,
                    ])>
                        📑
                    </div>
                </a>
            @endif

            <!-- 5. Products -->
            @if ($canProducts)
                <a wire:navigate.hover x-show="isItemVisible('products')" href="{{ route('tenant.products.index') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isProducts ? 'true' : 'false' }}"
                   title="{{ __('Products & Catalog') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isProducts,
                        'bg-white/15 text-white hover:bg-white/30' => !$isProducts,
                    ])>
                        📦
                    </div>
                </a>
            @endif

            <!-- 6. Cash Register -->
            @if ($canFinance)
                <a wire:navigate.hover x-show="isItemVisible('register')" href="{{ route('tenant.financials.cash_register') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isCashRegister ? 'true' : 'false' }}"
                   title="{{ __('Cash Register') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isCashRegister,
                        'bg-white/15 text-white hover:bg-white/30' => !$isCashRegister,
                    ])>
                        🗄️
                    </div>
                </a>
            @endif

            <!-- 7. Reports -->
            @if ($canReports)
                <a wire:navigate.hover x-show="isItemVisible('reports')" href="{{ route('tenant.reports.index') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isReports ? 'true' : 'false' }}"
                   title="{{ __('Reports & Analytics') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isReports,
                        'bg-white/15 text-white hover:bg-white/30' => !$isReports,
                    ])>
                        📊
                    </div>
                </a>
            @endif

            <!-- 8. Settings -->
            @if ($canSettings)
                <a wire:navigate.hover x-show="isItemVisible('settings')" href="{{ route('tenant.settings.index') }}"
                   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
                   aria-selected="{{ $isSettings ? 'true' : 'false' }}"
                   title="{{ __('Store Settings') }}">
                    <div @class([
                        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
                        'bg-white text-blue-600 font-bold ring-2 ring-blue-400' => $isSettings,
                        'bg-white/15 text-white hover:bg-white/30' => !$isSettings,
                    ])>
                        ⚙️
                    </div>
                </a>
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
                
                <a wire:navigate.hover href="{{ route('tenant.dashboard') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                    <span>{{ __('Overview') }}</span>
                    <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">📊</span>
                </a>

                @if ($isRestaurant)
                    @if ($canPos)
                        <a wire:navigate.hover x-show="isItemVisible('restaurant_pos')" href="{{ route('tenant.restaurant.pos') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                            <span>{{ __('Restaurant POS') }}</span>
                            <span class="w-8 h-8 rounded-full bg-lime-500 text-slate-950 flex items-center justify-center">🍽️</span>
                        </a>
                    @endif
                @else
                    @if ($canPos)
                        <a wire:navigate.hover x-show="isItemVisible('pos')" href="{{ route('tenant.sales.create') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                            <span>{{ __('Cashier POS') }}</span>
                            <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">🛒</span>
                        </a>
                    @endif
                @endif

                @if ($canSales)
                    <a wire:navigate.hover x-show="isItemVisible('sales')" href="{{ route('tenant.sales.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                        <span>{{ __('Invoices & Sales') }}</span>
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">🧾</span>
                    </a>
                @endif

                @if (!$isRestaurant && $canQuotes)
                    <a wire:navigate.hover x-show="isItemVisible('quotes')" href="{{ route('tenant.quotes.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                        <span>{{ __('Quotations') }}</span>
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">📑</span>
                    </a>
                @endif

                @if ($canFinance)
                    <a wire:navigate.hover x-show="isItemVisible('register')" href="{{ route('tenant.financials.cash_register') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                        <span>{{ __('Cash Register') }}</span>
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">🗄️</span>
                    </a>
                @endif

                @if ($canReports)
                    <a wire:navigate.hover x-show="isItemVisible('reports')" href="{{ route('tenant.reports.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                        <span>{{ __('Reports') }}</span>
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">📊</span>
                    </a>
                @endif

                @if ($canSettings)
                    <a wire:navigate.hover x-show="isItemVisible('settings')" href="{{ route('tenant.settings.index') }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
                        <span>{{ __('Settings') }}</span>
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">⚙️</span>
                    </a>
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
                <nav class="space-y-5 text-xs font-semibold">
                    
                    @if ($isRestaurant)
                        <!-- RESTAURANT MODE DRAWER ITEMS -->
                        @if ($canPos)
                            <div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-lime-600 dark:text-lime-400 mb-2 px-3">{{ __('Restaurant Operations') }}</div>
                                <div class="space-y-1">
                                    <a wire:navigate.hover href="{{ route('tenant.restaurant.pos') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-lime-50 dark:hover:bg-lime-950/50 hover:text-lime-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-lime-50 dark:bg-lime-950/60 text-lime-600 dark:text-lime-400 flex items-center justify-center text-xs group-hover:bg-[#a3e635] group-hover:text-slate-950 transition">
                                            🍽️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Restaurant POS Terminal') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Dine-In, Takeaway & Delivery') }}</div>
                                        </div>
                                    </a>

                                    <a wire:navigate.hover href="{{ route('tenant.restaurant.tables') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-lime-50 dark:hover:bg-lime-950/50 hover:text-lime-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-[#a3e635] group-hover:text-slate-950 transition">
                                            🪑
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Floor Plan & Tables') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Live Table Status & QR Menus') }}</div>
                                        </div>
                                    </a>

                                    <a wire:navigate.hover href="{{ route('tenant.restaurant.kds') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-lime-50 dark:hover:bg-lime-950/50 hover:text-lime-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-[#a3e635] group-hover:text-slate-950 transition">
                                            🍳
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Kitchen Display (KDS)') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Live KOT preparation queue') }}</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Orders & Cash') }}</div>
                            <div class="space-y-1">
                                @if ($canSales)
                                    <a wire:navigate.hover href="{{ route('tenant.sales.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Dining & Sales History') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Thermal receipts & order history') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canFinance)
                                    <a wire:navigate.hover href="{{ route('tenant.financials.cash_register') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-lime-50 dark:hover:bg-lime-950/50 hover:text-lime-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-[#a3e635] group-hover:text-slate-950 transition">
                                            🗄️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Cash Register') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Opening, closing, cash withdrawals') }}</div>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($canFinance)
                            <div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Financial Management') }}</div>
                                <div class="space-y-1">
                                    <a wire:navigate.hover href="{{ route('tenant.financials.receivables') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📈
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Accounts Receivable') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Customer credit & unpaid bills') }}</div>
                                        </div>
                                    </a>

                                    <a wire:navigate.hover href="{{ route('tenant.financials.payables') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📉
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Accounts Payable') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Supplier bills & food purchases') }}</div>
                                        </div>
                                    </a>

                                    @if ($canReports)
                                        <a wire:navigate.hover href="{{ route('tenant.reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                            <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                                📊
                                            </span>
                                            <div>
                                                <div class="font-bold">{{ __('Reports & Analytics') }}</div>
                                                <div class="text-[10px] text-slate-400 font-normal">{{ __('Sales, commissions & aging') }}</div>
                                            </div>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Kitchen Menu & Catalog') }}</div>
                            <div class="space-y-1">
                                @if ($canProducts)
                                    <a wire:navigate.hover href="{{ route('tenant.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📦
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Menu Dishes & Stock') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Dishes, ingredients & pricing') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCategories)
                                    <a wire:navigate.hover href="{{ route('tenant.categories.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🏷️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Categories') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Menu sections & tax rates') }}</div>
                                        </div>
                                    </a>
                                @endif

                                <a wire:navigate.hover href="{{ route('tenant.brands.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                    <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                        ✨
                                    </span>
                                    <div>
                                        <div class="font-bold">{{ __('Brands & Modifiers') }}</div>
                                        <div class="text-[10px] text-slate-400 font-normal">{{ __('Product brands & food options') }}</div>
                                    </div>
                                </a>

                                @if ($canUnits)
                                    <a wire:navigate.hover href="{{ route('tenant.units.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            ⚖️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Units of Measure') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Portions, kg, litres & grams') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canSuppliers)
                                    <a wire:navigate.hover href="{{ route('tenant.suppliers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🚚
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Food Suppliers') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Vendor contacts & purchasing') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCatalog)
                                    <a wire:navigate.hover href="{{ route('tenant.catalog.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🌐
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Online QR Menu') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Digital QR menu & WhatsApp store') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCustomers)
                                    <a wire:navigate.hover href="{{ route('tenant.customers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            👥
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Guest Directory') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Customer history & contact list') }}</div>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        </div>

                    @else
                        <!-- GENERAL RETAIL DRAWER ITEMS -->
                        <div>
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-2 px-3">{{ __('Cashier & Sales') }}</div>
                            <div class="space-y-1">
                                @if ($canPos)
                                    <a wire:navigate.hover href="{{ route('tenant.sales.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🛒
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Cashier POS Terminal') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Fast barcode scan & cash checkout') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canSales)
                                    <a wire:navigate.hover href="{{ route('tenant.sales.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🧾
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Sales & Invoices') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('History, print receipts & refunds') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canQuotes)
                                    <a wire:navigate.hover href="{{ route('tenant.quotes.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📑
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Quotations & Proposals') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Quotes, estimates & 1-click sales conversion') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCustomers)
                                    <a wire:navigate.hover href="{{ route('tenant.customers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            👥
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Customers & CRM') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Customer directory & loyalty points') }}</div>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($canFinance)
                            <div>
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Financial Management') }}</div>
                                <div class="space-y-1">
                                    <a wire:navigate.hover href="{{ route('tenant.financials.cash_register') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🗄️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Cash Register') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Opening, closing, cash withdrawals') }}</div>
                                        </div>
                                    </a>

                                    <a wire:navigate.hover href="{{ route('tenant.financials.receivables') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📈
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Accounts Receivable') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Customer credit & pending payments') }}</div>
                                        </div>
                                    </a>

                                    <a wire:navigate.hover href="{{ route('tenant.financials.payables') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📉
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Accounts Payable') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Supplier bills & purchase dues') }}</div>
                                        </div>
                                    </a>

                                    @if ($canReports)
                                        <a wire:navigate.hover href="{{ route('tenant.reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                            <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                                📊
                                            </span>
                                            <div>
                                                <div class="font-bold">{{ __('Reports & Analytics') }}</div>
                                                <div class="text-[10px] text-slate-400 font-normal">{{ __('Sales, commissions & aging') }}</div>
                                            </div>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Products & Inventory') }}</div>
                            <div class="space-y-1">
                                @if ($canProducts)
                                    <a wire:navigate.hover href="{{ route('tenant.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            📦
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('All Products') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('SKUs, pricing & inventory levels') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCategories)
                                    <a wire:navigate.hover href="{{ route('tenant.categories.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🏷️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Categories') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Tax rates & category hierarchy') }}</div>
                                        </div>
                                    </a>
                                @endif

                                <a wire:navigate.hover href="{{ route('tenant.brands.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                    <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                        ✨
                                    </span>
                                    <div>
                                        <div class="font-bold">{{ __('Brands & Manufacturers') }}</div>
                                        <div class="text-[10px] text-slate-400 font-normal">{{ __('Brand names & supplier labels') }}</div>
                                    </div>
                                </a>

                                @if ($canUnits)
                                    <a wire:navigate.hover href="{{ route('tenant.units.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            ⚖️
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Units of Measure') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Pieces, kg, box, packs & liters') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canSuppliers)
                                    <a wire:navigate.hover href="{{ route('tenant.suppliers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🚚
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Suppliers & Vendors') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Vendor directory & purchasing') }}</div>
                                        </div>
                                    </a>
                                @endif

                                @if ($canCatalog)
                                    <a wire:navigate.hover href="{{ route('tenant.catalog.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition group">
                                        <span class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                            🌐
                                        </span>
                                        <div>
                                            <div class="font-bold">{{ __('Online Digital Catalog') }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ __('Shareable web catalog & WhatsApp store') }}</div>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Administration & Settings -->
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 px-3">{{ __('Administration & Settings') }}</div>
                        <div class="space-y-1">
                            <a wire:navigate.hover href="{{ route('tenant.billing.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="font-bold">{{ __('Subscription & Billing') }}</span>
                                </div>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">{{ __('Invoices') }}</span>
                            </a>

                            @if ($canSettings)
                                <a wire:navigate.hover href="{{ route('tenant.settings.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                        <span>{{ __('Store Settings') }}</span>
                                    </div>
                                </a>
                            @endif

                            <a wire:navigate.hover href="{{ route('tenant.languages.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>{{ __('Languages & Translations') }}</span>
                                </div>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">{{ __('Multi-Lang') }}</span>
                            </a>

                            @if ($canUsers)
                                <a wire:navigate.hover href="{{ route('tenant.users.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                        <span>{{ __('Users & Permissions') }}</span>
                                    </div>
                                </a>
                            @endif

                            <a wire:navigate.hover href="{{ route('tenant.devices.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    <span>{{ __('Terminals & Devices') }}</span>
                                </div>
                            </a>
                        </div>
                    </div>

                </nav>
            </div>

            <!-- Drawer Bottom Bar -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <button type="button" x-on:click="dark = !dark" class="text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1.5 font-bold transition">
                    <span x-show="!dark">🌙 Dark Mode</span>
                    <span x-show="dark">☀️ Light Mode</span>
                </button>

                <form method="POST" action="{{ route('tenant.logout') }}">
                    @csrf
                    <button type="submit" class="text-rose-600 hover:underline font-extrabold">{{ __('Sign Out') }}</button>
                </form>
            </div>
        </div>

        <!-- Backdrop overlay for slide-out drawer -->
        <div x-show="sidebarOpen"
             x-cloak
             x-on:click="sidebarOpen = false"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40"></div>

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
                            class="px-2.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs border border-blue-200/50 dark:border-blue-800/50"
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

                    <!-- User Pill & Sign out -->
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-sm">
                            {{ substr(auth()->user()?->name ?? 'U', 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 leading-none">{{ auth()->user()?->name }}</div>
                            <div class="text-[10px] text-slate-400 capitalize mt-0.5">{{ auth()->user()?->role }}</div>
                        </div>

                        <form method="POST" action="{{ route('tenant.logout') }}" class="ml-2">
                            @csrf
                            <button type="submit" class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" title="Sign out">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Main Dynamic View Container with Smooth Transition -->
            @php
                $isPosScreen = request()->routeIs('tenant.sales.create') || request()->routeIs('tenant.restaurant.pos');
            @endphp
            <main class="flex-1 w-full max-w-none spa-page-enter transition-all duration-200 {{ $isPosScreen ? 'p-1.5 sm:p-2.5 overflow-hidden flex flex-col min-h-0 h-full' : 'p-3 sm:p-5 md:p-6 overflow-y-auto' }}" id="main-app-content">
                <div class="w-full max-w-none {{ $isPosScreen ? 'flex-1 min-h-0 flex flex-col overflow-hidden h-full' : '' }}">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </main>
        </div>

        <!-- Appearance & Layout Customizer Modal (Inside x-data scope) -->
        @include('layouts.partials.nav-customizer-modal')

    </div>

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
