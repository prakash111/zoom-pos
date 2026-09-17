@php
    $currentDarkBg = $themeSettings['landing_dark_bg'] ?? setting('landing_dark_bg', '#0b0f19');
    $currentPalette = get_landing_sections_palette();
@endphp

<div class="space-y-8"
     x-data="{
         landingDarkBg: '{{ $currentDarkBg }}',
         palette: @js($currentPalette),
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
         setLandingBg(hex) {
             this.landingDarkBg = hex;
             var hexInput = document.getElementById('landing_dark_bg_hex');
             if (hexInput) hexInput.value = hex.replace('#', '');
             var picker = document.getElementById('landing_dark_bg_picker');
             if (picker) picker.value = hex;
             if (this.palette && this.palette.mission) this.palette.mission.dark_bg = hex;
             if (this.palette && this.palette.pricing) this.palette.pricing.dark_bg = hex;
             if (this.palette && this.palette.cta) this.palette.cta.dark_bg = hex;
         }
     }">

    <!-- GLOBAL LANDING PAGE DARK SECTION BACKGROUND FORM -->
    <form action="{{ route('superadmin.theme.customizer.save') }}" method="POST">
        @csrf
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-7 border border-slate-200 dark:border-slate-700 shadow-sm space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🌙</span> {{ __('Global Landing Dark Background') }}
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Set the default dark background color applied across the mission, pricing, and CTA landing sections.') }}
                    </p>
                </div>
                <button type="button" @click="setLandingBg('#0b0f19')" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline cursor-pointer">
                    {{ __('Default Obsidian') }}
                </button>
            </div>

            <!-- Color Presets -->
            <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
                <button type="button" @click="setLandingBg('#0b0f19')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#0b0f19' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #0b0f19;" title="Obsidian Dark"><span x-show="landingDarkBg === '#0b0f19'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#0f172a')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#0f172a' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #0f172a;" title="Slate Navy"><span x-show="landingDarkBg === '#0f172a'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#18181b')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#18181b' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #18181b;" title="Zinc Charcoal"><span x-show="landingDarkBg === '#18181b'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#1e1b4b')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#1e1b4b' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #1e1b4b;" title="Deep Indigo"><span x-show="landingDarkBg === '#1e1b4b'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#064e3b')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#064e3b' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #064e3b;" title="Emerald Night"><span x-show="landingDarkBg === '#064e3b'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#4c0519')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#4c0519' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #4c0519;" title="Deep Crimson"><span x-show="landingDarkBg === '#4c0519'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#2e1065')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#2e1065' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #2e1065;" title="Dark Violet"><span x-show="landingDarkBg === '#2e1065'" class="text-white text-xs font-black">✓</span></button>
                <button type="button" @click="setLandingBg('#000000')" class="h-10 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer" :class="landingDarkBg === '#000000' ? 'border-indigo-600 ring-2 ring-indigo-500/40' : 'border-slate-300 dark:border-slate-600'" style="background-color: #000000;" title="OLED Black"><span x-show="landingDarkBg === '#000000'" class="text-white text-xs font-black">✓</span></button>
            </div>

            <!-- Custom HEX Input -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-mono text-sm">#</span>
                    <input type="text" 
                           id="landing_dark_bg_hex" 
                           name="landing_dark_bg" 
                           :value="landingDarkBg.replace('#', '')" 
                           class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white font-mono uppercase"
                           placeholder="0B0F19"
                           @input="setLandingBg($event.target.value)">
                </div>
                <input type="color" 
                       id="landing_dark_bg_picker" 
                       :value="landingDarkBg" 
                       class="w-10 h-10 p-1 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-transparent"
                       @input="setLandingBg($event.target.value)">
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm cursor-pointer">
                    {{ __('Save Global Dark Setting') }}
                </button>
            </div>
        </div>
    </form>

    <!-- GRANULAR PER-SECTION THEMES & CONTRAST FORM -->
    <form action="{{ route('superadmin.theme.customizer.sections') }}" method="POST">
        @csrf
        
        <!-- Hidden inputs ensuring standard form POST captures all sections -->
        <template x-for="(secData, secKey) in palette" :key="secKey">
            <div>
                <input type="hidden" :name="'palette[' + secKey + '][light_bg]'" :value="secData.light_bg">
                <input type="hidden" :name="'palette[' + secKey + '][light_card_bg]'" :value="secData.light_card_bg || ''">
                <input type="hidden" :name="'palette[' + secKey + '][light_text]'" :value="secData.light_text">
                <input type="hidden" :name="'palette[' + secKey + '][light_muted]'" :value="secData.light_muted">
                <input type="hidden" :name="'palette[' + secKey + '][dark_bg]'" :value="secData.dark_bg">
                <input type="hidden" :name="'palette[' + secKey + '][dark_card_bg]'" :value="secData.dark_card_bg || ''">
                <input type="hidden" :name="'palette[' + secKey + '][dark_text]'" :value="secData.dark_text">
                <input type="hidden" :name="'palette[' + secKey + '][dark_muted]'" :value="secData.dark_muted">
            </div>
        </template>

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-7 border border-slate-200 dark:border-slate-700 shadow-sm space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🎨</span> {{ __('Landing Page Section Themes & Contrast') }}
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Customize per-section background colors, headline text, and secondary muted copy for both Light and Dark theme modes.') }}
                    </p>
                </div>
                
                <!-- Dual Mode Toggle -->
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
                            {{ __('Instantly setup a coordinated, alternating color combination across all landing sections for both Light & Dark modes.') }}
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                    <template x-for="(mp, mKey) in matchingPatterns" :key="mKey">
                        <button type="button"
                                @click="applyMatchingPattern(mKey)"
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

            <!-- Main Layout: Controls (Left) vs Live Preview (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Controls -->
                <div class="lg:col-span-7 space-y-5">
                    <!-- Presets -->
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

                    <!-- Token Inputs -->
                    <div class="space-y-3 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-wider">
                                <span x-text="sectionNames[activeSection]"></span> · <span class="capitalize" x-text="paletteMode"></span> Tokens
                            </span>
                            <span class="text-[10px] font-mono text-slate-400">HEX / RGB</span>
                        </div>

                        <!-- Background -->
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

                        <!-- Card Background (Contact Form Section) -->
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

                        <!-- Primary Text -->
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

                        <!-- Muted Copy -->
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

                    <!-- Submit -->
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[11px] text-slate-500">
                            {{ __('Saves all section color values for both Light and Dark themes.') }}
                        </span>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5 cursor-pointer">
                            <span>💾</span>
                            <span>{{ __('Save Section Themes') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Preview -->
                <div class="lg:col-span-5">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2">
                        {{ __('Live Preview') }} (<span x-text="paletteMode === 'dark' ? '🌙 Dark Mode' : '☀️ Light Mode'"></span>)
                    </label>
                    
                    <div class="rounded-2xl p-6 transition-all duration-300 shadow-xl border overflow-hidden relative"
                         :style="{
                             backgroundColor: paletteMode === 'dark' ? palette[activeSection].dark_bg : palette[activeSection].light_bg,
                             borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)'
                         }">
                        
                        <!-- Badge -->
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider mb-4 border"
                             :style="{
                                 backgroundColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.04)',
                                 borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)',
                                 color: paletteMode === 'dark' ? palette[activeSection].dark_text : palette[activeSection].light_text
                             }">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            <span x-text="sectionNames[activeSection]"></span>
                        </div>

                        <!-- Heading -->
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

                        <!-- Paragraph -->
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

                        <!-- Mock Action -->
                        <div class="mt-5 pt-4 border-t flex items-center justify-between gap-3"
                             :style="{ borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)' }">
                            <span class="text-[10px] font-bold"
                                  :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_muted : palette[activeSection].light_muted }">
                                Token: <span class="font-mono" x-text="paletteMode === 'dark' ? palette[activeSection].dark_bg : palette[activeSection].light_bg"></span>
                            </span>
                            <button type="button"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-black shadow-sm transition bg-indigo-600 text-white">
                                Explore →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
