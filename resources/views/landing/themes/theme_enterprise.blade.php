@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Enterprise Retail & Hardware POS Platform')
@section('meta_description', 'High-volume Multi-Store POS, 80mm/58mm Thermal Receipt Printing, Multi-Jurisdiction Fiscal Tax Engine, Cash Register Shift Sessions (Sangria/Suprimento), and Accounts Receivable.')

@php
    $heroBadge = filled($branding->landing_hero_badge) ? $branding->landing_hero_badge : __('Enterprise Multi-Store Suite · Offline First & Hardware Native');
    $heroTitle = filled($branding->landing_hero_title) ? $branding->landing_hero_title : __('Enterprise POS & Retail Cloud Engine for High-Volume Stores');
    $heroSubtitle = filled($branding->landing_hero_subtitle) ? $branding->landing_hero_subtitle : __('Unify multi-store inventory matrices, sub-second 80mm/58mm thermal receipts, fiscal tax engines, daily register float sessions (Sangria/Suprimento), and accounts receivable into one unified system.');
    $ctaPrimaryText = filled($branding->landing_hero_cta_primary_text) ? $branding->landing_hero_cta_primary_text : __('Start Free Trial / Live Demo');
    $ctaPrimaryUrl = filled($branding->landing_hero_cta_primary_url) ? $branding->landing_hero_cta_primary_url : route('tenant.register');
    $ctaSecondaryText = filled($branding->landing_hero_cta_secondary_text) ? $branding->landing_hero_cta_secondary_text : __('Watch Tour & Architecture Video');
    $ctaSecondaryUrl = filled($branding->landing_hero_cta_secondary_url) ? $branding->landing_hero_cta_secondary_url : '#tour';

    $landingFeatures = $branding->landingFeatures();
    $landingHardware = $branding->landingHardware();
    $landingFaqs = $branding->landingFaqs();
    $landingStats = $branding->landingStatsList();
    $landingTestimonials = $branding->landingTestimonials();
@endphp

@section('content')
<div x-data="{
    activeTab: 'multistore',
    activeHardware: 'desktop',
    showTourModal: false,
    annualBilling: false
}" class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 selection:bg-emerald-500 selection:text-slate-950 transition-colors duration-300">

    {{-- ========================================================================= --}}
    {{-- 1. HERO SECTION: HIGH-CONVERTING ENTERPRISE HEADLINE & FLOATING POS MOCKUP --}}
    {{-- ========================================================================= --}}
    <section class="landing-sec-hero relative pt-12 pb-20 sm:pt-20 sm:pb-32 overflow-hidden border-b border-slate-200 dark:border-slate-800">
        <!-- Ambient Grid Background Pattern -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] pointer-events-none"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-emerald-500/20 via-teal-500/10 to-transparent blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-4xl mx-auto">
                <!-- Enterprise Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-400 text-xs font-black uppercase tracking-widest mb-6 shadow-md dark:shadow-lg dark:shadow-emerald-950/50">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                    <span>{{ $heroBadge }}</span>
                </div>

                <!-- Headline -->
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-slate-900 dark:text-white tracking-tight leading-[1.08]">
                    @php
                        $titleParts = explode(' for ', $heroTitle, 2);
                    @endphp
                    @if (count($titleParts) === 2)
                        {{ $titleParts[0] }} {{ __('for') }}<br>
                        <span class="bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500 dark:from-emerald-400 dark:via-teal-300 dark:to-cyan-400 bg-clip-text text-transparent">
                            {{ $titleParts[1] }}
                        </span>
                    @else
                        {{ $heroTitle }}
                    @endif
                </h1>

                <!-- Subheadline -->
                <p class="mt-6 text-base sm:text-xl text-slate-600 dark:text-slate-400 max-w-3xl mx-auto font-normal leading-relaxed">
                    {{ $heroSubtitle }}
                </p>

                <!-- Dual Action Triggers -->
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ $ctaPrimaryUrl }}"
                       class="px-8 py-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-sm font-black transition-all shadow-xl shadow-emerald-500/25 active:scale-95 flex items-center gap-2.5">
                        <span>🚀 {{ $ctaPrimaryText }}</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>

                    <button type="button"
                            @click="showTourModal = true"
                            class="px-7 py-4 rounded-2xl bg-white dark:bg-slate-900/90 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white text-sm font-bold border border-slate-200 dark:border-slate-700 transition shadow-lg flex items-center gap-2.5 cursor-pointer">
                        <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">▶</span>
                        <span>{{ $ctaSecondaryText }}</span>
                    </button>
                </div>

                <x-landing.download-buttons :branding="$branding" class="mt-6 justify-center" />

                <!-- Live Metrics Bar -->
                <div class="mt-12 pt-8 border-t border-slate-200 dark:border-slate-800/80 grid grid-cols-2 sm:grid-cols-4 gap-4 text-left">
                    @if (!empty($landingStats))
                        @foreach (array_slice($landingStats, 0, 4) as $stat)
                            <div class="p-3 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm">
                                <div class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">{{ $stat['label'] ?? '' }}</div>
                                <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $stat['value'] ?? '' }}</div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">{{ __('Checkout Latency') }}</div>
                            <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-0.5">&lt; 15ms</div>
                        </div>
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">{{ __('Thermal Printing') }}</div>
                            <div class="text-lg font-black text-slate-900 dark:text-white mt-0.5">80mm / 58mm ESC</div>
                        </div>
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">{{ __('Register Sessions') }}</div>
                            <div class="text-lg font-black text-teal-600 dark:text-teal-400 mt-0.5">X/Z Auto Balance</div>
                        </div>
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <div class="text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">{{ __('Tax Compliance') }}</div>
                            <div class="text-lg font-black text-slate-900 dark:text-white mt-0.5">Multi-Jurisdiction</div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Interactive Floating POS Interface Mockup -->
            <div class="mt-14 relative max-w-5xl mx-auto">
                <div class="rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700/80 shadow-2xl p-4 sm:p-6 backdrop-blur-2xl relative overflow-hidden">
                    
                    <!-- Top POS Window Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="flex gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            </div>
                            <div class="h-4 w-[1px] bg-slate-200 dark:bg-slate-800"></div>
                            <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-700 dark:text-slate-300">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-ping"></span>
                                <span>POS-TILL #01 · MAIN STORE CASH REGISTER</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[11px] font-mono font-bold">
                                💵 Cash Drawer: $450.00 Float
                            </span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-mono">
                                Session #2089
                            </span>
                        </div>
                    </div>

                    <!-- POS Grid Content -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-4">
                        <!-- Left 7 cols: Catalog & Live Barcode Scan -->
                        <div class="lg:col-span-7 space-y-3">
                            <!-- Scanner Bar -->
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 font-mono flex-1">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">🔍 SCAN:</span>
                                    <span class="text-slate-800 dark:text-slate-200">COF-482910 | High-Grade Espresso Roast 1kg</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-bold text-[10px]">+ Added (12ms)</span>
                            </div>

                            <!-- Cart Items -->
                            <div class="space-y-2 max-h-56 overflow-y-auto no-scrollbar">
                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white">Artisan Coffee Roast 1kg</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">SKU: COF-4829 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">2 × $14.50 = $29.00</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">Tax: $2.46</div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white">Gourmet Extra Virgin Olive Oil 500ml</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">SKU: OIL-9104 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">1 × $18.20 = $18.20</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">Tax: $1.55</div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white">Cold-Pressed Juice Bottle 330ml</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">SKU: JUC-0012 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">3 × $4.50 = $13.50</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">Tax: $1.15</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right 5 cols: Split Payment & Live Slip Preview -->
                        <div class="lg:col-span-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-200 dark:border-slate-800">
                                    <span>{{ __('Subtotal (3 lines)') }}</span>
                                    <span class="font-mono text-slate-900 dark:text-white font-bold">$60.70</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 py-1.5">
                                    <span>{{ __('Fiscal VAT (8.5%)') }}</span>
                                    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">$5.16</span>
                                </div>
                                <div class="flex items-center justify-between text-sm text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800">
                                    <span class="font-black">{{ __('TOTAL DUE') }}</span>
                                    <span class="font-mono text-xl font-black text-emerald-600 dark:text-emerald-400">$65.86</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-[10px] font-mono uppercase text-slate-500 dark:text-slate-400 font-bold">{{ __('Split Tender Tendered') }}:</div>
                                <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                                        💵 Cash: <strong class="text-slate-900 dark:text-white">$30.00</strong>
                                    </div>
                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                                        💳 Card: <strong class="text-slate-900 dark:text-white">$35.86</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                                <button type="button" class="flex-1 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition">
                                    🖨️ {{ __('Print 80mm ESC Receipt') }}
                                </button>
                                <span class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400" title="Fiscal QR Code">📱 QR</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Floating Thermal Receipt Card Badge -->
                <div class="absolute -right-4 -bottom-8 hidden md:block w-72 rounded-2xl bg-white dark:bg-slate-900 text-slate-950 dark:text-white p-4 shadow-2xl border border-slate-300 dark:border-slate-700 font-mono text-[11px] rotate-1 hover:rotate-0 transition-transform">
                    <div class="text-center font-bold pb-2 border-b border-dashed border-slate-400 dark:border-slate-600">
                        <div>=== {{ $branding->platform_name }} ===</div>
                        <div class="text-[9px] text-slate-500 dark:text-slate-400">STORE #01 · FISCAL RECEIPT #2089</div>
                    </div>
                    <div class="py-2 space-y-1 text-slate-700 dark:text-slate-300">
                        <div class="flex justify-between"><span>2x Espresso Roast</span><span>$29.00</span></div>
                        <div class="flex justify-between"><span>1x Olive Oil 500ml</span><span>$18.20</span></div>
                        <div class="flex justify-between"><span>3x Fresh Juice</span><span>$13.50</span></div>
                    </div>
                    <div class="pt-2 border-t border-dashed border-slate-400 dark:border-slate-600 flex justify-between font-bold text-xs text-slate-900 dark:text-white">
                        <span>TOTAL PAID</span>
                        <span>$65.86</span>
                    </div>
                    <div class="mt-2 text-center text-[8px] text-slate-400 dark:text-slate-500">
                        [||||||||||||||||||||||||||||||||||||||]
                        <div>AUT: 9812-4910-8401</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 2. MODULE CAPABILITIES TOUR: EDITABLE FEATURES & CORE ENTERPRISE PILLARS  --}}
    {{-- ========================================================================= --}}
    <section id="tour" class="landing-sec-features py-20 sm:py-28 w-full border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-black uppercase tracking-wider mb-3">
                {{ $branding->getSectionBadge('features', __('Deep Architecture Tour')) }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ $branding->getSectionTitle('features', __('5 Enterprise Capabilities Built for Scale')) }}
            </h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto text-sm sm:text-base">
                {{ $branding->getSectionSubtitle('features', __('Engineered specifically according to retail franchise workflows: multi-branch syncing, hardware printing, fiscal engine, drawer sessions, and credit ledgers.')) }}
            </p>
        </div>

        {{-- Dynamic Admin Configured Feature Cards (Editable from SuperAdmin Studio) --}}
        @if (!empty($landingFeatures))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
                @foreach ($landingFeatures as $feat)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition">
                        <div class="text-3xl mb-3">{{ $feat['icon'] ?: '✨' }}</div>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white">{{ $feat['title'] ?? '' }}</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 leading-relaxed whitespace-pre-line">{{ $feat['body'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Capability Tabs Navigation -->
        <div class="flex items-center justify-center gap-2 flex-wrap mb-10">
            <button type="button" @click="activeTab = 'multistore'"
                    :class="activeTab === 'multistore' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-bold transition cursor-pointer flex items-center gap-2">
                <span>🏢</span> <span>{{ __('Multi-Store POS') }}</span>
            </button>

            <button type="button" @click="activeTab = 'thermal'"
                    :class="activeTab === 'thermal' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-bold transition cursor-pointer flex items-center gap-2">
                <span>🖨️</span> <span>{{ __('80mm/58mm Thermal Printing') }}</span>
            </button>

            <button type="button" @click="activeTab = 'fiscal'"
                    :class="activeTab === 'fiscal' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-bold transition cursor-pointer flex items-center gap-2">
                <span>🏛️</span> <span>{{ __('Fiscal Tax Engine') }}</span>
            </button>

            <button type="button" @click="activeTab = 'cash'"
                    :class="activeTab === 'cash' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-bold transition cursor-pointer flex items-center gap-2">
                <span>💵</span> <span>{{ __('Cash Drawer & Sangria / Suprimento') }}</span>
            </button>

            <button type="button" @click="activeTab = 'ar'"
                    :class="activeTab === 'ar' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-bold transition cursor-pointer flex items-center gap-2">
                <span>📊</span> <span>{{ __('Accounts Receivable (AR)') }}</span>
            </button>
        </div>

        <!-- Capability Tab Contents -->
        <div class="rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 p-6 sm:p-10 shadow-xl relative overflow-hidden">
            
            <!-- 1. MULTI-STORE POS -->
            <div x-show="activeTab === 'multistore'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">🏢</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('Centralized Multi-Location Catalog & Stock Matrix') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        {{ __('Manage unified SKU barcodes across 100+ stores. Control regional pricing, transfer inventory between branches with transfer receipt audit trails, and view consolidated sales analytics in real-time.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-700 dark:text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Store-to-store stock transfer workflows with transit reconciliation') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Granular employee permissions per terminal & branch') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Synchronized promotions, discount coupons & customer loyalty') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-3">
                    <div class="flex justify-between items-center text-[10px] text-emerald-600 dark:text-emerald-400 pb-2 border-b border-slate-200 dark:border-slate-800">
                        <span>MULTI-STORE SYNC MONITOR</span>
                        <span>4 LOCATIONS ACTIVE</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between p-2 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <span>Main Warehouse HQ</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">14,200 SKUs (100% Synced)</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <span>Branch 01 (Downtown)</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">3,890 SKUs (Live)</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <span>Branch 02 (Uptown Mall)</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">2,940 SKUs (Live)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. THERMAL PRINTING -->
            <div x-show="activeTab === 'thermal'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">🖨️</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('Driverless 80mm & 58mm Thermal Printing') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        {{ __('Direct hardware integration for USB, Bluetooth, and Ethernet POS printers. Instant raw ESC/POS receipt generation with store logos, itemized tax breakdowns, and electronic invoice QR codes.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-700 dark:text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Support for Epson, Star, Xprinter, Sunmi & standard ESC/POS hardware') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Sub-second raw print dispatch without OS print dialog popups') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Automatic cash drawer kick trigger upon receipt completion') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 pb-2 border-b border-slate-200 dark:border-slate-800">HARDWARE PRINTER ROUTER</div>
                    <div class="p-3 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5 text-[11px]">
                        <div>PRINTER: EPSON TM-T88VI (80mm Thermal)</div>
                        <div>INTERFACE: USB / Raw ESC/POS Buffer</div>
                        <div>STATUS: <span class="text-emerald-600 dark:text-emerald-400 font-bold">CONNECTED · 0.04s SPEED</span></div>
                    </div>
                </div>
            </div>

            <!-- 3. FISCAL TAX ENGINE -->
            <div x-show="activeTab === 'fiscal'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">🏛️</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('Multi-Jurisdiction Fiscal Tax Engine') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        {{ __('Compliant tax calculation engine accommodating compound taxes, VAT, GST, state sales taxes, and zero-rated export rules with continuous sequence numbering and audit logs.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-700 dark:text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Inclusive vs. Exclusive tax computation per product category') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Automated tax reports ready for monthly accountant submission') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Tamper-proof sequential invoice IDs and audit trail records') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 pb-2 border-b border-slate-200 dark:border-slate-800">TAX RULE MATRIX (ACTIVE)</div>
                    <div class="space-y-2">
                        <div class="flex justify-between p-2 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <span>Standard Retail VAT (8.5%)</span>
                            <span class="text-slate-900 dark:text-white font-bold">Auto-Calculated</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <span>Municipal Hospitality Surcharge (2.0%)</span>
                            <span class="text-slate-900 dark:text-white font-bold">Category Dining</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. CASH REGISTER & SANGRIA / SUPRIMENTO -->
            <div x-show="activeTab === 'cash'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">💵</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('Daily Cash Register Sessions: Float, Sangria & Suprimento') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        {{ __('Complete cash drawer audit controls. Record morning opening floats, handle midday cash bleed (Sangria / Cash Drop), inject small change (Suprimento), and perform blind shift closing with automated X & Z report slips.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-700 dark:text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Sangria (Cash Drop / Payout) with reason & manager PIN authentication') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Suprimento (Cash Injection) tracking for initial till floats') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Blind cash count reconciliation: System balance vs. counted cash variance') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-3">
                    <div class="flex justify-between text-[10px] text-emerald-600 dark:text-emerald-400 pb-2 border-b border-slate-200 dark:border-slate-800">
                        <span>SHIFT SESSION #2089</span>
                        <span>STATUS: OPEN</span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span>Opening Float (Suprimento):</span><span class="text-slate-900 dark:text-white font-bold">$200.00</span></div>
                        <div class="flex justify-between"><span>Cash Sales:</span><span class="text-emerald-600 dark:text-emerald-400 font-bold">+$1,450.20</span></div>
                        <div class="flex justify-between"><span>Midday Safe Drop (Sangria):</span><span class="text-rose-600 dark:text-rose-400 font-bold">-$800.00</span></div>
                        <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 font-bold text-slate-900 dark:text-white"><span>Expected Drawer Cash:</span><span class="text-emerald-600 dark:text-emerald-400">$850.20</span></div>
                    </div>
                </div>
            </div>

            <!-- 5. ACCOUNTS RECEIVABLE -->
            <div x-show="activeTab === 'ar'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl">📊</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('Accounts Receivable (AR) & Customer Credit Lines') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                        {{ __('Issue store credit, track customer outstanding tabs, record partial installment payments, and automatically send statement summaries via WhatsApp & email.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-700 dark:text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Customer credit limits with automatic checkout lockout when exceeded') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('Aging balance reports (30 / 60 / 90 days overdue)') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> {{ __('One-click digital payment links dispatched directly to customer phones') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-700 dark:text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 pb-2 border-b border-slate-200 dark:border-slate-800">CUSTOMER LEDGER OVERVIEW</div>
                    <div class="p-3 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex justify-between font-bold text-slate-900 dark:text-white">
                            <span>Apex Commercial Group</span>
                            <span class="text-amber-600 dark:text-amber-400">Balance: $2,400.00</span>
                        </div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Credit Limit: $5,000.00 · Last Payment: $1,000.00 (3 days ago)</div>
                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">Status: In Good Standing (Prompt Payer)</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

    {{-- ========================================================================= --}}
    {{-- 3. HARDWARE ECOSYSTEM: DESKTOP, TABLET STAND & MOBILE TOUCH HANDHELD      --}}
    {{-- ========================================================================= --}}
    <section class="landing-sec-trust py-20 sm:py-28 bg-slate-100/60 dark:bg-slate-900/40 border-y border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-700 dark:text-teal-400 text-xs font-black uppercase tracking-wider mb-3">
                    {{ $branding->getSectionBadge('trust_bar', __('Hardware Freedom')) }}
                </span>
                <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $branding->getSectionTitle('trust_bar', __('Universal Hardware Compatibility')) }}
                </h2>
                <p class="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto text-sm sm:text-base">
                    {{ $branding->getSectionSubtitle('trust_bar', __('Run on any existing device without expensive proprietary hardware lock-ins.')) }}
                </p>
            </div>

            <!-- Hardware Mode Switcher -->
            <div class="flex items-center justify-center gap-3 mb-10">
                <button type="button" @click="activeHardware = 'desktop'"
                        :class="activeHardware === 'desktop' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2 font-semibold">
                    <span>🖥️</span> <span>{{ __('Desktop Browser & POS Barcode Gun') }}</span>
                </button>
                <button type="button" @click="activeHardware = 'tablet'"
                        :class="activeHardware === 'tablet' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2 font-semibold">
                    <span>📱</span> <span>{{ __('Tablet Stand Countertop Mode') }}</span>
                </button>
                <button type="button" @click="activeHardware = 'mobile'"
                        :class="activeHardware === 'mobile' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2 font-semibold">
                    <span>📲</span> <span>{{ __('Mobile Touch & PDA Handheld') }}</span>
                </button>
            </div>

            <!-- Hardware Showcase Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- 1. Desktop -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border transition-all duration-300 shadow-sm"
                     :class="activeHardware === 'desktop' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-800'">
                    <div class="text-3xl mb-4">🖥️</div>
                    <h4 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Desktop & Countertop PC') }}</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                        {{ __('Full keyboard navigation, instant USB barcode gun integration, multi-monitor customer displays, and dual cash drawer support.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                        <span>●</span> <span>{{ __('Windows, macOS & Linux Ready') }}</span>
                    </div>
                </div>

                <!-- 2. Tablet Stand -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border transition-all duration-300 shadow-sm"
                     :class="activeHardware === 'tablet' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-800'">
                    <div class="text-3xl mb-4">📱</div>
                    <h4 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Countertop Tablet Stand') }}</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                        {{ __('Sleek, touch-optimized POS interface. Ideal for boutiques, specialty coffee shops, and dining floor servers.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                        <span>●</span> <span>{{ __('iPad, Android Tablet & ChromeOS') }}</span>
                    </div>
                </div>

                <!-- 3. Mobile Touch Handheld -->
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border transition-all duration-300 shadow-sm"
                     :class="activeHardware === 'mobile' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200 dark:border-slate-800'">
                    <div class="text-3xl mb-4">📲</div>
                    <h4 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Mobile Handheld POS') }}</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                        {{ __('Ring up sales on the sales floor, scan barcodes via built-in camera, and print mobile receipts on handheld wireless terminals.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                        <span>●</span> <span>{{ __('Sunmi, PAX, Android PDA & iPhone') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 4. SUBSCRIPTION TIERS: MONTHLY VS. YEARLY SWITCH & DIRECT CHECKOUT HOOKS --}}
    {{-- ========================================================================= --}}
    @include('landing.pricing', ['plans' => $plans, 'branding' => $branding])

    {{-- Customer Testimonials (Editable from SuperAdmin Studio) --}}
    @if (!empty($landingTestimonials))
    <section id="testimonials" class="landing-sec-testimonials py-16 sm:py-24 w-full border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-black uppercase tracking-wider mb-3">
                    {{ $branding->getSectionBadge('testimonials', __('Customer Proof')) }}
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $branding->getSectionTitle('testimonials', __('Trusted by High-Volume Retailers')) }}
                </h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($landingTestimonials as $testim)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                        <p class="text-xs text-slate-600 dark:text-slate-300 italic leading-relaxed">"{{ $testim['quote'] ?? '' }}"</p>
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-black text-xs flex items-center justify-center">
                                {{ mb_substr($testim['name'] ?? 'C', 0, 1) }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $testim['name'] ?? '' }}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ $testim['role'] ?? '' }} · {{ $testim['company'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- FAQs Section (Editable from SuperAdmin Studio) --}}
    @if (!empty($landingFaqs))
    <section id="faq" class="landing-sec-faq py-16 sm:py-24 w-full border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-black uppercase tracking-wider mb-3">
                    {{ $branding->getSectionBadge('faq', __('FAQ')) }}
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $branding->getSectionTitle('faq', __('Frequently Asked Questions')) }}
                </h2>
            </div>
            <div class="space-y-4">
                @foreach ($landingFaqs as $faq)
                    <details class="group p-5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm transition">
                        <summary class="flex items-center justify-between cursor-pointer font-bold text-sm text-slate-900 dark:text-white list-none">
                            <span>{{ $faq['q'] ?? ($faq['question'] ?? '') }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ $faq['a'] ?? ($faq['answer'] ?? '') }}
                        </p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Contact Inquiries Section --}}
    @if ($branding->isSectionEnabled('contact'))
        <x-landing.contact :branding="$branding" />
    @endif

    {{-- Tour Modal Triggered by 'Watch Tour' --}}
    <div x-show="showTourModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div @click.outside="showTourModal = false"
             class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-6 max-w-2xl w-full shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🎬</span> {{ __('Architecture & POS Hardware Tour') }}
                </h3>
                <button type="button" @click="showTourModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg font-black">&times;</button>
            </div>
            
            <div class="aspect-video rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center p-6 text-center space-y-3">
                <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-3xl">
                    ▶
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Interactive Hardware & Multi-Store Video Tour') }}</div>
                <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md">
                    {{ __('Demonstrating 80mm ESC/POS thermal printing, multi-store stock transfers, and daily register float sessions.') }}
                </p>
                <a href="{{ route('tenant.register') }}" class="px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition">
                    {{ __('Launch Free Interactive Trial Now') }} →
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
