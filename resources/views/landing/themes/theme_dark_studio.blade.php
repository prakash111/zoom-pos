@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Dark Studio POS & Inventory Command Center')
@section('meta_description', 'High-performance Dark Studio POS and real-time inventory management command center for tech-forward retail and dining stores.')

@php
    $heroBadge = filled($branding->landing_hero_badge) ? $branding->landing_hero_badge : __('Dark Studio POS · Native Velocity Command Suite');
    $heroTitle = filled($branding->landing_hero_title) ? $branding->landing_hero_title : __('Next-Gen Cloud POS Built for Peak-Performance Retail');
    $heroSubtitle = filled($branding->landing_hero_subtitle) ? $branding->landing_hero_subtitle : __('An uncompromising command center for high-velocity checkout, stock radar analytics, kitchen display queues, and multi-tender ledgers.');
    $ctaPrimaryText = filled($branding->landing_hero_cta_primary_text) ? $branding->landing_hero_cta_primary_text : __('Deploy Studio Workspace');
    $ctaPrimaryUrl = filled($branding->landing_hero_cta_primary_url) ? $branding->landing_hero_cta_primary_url : route('tenant.register');
    $ctaSecondaryText = filled($branding->landing_hero_cta_secondary_text) ? $branding->landing_hero_cta_secondary_text : __('Live Terminal Login');
    $ctaSecondaryUrl = filled($branding->landing_hero_cta_secondary_url) ? $branding->landing_hero_cta_secondary_url : route('tenant.login');
    $features = $branding->landingFeatures();
    $faqs = $branding->landingFaqs();
@endphp

@section('content')
<div class="bg-slate-50 text-slate-900 dark:bg-[#0b0c14] dark:text-slate-100 min-h-screen selection:bg-purple-500 selection:text-white transition-colors duration-300" x-data="{ studioTab: 'pos', annual: false }">

    <!-- Studio Ambient Glows -->
    <div class="fixed top-0 left-1/4 w-[600px] h-[600px] bg-purple-600/10 rounded-full blur-[150px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-0 right-1/4 w-[600px] h-[600px] bg-cyan-600/10 rounded-full blur-[150px] pointer-events-none -z-10"></div>

    <!-- Studio Hero -->
    <section class="landing-sec-hero w-full pt-16 pb-24 text-center relative z-10 border-b border-slate-200 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-500/30 text-purple-700 dark:text-purple-300 text-xs font-mono font-bold uppercase tracking-wider mb-6 shadow-md dark:shadow-lg dark:shadow-purple-950/50">
                <span class="w-2 h-2 rounded-full bg-purple-500 dark:bg-purple-400 animate-ping"></span>
                <span>{{ $heroBadge }}</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-slate-900 dark:text-white tracking-tight leading-[1.08]">
                @php
                    $titleParts = explode(' for ', $heroTitle, 2);
                @endphp
                @if (count($titleParts) === 2)
                    {{ $titleParts[0] }} {{ __('for') }}<br>
                    <span class="bg-gradient-to-r from-purple-600 via-pink-600 to-indigo-600 dark:from-purple-400 dark:via-pink-400 dark:to-cyan-400 bg-clip-text text-transparent">
                        {{ $titleParts[1] }}
                    </span>
                @else
                    <span class="bg-gradient-to-r from-purple-600 via-pink-600 to-indigo-600 dark:from-purple-400 dark:via-pink-400 dark:to-cyan-400 bg-clip-text text-transparent">
                        {{ $heroTitle }}
                    </span>
                @endif
            </h1>

            <p class="mt-6 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto font-normal leading-relaxed">
                {{ $heroSubtitle }}
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ $ctaPrimaryUrl }}"
                   class="px-8 py-4 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-black text-sm transition shadow-xl shadow-purple-600/30 active:scale-95 flex items-center gap-2">
                    <span>✨ {{ $ctaPrimaryText }}</span>
                    <span>→</span>
                </a>
                <a href="{{ $ctaSecondaryUrl }}"
                   class="px-7 py-4 rounded-2xl bg-white dark:bg-slate-900/80 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-bold border border-slate-200 dark:border-slate-700/80 transition flex items-center gap-2 shadow-sm">
                    <span>⚡ {{ $ctaSecondaryText }}</span>
                </a>
            </div>

            <x-landing.download-buttons :branding="$branding" class="mt-6 justify-center" />

            <!-- Studio Interactive Terminal Showcase -->
            <div class="mt-16 max-w-5xl mx-auto rounded-3xl bg-white dark:bg-[#0f111e] border border-slate-200 dark:border-purple-500/20 shadow-2xl p-4 sm:p-6 backdrop-blur-2xl text-left relative overflow-hidden">
                <!-- Studio Tab Switcher -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="ml-3 text-xs font-mono text-slate-500 dark:text-slate-400">STUDIO://CORE.ENGINE</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" @click="studioTab = 'pos'"
                                :class="studioTab === 'pos' ? 'bg-purple-100 text-purple-800 dark:bg-purple-600/20 dark:text-purple-300 border border-purple-300 dark:border-purple-500/40 font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs font-mono transition cursor-pointer">
                            POS Terminal
                        </button>
                        <button type="button" @click="studioTab = 'matrix'"
                                :class="studioTab === 'matrix' ? 'bg-purple-100 text-purple-800 dark:bg-purple-600/20 dark:text-purple-300 border border-purple-300 dark:border-purple-500/40 font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs font-mono transition cursor-pointer">
                            Live Matrix
                        </button>
                        <button type="button" @click="studioTab = 'kot'"
                                :class="studioTab === 'kot' ? 'bg-purple-100 text-purple-800 dark:bg-purple-600/20 dark:text-purple-300 border border-purple-300 dark:border-purple-500/40 font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs font-mono transition cursor-pointer">
                            Kitchen KDS
                        </button>
                    </div>
                </div>

                <!-- Tab 1: Studio POS Screen -->
                <div x-show="studioTab === 'pos'" class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-8 space-y-3">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-black/40 border border-slate-200 dark:border-purple-500/10 font-mono text-xs text-purple-700 dark:text-purple-300 flex justify-between">
                            <span>▶ SKU SCAN: PAS-7741</span>
                            <span class="text-teal-600 dark:text-cyan-400">12ms LATENCY</span>
                        </div>

                        <div class="space-y-1.5 font-mono text-xs">
                            <div class="flex justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                                <span class="text-slate-700 dark:text-slate-300">Handcrafted Penne Pasta (x2)</span>
                                <span class="font-bold text-slate-900 dark:text-white">$12.00</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                                <span class="text-slate-700 dark:text-slate-300">Cold-Pressed Olive Oil 500ml</span>
                                <span class="font-bold text-slate-900 dark:text-white">$18.50</span>
                            </div>
                            <div class="flex justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                                <span class="text-slate-700 dark:text-slate-300">Ethiopian Single Roast 250g</span>
                                <span class="font-bold text-slate-900 dark:text-white">$19.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-4 p-4 rounded-2xl bg-gradient-to-b from-purple-50 to-indigo-50 dark:from-purple-950/40 dark:to-indigo-950/40 border border-purple-200 dark:border-purple-500/30 flex flex-col justify-between font-mono">
                        <div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">SUBTOTAL: $49.50</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">TAX (8.25%): $0.00</div>
                            <div class="text-xs text-purple-700 dark:text-purple-300 font-bold mt-2">TOTAL DUE: $49.50</div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-purple-200 dark:border-purple-500/30">
                            <button type="button" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-black text-xs transition shadow-lg shadow-purple-600/30">
                                ⚡ INSTANT PAY
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Studio Live Matrix -->
                <div x-show="studioTab === 'matrix'" x-cloak class="py-4 space-y-3 font-mono text-xs">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/5">
                            <div class="text-slate-500 dark:text-slate-400 text-[10px]">TOTAL CHANNELS</div>
                            <div class="text-lg font-black text-purple-600 dark:text-purple-300 mt-1">12 Active</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/5">
                            <div class="text-slate-500 dark:text-slate-400 text-[10px]">REAL-TIME ORDERS</div>
                            <div class="text-lg font-black text-teal-600 dark:text-cyan-300 mt-1">348 Orders</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/5">
                            <div class="text-slate-500 dark:text-slate-400 text-[10px]">STOCK TURNOVER</div>
                            <div class="text-lg font-black text-pink-600 dark:text-pink-300 mt-1">99.4% Velocity</div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Studio KDS -->
                <div x-show="studioTab === 'kot'" x-cloak class="py-4 space-y-2 font-mono text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
                            <div class="flex justify-between font-bold text-rose-700 dark:text-rose-300">
                                <span>TICKET #402 (TABLE 4)</span>
                                <span>01:42</span>
                            </div>
                            <div class="mt-2 text-slate-700 dark:text-slate-300 text-[11px] space-y-0.5">
                                <div>• 2x Penne Arrabbiata (Extra Spicy)</div>
                                <div>• 1x Truffle Risotto</div>
                            </div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
                            <div class="flex justify-between font-bold text-emerald-700 dark:text-emerald-300">
                                <span>TICKET #403 (BAR 1)</span>
                                <span>00:28</span>
                            </div>
                            <div class="mt-2 text-slate-700 dark:text-slate-300 text-[11px] space-y-0.5">
                                <div>• 2x Espresso Double</div>
                                <div>• 1x Iced Almond Latte</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Studio Features (Editable from SuperAdmin Studio) -->
    @if (!empty($features))
    <section class="landing-sec-features w-full py-16 border-b border-slate-200 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="px-3 py-1 rounded-full bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-500/30 text-purple-700 dark:text-purple-300 text-xs font-mono font-bold">
                    {{ $branding->getSectionBadge('features', __('CORE PLATFORM CAPABILITIES')) }}
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mt-3">{{ $branding->getSectionTitle('features', __('Engineered for High Velocity')) }}</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($features as $feat)
                    <div class="p-6 rounded-3xl bg-white dark:bg-[#0f111e] border border-slate-200 dark:border-purple-500/20 shadow-sm hover:border-purple-400 dark:hover:border-purple-500/50 transition">
                        <div class="text-3xl mb-3">{{ $feat['icon'] ?: '⚡' }}</div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white font-mono">{{ $feat['title'] ?? '' }}</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 leading-relaxed whitespace-pre-line">{{ $feat['body'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Studio Pricing (Editable from Plans) -->
    @include('landing.pricing', ['plans' => $plans, 'branding' => $branding])

    <!-- Studio FAQs (Editable from SuperAdmin Studio) -->
    @if (!empty($faqs))
    <section class="landing-sec-faq w-full py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <h3 class="text-2xl font-black text-slate-900 dark:text-white text-center mb-8 font-mono">{{ $branding->getSectionTitle('faq', __('Frequently Asked Questions')) }}</h3>
            <div class="space-y-3">
                @foreach ($faqs as $faq)
                    <details class="group p-4 rounded-2xl bg-white dark:bg-[#0f111e] border border-slate-200 dark:border-purple-500/20 shadow-sm transition">
                        <summary class="flex items-center justify-between cursor-pointer font-bold text-sm text-slate-900 dark:text-white list-none font-mono">
                            <span>{{ $faq['q'] ?? ($faq['question'] ?? '') }}</span>
                            <span class="text-purple-500 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-mono">
                            {{ $faq['a'] ?? ($faq['answer'] ?? '') }}
                        </p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Studio Contact Inquiries (Editable from SuperAdmin Studio) -->
    @if ($branding->isSectionEnabled('contact'))
    <section id="contact" class="landing-sec-contact w-full py-16 border-t border-slate-200 dark:border-slate-800/80">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-10">
                <span class="text-xs font-black uppercase tracking-widest text-purple-600 dark:text-purple-400 font-mono block mb-2">
                    {{ $branding->getSectionBadge('contact', __('Direct Channels')) }}
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white font-mono">
                    {{ $branding->getSectionTitle('contact', __('Deploy Custom Retail Architectures')) }}
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-400 font-mono mt-2 max-w-xl mx-auto">
                    {{ $branding->getSectionSubtitle('contact', __('Need dedicated custom drivers, multi-warehouse replication, or ERP bridging? Connect with our systems team.')) }}
                </p>
            </div>
            <div class="p-6 sm:p-10 rounded-3xl bg-white dark:bg-[#0f111e] border border-slate-200 dark:border-purple-500/20 shadow-xl">
                <x-landing.contact-form />
            </div>
        </div>
    </section>
    @endif

</div>
@endsection
