<div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-8"
     x-data="{
         defaults: @js(appearance_defaults()),
         layout: localStorage.getItem('nav_layout') || @js(appearance_defaults()['layout']),
         position: localStorage.getItem('nav_position') || @js(appearance_defaults()['position']),
         mode: localStorage.getItem('nav_mode') || @js(appearance_defaults()['mode']),
         theme: localStorage.getItem('nav_theme') || 'violet',
         sticky: localStorage.getItem('nav_sticky') === 'true',
         customBg: localStorage.getItem('nav_custom_bg') || @js(appearance_defaults()['customBg']),
         uiAccentColor: localStorage.getItem('ui_accent_color') || @js(appearance_defaults()['uiAccentColor']),
         navTextColor: localStorage.getItem('nav_text_color') || @js(appearance_defaults()['navTextColor']),
         navTextActiveColor: localStorage.getItem('nav_text_active_color') || @js(appearance_defaults()['navTextActiveColor']),
        visibleItems: (function() {
            try {
                var raw = localStorage.getItem('nav_visible_items');
                return raw ? JSON.parse(raw) : @js(appearance_defaults()['visibleItems']);
            } catch(e) {
                return @js(appearance_defaults()['visibleItems']);
            }
        })(),
        availableDockItems: [
             { key: 'dashboard', label: '{{ __('Dashboard Overview') }}', icon: '📊' },
             { key: 'tenants', label: '{{ __('Tenant Stores') }}', icon: '🏢' },
             { key: 'plans', label: '{{ __('SaaS Plans & Pricing') }}', icon: '👑' },
             { key: 'taxes', label: '{{ __('Global Tax Engine') }}', icon: '⚖️' },
             { key: 'menus', label: '{{ __('Menu Builder') }}', icon: '🧭' },
             { key: 'pages', label: '{{ __('CMS Custom Pages') }}', icon: '📄' },
             { key: 'settings', label: '{{ __('Platform Settings') }}', icon: '⚙️' },
             { key: 'smtp', label: '{{ __('SMTP & Mail Config') }}', icon: '✉️' }
        ],
        adminDockItems: [
             { key: 'dashboard', label: '{{ __('Dashboard Overview') }}', icon: '📊' },
             { key: 'tenants', label: '{{ __('Tenant Stores') }}', icon: '🏢' },
             { key: 'plans', label: '{{ __('SaaS Plans & Pricing') }}', icon: '👑' },
             { key: 'taxes', label: '{{ __('Global Tax Engine') }}', icon: '⚖️' },
             { key: 'menus', label: '{{ __('Menu Builder') }}', icon: '🧭' },
             { key: 'pages', label: '{{ __('CMS Custom Pages') }}', icon: '📄' },
             { key: 'settings', label: '{{ __('Platform Settings') }}', icon: '⚙️' },
             { key: 'smtp', label: '{{ __('SMTP & Mail Config') }}', icon: '✉️' }
        ],
        visibleAdminItems: (function() {
            try {
                var raw = localStorage.getItem('nav_visible_items');
                return raw ? JSON.parse(raw) : ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'];
            } catch(e) {
                return ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'];
            }
        })(),
        selectAllAdminItems() {
            this.visibleAdminItems = this.adminDockItems.map(i => i.key);
            this.persistAdminDock();
        },
        resetAdminDefaultItems() {
            this.visibleAdminItems = ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
            this.persistAdminDock();
        },
        persistAdminDock() {
            localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleAdminItems));
            this.visibleItems = [...this.visibleAdminItems];
            window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleAdminItems, visibleAdminItems: this.visibleAdminItems } }));
        },
        setLayout(v) {
             this.layout = v;
             localStorage.setItem('nav_layout', v);
             document.documentElement.setAttribute('data-nav-layout', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { layout: v } }));
         },
         setPosition(v) {
             this.position = v;
             this.mode = 'docked';
             localStorage.setItem('nav_position', v);
             localStorage.setItem('nav_mode', 'docked');
             document.documentElement.setAttribute('data-dock-pos', v);
             document.documentElement.setAttribute('data-dock-mode', 'docked');
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { position: v, mode: 'docked' } }));
         },
         setMode(v) {
             this.mode = v;
             if (v === 'floating') {
                 this.position = 'floating';
                 localStorage.setItem('nav_position', 'floating');
                 document.documentElement.setAttribute('data-dock-pos', 'floating');
             }
             localStorage.setItem('nav_mode', v);
             document.documentElement.setAttribute('data-dock-mode', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { mode: v, position: this.position } }));
         },
         setCustomBg(v) {
             this.customBg = v;
             localStorage.setItem('nav_custom_bg', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { customBg: v } }));
         },
         setUiAccentColor(v) {
             this.uiAccentColor = v;
             localStorage.setItem('ui_accent_color', v);
             document.documentElement.style.setProperty('--sa-primary-color', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { uiAccentColor: v } }));
         },
         setNavTextColor(v) {
             this.navTextColor = v;
             localStorage.setItem('nav_text_color', v);
             document.documentElement.style.setProperty('--nav-item-color', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { navTextColor: v } }));
         },
         setNavTextActiveColor(v) {
             this.navTextActiveColor = v;
             localStorage.setItem('nav_text_active_color', v);
             document.documentElement.style.setProperty('--nav-item-active-color', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { navTextActiveColor: v } }));
         },
         toggleItem(key) {
             if (this.visibleItems.includes(key)) {
                 this.visibleItems = this.visibleItems.filter(k => k !== key);
             } else {
                 this.visibleItems.push(key);
             }
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems } }));
         },
         isItemVisible(key) {
             return this.visibleItems.includes(key);
         },
         selectAllItems() {
             this.visibleItems = this.availableDockItems.map(i => i.key);
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems } }));
         },
         selectDefaultItems() {
             this.visibleItems = ['dashboard', 'tenants', 'plans', 'codes', 'settings', 'smtp'];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems } }));
         },
         uncheckAllItems() {
             this.visibleItems = [];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems } }));
         },
         resetAll() {
             this.setLayout('slim');
             this.setPosition('left');
             this.setCustomBg('');
             this.setUiAccentColor('#4f46e5');
             this.setNavTextColor('#ffffff');
             this.setNavTextActiveColor('#60a5fa');
             this.selectDefaultItems();
         },
        toggleCustomizerModal() {
             window.dispatchEvent(new CustomEvent('open-dock-customizer'));
         }
     }">
    <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🎨</span> {{ __('Navigation, Theme & Layout Customization') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Configure public landing page themes, menu structures, typography text colors, UI accent highlights, dock backgrounds, and item pinning in real time.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="$wire.saveAppearance({ layout, position, mode, customBg, uiAccentColor, navTextColor, navTextActiveColor, visibleItems })"
                    wire:loading.attr="disabled"
                    wire:target="saveAppearance"
                    class="px-4 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-md shadow-emerald-600/20 flex items-center gap-1.5 cursor-pointer disabled:opacity-60">
                <span wire:loading.remove wire:target="saveAppearance">💾</span>
                <span wire:loading wire:target="saveAppearance">⏳</span>
                <span>{{ __('Save Global Defaults') }}</span>
            </button>
            <button type="button"
                    @click="resetAll()"
                    class="px-3.5 py-2 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <span>🔄</span>
                <span>{{ __('Reset Layout Defaults') }}</span>
            </button>

            <button type="button"
                    @click="toggleCustomizerModal()"
                    class="px-4 py-2 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-md shadow-indigo-600/30 flex items-center gap-1.5 cursor-pointer">
                <span>🪟</span>
                <span>{{ __('Open Popup Customizer') }}</span>
            </button>
        </div>
    </div>

    <!-- 1. PUBLIC LANDING PAGE THEME & LAYOUT -->
    <div class="space-y-3 pb-6 border-b border-slate-150 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    1. Public Landing Page Theme & Layout
                </h4>
                <p class="text-[11px] text-slate-400">
                    Select the global landing page design and conversion structure shown on the root domain (<code>/</code>).
                </p>
            </div>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                Live Switcher
            </span>
        </div>

        <!-- Theme Grid Selector Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-1">
            
            <!-- Theme 1: Modern SaaS / Cloud POS (Default) -->
            <div wire:click="setLandingTheme('theme_modern')"
                 class="cursor-pointer relative p-3.5 rounded-2xl border-2 transition-all duration-150 flex flex-col justify-between
                 {{ $landingTheme === 'theme_modern' 
                     ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 shadow-sm ring-2 ring-blue-500/20' 
                     : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700' }}">
                <div class="flex items-start justify-between">
                    <div class="p-2 rounded-xl bg-blue-100/60 dark:bg-blue-900/40 text-blue-600 text-lg">🚀</div>
                    @if($landingTheme === 'theme_modern')
                        <span class="text-blue-600 dark:text-blue-400 font-bold text-sm">✓</span>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Modern Cloud POS</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Vibrant SaaS gradients & interactive product features</div>
                </div>
            </div>

            <!-- Theme 2: Enterprise Retail & Hardware Showcase (From /read Specs) -->
            <div wire:click="setLandingTheme('theme_enterprise')"
                 class="cursor-pointer relative p-3.5 rounded-2xl border-2 transition-all duration-150 flex flex-col justify-between
                 {{ $landingTheme === 'theme_enterprise' 
                     ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 shadow-sm ring-2 ring-blue-500/20' 
                     : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700' }}">
                <div class="flex items-start justify-between">
                    <div class="p-2 rounded-xl bg-emerald-100/60 dark:bg-emerald-900/40 text-emerald-600 text-lg">🏪</div>
                    @if($landingTheme === 'theme_enterprise')
                        <span class="text-blue-600 dark:text-blue-400 font-bold text-sm">✓</span>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Enterprise Showcase</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">High-contrast retail structure based on video guide</div>
                </div>
            </div>

            <!-- Theme 3: Minimal Funnel & Direct Register -->
            <div wire:click="setLandingTheme('theme_minimal')"
                 class="cursor-pointer relative p-3.5 rounded-2xl border-2 transition-all duration-150 flex flex-col justify-between
                 {{ $landingTheme === 'theme_minimal' 
                     ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 shadow-sm ring-2 ring-blue-500/20' 
                     : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700' }}">
                <div class="flex items-start justify-between">
                    <div class="p-2 rounded-xl bg-amber-100/60 dark:bg-amber-900/40 text-amber-600 text-lg">⚡</div>
                    @if($landingTheme === 'theme_minimal')
                        <span class="text-blue-600 dark:text-blue-400 font-bold text-sm">✓</span>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Minimal Conversion</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Focused single-page funnel for quick tenant signups</div>
                </div>
            </div>

            <!-- Theme 4: Dark Studio / Tech POS -->
            <div wire:click="setLandingTheme('theme_dark_studio')"
                 class="cursor-pointer relative p-3.5 rounded-2xl border-2 transition-all duration-150 flex flex-col justify-between
                 {{ $landingTheme === 'theme_dark_studio' 
                 ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 shadow-sm ring-2 ring-blue-500/20' 
                 : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700' }}">
                <div class="flex items-start justify-between">
                    <div class="p-2 rounded-xl bg-purple-100/60 dark:bg-purple-900/40 text-purple-600 text-lg">✨</div>
                    @if($landingTheme === 'theme_dark_studio')
                        <span class="text-blue-600 dark:text-blue-400 font-bold text-sm">✓</span>
                    @endif
                </div>
                <div class="mt-3">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Dark Studio POS</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Sleek, dark-mode native interface showcase</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Menu Layout Structure -->
    <div class="space-y-3">
        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
            2. {{ __('Menu Layout Structure') }}
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <button type="button"
                    @click="setLayout('slim')"
                    class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                    :class="layout === 'slim' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-700 dark:text-slate-300'">
                <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">🗂️</div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold flex items-center justify-between text-xs">
                        <span>{{ __('Slim Icon Rail') }}</span>
                        <span x-show="layout === 'slim'" class="text-indigo-600 font-black">✓</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-normal mt-0.5 leading-snug">{{ __('Narrow rail with tooltips') }}</p>
                </div>
            </button>

            <button type="button"
                    @click="setLayout('expanded')"
                    class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                    :class="layout === 'expanded' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-700 dark:text-slate-300'">
                <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">📋</div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold flex items-center justify-between text-xs">
                        <span>{{ __('Expanded Sidebar') }}</span>
                        <span x-show="layout === 'expanded'" class="text-indigo-600 font-black">✓</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-normal mt-0.5 leading-snug">{{ __('Wide grouped sidebar') }}</p>
                </div>
            </button>

            <button type="button"
                    @click="setLayout('macos-dock')"
                    class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                    :class="layout === 'macos-dock' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-700 dark:text-slate-300'">
                <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">🏝️</div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold flex items-center justify-between text-xs">
                        <span>{{ __('macOS Island Dock') }}</span>
                        <span x-show="layout === 'macos-dock'" class="text-indigo-600 font-black">✓</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-normal mt-0.5 leading-snug">{{ __('Floating bottom pill') }}</p>
                </div>
            </button>

            <button type="button"
                    @click="setLayout('speed-dial')"
                    class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                    :class="layout === 'speed-dial' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-700 dark:text-slate-300'">
                <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">⚡</div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold flex items-center justify-between text-xs">
                        <span>{{ __('Speed-Dial Bubble') }}</span>
                        <span x-show="layout === 'speed-dial'" class="text-indigo-600 font-black">✓</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-normal mt-0.5 leading-snug">{{ __('Collapsible radial bubble') }}</p>
                </div>
            </button>
        </div>
    </div>

    <!-- 3. Menu Item Typography & Text Colors -->
    <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                3. {{ __('Navigation Menu Item Text Colors & Typography') }}
            </label>
            <p class="text-[11px] text-slate-400 mt-0.5">
                {{ __('Customize font colors for default inactive links and active highlight states across all dock and top-bar navigation bars.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Default Inactive Color -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-700 dark:text-slate-300 text-xs">{{ __('Default Inactive Text Color') }}</span>
                    <div class="flex items-center gap-2">
                        <input type="color"
                               :value="navTextColor || '#ffffff'"
                               @input="setNavTextColor($event.target.value)"
                               class="w-7 h-7 rounded-lg border-none cursor-pointer p-0 bg-transparent">
                        <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300" x-text="navTextColor || '#ffffff'"></span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <template x-for="c in ['#ffffff', '#e2e8f0', '#cbd5e1', '#94a3b8', '#f8fafc', '#334155', '#1e293b']" :key="c">
                        <button type="button"
                                @click="setNavTextColor(c)"
                                class="w-7 h-7 rounded-xl border flex items-center justify-center transition cursor-pointer shadow-xs"
                                :class="navTextColor === c ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-700'"
                                :style="'background-color: ' + c">
                            <span x-show="navTextColor === c" class="text-[10px] font-black" :class="(c === '#ffffff' || c === '#f8fafc' || c === '#e2e8f0' || c === '#cbd5e1') ? 'text-slate-900' : 'text-white'">✓</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Active Highlight Color -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-700 dark:text-slate-300 text-xs">{{ __('Active Highlight Text Color') }}</span>
                    <div class="flex items-center gap-2">
                        <input type="color"
                               :value="navTextActiveColor || '#60a5fa'"
                               @input="setNavTextActiveColor($event.target.value)"
                               class="w-7 h-7 rounded-lg border-none cursor-pointer p-0 bg-transparent">
                        <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300" x-text="navTextActiveColor || '#60a5fa'"></span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <template x-for="c in ['#60a5fa', '#38bdf8', '#4f46e5', '#a855f7', '#34d399', '#fde047', '#f43f5e', '#ffffff']" :key="c">
                        <button type="button"
                                @click="setNavTextActiveColor(c)"
                                class="w-7 h-7 rounded-xl border flex items-center justify-center transition cursor-pointer shadow-xs"
                                :class="navTextActiveColor === c ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-700'"
                                :style="'background-color: ' + c">
                            <span x-show="navTextActiveColor === c" class="text-[10px] font-black" :class="(c === '#ffffff' || c === '#fde047') ? 'text-slate-900' : 'text-white'">✓</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Primary UI Accent Color -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    4. {{ __('Dashboard Hero & UI Accent Color') }}
                </label>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ __('Primary color of dashboard cards, action buttons, and active highlight pills') }}</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="color" :value="uiAccentColor" @input="setUiAccentColor($event.target.value)" class="w-7 h-7 rounded-lg border-none cursor-pointer p-0 bg-transparent">
                <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300" x-text="uiAccentColor"></span>
            </div>
        </div>

        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
            <template x-for="accent in [
                { name: 'Cobalt Blue', hex: '#2563eb' },
                { name: 'Emerald Green', hex: '#059669' },
                { name: 'Royal Indigo', hex: '#4f46e5' },
                { name: 'Violet Purple', hex: '#7c3aed' },
                { name: 'Warm Amber', hex: '#d97706' },
                { name: 'Ruby Rose', hex: '#e11d48' },
                { name: 'Teal Ocean', hex: '#0d9488' },
                { name: 'Midnight Slate', hex: '#334155' }
            ]" :key="accent.hex">
                <button type="button"
                        @click="setUiAccentColor(accent.hex)"
                        class="p-2 rounded-xl border flex flex-col items-center gap-1.5 transition cursor-pointer"
                        :class="uiAccentColor === accent.hex ? 'border-indigo-600 ring-2 ring-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'">
                    <span class="w-5 h-5 rounded-full shadow-xs flex items-center justify-center text-white text-[10px] font-black" :style="'background-color: ' + accent.hex">
                        <span x-show="uiAccentColor === accent.hex">✓</span>
                    </span>
                    <span class="text-[9px] font-bold text-slate-700 dark:text-slate-300 truncate w-full text-center" x-text="accent.name"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- 5. Dock Navigation Custom Background -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    5. {{ __('Dock Navigation Background') }}
                </label>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ __('Set an independent background color or gradient for the navigation bar') }}</p>
            </div>
            <button type="button" @click="setCustomBg('')" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">{{ __('Default Theme') }}</button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <button type="button" @click="setCustomBg('')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="!customBg ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Default Palette') }}</span>
            </button>
            <button type="button" @click="setCustomBg('linear-gradient(135deg, #064e3b, #047857)')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === 'linear-gradient(135deg, #064e3b, #047857)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-emerald-900 to-emerald-600 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Deep Emerald') }}</span>
            </button>
            <button type="button" @click="setCustomBg('linear-gradient(135deg, #1e3a8a, #1d4ed8)')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === 'linear-gradient(135deg, #1e3a8a, #1d4ed8)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-blue-900 to-blue-600 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Royal Blue') }}</span>
            </button>
            <button type="button" @click="setCustomBg('linear-gradient(135deg, #2e1065, #4c1d95)')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === 'linear-gradient(135deg, #2e1065, #4c1d95)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-purple-950 to-purple-800 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Midnight Violet') }}</span>
            </button>
            <button type="button" @click="setCustomBg('linear-gradient(135deg, #881337, #be123c)')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === 'linear-gradient(135deg, #881337, #be123c)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-rose-950 to-rose-700 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Crimson Velvet') }}</span>
            </button>
            <button type="button" @click="setCustomBg('#0f172a')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === '#0f172a' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-slate-900 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Obsidian Dark') }}</span>
            </button>
            <button type="button" @click="setCustomBg('rgba(15, 23, 42, 0.85)')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === 'rgba(15, 23, 42, 0.85)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-slate-900/60 border border-white/20 shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('Smoky Glass') }}</span>
            </button>
            <button type="button" @click="setCustomBg('#000000')" class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer" :class="customBg === '#000000' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                <span class="w-5 h-5 rounded-lg bg-black shrink-0"></span>
                <span class="font-bold text-[11px] truncate">{{ __('OLED Black') }}</span>
            </button>
        </div>
    </div>

    <!-- 6. Docking Screen Position -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800" x-show="layout !== 'speed-dial' && layout !== 'macos-dock'">
        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
            6. {{ __('Docking Screen Position & Floating Mode') }}
        </label>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 text-center font-bold text-xs">
            <button type="button" @click="setPosition('left')" class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5" :class="(position === 'left' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'">
                <span class="text-base">⬅️</span>
                <span>{{ __('Left Sidebar') }}</span>
            </button>
            <button type="button" @click="setPosition('right')" class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5" :class="(position === 'right' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'">
                <span class="text-base">➡️</span>
                <span>{{ __('Right Sidebar') }}</span>
            </button>
            <button type="button" @click="setPosition('top')" class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5" :class="(position === 'top' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'">
                <span class="text-base">⬆️</span>
                <span>{{ __('Top Header') }}</span>
            </button>
            <button type="button" @click="setPosition('bottom')" class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5" :class="(position === 'bottom' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'">
                <span class="text-base">⬇️</span>
                <span>{{ __('Bottom Dock') }}</span>
            </button>
            <button type="button" @click="setMode('floating')" class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5 col-span-2 sm:col-span-1" :class="mode === 'floating' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'">
                <span class="text-base">🪟</span>
                <span>{{ __('Floating') }}</span>
            </button>
        </div>
    </div>

    <!-- 7. Visible Dock Items (Menu Pinning) -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div>
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    7. {{ __('Visible Dock Items (Pinning & Presets)') }}
                </label>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ __('Uncheck items to remove them from your dockable navigation bar for a compact interface') }}</p>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                <button type="button" @click="selectAllItems()" class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition cursor-pointer">✓ {{ __('Select All') }}</button>
                <button type="button" @click="selectDefaultItems()" class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition cursor-pointer">⭐ {{ __('Default (Essential 6)') }}</button>
                <button type="button" @click="uncheckAllItems()" class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 hover:bg-rose-100 transition cursor-pointer">✕ {{ __('Reset / Uncheck All') }}</button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800">
            <template x-for="item in availableDockItems" :key="item.key">
                <label class="flex items-center gap-2.5 p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 cursor-pointer hover:bg-indigo-50/30 transition select-none">
                    <input type="checkbox" :checked="isItemVisible(item.key)" @change="toggleItem(item.key)" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                    <span class="text-sm shrink-0" x-text="item.icon"></span>
                    <span class="font-bold text-[11px] text-slate-800 dark:text-slate-200 truncate" x-text="item.label"></span>
                </label>
            </template>
        </div>
    </div>

</div>
