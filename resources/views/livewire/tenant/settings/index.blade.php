<div class="w-full space-y-6"
     x-data="{
         activeTab: @js($activeSection),
         validTabs: ['overview', 'mode', 'profile', 'receipts', 'financial', 'taxes', 'api', 'navigation']
     }"
     x-init="
         if (window.location.hash && validTabs.includes(window.location.hash.substring(1))) {
             activeTab = window.location.hash.substring(1);
         }
         window.addEventListener('hashchange', () => {
             const h = window.location.hash ? window.location.hash.substring(1) : '';
             if (validTabs.includes(h)) activeTab = h;
         });
     ">
    
    <!-- Flash Status Messages -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm animate-in fade-in">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm animate-in fade-in">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Section Breadcrumb & Back to Overview Navigation -->
    <div x-show="activeTab !== 'overview'" class="flex items-center justify-between gap-3 pb-1 border-b border-slate-200/60 dark:border-slate-800">
        <div class="flex items-center gap-2 text-xs sm:text-sm font-bold text-slate-500 dark:text-slate-400">
            <a href="{{ route('tenant.settings.index') }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400 flex items-center gap-1.5 transition">
                <span>⚙️</span>
                <span>{{ __('Store Settings') }}</span>
            </a>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span class="text-slate-900 dark:text-white font-extrabold" x-text="{
                'mode': '{{ __('Store Operating Mode') }}',
                'profile': '{{ __('Store Profile & Branding') }}',
                'receipts': '{{ __('Receipt Prefixes & Bank Terms') }}',
                'financial': '{{ __('Financial & Currency') }}',
                'taxes': '{{ __('Taxes & Compliance') }}',
                'api': '{{ __('API & Integrations') }}',
                'navigation': '{{ __('Navigation Menu') }}'
            }[activeTab] || '{{ __('Settings') }}'"></span>
        </div>
        <a href="{{ route('tenant.settings.index') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-200 transition active:scale-95 shadow-2xs">
            <span>←</span>
            <span>{{ __('Back to Settings Overview') }}</span>
        </a>
    </div>

    <!-- Modular Tab Navigation Bar with Smooth Scroll Synchronization & Dedicated URL Links -->
    <div class="settings-subnav-container flex items-center gap-2 overflow-x-auto py-2 px-1 scrollbar-none snap-x snap-mandatory bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200/60 dark:border-slate-700/60 shadow-2xs"
         style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
        
        <!-- Tab 0: Settings Overview -->
        <a href="{{ route('tenant.settings.index') }}"
           wire:navigate
           :aria-selected="activeTab === 'overview' ? 'true' : 'false'"
           :class="activeTab === 'overview' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🏛️</span>
            <span>{{ __('Overview') }}</span>
        </a>

        <!-- Tab 1: Operating Mode -->
        <a href="{{ route('tenant.settings.mode') }}"
           wire:navigate
           :aria-selected="activeTab === 'mode' ? 'true' : 'false'"
           :class="activeTab === 'mode' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">⚡</span>
            <span>{{ __('Store Operating Mode') }}</span>
        </a>

        <!-- Tab 2: Profile & Branding -->
        <a href="{{ route('tenant.settings.profile') }}"
           wire:navigate
           :aria-selected="activeTab === 'profile' ? 'true' : 'false'"
           :class="activeTab === 'profile' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🏢</span>
            <span>{{ __('Store Profile & Branding') }}</span>
        </a>

        <!-- Tab 3: Receipt Prefixes & Terms -->
        <a href="{{ route('tenant.settings.receipts') }}"
           wire:navigate
           :aria-selected="activeTab === 'receipts' ? 'true' : 'false'"
           :class="activeTab === 'receipts' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">📄</span>
            <span>{{ __('Receipt Prefixes & Bank Terms') }}</span>
        </a>

        <!-- Tab 4: Financial & Currency -->
        <a href="{{ route('tenant.settings.financial') }}"
           wire:navigate
           :aria-selected="activeTab === 'financial' ? 'true' : 'false'"
           :class="activeTab === 'financial' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">💳</span>
            <span>{{ __('Financial & Currency') }}</span>
        </a>

        <!-- Tab 5: Taxes & Compliance -->
        <a href="{{ route('tenant.settings.taxes') }}"
           wire:navigate
           :aria-selected="activeTab === 'taxes' ? 'true' : 'false'"
           :class="activeTab === 'taxes' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">⚖️</span>
            <span>{{ __('Taxes & Compliance') }}</span>
        </a>

        <!-- Tab 6: API & Integrations -->
        <a href="{{ route('tenant.settings.api') }}"
           wire:navigate
           :aria-selected="activeTab === 'api' ? 'true' : 'false'"
           :class="activeTab === 'api' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🔌</span>
            <span>{{ __('API & Integrations') }}</span>
        </a>

        <!-- Tab 7: Navigation Menu -->
        <a href="{{ route('tenant.settings.navigation') }}"
           wire:navigate
           :aria-selected="activeTab === 'navigation' ? 'true' : 'false'"
           :class="activeTab === 'navigation' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
           class="snap-center px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🧭</span>
            <span>{{ __('Navigation Menu') }}</span>
        </a>
    </div>

    <!-- =========================================================================
         TAB 0: SETTINGS OVERVIEW HUB (When on /settings or overview tab)
         ========================================================================= -->
    <div x-show="activeTab === 'overview'" x-cloak class="space-y-6">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-blue-500/10">
            <div class="max-w-2xl">
                <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-white/20 text-white backdrop-blur-xs mb-3 inline-block">
                    {{ __('Store Administration') }}
                </span>
                <h2 class="text-xl sm:text-2xl font-black">{{ __('Store Settings Overview') }}</h2>
                <p class="text-xs sm:text-sm text-blue-100/90 mt-1 leading-relaxed">
                    {{ __('Manage operating mode, branding, receipts, currencies, taxes, APIs, and custom sidebar navigation from dedicated settings consoles.') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <!-- Card 1: Operating Mode -->
            <a href="{{ route('tenant.settings.mode') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl font-bold">
                        ⚡
                    </div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Operating Mode') }}</h3>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {{ $posMode === 'restaurant' ? __('Restaurant') : __('Retail') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Store operational model, consignment controls, and module permissions.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Mode') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 2: Profile & Branding -->
            <a href="{{ route('tenant.settings.profile') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl font-bold">
                        🏢
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Profile & Branding') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Store legal name, trading details, address, logos, favicons, and accent colors.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Profile') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 3: Receipt Prefixes & Bank Terms -->
            <a href="{{ route('tenant.settings.receipts') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl font-bold">
                        📄
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Receipts & Terms') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Invoice/quote numbering prefixes, receipt formats (80mm/58mm), and bank terms.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Receipts') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 4: Financial & Currency -->
            <a href="{{ route('tenant.settings.financial') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold">
                        💳
                    </div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Financial & Currency') }}</h3>
                        <span class="text-[10px] font-extrabold font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {{ $currency }} ({{ $currencySymbol }})
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Base and multi-currency rates, payment methods, card processing fee tiers, and PIX/scale.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Financials') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 5: Taxes & Compliance -->
            <a href="{{ route('tenant.settings.taxes') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-2xl font-bold">
                        ⚖️
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Taxes & Compliance') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Multi-jurisdiction fiscal rules, inclusive/exclusive modes, and CGST/SGST/VAT splits.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Taxes') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 6: API & Integrations -->
            <a href="{{ route('tenant.settings.api') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl font-bold">
                        🔌
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('API & Integrations') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Sanctum API keys, Generative AI product studio, SMTP mail server, and webhooks.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Configure Integrations') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>

            <!-- Card 7: Navigation Menu -->
            <a href="{{ route('tenant.settings.navigation') }}" wire:navigate class="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center text-2xl font-bold">
                        🧭
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ __('Navigation Menu') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Customize sidebar menu structure, drag-and-drop ordering, visibility, and sub-menu nesting.') }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-black text-blue-600 dark:text-blue-400">
                    <span>{{ __('Customize Navigation') }}</span>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
            </a>
        </div>
    </div>

    <!-- =========================================================================
         TAB 1: STORE OPERATING MODE (Retail POS vs Restaurant POS)
         ========================================================================= -->
    <div x-show="activeTab === 'mode'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl">
                    ⚡
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Store Operating Mode') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Choose between General Retail POS vs Food & Restaurant Mode with strict UI & route isolation') }}</p>
                </div>
            </div>

            <div class="p-5 sm:p-6 rounded-3xl border-2 flex flex-col sm:flex-row sm:items-center gap-4
                        @if ($posMode === 'restaurant' && ! $this->restaurantModeLocked)
                            border-lime-500 bg-lime-50/50 dark:bg-lime-950/40 dark:border-lime-400
                        @else
                            border-blue-600 bg-blue-50/50 dark:bg-blue-950/40 dark:border-blue-500
                        @endif">
                <div @class([
                        'w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center text-2xl font-black',
                        'bg-lime-100 dark:bg-lime-900/60 text-lime-700 dark:text-lime-400' => $posMode === 'restaurant' && ! $this->restaurantModeLocked,
                        'bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400' => $posMode !== 'restaurant' || $this->restaurantModeLocked,
                    ])>
                    {{ $posMode === 'restaurant' && ! $this->restaurantModeLocked ? '🍽️' : '🏪' }}
                </div>
                <div class="flex-1">
                    <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">
                        @if ($posMode === 'restaurant' && ! $this->restaurantModeLocked)
                            {{ __('Food & Restaurant Mode') }}
                        @else
                            {{ __('General Retail POS') }}
                        @endif
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        @if ($posMode === 'restaurant' && ! $this->restaurantModeLocked)
                            {{ __('Floor plans & live tables, KOT tickets, Kitchen Display (KDS), Dine-In/Takeaway routing, and QR table ordering.') }}
                        @else
                            {{ __('Barcode scanning, cash register, quotations, invoices, and standard stock management for retail shops.') }}
                        @endif
                    </p>
                    @if ($posMode === 'restaurant' && $this->restaurantModeLocked)
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-2">
                            {{ __('Restaurant Mode disabled by platform administrator — running in General Retail POS until re-enabled.') }}
                        </p>
                    @endif
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mt-2">
                        {{ __('Operating mode is fixed at registration and can only be changed by platform support.') }}
                    </p>
                </div>
            </div>

            <!-- Master Consignment Toggle -->
            <div class="p-4 sm:p-5 rounded-3xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200">
                        <span>📦</span>
                        <span>{{ __("Enable Consignment Sales") }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5 max-w-xl">
                        {{ __("Allow POS checkout directly under Consignment mode with immediate shelf inventory deduction and open client consignment ledger tracking.") }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" wire:model="enableConsignments" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <!-- Save Button for Operating Mode -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    {{ __('Save Operating Mode') }}
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 2: STORE PROFILE & BRANDING
         ========================================================================= -->
    <div x-show="activeTab === 'profile'" x-cloak class="space-y-6">
        
        <!-- Store Information -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Store Profile & Location') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Business details displayed on POS terminal, receipts, and invoices') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Company / Store Name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Trade / Brand Name') }}</label>
                    <input type="text" wire:model="tradeName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Tax ID / Business Reg') }}</label>
                    <input type="text" wire:model="taxId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Email') }}</label>
                    <input type="email" wire:model="email" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Phone / WhatsApp') }}</label>
                    <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('phone') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Website URL') }}</label>
                    <input type="text" wire:model="website" placeholder="{{ __('e.g. yourstore.com or https://yourstore.com') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('website') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Subdomain / Slug -->
                @php
                    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'saas.zoomnearby.com';
                @endphp
                <div x-data="{ slugValue: @js($slug) }">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Subdomain / Slug') }}</label>
                    <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                        <input type="text" wire:model="slug" x-on:input="slugValue = $event.target.value" placeholder="{{ __('my-store') }}" class="w-full border-none bg-transparent text-xs sm:text-sm font-mono focus:ring-0 py-2.5 px-3">
                        <span class="pr-3 text-xs font-mono text-slate-400">.{{ $appHost }}</span>
                    </div>
                    @error('slug') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <template x-if="slugValue">
                        <a :href="'https://' + slugValue.toLowerCase() + '.{{ $appHost }}'"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-mono text-blue-600 dark:text-blue-400 hover:underline">
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            <span x-text="'https://' + slugValue.toLowerCase() + '.{{ $appHost }}'"></span>
                        </a>
                    </template>
                </div>

                <!-- Custom Domain -->
                <div class="sm:col-span-2" x-data="{ customDomainValue: @js($customDomain) }">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                        <span>🌐 {{ __('Custom Domain Configuration') }}</span>
                        <span class="text-[10px] text-slate-400 font-normal">({{ __('Optional') }})</span>
                    </label>
                    <input type="text" wire:model="customDomain" x-on:input="customDomainValue = $event.target.value" placeholder="{{ __('pos.yourdomain.com or mycompany.com') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-[11px] text-slate-400 mt-1">
                        {{ __("Point your domain's") }} <span class="font-semibold text-slate-500 dark:text-slate-400">CNAME</span> ({{ __('or') }} <span class="font-semibold text-slate-500 dark:text-slate-400">A-record</span>, {{ __("if CNAME isn't supported at your registrar for this name) to") }}
                        <span class="font-mono text-slate-600 dark:text-slate-300">{{ $appHost }}</span>, {{ __("save this form, then ask your platform admin to finish HTTPS setup for the domain — until that's done it may show a certificate warning.") }}
                    </p>
                    @error('customDomain') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <template x-if="customDomainValue">
                        <a :href="'https://' + customDomainValue.toLowerCase().replace(/^https?:\/\//, '')"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-mono text-blue-600 dark:text-blue-400 hover:underline">
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            <span x-text="'https://' + customDomainValue.toLowerCase().replace(/^https?:\/\//, '')"></span>
                        </a>
                    </template>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Physical Address') }}</label>
                    <input type="text" wire:model="address" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('City') }}</label>
                    <input type="text" wire:model="city" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('State / Province') }}</label>
                    <input type="text" wire:model="state" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Postal Code') }}</label>
                    <input type="text" wire:model="postalCode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Country (2-letter ISO)') }}</label>
                    <input type="text" wire:model="country" maxlength="2" class="w-full uppercase rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        <!-- Store Branding & Theme Styling -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Store Theme, Branding & POS Layout') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Application primary theme color, default POS layout design, logo, and document colors') }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Application Theme Color Palette -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Application & Navigation Theme Color') }}</label>
                        <p class="text-[11px] text-slate-400">{{ __('Select the primary accent color for your navigation sidebar, buttons, and badges') }}</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                        @php
                            $themePresets = [
                                'blue' => ['label' => __('Cobalt Blue'), 'bg' => 'bg-blue-600', 'hex' => '#2563eb'],
                                'emerald' => ['label' => __('Emerald Green'), 'bg' => 'bg-emerald-600', 'hex' => '#059669'],
                                'indigo' => ['label' => __('Royal Indigo'), 'bg' => 'bg-indigo-600', 'hex' => '#4f46e5'],
                                'purple' => ['label' => __('Violet Purple'), 'bg' => 'bg-purple-600', 'hex' => '#7c3aed'],
                                'amber' => ['label' => __('Warm Amber'), 'bg' => 'bg-amber-600', 'hex' => '#d97706'],
                                'rose' => ['label' => __('Ruby Rose'), 'bg' => 'bg-rose-600', 'hex' => '#e11d48'],
                                'slate' => ['label' => __('Midnight Slate'), 'bg' => 'bg-slate-800', 'hex' => '#334155'],
                            ];
                        @endphp

                        @foreach ($themePresets as $key => $preset)
                            <button type="button"
                                    wire:click="setThemeColor('{{ $key }}')"
                                    @class([
                                        'p-3 rounded-2xl border-2 flex flex-col items-center gap-2 text-center transition-all cursor-pointer shadow-xs active:scale-95',
                                        'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $themeColor === $key,
                                        'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $themeColor !== $key,
                                    ])>
                                <span class="w-7 h-7 rounded-full {{ $preset['bg'] }} shadow-sm flex items-center justify-center text-white text-xs font-black">
                                    @if ($themeColor === $key) ✓ @endif
                                </span>
                                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate w-full">
                                    {{ $preset['label'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Dashboard Hero & UI Accent Color -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Dashboard Hero & UI Accent Color') }}</label>
                            <p class="text-[11px] text-slate-400">{{ __('Select the primary accent color applied to the dashboard welcome banner, action buttons, and active highlight pills') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live="primaryColor" class="w-8 h-8 rounded-lg border-none cursor-pointer p-0 bg-transparent" title="{{ __('Pick Custom Accent Color') }}">
                            <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $primaryColor }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-2.5">
                        @php
                            $accentPresets = [
                                '#2563eb' => ['name' => __('Cobalt Blue'), 'hex' => '#2563eb'],
                                '#059669' => ['name' => __('Emerald Green'), 'hex' => '#059669'],
                                '#4f46e5' => ['name' => __('Royal Indigo'), 'hex' => '#4f46e5'],
                                '#7c3aed' => ['name' => __('Violet Purple'), 'hex' => '#7c3aed'],
                                '#d97706' => ['name' => __('Warm Amber'), 'hex' => '#d97706'],
                                '#e11d48' => ['name' => __('Ruby Rose'), 'hex' => '#e11d48'],
                                '#0d9488' => ['name' => __('Teal Ocean'), 'hex' => '#0d9488'],
                                '#334155' => ['name' => __('Midnight Slate'), 'hex' => '#334155'],
                            ];
                        @endphp

                        @foreach ($accentPresets as $hex => $accent)
                            <button type="button"
                                    wire:click="setQuotationColor('{{ $hex }}')"
                                    @class([
                                        'p-2.5 rounded-2xl border-2 flex flex-col items-center gap-1.5 text-center transition-all cursor-pointer shadow-xs active:scale-95',
                                        'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => strtolower($primaryColor) === strtolower($hex),
                                        'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => strtolower($primaryColor) !== strtolower($hex),
                                    ])>
                                <span class="w-6 h-6 rounded-full shadow-xs flex items-center justify-center text-white text-[10px] font-black"
                                      style="background-color: {{ $hex }}">
                                    @if (strtolower($primaryColor) === strtolower($hex)) ✓ @endif
                                </span>
                                <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate w-full">
                                    {{ $accent['name'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Default POS Layout Design Selector -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Default POS Register Layout Design') }}</label>
                        <p class="text-[11px] text-slate-400">{{ __('Choose the default interface style for your Point of Sale counter') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Layout 1: Standard Retail -->
                        <button type="button"
                                wire:click="setPosLayout('standard')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'standard',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'standard',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">🛒</span>
                                    @if ($posLayout === 'standard')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Standard Scanner POS') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Search bar, category chips, 3-column retail item cards with side cart.') }}
                                </p>
                            </div>
                        </button>

                        <!-- Layout 2: Supermarket Touch -->
                        <button type="button"
                                wire:click="setPosLayout('touch')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'touch',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'touch',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">🏪</span>
                                    @if ($posLayout === 'touch')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Supermarket Touch POS') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Function toolbar, vibrant department tiles & right receipt checkout slip.') }}
                                </p>
                            </div>
                        </button>

                        <!-- Layout 3: Square Stand Modern -->
                        <button type="button"
                                wire:click="setPosLayout('stand')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'stand',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'stand',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">📱</span>
                                    @if ($posLayout === 'stand')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Square Stand Register') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Clean photo tiles, top category tabs, discounts bar & quick-charge pill.') }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Logo & Favicon Upload Section -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-800"
                     x-data="{
                         instantLogoPreview: null,
                         instantFaviconPreview: null,
                         instantDrawerCoverPreview: null,
                         logoError: '',
                         faviconError: '',
                         drawerCoverError: '',
                         handleLogoChange(event) {
                             this.logoError = '';
                             const file = event.target.files[0];
                             if (!file) return;
                             const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
                             if (!allowed.includes(file.type)) {
                                 this.logoError = '{{ __('Invalid file format: Please select a valid image file (.png, .jpg, .jpeg, .webp, .svg)') }}';
                                 event.target.value = '';
                                 this.instantLogoPreview = null;
                                 return;
                             }
                             if (file.size > 2048 * 1024) {
                                 this.logoError = '{{ __('File size exceeds 2MB limit. Please choose a smaller image.') }}';
                                 event.target.value = '';
                                 this.instantLogoPreview = null;
                                 return;
                             }
                             const reader = new FileReader();
                             reader.onload = (e) => { this.instantLogoPreview = e.target.result; };
                             reader.readAsDataURL(file);
                         },
                         handleFaviconChange(event) {
                             this.faviconError = '';
                             const file = event.target.files[0];
                             if (!file) return;
                             const allowed = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/jpeg', 'image/webp'];
                             if (!allowed.includes(file.type) && !file.name.endsWith('.ico')) {
                                 this.faviconError = '{{ __('Invalid format: Please select an .ico, .png, or .svg favicon.') }}';
                                 event.target.value = '';
                                 this.instantFaviconPreview = null;
                                 return;
                             }
                             const reader = new FileReader();
                             reader.onload = (e) => { this.instantFaviconPreview = e.target.result; };
                             reader.readAsDataURL(file);
                         },
                         handleDrawerCoverChange(event) {
                             this.drawerCoverError = '';
                             const file = event.target.files[0];
                             if (!file) return;
                             const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
                             if (!allowed.includes(file.type)) {
                                 this.drawerCoverError = '{{ __('Invalid file format: Please select a valid image file (.png, .jpg, .jpeg, .webp)') }}';
                                 event.target.value = '';
                                 this.instantDrawerCoverPreview = null;
                                 return;
                             }
                             if (file.size > 4096 * 1024) {
                                 this.drawerCoverError = '{{ __('File size exceeds 4MB limit. Please choose a smaller image.') }}';
                                 event.target.value = '';
                                 this.instantDrawerCoverPreview = null;
                                 return;
                             }
                             const reader = new FileReader();
                             reader.onload = (e) => { this.instantDrawerCoverPreview = e.target.result; };
                             reader.readAsDataURL(file);
                         }
                     }">
                    <!-- Store Logo Upload -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Business / Store Logo') }}
                        </label>
                        <div class="flex items-start gap-4">
                            <!-- Preview Box -->
                            <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-center overflow-hidden shrink-0 relative group">
                                <template x-if="instantLogoPreview">
                                    <img :src="instantLogoPreview" alt="{{ __('Preview') }}" class="max-w-full max-h-full object-contain p-2">
                                </template>
                                <template x-if="!instantLogoPreview">
                                    <div class="w-full h-full flex items-center justify-center">
                                        @if ($logoFile && $this->logoPreviewUrl)
                                            <img src="{{ $this->logoPreviewUrl }}" alt="{{ __('Preview') }}" class="max-w-full max-h-full object-contain p-2">
                                        @elseif ($company && $company->getLogoUrl())
                                            <img src="{{ $company->getLogoUrl() }}" alt="{{ $company->name }}" class="max-w-full max-h-full object-contain p-2">
                                        @else
                                            <div class="text-center p-2 text-slate-400">
                                                <svg class="w-8 h-8 mx-auto stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-[9px] block mt-1 font-semibold">{{ __('No Logo') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </template>

                                @if ($logoFile || $company->getLogoUrl())
                                    <button type="button"
                                            wire:click="removeLogo"
                                            x-on:click="instantLogoPreview = null"
                                            wire:confirm="{{ __('Are you sure you want to remove the business logo?') }}"
                                            class="absolute inset-0 bg-red-950/70 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-[10px] font-bold">
                                        <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        {{ __('Remove') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <span>{{ __('Upload Logo File') }}</span>
                                    <input type="file" wire:model="logoFile" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" x-on:change="handleLogoChange($event)" class="hidden">
                                </label>
                                <div wire:loading wire:target="logoFile" class="text-[11px] text-blue-600 font-semibold block">
                                    {{ __('Uploading logo...') }}
                                </div>
                                <p class="text-[10px] text-slate-400">
                                    {{ __('PNG, JPG, SVG or WEBP up to 2MB. Printed on 58mm/80mm receipts and PDF Quotations.') }}
                                </p>
                                <template x-if="logoError">
                                    <p class="text-[11px] text-red-500 font-bold" x-text="logoError"></p>
                                </template>
                                @error('logoFile') <span class="text-[10px] text-red-500 font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Favicon Upload -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Browser Favicon') }}
                        </label>
                        <div class="flex items-start gap-4">
                            <!-- Preview Box -->
                            <div class="w-16 h-16 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-center overflow-hidden shrink-0 relative group">
                                <template x-if="instantFaviconPreview">
                                    <img :src="instantFaviconPreview" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                </template>
                                <template x-if="!instantFaviconPreview">
                                    <div class="w-full h-full flex items-center justify-center">
                                        @if ($faviconFile && $this->faviconPreviewUrl)
                                            <img src="{{ $this->faviconPreviewUrl }}" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                        @elseif ($company && $company->getFaviconUrl())
                                            <img src="{{ $company->getFaviconUrl() }}" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                        @else
                                            <span class="text-xl">🌐</span>
                                        @endif
                                    </div>
                                </template>

                                @if ($faviconFile || $company->getFaviconUrl())
                                    <button type="button"
                                            wire:click="removeFavicon"
                                            x-on:click="instantFaviconPreview = null"
                                            class="absolute inset-0 bg-red-950/70 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-[9px] font-bold">
                                        {{ __('Clear') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span>{{ __('Upload Favicon') }}</span>
                                    <input type="file" wire:model="faviconFile" accept="image/png, image/x-icon, image/vnd.microsoft.icon, image/svg+xml, image/jpeg, image/webp, .ico" x-on:change="handleFaviconChange($event)" class="hidden">
                                </label>
                                <p class="text-[10px] text-slate-400">
                                    {{ __('Small 32x32 icon (.ico, .png, .svg) displayed in browser tabs.') }}
                                </p>
                                <template x-if="faviconError">
                                    <p class="text-[11px] text-red-500 font-bold" x-text="faviconError"></p>
                                </template>
                                @error('faviconFile') <span class="text-[10px] text-red-500 font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Cover Image Upload -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Drawer Cover Image') }}
                        </label>
                        <div class="flex items-start gap-4">
                            <!-- Preview Box -->
                            <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-center overflow-hidden shrink-0 relative group">
                                <template x-if="instantDrawerCoverPreview">
                                    <img :src="instantDrawerCoverPreview" alt="{{ __('Preview') }}" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!instantDrawerCoverPreview">
                                    <div class="w-full h-full flex items-center justify-center">
                                        @if ($drawerCoverFile && $this->drawerCoverPreviewUrl)
                                            <img src="{{ $this->drawerCoverPreviewUrl }}" alt="{{ __('Preview') }}" class="w-full h-full object-cover">
                                        @elseif ($company && $company->getDrawerCoverUrl())
                                            <img src="{{ $company->getDrawerCoverUrl() }}" alt="{{ __('Drawer Cover') }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="text-center p-2 text-slate-400">
                                                <svg class="w-8 h-8 mx-auto stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-[9px] block mt-1 font-semibold">{{ __('No Cover') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </template>

                                @if ($drawerCoverFile || $company->getDrawerCoverUrl())
                                    <button type="button"
                                            wire:click="removeDrawerCover"
                                            x-on:click="instantDrawerCoverPreview = null"
                                            wire:confirm="{{ __('Are you sure you want to remove the drawer cover image?') }}"
                                            class="absolute inset-0 bg-red-950/70 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-[10px] font-bold">
                                        <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        {{ __('Remove') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <span>{{ __('Upload Cover Image') }}</span>
                                    <input type="file" wire:model="drawerCoverFile" accept="image/png, image/jpeg, image/jpg, image/webp" x-on:change="handleDrawerCoverChange($event)" class="hidden">
                                </label>
                                <div wire:loading wire:target="drawerCoverFile" class="text-[11px] text-blue-600 font-semibold block">
                                    {{ __('Uploading cover image...') }}
                                </div>
                                <p class="text-[10px] text-slate-400">
                                    {{ __('PNG, JPG or WEBP up to 4MB. Shown as the background of the mobile app drawer header.') }}
                                </p>
                                <template x-if="drawerCoverError">
                                    <p class="text-[11px] text-red-500 font-bold" x-text="drawerCoverError"></p>
                                </template>
                                @error('drawerCoverFile') <span class="text-[10px] text-red-500 font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thermal Receipt Printing & Commission Configuration -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <!-- Thermal Receipt Paper Width -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Thermal Receipt Paper Width') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mb-2">
                            {{ __('Default printer paper dimension for checkout printouts and order slips') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                    wire:click="setReceiptFormat('80mm')"
                                    @class([
                                        'p-3 rounded-xl border-2 flex items-center gap-3 transition cursor-pointer text-left',
                                        'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-100' => $receiptFormat === '80mm',
                                        'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300' => $receiptFormat !== '80mm',
                                    ])>
                                <span class="text-2xl">🧾</span>
                                <div>
                                    <div class="text-xs font-black">{{ __('80mm (Standard)') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ __('Full 3-inch POS printer') }}</div>
                                </div>
                            </button>

                            <button type="button"
                                    wire:click="setReceiptFormat('58mm')"
                                    @class([
                                        'p-3 rounded-xl border-2 flex items-center gap-3 transition cursor-pointer text-left',
                                        'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-100' => $receiptFormat === '58mm',
                                        'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300' => $receiptFormat !== '58mm',
                                    ])>
                                <span class="text-2xl">📱</span>
                                <div>
                                    <div class="text-xs font-black">{{ __('58mm (Mini)') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ __('2-inch portable / bluetooth') }}</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Store Default Commission Scheme -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Default Sales Commission Scheme') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mb-2">
                            {{ __('Baseline commission rate auto-assigned to sales staff unless overridden per user') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('Commission Type') }}</label>
                                <select wire:model="defaultCommissionType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                                    <option value="percentage">{{ __('Percentage (%) of Sale Total') }}</option>
                                    <option value="fixed">{{ __('Fixed Amount ($) per Sale') }}</option>
                                    <option value="profit_percentage">{{ __('Percentage (%) of Net Profit') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('Rate / Amount') }}</label>
                                <input type="number" step="0.01" min="0" wire:model="defaultCommissionRate" placeholder="0.00" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quotation & Invoice Document Accent Color -->
                <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Quotation & Invoice Document Accent Color') }}</label>
                            <p class="text-[11px] text-slate-400">{{ __('Choose a primary header & table theme color for Quotations and PDF Receipts') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm" style="background-color: {{ $primaryColor }};"></span>
                            <input type="color" wire:model.live="primaryColor" class="w-8 h-8 rounded-lg cursor-pointer border-0 p-0 bg-transparent">
                        </div>
                    </div>

                    <!-- Palette Quick Swatches -->
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        @php
                            $palettes = [
                                '#2d7a58' => __('Forest Green'),
                                '#2563eb' => __('Cobalt Blue'),
                                '#7c3aed' => __('Royal Purple'),
                                '#e11d48' => __('Crimson Red'),
                                '#d97706' => __('Amber Gold'),
                                '#1e293b' => __('Midnight Slate'),
                                '#059669' => __('Emerald Mint'),
                                '#0284c7' => __('Ocean Sky'),
                            ];
                        @endphp
                        @foreach ($palettes as $hex => $label)
                            <button type="button"
                                    wire:click="setQuotationColor('{{ $hex }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-2xs border cursor-pointer"
                                    style="border-color: {{ $hex }}; {{ $primaryColor === $hex ? "background-color: {$hex}; color: #ffffff;" : "color: {$hex}; background-color: transparent;" }}">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $hex }};"></span>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Save Button for Profile & Branding -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    {{ __('Save Profile & Branding') }}
                </button>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">🔑</div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Change Password') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __('Update the password for your own account.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('tenant.settings.change-password') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Current Password') }}</label>
                    <input type="password" name="current_password" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    @error('current_password') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('New Password') }}</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    @error('new_password') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Confirm New Password') }}</label>
                    <input type="password" name="new_password_confirmation" required minlength="6" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-rose-600 hover:bg-rose-700 text-white shadow-lg shadow-rose-500/25 active:scale-95 transition-all cursor-pointer">
                        {{ __('Update Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         TAB 3: RECEIPT PREFIXES & BANK TERMS
         ========================================================================= -->
    <div x-show="activeTab === 'receipts'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Receipt Prefixes & Bank Terms') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Invoice prefixes, default quotation terms, and payment instructions') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Invoice Prefix') }}</label>
                    <input type="text" wire:model="invoicePrefix" placeholder="{{ __('INV-') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quotation Prefix') }}</label>
                    <input type="text" wire:model="quotationPrefix" placeholder="{{ __('QUO-') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Invoice Terms & Conditions (Rich-Text)') }}</label>
                    <x-rich-text-editor wire:model="invoiceTerms" placeholder="{{ __('Enter invoice policy & terms...') }}" height="150" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quote Terms & Notes (Rich-Text)') }}</label>
                    <p class="text-[11px] text-slate-400 mb-1">{{ __('Default terms and notes auto-loaded into new Quotations') }}</p>
                    <x-rich-text-editor wire:model="quoteTerms" placeholder="{{ __('Enter default quotation policy, deliverables & terms...') }}" height="150" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Bank & Payment Details (Rich-Text)') }}</label>
                    <x-rich-text-editor wire:model="bankDetails" placeholder="{{ __('Enter bank accounts & payment instructions...') }}" height="150" />
                </div>
            </div>

            <!-- Save Button for Receipt Prefixes & Bank Terms -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    {{ __('Save Receipt Prefixes & Bank Terms') }}
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 4: FINANCIAL & CURRENCY SETTINGS
         ========================================================================= -->
    <div x-show="activeTab === 'financial'" x-cloak class="space-y-6">
        
        <!-- Currency & Price Formatting -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg">
                    💱
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Currency & Price Formatting') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Base currency code, symbol, decimal precision, and placement used across POS, cart, checkout, and invoices') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Base Currency Code') }}</label>
                    <input type="text" wire:model="currency" maxlength="3" placeholder="{{ __('USD') }}" class="w-full uppercase rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                    @error('currency') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Currency Symbol') }}</label>
                    <input type="text" wire:model="currencySymbol" maxlength="8" placeholder="{{ __('$') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                    @error('currencySymbol') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Decimal Places') }}</label>
                    <select wire:model="currencyDecimals" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500 focus:border-blue-500">
                        <option value="0">0 (e.g. 1,235)</option>
                        <option value="1">1 (e.g. 1,234.5)</option>
                        <option value="2">2 (e.g. 1,234.50)</option>
                        <option value="3">3 (e.g. 1,234.500)</option>
                        <option value="4">4 (e.g. 1,234.5000)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Symbol Placement') }}</label>
                    <select wire:model="currencySymbolPosition" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500 focus:border-blue-500">
                        <option value="prefix">{{ __('Prefix') }} ({{ $currencySymbol ?: '$' }}100.00)</option>
                        <option value="suffix">{{ __('Suffix') }} (100.00{{ $currencySymbol ?: '$' }})</option>
                    </select>
                </div>
            </div>

            <!-- Other Currencies Reference List -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Other Currencies (Reference Rates)') }}</label>
                        <p class="text-[11px] text-slate-400">{{ __('Exchange rates against your base currency, for reporting and multi-currency displays') }}</p>
                    </div>

                    <button type="button" wire:click="addOtherCurrency" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] transition cursor-pointer">
                        + {{ __('Add Currency') }}
                    </button>
                </div>

                @if (empty($otherCurrencies))
                    <p class="text-xs text-slate-400 italic">{{ __('No other currencies configured.') }}</p>
                @else
                    <div class="space-y-2">
                        @foreach ($otherCurrencies as $idx => $oc)
                            <div class="grid grid-cols-12 gap-2 items-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.code" maxlength="10" placeholder="{{ __('EUR') }}" class="col-span-3 text-xs uppercase rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 font-mono font-bold py-1.5 px-2">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.name" placeholder="{{ __('Euro') }}" class="col-span-4 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 py-1.5 px-2">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.symbol" maxlength="8" placeholder="{{ __('€') }}" class="col-span-2 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 py-1.5 px-2">
                                <input type="number" step="0.0001" min="0" wire:model="otherCurrencies.{{ $idx }}.exchange_rate" placeholder="{{ __('Rate') }}" class="col-span-2 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 font-mono py-1.5 px-2">
                                <button type="button" wire:click="removeOtherCurrency({{ $idx }})" class="col-span-1 w-7 h-7 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 hover:bg-rose-100 font-black text-xs transition cursor-pointer justify-self-center">
                                    ✕
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Dynamic Payment Methods (CRUD) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Payment Methods') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('Add, edit, enable or remove custom payment methods for POS checkout') }}</p>
                    </div>
                </div>

                <button type="button"
                        wire:click="openAddPaymentMethodModal"
                        class="px-4 py-2 rounded-2xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>{{ __('Add Method') }}</span>
                </button>
            </div>

            <!-- Payment Methods Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm text-left">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 font-extrabold border-b border-slate-100 dark:border-slate-800 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3">{{ __('Method Name') }}</th>
                            <th class="px-4 py-3">{{ __('Code') }}</th>
                            <th class="px-4 py-3">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @forelse ($paymentMethods as $pm)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $pm->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    <span>{{ $pm->name }}</span>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-slate-500 text-xs">
                                    {{ $pm->code }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-400 text-xs truncate max-w-xs">
                                    {{ $pm->description ?: '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $pm->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $pm->is_active ? __('Active') : __('Disabled') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-2">
                                    <a href="{{ route('tenant.financials.payment_method_ledger', $pm->id) }}"
                                       class="text-slate-500 hover:underline font-bold text-xs cursor-pointer">
                                        {{ __('Ledger') }}
                                    </a>
                                    <button type="button"
                                            wire:click="openEditPaymentMethodModal('{{ $pm->id }}')"
                                            class="text-blue-600 hover:underline font-bold text-xs cursor-pointer">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button"
                                            wire:click="togglePaymentMethodStatus('{{ $pm->id }}')"
                                            class="text-amber-600 hover:underline font-bold text-xs cursor-pointer">
                                        {{ $pm->is_active ? __('Disable') : __('Enable') }}
                                    </button>
                                    <button type="button"
                                            wire:click="deletePaymentMethod('{{ $pm->id }}')"
                                            wire:confirm="{{ __('Are you sure you want to delete payment method') }} '{{ $pm->name }}'?"
                                            class="text-rose-500 hover:underline font-bold text-xs cursor-pointer">
                                        {{ __('Delete') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                    {{ __('No payment methods configured. Click "+ Add Method" to create one.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Save Button for Financial Settings -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    {{ __('Save Financial Settings') }}
                </button>
            </div>
        </div>

        <!-- PIX Gateway & Instant Payment Configuration -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold text-lg">
                    ⚡
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('PIX Instant Payment & QR Code Engine') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Configure PIX Key, merchant recipient details, and EMVCo static QR Code for POS checkout') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PIX Key Type') }}</label>
                    <select wire:model="pixKeyType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                        <option value="cpf_cnpj">{{ __('CPF / CNPJ') }}</option>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="phone">{{ __('Phone (+55...)') }}</option>
                        <option value="random">{{ __('Random Key (EVP)') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PIX Key') }}</label>
                    <input type="text" wire:model="pixKey" placeholder="{{ __('e.g. 12.345.678/0001-90 or finance@store.com') }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account Holder / Store Name') }}</label>
                    <input type="text" wire:model="pixMerchantName" placeholder="{{ $company->name }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account Holder City') }}</label>
                    <input type="text" wire:model="pixMerchantCity" placeholder="{{ __('e.g. SAO PAULO or NEW YORK') }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                </div>
            </div>
        </div>

        <!-- Card Machine Merchant Fees Matrix (Card Processing Fees) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
                    💳
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Card Processing Fees') }}</h3>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">{{ __('Card Machine Merchant Fees') }}</h4>
                    <p class="text-xs text-slate-400">{{ __('Configure automatic fee deductions and net receivable calculations for card transactions.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Standard Debit Fee (%)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="cardFeeDebit" placeholder="1.50"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Credit 1x / Single Installment Fee (%)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="cardFeeCredit1x" placeholder="3.20"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Credit Multi-Installments (2x–12x) Base Fee (%)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="cardFeeCreditInstallments" placeholder="4.50"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
                </div>
            </div>
        </div>

        <!-- Scale Barcode Protocol (Scale Barcode Integration) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-lg">
                    ⚖️
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Scale Barcode Integration') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Toledo, Filizola, Elgin and standard EAN-13 price/weight embedded barcode integration') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Scale Prefix Digit') }}</label>
                    <input type="text" wire:model="barcodeScalePrefix" maxlength="2" placeholder="2"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Embedded Data Format') }}</label>
                    <select wire:model="barcodeScaleType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                        <option value="weight">{{ __('Weight (Weight in Grams - e.g. 2 + CCCCC + WWWWW + D)') }}</option>
                        <option value="price">{{ __('Total Price (Price in Cents - e.g. 2 + CCCCC + VVVVV + D)') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Automated & Local Database Backup Download Card -->
        <div class="bg-slate-900 text-white rounded-3xl p-6 sm:p-7 border border-blue-900/50 shadow-lg space-y-5">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/20 text-blue-300 flex items-center justify-center font-bold text-2xl">
                        💾
                    </div>
                    <div>
                        <h3 class="text-base font-black tracking-tight">{{ __('Full Local Store Database Backup') }}</h3>
                        <p class="text-xs text-blue-200/70 mt-0.5">{{ __('Download an encrypted complete snapshot of your store products, customers, sales history, and settings to your computer.') }}</p>
                    </div>
                </div>

                <a href="{{ route('tenant.settings.backup.download') }}"
                   class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-black text-xs shadow-lg shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span>{{ __('Download Local Backup (.json / .sql)') }}</span>
                </a>
            </div>

            <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 text-xs text-blue-200/80 space-y-1">
                <div class="font-bold text-white flex items-center gap-1.5">
                    <span>🕒</span> {{ __('Automated Daily Backups Active') }}
                </div>
                <p class="text-[11px] leading-relaxed">
                    {{ __('Your store database is automatically secured and archived daily. You can download a standalone offsite backup copy to your local machine anytime with 1 click.') }}
                </p>
            </div>
        </div>
    </div>


    <!-- =========================================================================
         TAB 8: NAVIGATION MENU (item-level drag-and-drop customization)
         ========================================================================= -->
    <div x-show="activeTab === 'navigation'" x-cloak class="space-y-4"
         x-data="tenantNavigationBuilder(
             @js($navSections),
             @js(route('tenant.settings.navigation-menu.store')),
             @js(csrf_token()),
             @js([
                 'level0' => __('Main Menu'),
                 'level1' => __('Sub-Menu'),
                 'level2' => __('Sub-Sub-Menu'),
                 'saved' => __('Navigation menu updated.'),
                 'saveError' => __('The navigation menu could not be saved.'),
             ])
         )">
        <style>
            #nav-sections-container .nav-items-container {
                overflow-x: clip;
            }

            #nav-sections-container .nav-tree-item {
                --nav-indent: 0px;
                box-sizing: border-box;
                margin-left: var(--nav-indent);
                width: calc(100% - var(--nav-indent));
                min-width: 0;
                transition: margin-left 120ms ease, width 120ms ease;
            }

            #nav-sections-container .nav-item-row {
                box-sizing: border-box;
                min-height: 38px;
                border: 1px solid transparent;
                transition: border-color 120ms ease, background-color 120ms ease, box-shadow 120ms ease;
            }

            #nav-sections-container .nav-tree-item[data-nav-level="1"] > .nav-item-row,
            #nav-sections-container .nav-tree-item[data-nav-level="2"] > .nav-item-row {
                border-left-color: rgb(203 213 225);
            }

            .dark #nav-sections-container .nav-tree-item[data-nav-level="1"] > .nav-item-row,
            .dark #nav-sections-container .nav-tree-item[data-nav-level="2"] > .nav-item-row {
                border-left-color: rgb(71 85 105);
            }

            #nav-sections-container .nav-depth-guide {
                display: none;
            }

            #nav-sections-container .nav-depth-preview > .nav-item-row .nav-depth-guide {
                display: inline-flex;
            }

            #nav-sections-container .nav-drop-placeholder > .nav-item-row {
                border: 2px dashed rgb(59 130 246);
                background: rgb(239 246 255);
                box-shadow: 0 0 0 3px rgb(59 130 246 / 12%);
            }

            .dark #nav-sections-container .nav-drop-placeholder > .nav-item-row {
                border-color: rgb(96 165 250);
                background: rgb(30 58 138 / 24%);
            }

            #nav-sections-container .nav-item-chosen > .nav-item-row {
                cursor: grabbing;
            }

            #nav-sections-container .nav-section-placeholder {
                border-color: rgb(59 130 246);
                background: rgb(239 246 255 / 70%);
            }

            @media (prefers-reduced-motion: reduce) {
                #nav-sections-container .nav-tree-item,
                #nav-sections-container .nav-item-row {
                    transition: none;
                }
            }
        </style>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <h3 class="text-sm font-black text-slate-800 dark:text-slate-100">{{ __('Navigation Menu') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Drag vertically to reorder or move a destination. While dragging, move right or left to snap it between Main Menu, Sub-Menu, and Sub-Sub-Menu levels. Each step is 32 px; the first item always remains a Main Menu item. Changes apply to the web sidebar and mobile drawer.') }}
            </p>

            <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 p-3 overflow-hidden" aria-label="{{ __('Navigation indentation guide') }}">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-2">{{ __('Indent guide') }}</div>
                <div class="space-y-1.5 text-[10px] font-bold">
                    <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                        <span class="h-px w-4 bg-slate-400"></span>
                        <span>{{ __('Main Menu') }} · 0 px</span>
                    </div>
                    <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300" style="margin-left: 32px">
                        <span class="h-px w-4 bg-blue-400"></span>
                        <span>{{ __('Sub-Menu') }} · 32 px</span>
                    </div>
                    <div class="flex items-center gap-2 text-violet-700 dark:text-violet-300" style="margin-left: 64px">
                        <span class="h-px w-4 bg-violet-400"></span>
                        <span>{{ __('Sub-Sub-Menu') }} · 64 px</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="nav-sections-container" class="space-y-3">
            <template x-for="section in sections" :key="section.key">
                <div :data-section-key="section.key" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400" x-text="section.label"></span>
                        <button type="button"
                                class="nav-section-drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 p-1"
                                title="{{ __('Drag to reorder this section') }}"
                                aria-label="{{ __('Drag to reorder this section') }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 100 2 1 1 0 000-2zM7 9a1 1 0 100 2 1 1 0 000-2zM7 14a1 1 0 100 2 1 1 0 000-2zM13 4a1 1 0 100 2 1 1 0 000-2zM13 9a1 1 0 100 2 1 1 0 000-2zM13 14a1 1 0 100 2 1 1 0 000-2z"/></svg>
                        </button>
                    </div>

                    <div class="nav-items-container space-y-1 min-h-[44px] border-l border-transparent">
                        <template x-for="item in flattenedItems(section)" :key="section.key + ':' + item.key">
                            <div class="nav-tree-item"
                                 :data-item-key="item.key"
                                 :data-nav-level="item.level"
                                 :style="rowStyle(item.level)">
                                <div class="nav-item-row flex items-center gap-2 px-2 py-1.5 rounded-xl bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                                    <span class="nav-depth-guide shrink-0 rounded-md bg-blue-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-white"
                                          aria-live="polite"></span>
                                    <input type="checkbox"
                                           x-model="item.visible"
                                           class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500">
                                    <span class="flex-1 min-w-0 truncate text-xs font-semibold text-slate-700 dark:text-slate-300" x-text="item.label"></span>
                                    <span class="shrink-0 rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[9px] font-extrabold text-slate-500 dark:text-slate-400"
                                          x-text="levelLabel(item.level)"></span>
                                    <button type="button"
                                            class="nav-item-drag-handle shrink-0 cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 p-1"
                                            style="touch-action: none"
                                            @pointerdown="rememberPointer($event)"
                                            title="{{ __('Drag vertically to reorder and horizontally to change menu level') }}"
                                            aria-label="{{ __('Drag vertically to reorder and horizontally to change menu level') }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 100 2 1 1 0 000-2zM7 9a1 1 0 100 2 1 1 0 000-2zM7 14a1 1 0 100 2 1 1 0 000-2zM13 4a1 1 0 100 2 1 1 0 000-2zM13 9a1 1 0 100 2 1 1 0 000-2zM13 14a1 1 0 100 2 1 1 0 000-2z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex justify-end pt-2">
            <button type="button" @click="save()" :disabled="saving" class="px-5 py-2.5 rounded-xl text-xs font-extrabold bg-blue-600 text-white shadow-sm cursor-pointer disabled:opacity-50">
                <span x-show="!saving">{{ __('Save Navigation Menu') }}</span>
                <span x-show="saving">{{ __('Saving...') }}</span>
            </button>
        </div>
    </div>

    <!-- TAB 5: TAXES & COMPLIANCE -->
    @include('livewire.tenant.settings.partials.taxes-tab')

    <!-- TAB 6: DEVELOPER API & INTEGRATIONS -->
    @include('livewire.tenant.settings.partials.api-tab')

    <!-- Unified Bottom Save Action Bar -->
    <div class="flex justify-end items-center pt-2">
        <button wire:click="save"
                type="button"
                class="px-8 py-3.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
            {{ __('Save All Settings') }}
        </button>
    </div>

    <!-- Add / Edit Payment Method Modal -->
    @if ($showPaymentMethodModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                        {{ $editingPaymentMethodId ? __('Edit Payment Method') : __('Add New Payment Method') }}
                    </h3>
                    <button type="button" wire:click="$set('showPaymentMethodModal', false)" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Method Name *') }}</label>
                        <input type="text" wire:model="pmName" placeholder="{{ __('e.g. Credit Card, UPI, PIX, Stripe') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        @error('pmName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Code / Key (Optional)') }}</label>
                        <input type="text" wire:model="pmCode" placeholder="{{ __('e.g. card, upi, pix, stripe') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Description (Optional)') }}</label>
                        <input type="text" wire:model="pmDescription" placeholder="{{ __('e.g. Pay via terminal or contactless') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                            <input type="checkbox" wire:model="pmIsActive" class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>{{ __('Enable this payment method for POS checkout') }}</span>
                        </label>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-2">
                            {{ __('Bank / UPI Details (shown at checkout when this method is selected)') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" wire:model="pmBankName" placeholder="{{ __('Bank Name') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            <input type="text" wire:model="pmHolderName" placeholder="{{ __('Account Holder') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            <input type="text" wire:model="pmAccountNo" placeholder="{{ __('Account No.') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            <input type="text" wire:model="pmIfscCode" placeholder="{{ __('IFSC Code') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            <input type="text" wire:model="pmUpiId" placeholder="{{ __('UPI ID') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 col-span-2">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showPaymentMethodModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 cursor-pointer">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="savePaymentMethod" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-blue-600 text-white shadow-sm cursor-pointer">{{ __('Save Method') }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Tax Rule Create/Edit Modal -->
    @include('livewire.tenant.settings.partials.tax-rule-modal')

    <!-- API Key Generate Modal -->
    @include('livewire.tenant.settings.partials.api-key-modal')

    <script>
        (function() {
            function autoCenterSettingsTab(tabElement) {
                if (!tabElement) return;
                tabElement.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }

            // Global event listener for settings sub-nav tabs
            document.addEventListener('click', (e) => {
                const tab = e.target.closest('.settings-subnav-container button, .settings-subnav-container a');
                if (tab) {
                    autoCenterSettingsTab(tab);
                }
            });

            function syncActiveTabFocus() {
                const activeTab = document.querySelector('.settings-subnav-container button[aria-selected="true"], .settings-subnav-container [aria-selected="true"], .settings-subnav-container button.active');
                if (activeTab) {
                    autoCenterSettingsTab(activeTab);
                }
            }

            // Auto-center active tab on initial page load / Livewire navigation
            document.addEventListener('DOMContentLoaded', () => setTimeout(syncActiveTabFocus, 100));
            document.addEventListener('livewire:navigated', () => setTimeout(syncActiveTabFocus, 100));
            window.addEventListener('hashchange', () => setTimeout(syncActiveTabFocus, 50));
        })();
    </script>
</div>
