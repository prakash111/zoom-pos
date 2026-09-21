<div class="space-y-6" x-data="{
    subTab: 'landing',
    setSubTab(tab) {
        this.subTab = tab;
    }
}">

    <!-- Sub-Tabs Navigation Strip -->
    <div class="overflow-x-auto no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-1.5 shadow-sm border border-slate-200/80 dark:border-slate-800 flex items-center gap-1.5 min-w-max sm:min-w-0 sm:w-full">
            <button type="button" @click="setSubTab('landing')"
                    :class="subTab === 'landing' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold'"
                    class="shrink-0 sm:flex-1 py-2 sm:py-2.5 px-3.5 sm:px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer">
                <span>🌐</span>
                <span>{{ __('1. Homepage & Permalinks') }}</span>
            </button>

            <button type="button" @click="setSubTab('menus')"
                    :class="subTab === 'menus' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold'"
                    class="shrink-0 sm:flex-1 py-2 sm:py-2.5 px-3.5 sm:px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer">
                <span>🧭</span>
                <span>{{ __('2. Navigation Menu Items') }}</span>
            </button>

            <button type="button" @click="setSubTab('identity')"
                    :class="subTab === 'identity' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold'"
                    class="shrink-0 sm:flex-1 py-2 sm:py-2.5 px-3.5 sm:px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer">
                <span>🎨</span>
                <span>{{ __('3. Brand Identity & White-label') }}</span>
            </button>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SUB-TAB 1: HOMEPAGE & PERMALINK SETUP                                 -->
    <!-- ===================================================================== -->
    <div x-show="subTab === 'landing'" x-cloak class="space-y-6">

        <!-- Master Switch: Landing Page Enabled -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-4 sm:space-y-0 sm:flex sm:items-center sm:justify-between sm:gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl shrink-0">⚡</span>
                        <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">
                            {{ __('Public Landing Page Access') }}
                        </h2>
                    </div>
                    @if ($landingPageEnabled)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{{ __('Online at /') }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            <span>{{ __('Redirect to Login') }}</span>
                        </span>
                    @endif
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium max-w-2xl leading-relaxed">
                    {{ __('When enabled, root domain visitors at "/" will see your configured landing page. When disabled, visitors are automatically redirected to the tenant login page.') }}
                </p>
            </div>

            <div class="flex items-center justify-between sm:justify-end pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-800">
                <span class="sm:hidden text-xs font-bold text-slate-600 dark:text-slate-400">
                    {{ $landingPageEnabled ? __('Public Status: Active') : __('Public Status: Redirecting') }}
                </span>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" wire:model.live="landingPageEnabled" class="sr-only peer">
                    <div class="w-14 h-8 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:bg-slate-800 peer-checked:bg-emerald-600"></div>
                </label>
            </div>
        </div>

        <!-- Homepage Mode Picker -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📌</span> {{ __('Homepage Display Mode') }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    {{ __('Choose whether root domain ("/") serves your modular SaaS landing page or a dedicated static CMS page with live permalinks.') }}
                </p>
            </div>

            <!-- Two Mode Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Option 1: Modular SaaS Theme -->
                <div wire:click="setHomepageMode('modular')"
                     class="p-5 rounded-2xl border-2 transition cursor-pointer relative {{ $homepageMode === 'modular' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/20 shadow-md shadow-indigo-500/10' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-lg font-black shrink-0">
                                🚀
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Modular SaaS Theme') }}</h4>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('High-converting multi-section landing page') }}</span>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center {{ $homepageMode === 'modular' ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 dark:border-slate-700' }}">
                            @if ($homepageMode === 'modular')
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ __('Displays a full SaaS landing page with customizable hero banner, hardware bar, dynamic feature cards, pricing tiers, FAQs, and app downloads.') }}
                    </p>
                </div>

                <!-- Option 2: Static CMS Page (Permalink) -->
                <div wire:click="setHomepageMode('static_page')"
                     class="p-5 rounded-2xl border-2 transition cursor-pointer relative {{ $homepageMode === 'static_page' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/20 shadow-md shadow-indigo-500/10' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-lg font-black shrink-0">
                                🔗
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Static CMS Page (Permalink)') }}</h4>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Serve a rich TinyMCE custom page at root') }}</span>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center {{ $homepageMode === 'static_page' ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 dark:border-slate-700' }}">
                            @if ($homepageMode === 'static_page')
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ __('Serve one of your rich TinyMCE custom pages directly at "/" with clean permalinks, direct TinyMCE editing, and status monitoring.') }}
                    </p>
                </div>
            </div>

            <!-- STATIC CMS PERMALINK CONFIGURATION -->
            @if ($homepageMode === 'static_page')
                <div class="pt-5 border-t border-slate-100 dark:border-slate-800 space-y-5 animate-in fade-in">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Select CMS Page for Homepage *') }}
                            </label>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Choose the page that will be rendered at the root URL (https://saas.zoomnearby.com/).') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a wire:navigate.hover href="{{ route('superadmin.pages.create') }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 shadow-sm">
                                <span>➕</span>
                                <span>{{ __('Create New Page') }}</span>
                            </a>
                            <a wire:navigate.hover href="{{ route('superadmin.pages.index') }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition flex items-center gap-1.5">
                                <span>📑</span>
                                <span>{{ __('All CMS Pages') }}</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <select wire:model.live="landingPageId" class="w-full px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold text-slate-900 dark:text-white focus:ring-indigo-500">
                            <option value="">— {{ __('Select a CMS Page to serve at root URL') }} —</option>
                            @foreach ($availablePages as $p)
                                <option value="{{ $p->id }}">
                                    {{ $p->title }} (slug: /page/{{ $p->slug }}) [{{ $p->is_active ? '✓ Published' : '⚠ Draft' }}]
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($selectedLandingPage)
                        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-indigo-50/20 dark:from-slate-800/60 dark:to-slate-900/60 border border-indigo-100 dark:border-slate-700 space-y-4 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/60 dark:border-slate-700/60 pb-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xl">📄</span>
                                    <div>
                                        <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ $selectedLandingPage->title }}</h4>
                                        <span class="text-[11px] font-mono text-slate-500">slug: {{ $selectedLandingPage->slug }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($selectedLandingPage->is_active)
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ __('Published & Active') }}</span>
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>{{ __('Draft (Activate to display)') }}</span>
                                        </span>
                                    @endif

                                    <a wire:navigate.hover href="{{ route('superadmin.pages.edit', $selectedLandingPage->id) }}"
                                       class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1 shadow-sm">
                                        <span>✏️</span>
                                        <span>{{ __('Edit in TinyMCE') }}</span>
                                    </a>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 text-xs">
                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('Root Homepage Permalink:') }}</span>
                                    <div class="flex items-center justify-between gap-2 font-mono text-indigo-600 dark:text-indigo-400 font-bold truncate">
                                        <span class="truncate">{{ url('/') }}</span>
                                        <a href="{{ url('/') }}" target="_blank" class="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950 hover:bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:text-indigo-300 shrink-0">
                                            {{ __('Test ↗') }}
                                        </a>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('Direct Public Permalink:') }}</span>
                                    <div class="flex items-center justify-between gap-2 font-mono text-emerald-600 dark:text-emerald-400 font-bold truncate">
                                        <span class="truncate">{{ url('/page/' . $selectedLandingPage->slug) }}</span>
                                        <a href="{{ url('/page/' . $selectedLandingPage->slug) }}" target="_blank" class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950 hover:bg-emerald-100 text-[10px] font-bold text-emerald-700 dark:text-emerald-300 shrink-0">
                                            {{ __('Open ↗') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            @if (!empty($selectedLandingPage->content))
                                <div class="pt-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1.5">{{ __('Authored Content Excerpt:') }}</span>
                                    <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 line-clamp-3">
                                        {{ Str::limit(strip_tags($selectedLandingPage->content), 240) }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-6 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/50 text-center space-y-3">
                            <span class="text-2xl">⚠️</span>
                            <h4 class="text-sm font-black text-amber-900 dark:text-amber-200">{{ __('No CMS Page Selected') }}</h4>
                            <p class="text-xs text-amber-700 dark:text-amber-400 max-w-md mx-auto">
                                {{ __('Select an existing page from the dropdown above, or create a brand new custom page with our TinyMCE visual editor.') }}
                            </p>
                            <a wire:navigate.hover href="{{ route('superadmin.pages.create') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white transition shadow-sm">
                                <span>➕</span>
                                <span>{{ __('Create New Page Now') }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            <!-- MODULAR SAAS THEME CONFIGURATION -->
            @if ($homepageMode === 'modular')
                <div class="pt-5 border-t border-slate-100 dark:border-slate-800 space-y-6 animate-in fade-in">
                    <!-- Theme Selector -->
                    <div class="space-y-3">
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Landing Page Theme') }}
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <label wire:click="setLandingTheme('theme_fast')" class="p-4 rounded-2xl border-2 flex items-center justify-between gap-3 cursor-pointer transition {{ $landingTheme === 'theme_fast' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="landingTheme" value="theme_fast" class="text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Fast & Lightweight') }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Clean white / slate modern layout with high speed') }}</span>
                                    </div>
                                </div>
                                <span class="text-base">⚡</span>
                            </label>

                            <label wire:click="setLandingTheme('theme_modern')" class="p-4 rounded-2xl border-2 flex items-center justify-between gap-3 cursor-pointer transition {{ $landingTheme === 'theme_modern' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="landingTheme" value="theme_modern" class="text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Modern Cloud POS') }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Dynamic ambient glow with SaaS gradients') }}</span>
                                    </div>
                                </div>
                                <span class="text-base">🚀</span>
                            </label>

                            <label wire:click="setLandingTheme('theme_enterprise')" class="p-4 rounded-2xl border-2 flex items-center justify-between gap-3 cursor-pointer transition {{ $landingTheme === 'theme_enterprise' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="landingTheme" value="theme_enterprise" class="text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Enterprise Showcase') }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('High-contrast retail structure based on video guide') }}</span>
                                    </div>
                                </div>
                                <span class="text-base">🏪</span>
                            </label>

                            <label wire:click="setLandingTheme('theme_minimal')" class="p-4 rounded-2xl border-2 flex items-center justify-between gap-3 cursor-pointer transition {{ $landingTheme === 'theme_minimal' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="landingTheme" value="theme_minimal" class="text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Minimal Conversion') }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Focused single-page funnel for quick tenant signups') }}</span>
                                    </div>
                                </div>
                                <span class="text-base">⚡</span>
                            </label>

                            <label wire:click="setLandingTheme('theme_dark_studio')" class="p-4 rounded-2xl border-2 flex items-center justify-between gap-3 cursor-pointer transition {{ $landingTheme === 'theme_dark_studio' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="landingTheme" value="theme_dark_studio" class="text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Dark Studio POS') }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Sleek, dark-mode native interface showcase') }}</span>
                                    </div>
                                </div>
                                <span class="text-base">✨</span>
                            </label>
                        </div>
                    </div>

                    <!-- Interactive Modular Sections Studio -->
                    @include('superadmin.settings.partials.modular-sections-studio')
                </div>
            @endif
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SUB-TAB 2: NAVIGATION MENU ITEMS                                      -->
    <!-- ===================================================================== -->
    <div x-show="subTab === 'menus'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🧭</span> {{ __('Navigation Menu Items') }}
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ __('Manage navigation links displayed in the Header navbar and Footer columns with section anchors, CMS pages, and custom links.') }}
                    </p>
                </div>

                <!-- Location Selector Pills -->
                <div class="grid grid-cols-3 sm:flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-full sm:w-auto shrink-0 text-center">
                    <button type="button" wire:click="setMenuLocation('header')"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $menuLocation === 'header' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        {{ __('Header Bar') }}
                    </button>
                    <button type="button" wire:click="setMenuLocation('footer_col_1')"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $menuLocation === 'footer_col_1' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        {{ __('Footer Col 1') }}
                    </button>
                    <button type="button" wire:click="setMenuLocation('footer_col_2')"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $menuLocation === 'footer_col_2' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        {{ __('Footer Col 2') }}
                    </button>
                </div>
            </div>

            <!-- Current Location Description Banner -->
            <div class="p-3.5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 text-xs text-indigo-800 dark:text-indigo-300 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-base">📍</span>
                    <span>
                        @if ($menuLocation === 'header')
                            {{ __('Currently Editing: Top Header Navigation Bar (Public site and landing page header)') }}
                        @elseif ($menuLocation === 'footer_col_1')
                            {{ __('Currently Editing: Footer Column 1 (Features & Solutions list in the footer)') }}
                        @else
                            {{ __('Currently Editing: Footer Column 2 (Company & Legal policies in the footer)') }}
                        @endif
                    </span>
                </div>
                <span class="text-[11px] font-bold font-mono">{{ $currentMenuItems->count() }} {{ __('item(s)') }}</span>
            </div>

            <!-- Quick Add Tools -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                <!-- Tool A: Quick Add Section Anchors (Smooth Scroll) -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-3">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 block flex items-center gap-1.5">
                        <span>⚡</span> {{ __('1-Click Add Section Anchor') }}
                    </span>
                    <p class="text-[11px] text-slate-400">
                        {{ __('Quickly add a smooth-scrolling anchor link targeting a specific section on the landing page:') }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="addAnchorMenuLink('Features', '#features')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #features
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('Solutions', '#solutions')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #solutions
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('Pricing', '#pricing')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #pricing
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('Download', '#download')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #download
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('FAQ', '#faq')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #faq
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('About', '#about')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #about
                        </button>
                        <button type="button" wire:click="addAnchorMenuLink('Contact', '#contact')" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-500 hover:text-indigo-600 transition cursor-pointer">
                            + #contact
                        </button>
                    </div>
                </div>

                <!-- Tool B: Quick Add CMS Page Link -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-3">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 block flex items-center gap-1.5">
                        <span>📄</span> {{ __('1-Click Add CMS Page Link') }}
                    </span>
                    <p class="text-[11px] text-slate-400">
                        {{ __('Choose from your authored CMS pages to insert into this menu:') }}
                    </p>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <select wire:model="newMenuPageId" class="w-full sm:flex-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium">
                            <option value="">— {{ __('Select a CMS Page') }} —</option>
                            @foreach ($availablePages as $ap)
                                <option value="{{ $ap->id }}">{{ $ap->title }} (/page/{{ $ap->slug }})</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addPageMenuLink"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shrink-0 cursor-pointer shadow-sm text-center">
                            + {{ __('Add Page') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Custom URL Add Bar -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
                <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 block flex items-center gap-1.5">
                    <span>🔗</span> {{ __('Add Custom Link / URL') }}
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                    <div class="sm:col-span-4">
                        <input type="text" wire:model="newMenuTitle" placeholder="{{ __('Link Title (e.g. Documentation)') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        @error('newMenuTitle') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-5">
                        <input type="text" wire:model="newMenuUrl" placeholder="{{ __('URL (e.g. https://... or /docs or #pricing)') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono">
                        @error('newMenuUrl') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-3 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer shrink-0">
                            <input type="checkbox" wire:model="newMenuTargetBlank" class="w-4 h-4 rounded text-indigo-600">
                            <span class="text-[11px]">{{ __('New Tab') }}</span>
                        </label>
                        <button type="button" wire:click="addCustomMenuLink"
                                class="px-4 py-2 rounded-xl text-xs font-black bg-indigo-600 hover:bg-indigo-700 text-white transition shrink-0 cursor-pointer shadow-sm">
                            + {{ __('Add Link') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Menu Items List / Table -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">
                        {{ __('Menu Items in') }} {{ ucfirst(str_replace('_', ' ', $menuLocation)) }}
                    </span>
                    <span class="text-[11px] text-slate-400">{{ __('Use arrows to reorder items') }}</span>
                </div>

                @if ($currentMenuItems->isEmpty())
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-800/30 border border-dashed border-slate-200 dark:border-slate-700 text-center space-y-2">
                        <span class="text-2xl">📭</span>
                        <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('No Menu Items Configured Yet') }}</h4>
                        <p class="text-[11px] text-slate-400 max-w-sm mx-auto">
                            {{ __('Use the quick-add buttons above to add section anchors, CMS pages, or custom URLs to this navigation location.') }}
                        </p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($currentMenuItems as $item)
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-slate-700 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                                
                                @if ($editingMenuItemId === $item->id)
                                    <!-- Inline Edit Form -->
                                    <div class="w-full grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                        <div class="sm:col-span-4">
                                            <input type="text" wire:model="editingMenuItemTitle" class="w-full px-3 py-1.5 rounded-xl border border-indigo-300 dark:border-indigo-600 text-xs font-bold">
                                        </div>
                                        <div class="sm:col-span-4">
                                            <input type="text" wire:model="editingMenuItemUrl" class="w-full px-3 py-1.5 rounded-xl border border-indigo-300 dark:border-indigo-600 text-xs font-mono">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <select wire:model="editingMenuItemTarget" class="w-full px-2 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                                                <option value="_self">{{ __('Same Tab') }}</option>
                                                <option value="_blank">{{ __('New Tab') }}</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2 flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="saveMenuItem" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-xs">
                                                ✓ {{ __('Save') }}
                                            </button>
                                            <button type="button" wire:click="cancelEditMenuItem" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 cursor-pointer">
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <!-- Display Item View -->
                                    <div class="flex items-center gap-3 min-w-0">
                                        <!-- Reorder Controls -->
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button type="button" wire:click="moveMenuItemUp({{ $item->id }})" title="{{ __('Move Up') }}"
                                                    class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-xs font-black cursor-pointer">
                                                ⬆
                                            </button>
                                            <button type="button" wire:click="moveMenuItemDown({{ $item->id }})" title="{{ __('Move Down') }}"
                                                    class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-xs font-black cursor-pointer">
                                                ⬇
                                            </button>
                                        </div>

                                        <!-- Item Title & URL -->
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-black text-slate-900 dark:text-white truncate">{{ $item->title }}</span>
                                                
                                                @if (str_starts_with($item->url, '#'))
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                                        {{ __('Anchor') }}
                                                    </span>
                                                @elseif ($item->page_id)
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                                        {{ __('CMS Page') }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ __('Link') }}
                                                    </span>
                                                @endif

                                                @if ($item->target === '_blank')
                                                    <span class="text-[10px] font-mono text-slate-400">↗ {{ __('new tab') }}</span>
                                                @endif
                                            </div>
                                            <a href="{{ url($item->url) }}" target="_blank" class="text-[11px] font-mono text-slate-400 hover:text-indigo-600 truncate block">
                                                {{ $item->url }}
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Actions: Toggle Active, Edit, Delete -->
                                    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0 self-end sm:self-center">
                                        <button type="button" wire:click="toggleMenuItemActive({{ $item->id }})"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer {{ $item->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                            {{ $item->is_active ? __('Active') : __('Hidden') }}
                                        </button>

                                        <button type="button" wire:click="editMenuItem({{ $item->id }})"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                                            ✏️ {{ __('Edit') }}
                                        </button>

                                        <button type="button" wire:click="deleteMenuItem({{ $item->id }})" wire:confirm="{{ __('Delete this menu item?') }}"
                                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 transition cursor-pointer">
                                            🗑️
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SUB-TAB 3: BRAND IDENTITY & WHITE-LABEL                               -->
    <!-- ===================================================================== -->
    <div x-show="subTab === 'identity'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🎨</span> {{ __('Platform Identity & Logos') }} &mdash; {{ __('White-label Branding') }}
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    {{ __('Customize platform brand name, logos, dynamic color tokens, registration controls, and support contact.') }}
                </p>
            </div>

            <!-- Platform Name, Logo & Favicon -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Platform Brand Name *') }}</label>
                    <input type="text" wire:model="platformName" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold">
                    @error('platformName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Platform Logo') }}</label>
                        @if ($logoImage)
                            <span class="text-[10px] font-black text-amber-500">{{ __('Staged') }}</span>
                        @elseif ($logoUrl)
                            <button type="button" wire:click="removeLogo" class="text-[10px] text-rose-500 font-bold hover:underline">✕ {{ __('Remove') }}</button>
                        @endif
                    </div>
                    <input type="text" wire:model="logoUrl" placeholder="https://example.com/logo.png" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium">
                    <input type="file" wire:model="logoImage" accept="image/*" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Favicon URL') }}</label>
                    <input type="text" wire:model="faviconUrl" placeholder="https://example.com/favicon.ico" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                </div>
            </div>

            <!-- Dynamic Color Palette Studio -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-3">{{ __('Dynamic Color Studio') }}</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __('SuperAdmin Sidebar') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="superadminSidebarColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="superadminSidebarColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __('Landing Primary Emerald') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="landingPrimaryColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="landingPrimaryColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __('Landing Neon Lime Accent') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="landingAccentColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="landingAccentColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __('Tenant Primary Fallback') }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="primaryColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="primaryColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auth & Domain Registration Controls -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-2">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <span>🌐</span> {{ __('Subdomain & Custom Domain on Registration') }}
                        </span>
                        <input type="checkbox" wire:model="enableRegistrationDomainSetup" class="w-5 h-5 rounded text-indigo-600">
                    </label>
                    <p class="text-[11px] text-slate-400">{{ __('Allow stores to pick their custom subdomain during registration.') }}</p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-2">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <span>🔒</span> {{ __('Require Email OTP on Registration') }}
                        </span>
                        <input type="checkbox" wire:model="otpRegistrationEnabled" class="w-5 h-5 rounded text-indigo-600">
                    </label>
                    <p class="text-[11px] text-slate-400">{{ __('Verify registrant identity via 6-digit email OTP before access is granted.') }}</p>
                </div>
            </div>

            <!-- Auth Graphic Banner -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">{{ __('Auth Screens Banner Graphic') }}</h4>
                        <p class="text-[11px] text-slate-400">{{ __('Promotional graphic displayed on login and register screens.') }}</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="showAuthBanner" class="w-4 h-4 rounded text-indigo-600">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Show Graphic Banner') }}</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Banner Image URL') }}</label>
                        <input type="text" wire:model="authBannerImageUrl" placeholder="https://example.com/banner.png" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Upload Banner File') }}</label>
                            @if ($authBannerImageUrl || $authBannerImage)
                                <button type="button" wire:click="removeAuthBanner" class="text-[10px] text-rose-500 font-bold hover:underline">✕ {{ __('Remove') }}</button>
                            @endif
                        </div>
                        <input type="file" wire:model="authBannerImage" accept="image/*" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-indigo-50 file:text-indigo-600">
                    </div>
                </div>
            </div>

            <!-- Support Contact Details -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Public Support Email') }}</label>
                    <input type="email" wire:model="supportEmail" placeholder="support@yourdomain.com" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Public Support Phone / WhatsApp') }}</label>
                    <input type="text" wire:model="supportPhone" placeholder="+1 (555) 000-0000" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Head Office Physical Address') }}</label>
                    <input type="text" wire:model="headOfficeAddress" placeholder="Metrotech Center, NY 11201" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Working Hours') }}</label>
                    <input type="text" wire:model="workingHours" placeholder="Monday - Friday (07 am - 05 pm)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>

        </div>
    </div>

    <!-- Sticky Bottom Save Bar for White-label & Branding -->
    <div class="sticky bottom-4 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div class="flex items-center justify-between sm:justify-start gap-2 sm:gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold text-[10px] sm:text-[11px]">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ __('Live Cache Auto-Purged') }}</span>
            </span>
            <span class="text-[11px] text-slate-400">
                <span class="hidden sm:inline">&middot; {{ __('Mode') }}: </span>
                <strong class="text-slate-700 dark:text-slate-200">{{ $homepageMode === 'static_page' ? __('CMS Permalink') : __('Modular Theme') }}</strong>
            </span>
        </div>

        <div class="grid grid-cols-2 sm:flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <a href="{{ url('/') }}" target="_blank" class="h-11 sm:h-auto px-3 sm:px-4 py-2.5 rounded-xl sm:rounded-2xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition inline-flex items-center justify-center gap-1 cursor-pointer">
                <span>👁️ {{ __('Preview') }}</span>
                <span>↗</span>
            </a>

            <button type="button" wire:click="saveBranding" wire:loading.attr="disabled"
                    class="h-11 sm:h-auto px-4 sm:px-8 py-2.5 sm:py-3 rounded-xl sm:rounded-2xl text-xs sm:text-sm font-black bg-indigo-600 hover:bg-indigo-700 text-white shadow-xl shadow-indigo-500/25 active:scale-95 transition flex items-center justify-center gap-1.5 cursor-pointer">
                <span wire:loading.remove wire:target="saveBranding">💾 {{ __('Save Branding') }}</span>
                <span wire:loading wire:target="saveBranding" class="flex items-center gap-1.5">
                    <span class="animate-spin">⏳</span>
                    <span>{{ __('Saving...') }}</span>
                </span>
            </button>
        </div>
    </div>

</div>
