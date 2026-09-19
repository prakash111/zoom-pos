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
         landingDarkBg: localStorage.getItem('landing_dark_bg') || @js(appearance_defaults()['landingDarkBg'] ?? '#0b0f19'),
         palette: (function() {
             try {
                 var def = @js(default_landing_sections_palette());
                 var pal = @js(appearance_defaults()['landingSectionsPalette'] ?? []);
                 return Object.assign({}, def, pal);
             } catch(e) {
                 return @js(default_landing_sections_palette());
             }
         })(),
         activeSection: 'hero',
         paletteMode: 'dark',
         sectionNames: {
             hero: '{{ __('Hero Showcase') }}',
             features: '{{ __('Retail Features') }}',
             mission: '{{ __('Our Mission') }}',
             pricing: '{{ __('Pricing & Plans') }}',
             faq: '{{ __('FAQ') }}',
             cta: '{{ __('Scale CTA') }}',
             contact: '{{ __('Contact Form') }}'
         },
         matchingPatterns: @js(get_landing_matching_patterns()),
         applyMatchingPattern(key) {
             var p = this.matchingPatterns[key];
             if (!p) return;
             this.palette = JSON.parse(JSON.stringify(p.palette));
             this.setLandingBg(p.dark_bg);
         },
         applyAndSaveMatchingPattern(key) {
             var p = this.matchingPatterns[key];
             if (!p) return;
             this.palette = JSON.parse(JSON.stringify(p.palette));
             this.setLandingBg(p.dark_bg);
             if (this.$wire && this.$wire.applyMatchingPalettePattern) {
                 this.$wire.applyMatchingPalettePattern(key);
             }
         },
         palettePresets: [
             { key: 'obsidian', label: 'Obsidian Dark', color: '#0b0f19', dark_bg: '#0b0f19', dark_card_bg: '#131e29', dark_text: '#f8fafc', dark_muted: '#94a3b8', light_bg: '#ffffff', light_card_bg: '#ffffff', light_text: '#0f172a', light_muted: '#64748b' },
             { key: 'midnight', label: 'Midnight Navy', color: '#0f172a', dark_bg: '#0f172a', dark_card_bg: '#1e293b', dark_text: '#f8fafc', dark_muted: '#94a3b8', light_bg: '#f8fafc', light_card_bg: '#ffffff', light_text: '#0f172a', light_muted: '#64748b' },
             { key: 'emerald', label: 'Deep Emerald', color: '#064e3b', dark_bg: '#064e3b', dark_card_bg: '#022c22', dark_text: '#ecfdf5', dark_muted: '#6ee7b7', light_bg: '#f0fdf4', light_card_bg: '#ffffff', light_text: '#064e3b', light_muted: '#047857' },
             { key: 'indigo', label: 'Royal Indigo', color: '#1e1b4b', dark_bg: '#1e1b4b', dark_card_bg: '#312e81', dark_text: '#e0e7ff', dark_muted: '#a5b4fc', light_bg: '#eef2ff', light_card_bg: '#ffffff', light_text: '#1e1b4b', light_muted: '#4338ca' },
             { key: 'clean_white', label: 'Clean White', color: '#ffffff', dark_bg: '#020617', dark_card_bg: '#0f172a', dark_text: '#f8fafc', dark_muted: '#94a3b8', light_bg: '#ffffff', light_card_bg: '#f8fafc', light_text: '#0f172a', light_muted: '#64748b' },
             { key: 'warm_slate', label: 'Warm Slate', color: '#f1f5f9', dark_bg: '#1e293b', dark_card_bg: '#0f172a', dark_text: '#f1f5f9', dark_muted: '#94a3b8', light_bg: '#f1f5f9', light_card_bg: '#ffffff', light_text: '#0f172a', light_muted: '#475569' }
         ],
         applyPalettePreset(preset) {
             if (!preset || !this.palette[this.activeSection]) return;
             if (this.paletteMode === 'dark') {
                 this.palette[this.activeSection].dark_bg = preset.dark_bg;
                 this.palette[this.activeSection].dark_text = preset.dark_text;
                 this.palette[this.activeSection].dark_muted = preset.dark_muted;
                 if (this.activeSection === 'contact' && preset.dark_card_bg) {
                     this.palette[this.activeSection].dark_card_bg = preset.dark_card_bg;
                 }
                 if (['mission', 'pricing', 'cta'].includes(this.activeSection)) {
                     this.setLandingBg(preset.dark_bg);
                 }
             } else {
                 this.palette[this.activeSection].light_bg = preset.light_bg;
                 this.palette[this.activeSection].light_text = preset.light_text;
                 this.palette[this.activeSection].light_muted = preset.light_muted;
                 if (this.activeSection === 'contact' && preset.light_card_bg) {
                     this.palette[this.activeSection].light_card_bg = preset.light_card_bg;
                 }
             }
         },
         updateColorToken(sec, token, val) {
             var hex = val.trim();
             if (!hex.startsWith('#') && hex.length > 0) hex = '#' + hex;
             if (this.palette[sec]) {
                 this.palette[sec][token] = hex;
             }
             if (sec === 'mission' && token === 'dark_bg') {
                 this.setLandingBg(hex);
             }
         },
         resetSectionPalette(sec) {
             var def = @js(default_landing_sections_palette());
             if (def[sec]) {
                 this.palette[sec] = Object.assign({}, def[sec]);
                 if (sec === 'mission') {
                     this.setLandingBg(def[sec].dark_bg);
                 }
             }
         },
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
             document.documentElement.style.setProperty('--nav-inactive-color', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { navTextColor: v } }));
         },
         setNavTextActiveColor(v) {
             this.navTextActiveColor = v;
             localStorage.setItem('nav_text_active_color', v);
             document.documentElement.style.setProperty('--nav-item-active-color', v);
             document.documentElement.style.setProperty('--nav-active-color', v);
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { navTextActiveColor: v } }));
         },
         toggleItem(key) {
             if (this.visibleItems.includes(key)) {
                 this.visibleItems = this.visibleItems.filter(k => k !== key);
             } else {
                 this.visibleItems.push(key);
             }
             this.visibleAdminItems = [...this.visibleItems];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems, visibleAdminItems: this.visibleAdminItems } }));
         },
         isItemVisible(key) {
             return this.visibleItems.includes(key);
         },
         selectAllItems() {
             this.visibleItems = this.availableDockItems.map(i => i.key);
             this.visibleAdminItems = [...this.visibleItems];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems, visibleAdminItems: this.visibleAdminItems } }));
         },
         selectDefaultItems() {
             this.visibleItems = ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
             this.visibleAdminItems = [...this.visibleItems];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems, visibleAdminItems: this.visibleAdminItems } }));
         },
         uncheckAllItems() {
             this.visibleItems = [];
             this.visibleAdminItems = [];
             localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleItems));
             window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleItems, visibleAdminItems: this.visibleAdminItems } }));
         },
         setLandingBg(v) {
             this.landingDarkBg = v;
             localStorage.setItem('landing_dark_bg', v);
             if (this.palette && this.palette.mission) this.palette.mission.dark_bg = v;
             if (this.palette && this.palette.pricing) this.palette.pricing.dark_bg = v;
             if (this.palette && this.palette.cta) this.palette.cta.dark_bg = v;
             var hexInput = document.getElementById('landing_dark_bg_hex');
             if (hexInput) hexInput.value = v.replace('#', '');
             var picker = document.getElementById('landing_dark_bg_picker');
             if (picker) picker.value = v;
         },
         resetAll() {
             this.setLayout('slim');
             this.setPosition('left');
             this.setCustomBg('');
             this.setUiAccentColor('#4f46e5');
             this.setNavTextColor('#ffffff');
             this.setNavTextActiveColor('#60a5fa');
             this.setLandingBg('#0b0f19');
             this.palette = Object.assign({}, @js(default_landing_sections_palette()));
             this.selectDefaultItems();
         },
        toggleCustomizerModal() {
             window.dispatchEvent(new CustomEvent('open-dock-customizer'));
         }
     }">
    <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🎨</span> {{ __('Navigation & Layout Customization') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Configure menu structures, typography text colors, UI accent highlights, dock backgrounds, and item pinning in real time.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:flex sm:flex-wrap items-center gap-2 w-full sm:w-auto">
            <button type="button"
                    @click="$wire.saveAppearance({ layout, position, mode, customBg, uiAccentColor, navTextColor, navTextActiveColor, visibleItems, landingDarkBg, palette })"
                    wire:loading.attr="disabled"
                    wire:target="saveAppearance"
                    class="h-10 px-4 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-60">
                <span wire:loading.remove wire:target="saveAppearance">💾</span>
                <span wire:loading wire:target="saveAppearance">⏳</span>
                <span>{{ __('Save Global Defaults') }}</span>
            </button>
            <button type="button"
                    @click="resetAll()"
                    class="h-10 px-3.5 py-2 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                <span>🔄</span>
                <span>{{ __('Reset Layout Defaults') }}</span>
            </button>

            <button type="button"
                    @click="toggleCustomizerModal()"
                    class="h-10 px-4 py-2 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-md shadow-indigo-600/30 flex items-center justify-center gap-1.5 cursor-pointer">
                <span>🪟</span>
                <span>{{ __('Open Popup Customizer') }}</span>
            </button>
        </div>
    </div>

    <!-- 1. Menu Layout Structure -->
    <div class="space-y-3">
        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
            1. {{ __('Menu Layout Structure') }}
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

    <!-- 2. Menu Item Typography & Text Colors -->
    <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                2. {{ __('Navigation Menu Item Text Colors & Typography') }}
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

    <!-- 3. Primary UI Accent Color -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    3. {{ __('Dashboard Hero & UI Accent Color') }}
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

    <!-- 4. Dock Navigation Custom Background -->
    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    4. {{ __('Dock Navigation Background') }}
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

    <!-- 5. LANDING PAGE SECTION THEMES & CONTRAST -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-7 border border-slate-200 dark:border-slate-700 shadow-sm space-y-6 mt-6">
        <!-- Section Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
            <div>
                <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span>🎨</span> {{ __('Landing Page Section Themes & Contrast') }}
                </h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Configure per-section background colors, primary headline text, and secondary muted copy for both Light and Dark modes.') }}
                </p>
            </div>
            
            <!-- Dual Theme Mode Switcher -->
            <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-700 self-start sm:self-auto">
                <button type="button" 
                        @click="paletteMode = 'light'" 
                        :class="paletteMode === 'light' ? 'bg-white dark:bg-slate-800 text-amber-600 shadow-sm font-black' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white font-bold'"
                        class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                    <span>☀️</span>
                    <span>{{ __('Light Theme Values') }}</span>
                </button>
                <button type="button" 
                        @click="paletteMode = 'dark'" 
                        :class="paletteMode === 'dark' ? 'bg-white dark:bg-slate-800 text-indigo-500 shadow-sm font-black' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white font-bold'"
                        class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                    <span>🌙</span>
                    <span>{{ __('Dark Theme Values') }}</span>
                </button>
            </div>
        </div>

        <!-- One-Time Matching Color Combination Pattern Selector -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 dark:from-slate-950 dark:via-indigo-950 dark:to-slate-950 p-4 rounded-2xl border border-indigo-500/20 text-white shadow-md">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-indigo-300 flex items-center gap-2">
                        <span>🎨</span> {{ __('One-Time Matching Color Combination Patterns (All Sections)') }}
                    </span>
                    <p class="text-[11px] text-slate-300 mt-0.5">
                        {{ __('Instantly apply a harmonized, alternating color combination across all landing sections for both Light & Dark modes.') }}
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                <template x-for="(mp, mKey) in matchingPatterns" :key="mKey">
                    <button type="button"
                            @click="applyAndSaveMatchingPattern(mKey)"
                            class="p-2.5 rounded-xl border border-white/10 hover:border-indigo-400 bg-white/5 hover:bg-white/10 transition-all text-left group cursor-pointer relative flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="w-3.5 h-3.5 rounded-full border border-white/30 shadow-xs shrink-0" :style="{ backgroundColor: mp.color }"></span>
                            <span class="text-[9px] font-mono text-indigo-300 group-hover:text-white uppercase font-bold">{{ __('Apply') }} →</span>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white truncate" x-text="mp.name"></div>
                            <div class="text-[9px] text-slate-400 truncate mt-0.5" x-text="mp.description"></div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Section Navigation Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
            <template x-for="(name, sec) in sectionNames" :key="sec">
                <button type="button"
                        @click="activeSection = sec"
                        :class="activeSection === sec ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-500/20' : 'bg-slate-100 dark:bg-slate-900/80 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 font-semibold'"
                        class="px-3.5 py-2 rounded-xl text-xs transition whitespace-nowrap cursor-pointer flex items-center gap-2 shrink-0 border border-transparent">
                    <span x-text="sec === 'hero' ? '🚀' : (sec === 'features' ? '⚡' : (sec === 'mission' ? '🎯' : (sec === 'pricing' ? '💳' : (sec === 'faq' ? '❓' : (sec === 'contact' ? '✉️' : '✨')))))"></span>
                    <span x-text="name"></span>
                </button>
            </template>
        </div>

        <!-- Customization Grid: Controls (Left) vs Live Preview (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left: Presets & Color Token Inputs -->
            <div class="lg:col-span-7 space-y-5">
                <!-- Preset Swatches Bar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            {{ __('Quick Palette Presets') }} (<span x-text="paletteMode === 'dark' ? 'Dark' : 'Light'"></span>)
                        </label>
                        <button type="button" 
                                @click="resetSectionPalette(activeSection)" 
                                class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline font-bold cursor-pointer">
                            {{ __('Reset to Default') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <template x-for="p in palettePresets" :key="p.key">
                            <button type="button" 
                                    @click="applyPalettePreset(p)"
                                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:scale-105 transition-all text-left group cursor-pointer relative"
                                    :style="{ backgroundColor: paletteMode === 'dark' ? p.dark_bg : p.light_bg }">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="w-2.5 h-2.5 rounded-full border border-white/40 shadow-xs" :style="{ backgroundColor: p.color }"></div>
                                    <span class="text-[9px] font-bold opacity-75" :style="{ color: paletteMode === 'dark' ? p.dark_text : p.light_text }">✓</span>
                                </div>
                                <div class="text-[10px] font-bold truncate" :style="{ color: paletteMode === 'dark' ? p.dark_text : p.light_text }" x-text="p.label"></div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Color Token Inputs (Background, Card Background for Contact, Primary Text, Muted Text) -->
                <div class="space-y-3 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-wider">
                            <span x-text="sectionNames[activeSection]"></span> · <span class="capitalize" x-text="paletteMode"></span> Tokens
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">HEX / RGB</span>
                    </div>

                    <!-- Token 1: Background Color -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="w-1/3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                {{ __('Background Color') }}
                            </label>
                            <span class="text-[10px] text-slate-400">{{ __('Section backdrop') }}</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_bg' : 'light_bg'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_bg' : 'light_bg', $event.target.value)"
                                       class="w-full pl-6 pr-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white"
                                       placeholder="FFFFFF">
                            </div>
                            <input type="color"
                                   :value="palette[activeSection][paletteMode === 'dark' ? 'dark_bg' : 'light_bg'] || '#000000'"
                                   @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_bg' : 'light_bg', $event.target.value)"
                                   class="w-8 h-8 p-0.5 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-transparent shrink-0">
                        </div>
                    </div>

                    <!-- Token 1b: Card Background Color (Contact Form Section) -->
                    <div x-show="activeSection === 'contact'" class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-800">
                        <div class="w-1/3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                {{ __('Card Background') }}
                            </label>
                            <span class="text-[10px] text-slate-400">{{ __('Contact form surface') }}</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection] && palette[activeSection][paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg', $event.target.value)"
                                       class="w-full pl-6 pr-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white"
                                       placeholder="131E29">
                            </div>
                            <input type="color"
                                   :value="(palette[activeSection] && palette[activeSection][paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg']) || '#ffffff'"
                                   @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg', $event.target.value)"
                                   class="w-8 h-8 p-0.5 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-transparent shrink-0">
                        </div>
                    </div>

                    <!-- Token 2: Primary Text Color -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="w-1/3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                {{ __('Primary Text') }}
                            </label>
                            <span class="text-[10px] text-slate-400">{{ __('Headings & titles') }}</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_text' : 'light_text'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_text' : 'light_text', $event.target.value)"
                                       class="w-full pl-6 pr-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white"
                                       placeholder="0F172A">
                            </div>
                            <input type="color"
                                   :value="palette[activeSection][paletteMode === 'dark' ? 'dark_text' : 'light_text'] || '#000000'"
                                   @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_text' : 'light_text', $event.target.value)"
                                   class="w-8 h-8 p-0.5 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-transparent shrink-0">
                        </div>
                    </div>

                    <!-- Token 3: Muted / Secondary Text Color -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="w-1/3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                {{ __('Muted Copy Text') }}
                            </label>
                            <span class="text-[10px] text-slate-400">{{ __('Paragraphs & details') }}</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_muted' : 'light_muted'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_muted' : 'light_muted', $event.target.value)"
                                       class="w-full pl-6 pr-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white"
                                       placeholder="64748B">
                            </div>
                            <input type="color"
                                   :value="palette[activeSection][paletteMode === 'dark' ? 'dark_muted' : 'light_muted'] || '#000000'"
                                   @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_muted' : 'light_muted', $event.target.value)"
                                   class="w-8 h-8 p-0.5 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-transparent shrink-0">
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center justify-between pt-1">
                    <span class="text-[11px] text-slate-500">
                        {{ __('Tip: Switch between Light & Dark modes to configure both themes before saving.') }}
                    </span>
                    <button type="button"
                            @click="$wire.saveAppearance({ layout, position, mode, customBg, uiAccentColor, navTextColor, navTextActiveColor, visibleItems, landingDarkBg, palette })"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5 cursor-pointer">
                        <span>💾</span>
                        <span>{{ __('Save Section Themes') }}</span>
                    </button>
                </div>
            </div>

            <!-- Right: Live Interactive Contrast & Section Preview -->
            <div class="lg:col-span-5">
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2">
                    {{ __('Live Preview') }} (<span x-text="paletteMode === 'dark' ? '🌙 Dark Mode' : '☀️ Light Mode'"></span>)
                </label>
                
                <div class="rounded-2xl p-6 transition-all duration-300 shadow-xl border overflow-hidden relative"
                     :style="{
                         backgroundColor: paletteMode === 'dark' ? palette[activeSection].dark_bg : palette[activeSection].light_bg,
                         borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)'
                     }">
                    
                    <!-- Decorative subtle glow in dark mode -->
                    <div class="absolute -top-10 -right-10 w-36 h-36 rounded-full blur-2xl pointer-events-none opacity-20"
                         :style="{ backgroundColor: paletteMode === 'dark' ? palette[activeSection].dark_text : '#3b82f6' }"></div>

                    <!-- Badge -->
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider mb-4 border"
                         :style="{
                             backgroundColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.04)',
                             borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)',
                             color: paletteMode === 'dark' ? palette[activeSection].dark_text : palette[activeSection].light_text
                         }">
                        <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: uiAccentColor || '#4f46e5' }"></span>
                        <span x-text="sectionNames[activeSection]"></span>
                    </div>

                    <!-- Main Heading -->
                    <h3 class="text-xl sm:text-2xl font-black tracking-tight leading-snug transition-colors"
                        :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_text : palette[activeSection].light_text }">
                        <template x-if="activeSection === 'hero'">
                            <span>High-Performance Omnichannel Cloud POS</span>
                        </template>
                        <template x-if="activeSection === 'features'">
                            <span>Everything Your Business Needs to Scale</span>
                        </template>
                        <template x-if="activeSection === 'mission'">
                            <span>Engineered for Reliability & Modern Growth</span>
                        </template>
                        <template x-if="activeSection === 'pricing'">
                            <span>Simple, Transparent Pricing For Every Tier</span>
                        </template>
                        <template x-if="activeSection === 'faq'">
                            <span>Frequently Asked Questions & Answers</span>
                        </template>
                        <template x-if="activeSection === 'cta'">
                            <span>Ready to Scale Your Online & In-Store Sales?</span>
                        </template>
                        <template x-if="activeSection === 'contact'">
                            <span>Speak with an Omnichannel POS Specialist</span>
                        </template>
                    </h3>

                    <!-- Muted Paragraph Copy -->
                    <p class="mt-3 text-xs leading-relaxed transition-colors"
                       :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_muted : palette[activeSection].light_muted }">
                        Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp tax invoicing into one zero-latency cloud engine.
                    </p>

                    <!-- Contact Form Card Live Surface Preview -->
                    <template x-if="activeSection === 'contact'">
                        <div class="mt-4 p-3.5 rounded-xl border transition-all space-y-2"
                             :style="{
                                 backgroundColor: paletteMode === 'dark' ? (palette.contact && palette.contact.dark_card_bg ? palette.contact.dark_card_bg : '#131e29') : (palette.contact && palette.contact.light_card_bg ? palette.contact.light_card_bg : '#ffffff'),
                                 borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)'
                             }">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider"
                                      :style="{ color: paletteMode === 'dark' ? palette.contact.dark_text : palette.contact.light_text }">
                                    {{ __('Card Surface Preview') }}
                                </span>
                                <span class="text-[9px] font-mono opacity-70"
                                      :style="{ color: paletteMode === 'dark' ? palette.contact.dark_muted : palette.contact.light_muted }">
                                    <span x-text="paletteMode === 'dark' ? (palette.contact && palette.contact.dark_card_bg) : (palette.contact && palette.contact.light_card_bg)"></span>
                                </span>
                            </div>
                            <div class="p-2 rounded-lg border text-[11px] font-medium"
                                 :style="{
                                     backgroundColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.05)' : '#f8fafc',
                                     borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.15)' : '#cbd5e1',
                                     color: paletteMode === 'dark' ? '#f8fafc' : '#0f172a'
                                 }">
                                {{ __('Business Email: contact@store.com') }}
                            </div>
                        </div>
                    </template>

                    <!-- Mock Interaction Element -->
                    <div class="mt-5 pt-4 border-t flex items-center justify-between gap-3"
                         :style="{ borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)' }">
                        <span class="text-[10px] font-bold"
                              :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_muted : palette[activeSection].light_muted }">
                            Token: <span class="font-mono" x-text="paletteMode === 'dark' ? palette[activeSection].dark_bg : palette[activeSection].light_bg"></span>
                        </span>
                        <button type="button"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-black shadow-sm transition"
                                :style="{
                                    backgroundColor: uiAccentColor || '#4f46e5',
                                    color: '#ffffff'
                                }">
                            Explore →
                        </button>
                    </div>
                </div>
            </div>
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

<script>
function setLandingBg(hex) {
    if (window.Alpine) {
        var el = document.querySelector('[x-data]');
        if (el && el._x_dataStack && el._x_dataStack[0] && el._x_dataStack[0].setLandingBg) {
            el._x_dataStack[0].setLandingBg(hex);
        }
    }
    var hexInput = document.getElementById('landing_dark_bg_hex');
    if (hexInput) {
        hexInput.value = hex.replace('#', '');
        hexInput.dispatchEvent(new Event('input'));
    }
    var picker = document.getElementById('landing_dark_bg_picker');
    if (picker) {
        picker.value = hex;
        picker.dispatchEvent(new Event('change'));
    }
}
function syncLandingHexInput(val) {
    var hexInput = document.getElementById('landing_dark_bg_hex');
    if (hexInput) {
        hexInput.value = val.replace('#', '');
        hexInput.dispatchEvent(new Event('input'));
    }
    if (window.Alpine) {
        var el = document.querySelector('[x-data]');
        if (el && el._x_dataStack && el._x_dataStack[0]) {
            el._x_dataStack[0].landingDarkBg = val;
            localStorage.setItem('landing_dark_bg', val);
        }
    }
}
function syncLandingColorPreview(val) {
    var hex = val.startsWith('#') ? val : '#' + val;
    if (val.length === 6 || val.length === 7) {
        var picker = document.getElementById('landing_dark_bg_picker');
        if (picker) {
            picker.value = hex;
            picker.dispatchEvent(new Event('change'));
        }
        if (window.Alpine) {
            var el = document.querySelector('[x-data]');
            if (el && el._x_dataStack && el._x_dataStack[0]) {
                el._x_dataStack[0].landingDarkBg = hex;
                localStorage.setItem('landing_dark_bg', hex);
            }
        }
    }
}
</script>
