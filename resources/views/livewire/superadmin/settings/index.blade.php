<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header Banner & Quick Actions -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="space-y-2 relative z-10">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-xs font-black">
                    ⚙️
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        {{ __('Platform & System Settings') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium">
                        {{ __('Centralized management for platform settings, push gateways, email, branding, pages, and appearance.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 relative z-10 shrink-0">
            <button type="button"
                    wire:click="clearSystemCache"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-xs">
                <span wire:loading.remove wire:target="clearSystemCache">🧹</span>
                <span wire:loading wire:target="clearSystemCache" class="animate-spin">⏳</span>
                <span>{{ __('Clear Cache') }}</span>
            </button>
            
            <a wire:navigate.hover href="{{ route('superadmin.dashboard') }}"
               class="px-4 py-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 text-indigo-600 dark:text-indigo-400 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs border border-indigo-200/60 dark:border-indigo-800/50">
                <span>📊</span>
                <span>{{ __('Dashboard') }}</span>
            </a>
        </div>
    </div>

    <!-- Reactive URL-Synced Alpine Sub-Tab Manager -->
    <div x-data="{ 
        activeTab: (function() {
            const searchParam = new URLSearchParams(window.location.search).get('tab');
            const hashParam = window.location.hash ? window.location.hash.replace('#', '') : null;
            let tab = searchParam || hashParam || 'general';
            if (tab === 'branding') tab = 'whitelabel';
            return tab;
        })(),
        switchTab(tab) {
            this.activeTab = tab;
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            url.hash = '';
            window.history.replaceState({}, '', url);
        },
        init() {
            const syncTabFromUrl = () => {
                const searchParam = new URLSearchParams(window.location.search).get('tab');
                const hashParam = window.location.hash ? window.location.hash.replace('#', '') : null;
                let tab = searchParam || hashParam || 'general';
                if (tab === 'branding') tab = 'whitelabel';
                this.activeTab = tab;
            };
            window.addEventListener('popstate', syncTabFromUrl);
            window.addEventListener('turbo:load', syncTabFromUrl);
            window.addEventListener('livewire:navigated', syncTabFromUrl);
            window.addEventListener('spa:page-loaded', syncTabFromUrl);
        }
    }" class="space-y-6">

        <!-- Horizontal Sub-Tabs Bar -->
        <div class="flex items-center gap-2 p-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-x-auto no-scrollbar tab-scroll-container">
            <button type="button" 
                    @click="switchTab('general')"
                    :class="activeTab === 'general' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>⚙️</span> {{ __('General & Platform') }}
            </button>

            <button type="button" 
                    @click="switchTab('smtp')"
                    :class="activeTab === 'smtp' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>✉️</span> {{ __('SMTP & Email') }}
            </button>

            <button type="button"
                    @click="switchTab('push')"
                    :class="activeTab === 'push' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>🔔</span> {{ __('Push Notifications') }}
            </button>

            <button type="button" 
                    @click="switchTab('whitelabel')"
                    :class="(activeTab === 'whitelabel' || activeTab === 'branding') ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>🖌️</span> {{ __('White-label & Branding') }}
            </button>

            <button type="button" 
                    @click="switchTab('pages')"
                    :class="activeTab === 'pages' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>📄</span> {{ __('Custom Pages & CMS') }}
            </button>

            <button type="button"
                    @click="switchTab('social')"
                    :class="activeTab === 'social' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>🔐</span> {{ __('Social Login') }}
            </button>

            <button type="button"
                    @click="switchTab('appearance')"
                    :class="activeTab === 'appearance' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>🎨</span> {{ __('Navigation & Appearance') }}
            </button>

            <button type="button"
                    @click="switchTab('licensing')"
                    :class="activeTab === 'licensing' ? 'bg-blue-600 text-white shadow-sm font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition whitespace-nowrap shrink-0 cursor-pointer">
                <span>🔑</span> {{ __('Licensing') }}
            </button>
        </div>

        <!-- Tab Panes -->
        <div x-show="activeTab === 'general'" x-cloak> @include('superadmin.settings.partials.general') </div>
        <div x-show="activeTab === 'smtp'" x-cloak> @include('superadmin.settings.partials.smtp') </div>
        <div x-show="activeTab === 'push'" x-cloak> @include('superadmin.settings.partials.push-notifications') </div>
        <div x-show="activeTab === 'whitelabel' || activeTab === 'branding'" x-cloak> @include('superadmin.settings.partials.whitelabel') </div>
        <div x-show="activeTab === 'pages'" x-cloak> @include('superadmin.settings.partials.pages') </div>
        <div x-show="activeTab === 'social'" x-cloak> @include('superadmin.settings.partials.social-login') </div>
        <div x-show="activeTab === 'appearance'" x-cloak> @include('superadmin.settings.partials.appearance') </div>
        <div x-show="activeTab === 'licensing'" x-cloak> @include('superadmin.settings.partials.licensing') </div>
    </div>

</div>
