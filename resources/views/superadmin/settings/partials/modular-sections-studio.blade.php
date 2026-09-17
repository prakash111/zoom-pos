@php
    $sectionsCatalog = [
        [
            'key' => 'hero',
            'prop' => 'sectionHero',
            'name' => __('1. Hero Showcase'),
            'icon' => '🚀',
            'desc' => __('Top headline, call-to-actions, and key highlights'),
            'count_label' => count($landingHeroHighlights) . ' ' . __('highlights'),
        ],
        [
            'key' => 'trust_bar',
            'prop' => 'sectionTrustBar',
            'name' => __('2. Hardware Bar'),
            'icon' => '🖨️',
            'desc' => __('Supported printers, scanners, and POS devices'),
            'count_label' => count($landingHardwareItems) . ' ' . __('devices'),
        ],
        [
            'key' => 'features',
            'prop' => 'sectionFeatures',
            'name' => __('3. Features Suite'),
            'icon' => '✨',
            'desc' => __('Inventory, Retail POS, Restaurant Floor & Finance cards'),
            'count_label' => count($landingFeatures) . ' ' . __('cards'),
        ],
        [
            'key' => 'solutions',
            'prop' => 'sectionSolutions',
            'name' => __('4. Solutions & Verticals'),
            'icon' => '⚡',
            'desc' => __('Scale pillars, offline mode, multi-location architecture'),
            'count_label' => count($landingSolutions) . ' ' . __('pillars'),
        ],
        [
            'key' => 'downloads',
            'prop' => 'sectionDownloads',
            'name' => __('5. App Downloads'),
            'icon' => '📱',
            'desc' => __('Google Play Store & Windows desktop installer links'),
            'count_label' => ($landingPlaystoreEnabled || $landingWindowsEnabled) ? __('Active') : __('Disabled'),
        ],
        [
            'key' => 'stats',
            'prop' => 'sectionStats',
            'name' => __('6. Live Metrics & Stats'),
            'icon' => '📊',
            'desc' => __('Live counters (transactions, uptime SLA, latency)'),
            'count_label' => count($landingStats) . ' ' . __('metrics'),
        ],
        [
            'key' => 'about',
            'prop' => 'sectionAbout',
            'name' => __('7. Mission & About'),
            'icon' => '🎯',
            'desc' => __('Platform mission, leadership vision, company story'),
            'count_label' => __('Story & Mission'),
        ],
        [
            'key' => 'testimonials',
            'prop' => 'sectionTestimonials',
            'name' => __('8. Customer Reviews'),
            'icon' => '💬',
            'desc' => __('Client testimonials, quotes, ratings and social proof'),
            'count_label' => count($landingTestimonials) . ' ' . __('reviews'),
        ],
        [
            'key' => 'pricing',
            'prop' => 'sectionPricing',
            'name' => __('9. Pricing Plans'),
            'icon' => '💳',
            'desc' => __('Pricing header, trial highlights and billing tiers'),
            'count_label' => __('Plans Engine'),
        ],
        [
            'key' => 'faq',
            'prop' => 'sectionFaq',
            'name' => __('10. FAQ'),
            'icon' => '❓',
            'desc' => __('Frequently asked questions & expandable accordion answers'),
            'count_label' => count($landingFaqs) . ' ' . __('questions'),
        ],
        [
            'key' => 'contact',
            'prop' => 'sectionContact',
            'name' => __('11. Contact Inquiry'),
            'icon' => '📞',
            'desc' => __('Lead capture form, support email & WhatsApp inquiry'),
            'count_label' => __('Inquiry Channels'),
        ],
        [
            'key' => 'cta',
            'prop' => 'sectionCta',
            'name' => __('12. Conversion CTA'),
            'icon' => '📣',
            'desc' => __('Bottom conversion banner and quick registration button'),
            'count_label' => __('Conversion Action'),
        ],
    ];
@endphp

<div x-data="{ activeSection: 'hero' }" class="space-y-6 pt-4 border-t border-slate-100 dark:border-slate-800">

    <!-- Header / Explanation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🎛️</span>
                <span>{{ __('Modular Sections Studio: Add, Edit & Delete Content') }}</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Toggle the checkbox on any section to show/hide it on the page. Click any section card below to edit its titles, badges, and add or delete dynamic items (cards, FAQs, hardware, testimonials).') }}
            </p>
        </div>

        <div class="text-[11px] font-bold text-slate-400 shrink-0 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-xl">
            {{ __('12 Modular Sections Available') }}
        </div>
    </div>

    <!-- 12 Section Selector Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($sectionsCatalog as $sec)
            <div @click="activeSection = '{{ $sec['key'] }}'"
                 :class="activeSection === '{{ $sec['key'] }}' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20 shadow-md' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-600'"
                 class="p-3.5 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between gap-3 text-left group">
                
                <div class="flex items-start justify-between gap-2.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="text-xl shrink-0 p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 group-hover:scale-105 transition-transform">{{ $sec['icon'] }}</span>
                        <div class="min-w-0">
                            <span class="text-xs font-black text-slate-900 dark:text-white block truncate">{{ $sec['name'] }}</span>
                            <span class="text-[11px] text-slate-400 truncate block">{{ $sec['desc'] }}</span>
                        </div>
                    </div>

                    <!-- Visibility Toggle Checkbox -->
                    <div class="shrink-0 flex items-center pt-0.5" @click.stop title="{{ __('Toggle visibility on landing page') }}">
                        <input type="checkbox" wire:model="{{ $sec['prop'] }}" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60 text-[11px]">
                    <span class="font-medium text-slate-400 font-mono">{{ $sec['count_label'] }}</span>
                    <span :class="activeSection === '{{ $sec['key'] }}' ? 'text-indigo-600 dark:text-indigo-400 font-black' : 'text-slate-500 group-hover:text-slate-800 dark:group-hover:text-slate-200 font-bold'"
                          class="flex items-center gap-1">
                        <span>✏️</span>
                        <span>{{ __('Edit Content') }}</span>
                        <span :class="activeSection === '{{ $sec['key'] }}' ? 'translate-x-0.5 text-indigo-600 dark:text-indigo-400' : ''" class="transition-transform">→</span>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Active Section Studio Panel -->
    <div class="rounded-3xl border-2 border-indigo-200 dark:border-indigo-900/60 bg-slate-50/70 dark:bg-slate-900/60 p-5 sm:p-7 space-y-6 shadow-sm">
        
        <!-- Panel Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/80 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl p-2 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <template x-if="activeSection === 'hero'"><span>🚀</span></template>
                    <template x-if="activeSection === 'trust_bar'"><span>🖨️</span></template>
                    <template x-if="activeSection === 'features'"><span>✨</span></template>
                    <template x-if="activeSection === 'solutions'"><span>⚡</span></template>
                    <template x-if="activeSection === 'downloads'"><span>📱</span></template>
                    <template x-if="activeSection === 'stats'"><span>📊</span></template>
                    <template x-if="activeSection === 'about'"><span>🎯</span></template>
                    <template x-if="activeSection === 'testimonials'"><span>💬</span></template>
                    <template x-if="activeSection === 'pricing'"><span>💳</span></template>
                    <template x-if="activeSection === 'faq'"><span>❓</span></template>
                    <template x-if="activeSection === 'contact'"><span>📞</span></template>
                    <template x-if="activeSection === 'cta'"><span>📣</span></template>
                </span>
                <div>
                    <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>{{ __('Section Studio:') }}</span>
                        <span class="text-indigo-600 dark:text-indigo-400" x-text="{
                            'hero': '{{ __('1. Hero Showcase') }}',
                            'trust_bar': '{{ __('2. Hardware Bar') }}',
                            'features': '{{ __('3. Features Suite') }}',
                            'solutions': '{{ __('4. Solutions & Verticals') }}',
                            'downloads': '{{ __('5. App Downloads') }}',
                            'stats': '{{ __('6. Live Metrics & Stats') }}',
                            'about': '{{ __('7. Mission & About') }}',
                            'testimonials': '{{ __('8. Customer Reviews') }}',
                            'pricing': '{{ __('9. Pricing Plans') }}',
                            'faq': '{{ __('10. FAQ') }}',
                            'contact': '{{ __('11. Contact Inquiry') }}',
                            'cta': '{{ __('12. Conversion CTA') }}'
                        }[activeSection]"></span>
                    </h4>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ __('Configure copy, badges, titles, and manage individual child items (add, edit or delete).') }}
                    </p>
                </div>
            </div>

            <!-- Quick indicator -->
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-[11px] font-bold text-slate-400">{{ __('Live Auto-Purged Cache') }}</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 1: HERO SHOWCASE                                      -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'hero'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hero Top Badge Pill') }}</label>
                    <input type="text" wire:model="landingHeroBadge" placeholder="All-in-one POS, Inventory & Restaurant Platform" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hero Main Headline Title *') }}</label>
                    <input type="text" wire:model="landingHeroTitle" placeholder="The Most Modern POS & Inventory Platform for Your Business" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hero Subtitle / Description') }}</label>
                    <textarea wire:model="landingHeroSubtitle" rows="2" placeholder="Sub-second checkout, multi-branch synchronization, and restaurant floor management." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Primary CTA Button Text') }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryText" placeholder="Start Free Store" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Primary CTA Button URL') }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryUrl" placeholder="{{ route('tenant.register') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Secondary CTA Button Text') }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryText" placeholder="Watch 2-Min Demo" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Secondary CTA Button URL') }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryUrl" placeholder="https://..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>

            <!-- Hero Dynamic Highlights Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>✨</span> {{ __('Hero Feature Highlights / Bullet Points') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Displayed directly under the CTA buttons to build trust') }}</span>
                    </div>
                    <button type="button" wire:click="addHeroHighlight" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Highlight') }}
                    </button>
                </div>

                <div class="space-y-2.5">
                    @forelse ($landingHeroHighlights as $idx => $highlight)
                        <div wire:key="hero-highlight-{{ $idx }}" class="flex items-center gap-2">
                            <span class="w-6 text-center text-xs font-bold text-slate-400 font-mono">{{ $idx + 1 }}.</span>
                            <input type="text" wire:model="landingHeroHighlights.{{ $idx }}" placeholder="{{ __('e.g. Sub-second barcode checkout') }}" class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium">
                            <button type="button" wire:click="removeHeroHighlight({{ $idx }})" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer" title="{{ __('Delete this highlight') }}">
                                ✕
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No custom highlights added. Default highlights will be used.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 2: HARDWARE BAR (TRUST BAR)                           -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'trust_bar'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hardware Bar Title / Headline') }}</label>
                    <input type="text" wire:model="sectionMeta.trust_bar.title" placeholder="{{ __('Works out of the box with your existing retail & dining hardware') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hardware Bar Subtitle / Tagline') }}</label>
                    <input type="text" wire:model="sectionMeta.trust_bar.subtitle" placeholder="{{ __('Standard ESC/POS USB, Ethernet, Bluetooth and Serial devices supported') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>

            <!-- Hardware Items Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>🖨️</span> {{ __('Supported Hardware Devices & Peripherals') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete devices shown on the hardware carousel') }}</span>
                    </div>
                    <button type="button" wire:click="addHardware" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Hardware Item') }}
                    </button>
                </div>

                <div class="space-y-3">
                    @forelse ($landingHardwareItems as $idx => $hw)
                        <div wire:key="hw-item-{{ $idx }}" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                            <div class="sm:col-span-5">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Device Name') }}</label>
                                <input type="text" wire:model="landingHardwareItems.{{ $idx }}.label" placeholder="{{ __('e.g. Thermal Receipt Printers') }}" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Tag / Specification') }}</label>
                                <input type="text" wire:model="landingHardwareItems.{{ $idx }}.tag" placeholder="{{ __('e.g. 58mm / 80mm ESC/POS') }}" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Icon Type') }}</label>
                                <select wire:model="landingHardwareItems.{{ $idx }}.icon" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                                    <option value="barcode">Barcode</option>
                                    <option value="printer">Printer</option>
                                    <option value="card">Card Reader</option>
                                    <option value="drawer">Cash Drawer</option>
                                    <option value="display">Screen / KDS</option>
                                </select>
                            </div>
                            <div class="sm:col-span-1 flex justify-end pt-3 sm:pt-0">
                                <button type="button" wire:click="removeHardware({{ $idx }})" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer" title="{{ __('Delete this item') }}">
                                    ✕
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No hardware items configured. Default hardware list will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 3: FEATURES SUITE                                     -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'features'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.features.badge" placeholder="{{ __('Unified Operations Suite') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.features.title" placeholder="{{ __('Everything your business needs, in one unified engine') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.features.subtitle" rows="2" placeholder="{{ __('From real-time warehouse stock tracking to front-counter barcode POS and kitchen display, it is all synchronized.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <!-- Dynamic Features Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>✨</span> {{ __('Feature Cards & Capability Showcases') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete feature tabs. Separate bullet points with new lines in the description.') }}</span>
                    </div>
                    <button type="button" wire:click="addFeature" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Feature Card') }}
                    </button>
                </div>

                <div class="space-y-3">
                    @forelse ($landingFeatures as $idx => $feature)
                        <div wire:key="feature-item-{{ $idx }}" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2 flex-1">
                                    <input type="text" wire:model="landingFeatures.{{ $idx }}.icon" placeholder="📦" class="w-12 text-center px-2 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-base font-bold" title="{{ __('Icon or Emoji') }}">
                                    <input type="text" wire:model="landingFeatures.{{ $idx }}.title" placeholder="{{ __('Feature Title (e.g. Smart Inventory & Stock)') }}" class="flex-1 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                                </div>
                                <button type="button" wire:click="removeFeature({{ $idx }})" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer" title="{{ __('Delete this feature') }}">
                                    ✕
                                </button>
                            </div>

                            <div>
                                <textarea wire:model="landingFeatures.{{ $idx }}.body" rows="3" placeholder="{{ __('Enter bullet points (one per line):
Instant barcode & SKU generator
Multi-warehouse stock sync
Automated low-stock threshold alerts') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No custom features configured. Default 4 operational suites will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 4: SOLUTIONS & VERTICALS                              -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'solutions'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.solutions.badge" placeholder="{{ __('Architected For Scale') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.solutions.title" placeholder="{{ __('Engineered for maximum reliability under peak pressure') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.solutions.subtitle" rows="2" placeholder="{{ __('Enterprise-grade architecture built for retail chains and restaurant groups.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <!-- Dynamic Solutions Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>⚡</span> {{ __('Solution Pillars & Enterprise Advantages') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete architectural pillar cards') }}</span>
                    </div>
                    <button type="button" wire:click="addSolution" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Solution Pillar') }}
                    </button>
                </div>

                <div class="space-y-3">
                    @forelse ($landingSolutions as $idx => $sol)
                        <div wire:key="solution-item-{{ $idx }}" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2 flex-1">
                                    <input type="text" wire:model="landingSolutions.{{ $idx }}.icon" placeholder="⚡" class="w-12 text-center px-2 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-base font-bold" title="{{ __('Icon or Emoji') }}">
                                    <input type="text" wire:model="landingSolutions.{{ $idx }}.title" placeholder="{{ __('Pillar Title (e.g. Sub-Second Speed & Offline-Ready)') }}" class="flex-1 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                                </div>
                                <button type="button" wire:click="removeSolution({{ $idx }})" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer" title="{{ __('Delete this pillar') }}">
                                    ✕
                                </button>
                            </div>

                            <div>
                                <textarea wire:model="landingSolutions.{{ $idx }}.body" rows="2" placeholder="{{ __('Detailed description of this solution capability...') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No custom pillars added. Default architectural pillars will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 5: APP DOWNLOADS                                      -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'downloads'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.downloads.badge" placeholder="{{ __('Native Apps') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.downloads.title" placeholder="{{ __('Take the counter anywhere') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.downloads.subtitle" rows="2" placeholder="{{ __('Install the native Android or Windows app for offline-first speed, hardware integration and a full-screen terminal experience.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <!-- Download Links Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🤖</span> {{ __('Google Play Store App') }}
                        </span>
                        <input type="checkbox" wire:model="landingPlaystoreEnabled" class="w-4 h-4 rounded text-indigo-600">
                    </label>
                    <input type="text" wire:model="landingPlaystoreUrl" placeholder="https://play.google.com/store/apps/details?id=..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-mono">
                    <p class="text-[11px] text-slate-400">{{ __('Shown as a verified Play Store badge on the landing page & footer') }}</p>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>💻</span> {{ __('Windows Desktop POS App') }}
                        </span>
                        <input type="checkbox" wire:model="landingWindowsEnabled" class="w-4 h-4 rounded text-indigo-600">
                    </label>
                    <input type="text" wire:model="landingWindowsUrl" placeholder="https://downloads.example.com/POS-Setup.exe" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-mono">
                    <p class="text-[11px] text-slate-400">{{ __('Direct installer download for counter terminals & Windows POS') }}</p>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 6: LIVE METRICS & STATS                               -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'stats'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.stats.badge" placeholder="{{ __('Platform Telemetry') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.stats.title" placeholder="{{ __('Verified Live Telemetry & Metrics') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <input type="text" wire:model="sectionMeta.stats.subtitle" placeholder="{{ __('Battle-tested reliability across all connected retail & restaurant counters') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>

            <!-- Dynamic Stats Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>📊</span> {{ __('Numerical Counters & Trust Metrics') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete numeric stat cards') }}</span>
                    </div>
                    <button type="button" wire:click="addStat" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Stat Counter') }}
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse ($landingStats as $idx => $st)
                        <div wire:key="stat-item-{{ $idx }}" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-center gap-3">
                            <div class="space-y-1 flex-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Metric Value / Number') }}</label>
                                <input type="text" wire:model="landingStats.{{ $idx }}.value" placeholder="{{ __('e.g. 2.5M+') }}" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-black">
                            </div>

                            <div class="space-y-1 flex-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Metric Label') }}</label>
                                <input type="text" wire:model="landingStats.{{ $idx }}.label" placeholder="{{ __('e.g. Transactions Processed') }}" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold">
                            </div>

                            <button type="button" wire:click="removeStat({{ $idx }})" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer shrink-0 mt-3" title="{{ __('Delete this metric') }}">
                                ✕
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2 col-span-2">{{ __('No custom metrics added. Default 4 stats will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 7: MISSION & ABOUT                                    -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'about'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.about.badge" placeholder="{{ __('Our Mission') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.about.title" placeholder="{{ __('Built for high-velocity stores & modern commerce') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Mission Summary / Subtitle') }}</label>
                    <input type="text" wire:model="sectionMeta.about.subtitle" placeholder="{{ __('Why we exist and who we serve') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Company & Platform Story Body') }}</label>
                    <textarea wire:model="sectionMeta.about.body" rows="5" placeholder="{{ __('Tell your platform story, vision, enterprise philosophy, and how your team empowers retail and restaurant operators...') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium leading-relaxed"></textarea>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 8: CUSTOMER REVIEWS (TESTIMONIALS)                    -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'testimonials'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.testimonials.badge" placeholder="{{ __('Customer Validation') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.testimonials.title" placeholder="{{ __('Trusted by market leaders worldwide') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <input type="text" wire:model="sectionMeta.testimonials.subtitle" placeholder="{{ __('See what high-volume store owners and restaurant managers have to say') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>

            <!-- Dynamic Testimonials Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>💬</span> {{ __('Customer Reviews & Testimonials') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete social proof and feedback cards') }}</span>
                    </div>
                    <button type="button" wire:click="addTestimonial" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add Review') }}
                    </button>
                </div>

                <div class="space-y-3">
                    @forelse ($landingTestimonials as $idx => $review)
                        <div wire:key="review-item-{{ $idx }}" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Review Quote') }}</label>
                                    <textarea wire:model="landingTestimonials.{{ $idx }}.quote" rows="2" placeholder="{{ __('e.g. Cut our closing time in half. Cash reconciliation and inventory variance errors dropped to zero.') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                                </div>
                                <button type="button" wire:click="removeTestimonial({{ $idx }})" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer shrink-0 mt-4" title="{{ __('Delete this review') }}">
                                    ✕
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Author Name') }}</label>
                                    <input type="text" wire:model="landingTestimonials.{{ $idx }}.name" placeholder="{{ __('e.g. Marcus Sterling') }}" class="w-full px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ __('Author Role & Business') }}</label>
                                    <input type="text" wire:model="landingTestimonials.{{ $idx }}.role" placeholder="{{ __('e.g. Operations VP · Sterling Hospitality (14 Locations)') }}" class="w-full px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No custom testimonials added. Built-in customer reviews will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 9: PRICING PLANS                                      -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'pricing'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.pricing.badge" placeholder="{{ __('Predictable Investment') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.pricing.title" placeholder="{{ __('Simple, transparent pricing for every tier') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.pricing.subtitle" rows="2" placeholder="{{ __('Launch in minutes with zero setup fees. Choose monthly or annual billing.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <span class="text-xs font-black text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                        <span>💳</span> {{ __('Subscription Plans & Feature Matrices') }}
                    </span>
                    <p class="text-xs text-indigo-700/80 dark:text-indigo-300/80 max-w-xl leading-relaxed">
                        {{ __('Pricing plans (Starter, Growth, Enterprise), currencies, monthly/annual prices, and enabled feature modules are dynamically synced with the SaaS Subscription Plans engine.') }}
                    </p>
                </div>

                <a href="{{ route('superadmin.plans.index') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shrink-0 inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <span>{{ __('Manage SaaS Plans') }}</span>
                    <span>→</span>
                </a>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 10: FAQ                                               -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'faq'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.faq.badge" placeholder="{{ __('Answers') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.faq.title" placeholder="{{ __('Frequently asked questions') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.faq.subtitle" rows="2" placeholder="{{ __('Everything you need to know before getting started.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <!-- Dynamic FAQs Repeater -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div>
                        <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>❓</span> {{ __('Frequently Asked Questions & Answers') }}
                        </span>
                        <span class="text-[11px] text-slate-400 block">{{ __('Add, edit or delete Q&A accordions displayed on the landing page') }}</span>
                    </div>
                    <button type="button" wire:click="addFaq" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer">
                        <span>+</span> {{ __('Add FAQ Item') }}
                    </button>
                </div>

                <div class="space-y-3">
                    @forelse ($landingFaqs as $idx => $faq)
                        <div wire:key="faq-item-{{ $idx }}" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2 flex-1">
                                    <span class="text-xs font-bold text-slate-400 font-mono">Q{{ $idx + 1 }}.</span>
                                    <input type="text" wire:model="landingFaqs.{{ $idx }}.q" placeholder="{{ __('Question (e.g. Can I run offline during internet outages?)') }}" class="flex-1 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                                </div>
                                <button type="button" wire:click="removeFaq({{ $idx }})" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs font-bold cursor-pointer" title="{{ __('Delete this question') }}">
                                    ✕
                                </button>
                            </div>

                            <div>
                                <textarea wire:model="landingFaqs.{{ $idx }}.a" rows="2" placeholder="{{ __('Answer (e.g. Yes! Sales queue locally and automatically synchronize once the connection restores.)') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">{{ __('No FAQs configured. Default Q&A accordion will be displayed.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 11: CONTACT INQUIRY                                   -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'contact'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.contact.badge" placeholder="{{ __('Get In Touch') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.contact.title" placeholder="{{ __('Questions before you sign up?') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Overview') }}</label>
                    <textarea wire:model="sectionMeta.contact.subtitle" rows="2" placeholder="{{ __('Send our enterprise solutions team a note and we will reply within 24 hours.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Support Email Address') }}</label>
                    <input type="email" wire:model="supportEmail" placeholder="support@zoomnearby.com" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Support Phone / WhatsApp') }}</label>
                    <input type="text" wire:model="supportPhone" placeholder="+1 (555) 019-2834" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECTION 12: CONVERSION CTA                                    -->
        <!-- ============================================================== -->
        <div x-show="activeSection === 'cta'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Section Badge Pill') }}</label>
                    <input type="text" wire:model="sectionMeta.cta.badge" placeholder="{{ __('Instant Provisioning') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Main Title') }}</label>
                    <input type="text" wire:model="sectionMeta.cta.title" placeholder="{{ __('Ready to speed up your counter?') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold">
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Subtitle / Call to Action') }}</label>
                    <textarea wire:model="sectionMeta.cta.subtitle" rows="2" placeholder="{{ __('Create your store workspace in minutes. No card required.') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium"></textarea>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-500 space-y-1.5">
                <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <span>💡</span> {{ __('Registration Route Connection') }}
                </span>
                <p>
                    {{ __('The primary CTA button automatically links visitors directly to the Tenant Registration portal at') }} <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900 font-mono text-[11px] text-indigo-600 dark:text-indigo-400">{{ route('tenant.register') }}</code>{{ __(' and uses your Primary CTA button label.') }}
                </p>
            </div>
        </div>
    </div>
</div>
