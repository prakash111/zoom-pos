<!-- Navigation Appearance & Layout Customizer Modal -->
<div x-show="showCustomizerModal"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6 select-none">
    
    <!-- Modal Backdrop -->
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity"
         @click="showCustomizerModal = false"></div>

    <!-- Modal Content Card -->
    <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200/60 dark:border-white/10 overflow-hidden flex flex-col max-h-[90vh]">
        
        <!-- Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/40">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-xs">
                    🎨
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Navigation & Appearance Settings') }}
                    </h3>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">
                        {{ __('Customize layout structure, UI accent colors, dock background & visible menu items') }}
                    </p>
                </div>
            </div>

            <button type="button"
                    @click="showCustomizerModal = false"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center font-bold text-sm transition cursor-pointer">
                &times;
            </button>
        </div>

        <!-- Body Scrollable Content -->
        <div class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
            
            <!-- 1. Layout Structure (4 distinct layouts) -->
            <div class="space-y-3">
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    1. {{ __('Menu Layout Structure') }}
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Layout 1: Slim Rail -->
                    <button type="button"
                            @click="setLayout('slim')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="layout === 'slim' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            🗂️
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Slim Icon Rail') }}</span>
                                <span x-show="layout === 'slim'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('Minimalist narrow rail with tooltips and high screen efficiency') }}
                            </p>
                        </div>
                    </button>

                    <!-- Layout 2: Expanded Sidebar -->
                    <button type="button"
                            @click="setLayout('expanded')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="layout === 'expanded' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            📋
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Expanded Full Sidebar') }}</span>
                                <span x-show="layout === 'expanded'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('Wide grouped sidebar with section headers, full titles & badges') }}
                            </p>
                        </div>
                    </button>

                    <!-- Layout 3: macOS Floating Dock -->
                    <button type="button"
                            @click="setLayout('macos-dock')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="layout === 'macos-dock' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            🏝️
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('macOS-Style Island Dock') }}</span>
                                <span x-show="layout === 'macos-dock'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('Floating pill with subtle magnify scale hover micro-interactions') }}
                            </p>
                        </div>
                    </button>

                    <!-- Layout 4: Compact Speed-Dial -->
                    <button type="button"
                            @click="setLayout('speed-dial')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="layout === 'speed-dial' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            ⚡
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Speed-Dial Bubble') }}</span>
                                <span x-show="layout === 'speed-dial'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('Collapsible floating radial bubble for max screen real estate') }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- 2. Dashboard Hero & UI Accent Color (NEW) -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                            2. {{ __('Dashboard Hero & UI Accent Color') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            {{ __('Customize the primary color of the dashboard welcome banner, action buttons, and active highlight pills') }}
                        </p>
                    </div>

                    <!-- Native Color Picker Input -->
                    <div class="flex items-center gap-2">
                        <input type="color"
                               :value="uiAccentColor"
                               @input="setUiAccentColor($event.target.value)"
                               class="w-7 h-7 rounded-lg border-none cursor-pointer p-0 bg-transparent"
                               title="{{ __('Custom Accent Color') }}">
                        <span class="font-mono text-[11px] font-bold text-slate-600 dark:text-slate-300" x-text="uiAccentColor"></span>
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
                            <span class="w-5 h-5 rounded-full shadow-xs flex items-center justify-center text-white text-[10px] font-black"
                                  :style="'background-color: ' + accent.hex">
                                <span x-show="uiAccentColor === accent.hex">✓</span>
                            </span>
                            <span class="text-[9px] font-bold text-slate-700 dark:text-slate-300 truncate w-full text-center" x-text="accent.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- 3. Dock Navigation Custom Background (NEW) -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                            3. {{ __('Dock Navigation Background') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            {{ __('Set an independent background color or gradient for the navigation bar') }}
                        </p>
                    </div>

                    <!-- Reset Background to Default -->
                    <button type="button"
                            @click="setCustomBg('')"
                            class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                        {{ __('Default Theme') }}
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <button type="button"
                            @click="setCustomBg('')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="!customBg ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Default Palette') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('linear-gradient(135deg, #064e3b, #047857)')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === 'linear-gradient(135deg, #064e3b, #047857)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-emerald-900 to-emerald-600 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Deep Emerald') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('linear-gradient(135deg, #1e3a8a, #1d4ed8)')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === 'linear-gradient(135deg, #1e3a8a, #1d4ed8)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-blue-900 to-blue-600 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Royal Blue') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('linear-gradient(135deg, #2e1065, #4c1d95)')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === 'linear-gradient(135deg, #2e1065, #4c1d95)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-purple-950 to-purple-800 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Midnight Violet') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('linear-gradient(135deg, #881337, #be123c)')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === 'linear-gradient(135deg, #881337, #be123c)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-gradient-to-tr from-rose-950 to-rose-700 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Crimson Velvet') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('#0f172a')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === '#0f172a' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-slate-900 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Obsidian Dark') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('rgba(15, 23, 42, 0.85)')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === 'rgba(15, 23, 42, 0.85)' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-slate-900/60 border border-white/20 shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('Smoky Glass') }}</span>
                    </button>

                    <button type="button"
                            @click="setCustomBg('#000000')"
                            class="p-2.5 rounded-xl border text-left transition flex items-center gap-2 cursor-pointer"
                            :class="customBg === '#000000' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'">
                        <span class="w-5 h-5 rounded-lg bg-black shrink-0"></span>
                        <span class="font-bold text-[11px] truncate">{{ __('OLED Black') }}</span>
                    </button>
                </div>
            </div>

            <!-- 4. Navigation Menu Item Text Colors & Typography (NEW) -->
            <div class="space-y-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                        4. {{ __('Menu Item Text Colors & Active Highlighting') }}
                    </label>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ __('Customize font colors for default inactive links and active highlight states in real time') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Default / Inactive Item Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700 dark:text-slate-300 text-[11px]">{{ __('Inactive Text Color') }}</span>
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       :value="navTextColor || '#ffffff'"
                                       @input="setNavTextColor($event.target.value)"
                                       class="w-6 h-6 rounded-lg border-none cursor-pointer p-0 bg-transparent"
                                       title="{{ __('Select Inactive Color') }}">
                                <span class="font-mono text-[10px] font-bold text-slate-500 dark:text-slate-400" x-text="navTextColor || '#ffffff'"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <template x-for="c in ['#ffffff', '#e2e8f0', '#cbd5e1', '#94a3b8', '#f8fafc', '#334155', '#1e293b']" :key="c">
                                <button type="button"
                                        @click="setNavTextColor(c)"
                                        class="w-6 h-6 rounded-lg border flex items-center justify-center transition cursor-pointer shadow-xs"
                                        :class="navTextColor === c ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-700'"
                                        :style="'background-color: ' + c"
                                        :title="c">
                                    <span x-show="navTextColor === c" class="text-[9px] font-black" :class="(c === '#ffffff' || c === '#f8fafc' || c === '#e2e8f0' || c === '#cbd5e1') ? 'text-slate-900' : 'text-white'">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Active / Highlight Item Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700 dark:text-slate-300 text-[11px]">{{ __('Active Highlight Color') }}</span>
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       :value="navTextActiveColor || '#60a5fa'"
                                       @input="setNavTextActiveColor($event.target.value)"
                                       class="w-6 h-6 rounded-lg border-none cursor-pointer p-0 bg-transparent"
                                       title="{{ __('Select Active Color') }}">
                                <span class="font-mono text-[10px] font-bold text-slate-500 dark:text-slate-400" x-text="navTextActiveColor || '#60a5fa'"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <template x-for="c in ['#60a5fa', '#38bdf8', '#4f46e5', '#a855f7', '#34d399', '#fde047', '#f43f5e', '#ffffff']" :key="c">
                                <button type="button"
                                        @click="setNavTextActiveColor(c)"
                                        class="w-6 h-6 rounded-lg border flex items-center justify-center transition cursor-pointer shadow-xs"
                                        :class="navTextActiveColor === c ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-700'"
                                        :style="'background-color: ' + c"
                                        :title="c">
                                    <span x-show="navTextActiveColor === c" class="text-[9px] font-black" :class="(c === '#ffffff' || c === '#fde047') ? 'text-slate-900' : 'text-white'">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Customize Visible Dock Items (Menu Item Pinning) -->
            <template x-if="storageKey === 'sa_dock_nav_state' || (typeof adminDockItems !== 'undefined' && availableDockItems === adminDockItems)">
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    @include('superadmin.settings.appearance-modal')
                </div>
            </template>
            <template x-if="storageKey !== 'sa_dock_nav_state' && !(typeof adminDockItems !== 'undefined' && availableDockItems === adminDockItems)">
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div>
                            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                                5. {{ __('Customize Visible Dock Items (Pinning)') }}
                            </label>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ __('Uncheck items to remove them from your dockable navigation bar for a compact, clutter-free interface') }}
                            </p>
                        </div>

                        <!-- Preset Action Buttons -->
                        <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                            <button type="button"
                                    @click="selectAllItems()"
                                    class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 border border-indigo-200/60 dark:border-indigo-800/50 transition cursor-pointer active:scale-95 shadow-xs">
                                ✓ {{ __('Select All') }}
                            </button>
                            <button type="button"
                                    @click="selectDefaultItems()"
                                    class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition cursor-pointer active:scale-95 shadow-xs">
                                ⭐ {{ __('Default (Essential 6)') }}
                            </button>
                            <button type="button"
                                    @click="uncheckAllItems()"
                                    class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 transition cursor-pointer active:scale-95 shadow-xs">
                                ✕ {{ __('Reset / Uncheck All') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3 rounded-2xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800">
                        <template x-for="item in availableDockItems" :key="item.key">
                            <label class="flex items-center gap-2.5 p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 cursor-pointer hover:bg-indigo-50/30 transition select-none">
                                <input type="checkbox"
                                       :checked="isItemVisible(item.key)"
                                       @change="toggleItem(item.key)"
                                       class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                                <span class="text-sm shrink-0" x-text="item.icon"></span>
                                <span class="font-bold text-[11px] text-slate-800 dark:text-slate-200 truncate" x-text="item.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>

            <!-- 6. Visual Theme Styles (4 distinct themes) -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    6. {{ __('Visual Theme Style Preset') }}
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Theme 1: Violet Glow -->
                    <button type="button"
                            @click="setTheme('violet')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-center gap-3 cursor-pointer group"
                            :class="theme === 'violet' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-700 to-purple-600 text-white flex items-center justify-center text-sm shadow-md shrink-0">
                            🔮
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Signature Violet Glow') }}</span>
                                <span x-show="theme === 'violet'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5">
                                {{ __('Vibrant purple accent with glowing active states') }}
                            </p>
                        </div>
                    </button>

                    <!-- Theme 2: Glassmorphism -->
                    <button type="button"
                            @click="setTheme('glass')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-center gap-3 cursor-pointer group"
                            :class="theme === 'glass' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-slate-900/90 text-cyan-300 flex items-center justify-center text-sm border border-white/20 shadow-md shrink-0">
                            🧊
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Frosted Glassmorphism') }}</span>
                                <span x-show="theme === 'glass'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5">
                                {{ __('Modern translucent acrylic with thin frosted borders') }}
                            </p>
                        </div>
                    </button>

                    <!-- Theme 3: Enterprise Flat Dark -->
                    <button type="button"
                            @click="setTheme('enterprise')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-center gap-3 cursor-pointer group"
                            :class="theme === 'enterprise' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-lg bg-slate-950 text-emerald-400 flex items-center justify-center text-sm border border-slate-700 font-mono shadow-md shrink-0">
                            🏢
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Enterprise Flat Dark') }}</span>
                                <span x-show="theme === 'enterprise'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5">
                                {{ __('High-contrast sharp panels for data-dense workflows') }}
                            </p>
                        </div>
                    </button>

                    <!-- Theme 4: Clean Neumorphic -->
                    <button type="button"
                            @click="setTheme('neumorphic')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-center gap-3 cursor-pointer group"
                            :class="theme === 'neumorphic' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-2xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-white flex items-center justify-center text-sm shadow-[2px_2px_5px_rgba(0,0,0,0.2),-2px_-2px_5px_rgba(255,255,255,0.7)] shrink-0">
                            🪞
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Clean Neumorphic') }}</span>
                                <span x-show="theme === 'neumorphic'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5">
                                {{ __('Soft dual-depth shadows with tactile embossed pills') }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- 7. Docking Behavior Mode (Fixed Docked vs Floating / Free Drag) -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800" x-show="layout !== 'speed-dial' && layout !== 'macos-dock'">
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    7. {{ __('Docking Behavior') }}
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Option 1: Fixed Docked -->
                    <button type="button"
                            @click="setMode('docked')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="mode === 'docked' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            ⚓
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Fixed Docked') }}</span>
                                <span x-show="mode === 'docked'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('The bar snaps rigidly into the screen structure and adjusts page padding accordingly so content never scrolls under or overlaps it.') }}
                            </p>
                        </div>
                    </button>

                    <!-- Option 2: Floating / Free Drag -->
                    <button type="button"
                            @click="setMode('floating')"
                            class="p-3.5 rounded-2xl border text-left transition flex items-start gap-3 cursor-pointer group"
                            :class="mode === 'floating' ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'">
                        <div class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-base shadow-xs shrink-0">
                            🪟
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold flex items-center justify-between">
                                <span>{{ __('Floating / Free Drag') }}</span>
                                <span x-show="mode === 'floating'" class="text-indigo-600 dark:text-indigo-400 font-black">✓</span>
                            </div>
                            <p class="text-[11px] text-slate-400 font-normal mt-0.5 leading-snug">
                                {{ __('The bar floats as an overlay above the UI, enabling free drag-and-drop placement anywhere across the viewport.') }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- 8. Docking Screen Position (When Fixed Docked) -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800" x-show="layout !== 'speed-dial' && layout !== 'macos-dock' && mode === 'docked'">
                <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                    8. {{ __('Docking Screen Position') }}
                </label>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-center font-bold">
                    <button type="button"
                            @click="setPosition('left')"
                            class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5"
                            :class="(position === 'left' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'">
                        <span class="text-lg">⬅️</span>
                        <span>{{ __('Left Sidebar') }}</span>
                    </button>

                    <button type="button"
                            @click="setPosition('right')"
                            class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5"
                            :class="(position === 'right' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'">
                        <span class="text-lg">➡️</span>
                        <span>{{ __('Right Sidebar') }}</span>
                    </button>

                    <button type="button"
                            @click="setPosition('top')"
                            class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5"
                            :class="(position === 'top' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'">
                        <span class="text-lg">⬆️</span>
                        <span>{{ __('Top Header Bar') }}</span>
                    </button>

                    <button type="button"
                            @click="setPosition('bottom')"
                            class="p-3 rounded-2xl border transition cursor-pointer flex flex-col items-center gap-1.5"
                            :class="(position === 'bottom' && mode === 'docked') ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'">
                        <span class="text-lg">⬇️</span>
                        <span>{{ __('Bottom Dock Bar') }}</span>
                    </button>
                </div>
            </div>

            <!-- 9. Sticky Navigation Bar Setting -->
            <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800" x-show="mode === 'docked'">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                            9. {{ __('Sticky Navigation Bar') }}
                        </label>
                        <p class="text-[11px] text-slate-400 font-normal mt-0.5">
                            {{ __('Keep the navigation bar pinned in view while scrolling through long views and forms') }}
                        </p>
                    </div>

                    <button type="button"
                            @click="setSticky(!sticky)"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                            :class="sticky ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'">
                        <span class="sr-only">{{ __('Toggle Sticky Navigation') }}</span>
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                              :class="sticky ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Footer Actions -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 flex items-center justify-between">
            <button type="button"
                    @click="resetAll()"
                    class="px-4 py-2 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/60 font-bold transition flex items-center gap-1.5 cursor-pointer">
                <span>🔄</span>
                <span>{{ __('Reset to Defaults') }}</span>
            </button>

            <button type="button"
                    @click="showCustomizerModal = false"
                    class="px-6 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-lg shadow-indigo-500/25 active:scale-95 cursor-pointer">
                {{ __('Apply & Close') }}
            </button>
        </div>

    </div>
</div>
