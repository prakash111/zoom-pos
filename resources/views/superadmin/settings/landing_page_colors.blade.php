{{-- Superadmin Landing Page Colors Customizer Component --}}
@php
    $currentDarkBg = $themeSettings['landing_dark_bg'] ?? setting('landing_dark_bg', '#0b0f19');
    $currentPalette = get_landing_sections_palette();
@endphp

<div class="space-y-6"
     x-data="{
         palette: @js($currentPalette),
         paletteMode: 'light',
         activeSection: 'contact',
         sectionNames: {
             hero: '{{ __('Hero Showcase') }}',
             features: '{{ __('Retail Features') }}',
             mission: '{{ __('Our Mission') }}',
             pricing: '{{ __('Pricing & Plans') }}',
             faq: '{{ __('FAQ') }}',
             cta: '{{ __('Scale CTA') }}',
             contact: '{{ __('Contact Form Section') }}'
         },
         matchingPatterns: @js(get_landing_matching_patterns()),
         applyMatchingPattern(key) {
             var p = this.matchingPatterns[key];
             if (!p) return;
             this.palette = JSON.parse(JSON.stringify(p.palette));
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
         }
     }">

    <form action="{{ route('superadmin.theme.customizer.sections') }}" method="POST">
        @csrf
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

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h4 class="text-base font-black text-slate-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>✉️</span> {{ __('Contact Form Section & Page Palette') }}
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Manage background surfaces, form cards, and text contrast for the public contact section and all landing modules.') }}
                    </p>
                </div>
                
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <button type="button" 
                            @click="paletteMode = 'light'" 
                            :class="paletteMode === 'light' ? 'bg-white dark:bg-slate-800 text-amber-600 shadow-sm font-black' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white font-bold'"
                            class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                        <span>☀️</span>
                        <span>{{ __('Light Mode') }}</span>
                    </button>
                    <button type="button" 
                            @click="paletteMode = 'dark'" 
                            :class="paletteMode === 'dark' ? 'bg-white dark:bg-slate-800 text-indigo-500 shadow-sm font-black' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white font-bold'"
                            class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                        <span>🌙</span>
                        <span>{{ __('Dark Mode') }}</span>
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

            <!-- Tabs -->
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

            <!-- Controls and Preview -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <div class="lg:col-span-7 space-y-4">
                    <!-- Presets -->
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <template x-for="p in palettePresets" :key="p.key">
                            <button type="button" 
                                    @click="applyPalettePreset(p)"
                                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:scale-105 transition-all text-left cursor-pointer"
                                    :style="{ backgroundColor: paletteMode === 'dark' ? p.dark_bg : p.light_bg }">
                                <div class="w-2.5 h-2.5 rounded-full border border-white/40 mb-1" :style="{ backgroundColor: p.color }"></div>
                                <div class="text-[10px] font-bold truncate" :style="{ color: paletteMode === 'dark' ? p.dark_text : p.light_text }" x-text="p.label"></div>
                            </button>
                        </template>
                    </div>

                    <!-- Tokens -->
                    <div class="space-y-3 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                        <!-- Background -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="w-1/3">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Backdrop') }}</label>
                            </div>
                            <div class="flex-1 flex items-center gap-2">
                                <span class="text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_bg' : 'light_bg'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_bg' : 'light_bg', $event.target.value)"
                                       class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                <input type="color"
                                       :value="palette[activeSection][paletteMode === 'dark' ? 'dark_bg' : 'light_bg'] || '#000000'"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_bg' : 'light_bg', $event.target.value)"
                                       class="w-8 h-8 p-0.5 rounded-lg border cursor-pointer shrink-0">
                            </div>
                        </div>

                        <!-- Card Background for Contact -->
                        <div x-show="activeSection === 'contact'" class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-800">
                            <div class="w-1/3">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Card Surface') }}</label>
                            </div>
                            <div class="flex-1 flex items-center gap-2">
                                <span class="text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection] && palette[activeSection][paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg', $event.target.value)"
                                       class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                <input type="color"
                                       :value="(palette[activeSection] && palette[activeSection][paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg']) || '#ffffff'"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_card_bg' : 'light_card_bg', $event.target.value)"
                                       class="w-8 h-8 p-0.5 rounded-lg border cursor-pointer shrink-0">
                            </div>
                        </div>

                        <!-- Text -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="w-1/3">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Headlines') }}</label>
                            </div>
                            <div class="flex-1 flex items-center gap-2">
                                <span class="text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_text' : 'light_text'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_text' : 'light_text', $event.target.value)"
                                       class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                <input type="color"
                                       :value="palette[activeSection][paletteMode === 'dark' ? 'dark_text' : 'light_text'] || '#000000'"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_text' : 'light_text', $event.target.value)"
                                       class="w-8 h-8 p-0.5 rounded-lg border cursor-pointer shrink-0">
                            </div>
                        </div>

                        <!-- Muted -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="w-1/3">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Muted Labels') }}</label>
                            </div>
                            <div class="flex-1 flex items-center gap-2">
                                <span class="text-slate-400 font-mono text-xs">#</span>
                                <input type="text"
                                       :value="(palette[activeSection][paletteMode === 'dark' ? 'dark_muted' : 'light_muted'] || '').replace('#', '')"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_muted' : 'light_muted', $event.target.value)"
                                       class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono uppercase bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                <input type="color"
                                       :value="palette[activeSection][paletteMode === 'dark' ? 'dark_muted' : 'light_muted'] || '#000000'"
                                       @input="updateColorToken(activeSection, paletteMode === 'dark' ? 'dark_muted' : 'light_muted', $event.target.value)"
                                       class="w-8 h-8 p-0.5 rounded-lg border cursor-pointer shrink-0">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm cursor-pointer">
                            {{ __('Save Palette Settings') }}
                        </button>
                    </div>
                </div>

                <!-- Preview -->
                <div class="lg:col-span-5">
                    <div class="rounded-2xl p-6 transition-all shadow-xl border overflow-hidden"
                         :style="{
                             backgroundColor: paletteMode === 'dark' ? palette[activeSection].dark_bg : palette[activeSection].light_bg,
                             borderColor: paletteMode === 'dark' ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.08)'
                         }">
                        <h4 class="text-lg font-black transition-colors"
                            :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_text : palette[activeSection].light_text }">
                            <span x-text="sectionNames[activeSection]"></span>
                        </h4>
                        <p class="mt-2 text-xs transition-colors"
                           :style="{ color: paletteMode === 'dark' ? palette[activeSection].dark_muted : palette[activeSection].light_muted }">
                            {{ __('Preview of contrast between background, card surface, headline typography, and input labels.') }}
                        </p>
                        
                        <div class="mt-4 p-4 rounded-xl border transition-all space-y-2"
                             :style="{
                                 backgroundColor: paletteMode === 'dark' ? (palette.contact && palette.contact.dark_card_bg ? palette.contact.dark_card_bg : '#131e29') : (palette.contact && palette.contact.light_card_bg ? palette.contact.light_card_bg : '#ffffff'),
                                 borderColor: paletteMode === 'dark' ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)'
                             }">
                            <div class="text-[11px] font-bold uppercase tracking-wider"
                                 :style="{ color: paletteMode === 'dark' ? palette.contact.dark_text : palette.contact.light_text }">
                                {{ __('Card Container') }}
                            </div>
                            <div class="p-2.5 rounded-lg border text-xs"
                                 :style="{
                                     backgroundColor: paletteMode === 'dark' ? 'rgba(255,255,255,0.05)' : '#f8fafc',
                                     borderColor: paletteMode === 'dark' ? 'rgba(255,255,255,0.15)' : '#cbd5e1',
                                     color: paletteMode === 'dark' ? '#f8fafc' : '#0f172a'
                                 }">
                                {{ __('Name & Business Email Input Field') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
