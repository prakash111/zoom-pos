@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Fast Cloud POS, Inventory & Restaurant Management')
@section('meta_description', $branding->platform_name . ' — a fast, offline-ready POS with multi-branch inventory, restaurant KOT and automated invoicing for retail stores and food businesses.')

@php
    $heroBadge = $branding->getHeroBadge();
    $heroTitle = $branding->getHeroTitle();
    $heroSubtitle = $branding->getHeroSubtitle();
    $ctaPrimaryText = $branding->getHeroCtaPrimaryText();
    $ctaPrimaryUrl = $branding->getHeroCtaPrimaryUrl();
    $ctaSecondaryText = $branding->getHeroCtaSecondaryText();
    $ctaSecondaryUrl = $branding->getHeroCtaSecondaryUrl();

    $features = [
        ['icon' => '📦', 'title' => __('Smart Inventory & Stock'), 'body' => __('Real-time multi-warehouse stock, barcode & SKU labels, batch/expiry tracking and automatic low-stock re-order alerts.')],
        ['icon' => '🛒', 'title' => __('Retail & Store POS'), 'body' => __('Sub-second barcode checkout, split cash/card tender, customer credit accounts, and full X/Z shift reports.')],
        ['icon' => '🍽️', 'title' => __('Restaurant & Food POS'), 'body' => __('Live dining floor plans, Kitchen Order Tickets to KDS screens, QR table ordering, bill splitting and table merge.')],
        ['icon' => '🧾', 'title' => __('Finance & Invoicing'), 'body' => __('Compliant tax invoices (VAT/GST), multi-currency pricing, thermal + A4 receipts, and built-in AP/AR ledgers.')],
        ['icon' => '⚡', 'title' => __('Offline-First Sync'), 'body' => __('Keep ringing up sales when the internet drops. Transactions queue locally and sync automatically on reconnect.')],
        ['icon' => '🏢', 'title' => __('Multi-Location Workspaces'), 'body' => __('Scale from one till to a nationwide franchise with isolated tenant data, custom domains and granular roles.')],
    ];

    $stats = [
        ['2,500,000+', __('Transactions Processed')],
        ['1,200+', __('Active Business Outlets')],
        ['99.99%', __('Platform Uptime SLA')],
        ['< 20ms', __('Auth & Checkout Latency')],
    ];
@endphp

@section('content')
<div class="bg-slate-950 text-slate-100">

    {{-- 1. Hero --------------------------------------------------------------}}
    <section id="showcase" class="border-b border-white/10 bg-gradient-to-b from-slate-900 to-slate-950">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 grid lg:grid-cols-12 gap-10 lg:gap-8 items-center">
            <div class="lg:col-span-7">
                @if ($heroBadge)
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs font-black uppercase tracking-wider mb-5">
                        {{ $heroBadge }}
                    </span>
                @endif
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.08] text-white">
                    {{ $heroTitle }}
                </h1>
                <p class="mt-5 text-sm sm:text-base text-slate-400 leading-relaxed max-w-xl">
                    {{ $heroSubtitle }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="{{ $ctaPrimaryUrl }}" class="px-6 py-3 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-sm font-black shadow-lg shadow-brand-lime/25 transition active:scale-95">
                        {{ $ctaPrimaryText }} →
                    </a>
                    <a href="{{ $ctaSecondaryUrl }}" class="text-sm font-bold text-white hover:text-emerald-400 transition">
                        {{ $ctaSecondaryText }} →
                    </a>
                </div>
                <div class="mt-8 pt-6 border-t border-white/10 flex flex-wrap gap-x-6 gap-y-2 text-xs font-bold text-slate-400">
                    <span>✓ {{ __('Barcode & Touch POS') }}</span>
                    <span>✓ {{ __('Live Stock Alerts') }}</span>
                    <span>✓ {{ __('Restaurant Floor KOT') }}</span>
                    <span>✓ {{ __('Offline First Sync') }}</span>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="rounded-2xl bg-slate-900 border border-white/10 shadow-xl p-5 sm:p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10 text-xs">
                        <span class="font-black text-white">{{ __('Smart POS & Inventory') }}</span>
                        <span class="text-emerald-400 font-bold">● {{ __('Live') }}</span>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ([['☕ Artisan Coffee Roast 1kg', '$14.50', __('In Stock'), 'emerald'], ['🫒 Gourmet Truffle Oil 500ml', '$18.20', __('In Stock'), 'emerald'], ['🌾 Organic Almond Flour 1kg', '$8.90', __('Low Stock'), 'amber']] as [$name, $price, $tag, $c])
                            <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/5 text-xs">
                                <span class="font-medium text-slate-200">{{ $name }}</span>
                                <span class="flex items-center gap-2">
                                    <span class="font-bold text-white">{{ $price }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $c === 'amber' ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300' }}">{{ $tag }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center justify-between px-3 py-2.5 rounded-lg bg-brand-lime/10 border border-brand-lime/25 text-xs">
                        <span class="font-bold text-brand-lime">{{ __('Total') }} ({{ __('Split Cash / Card') }})</span>
                        <span class="font-black text-white">$41.60</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Hardware trust strip -------------------------------------------------}}
    @if ($branding->isSectionEnabled('trust_bar'))
        <section class="border-b border-white/10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <p class="text-center text-[11px] font-black uppercase tracking-widest text-slate-500 mb-5">
                    {{ __('Works out of the box with your existing retail & dining hardware') }}
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-center">
                    @foreach ([['Barcode Scanners', 'Instant Scan'], ['Thermal Receipt Printers', '80mm / 58mm'], ['Card Readers & Terminals', 'EMV & NFC'], ['Smart Cash Drawers', 'Auto Kick'], ['Kitchen Display Screens', 'Live KDS']] as [$label, $tag])
                        <div class="p-3 rounded-xl bg-white/5 border border-white/5">
                            <div class="text-xs font-black text-white leading-tight">{{ __($label) }}</div>
                            <div class="text-[10px] text-slate-400 mt-1">{{ __($tag) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 3. Features grid ---------------------------------------------------------}}
    @if ($branding->isSectionEnabled('features'))
        <section id="features" class="scroll-mt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <div class="text-center mb-12">
                    <span class="inline-block px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">{{ __('Unified Operations Suite') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Everything your business needs, in one engine') }}</h2>
                    <p class="mt-3 text-sm text-slate-400 max-w-2xl mx-auto">{{ __('From warehouse stock to front-counter POS to the kitchen display — one synchronized system.') }}</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($features as $f)
                        <div class="p-6 rounded-2xl bg-slate-900 border border-white/10">
                            <div class="text-2xl mb-3">{{ $f['icon'] }}</div>
                            <h3 class="text-base font-black text-white">{{ $f['title'] }}</h3>
                            <p class="mt-2 text-sm text-slate-400 leading-relaxed">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 4. Value pillars ------------------------------------------------------}}
    @if ($branding->isSectionEnabled('solutions'))
        <section id="solutions" class="scroll-mt-20 border-y border-white/10 bg-slate-900/40">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <div class="max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">{{ __('Architected For Scale') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white leading-tight">{{ __('Engineered for reliability under peak pressure') }}</h2>
                </div>
                <div class="mt-10 grid sm:grid-cols-2 gap-6">
                    @foreach ([
                        ['⚡', __('Sub-Second Speed & Offline-Ready'), __('Checkout keeps running if the internet drops. Sales queue safely and sync automatically on reconnect.')],
                        ['💳', __('Direct Card Issuing & Split Payments'), __('Issue virtual and physical cards, set spend controls, and take multi-tender checkouts without extra merchant accounts.')],
                        ['📊', __('Real-Time Financial & Ledger Control'), __('Automated register X/Z reconciliation, payable/receivable balances and compliance-ready tax invoices.')],
                        ['🏢', __('Multi-Location Enterprise Workspaces'), __('Isolated tenant databases, custom domains and granular role permissions from one till to a national franchise.')],
                    ] as [$icon, $title, $body])
                        <div class="p-6 rounded-2xl bg-white/5 border border-white/10">
                            <div class="text-xl mb-3">{{ $icon }}</div>
                            <h3 class="text-base font-black text-white">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-slate-400 leading-relaxed">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 5. CMS page body (TinyMCE) ------------------------------------------------}}
    @if (!empty($page->content))
        <section class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content rounded-2xl bg-slate-900 border border-white/10 p-8 sm:p-10 text-slate-100">
                {!! clean_html($page->content) !!}
            </div>
        </section>
    @endif

    {{-- 6. Stats ------------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('stats'))
        <section class="border-y border-white/10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid grid-cols-2 sm:grid-cols-4 gap-8 text-center">
                @foreach ($stats as [$value, $label])
                    <div>
                        <div class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ $value }}</div>
                        <div class="w-8 h-1 rounded-full bg-brand-lime mx-auto mt-3 mb-2"></div>
                        <div class="text-xs text-slate-400 font-semibold">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 7. About ------------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('about'))
        <section id="about" class="scroll-mt-20">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
                <span class="inline-block px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4">{{ __('Our Mission') }}</span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Built for high-velocity stores & modern commerce') }}</h2>
                <p class="mt-5 text-sm sm:text-base text-slate-300 leading-relaxed">
                    {{ $branding->platform_name }} {{ __('gives retailers, restaurateurs and growing enterprises point-of-sale and inventory infrastructure that keeps executing under peak pressure — from a single busy counter to nationwide multi-terminal operations.') }}
                </p>
            </div>
        </section>
    @endif

    {{-- 8. Testimonials --------------------------------------------------------}}
    @if ($branding->isSectionEnabled('testimonials'))
        <section class="border-y border-white/10 bg-slate-900/40">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <h2 class="text-center text-3xl sm:text-4xl font-black tracking-tight text-white mb-12">{{ __('Trusted by market leaders worldwide') }}</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach ([
                        [__('We switched all our retail outlets over in one afternoon. Inventory clears immediately and end-of-day reconciliation takes seconds.'), 'Alexander Hayes', __('Operations Director · Apex Retail Group')],
                        [__('The offline checkout saved us during a major fiber cut on a busy weekend. Not a single sale or customer was lost.'), 'Elena Rostova', __('Founder · Metro Gourmet Markets')],
                        [__('POS, inventory and KOT kitchen displays in a single dashboard transformed our restaurant chain.'), 'Tariq Mansour', __('Head of Operations · Urban Dine Hospitality')],
                    ] as [$quote, $name, $role])
                        <figure class="rounded-2xl bg-slate-900 border border-white/10 p-6 flex flex-col">
                            <div class="text-brand-lime text-sm mb-3">★★★★★</div>
                            <blockquote class="text-sm text-slate-300 leading-relaxed flex-1">&ldquo;{{ $quote }}&rdquo;</blockquote>
                            <figcaption class="mt-6 pt-4 border-t border-white/10">
                                <div class="text-sm font-black text-white">{{ $name }}</div>
                                <div class="text-xs text-slate-400">{{ $role }}</div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 9. Pricing -----------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('pricing') && $plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24" x-data="{ annual: false }">
                <div class="text-center mb-10">
                    <span class="inline-block px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">{{ __('Predictable Investment') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Simple, transparent pricing for every tier') }}</h2>
                </div>

                <div class="flex items-center justify-center gap-3 mb-12">
                    <span class="text-xs font-bold" :class="!annual ? 'text-white' : 'text-slate-400'">{{ __('Monthly') }}</span>
                    <button type="button" x-on:click="annual = !annual" role="switch" :aria-checked="annual.toString()"
                            :class="annual ? 'bg-brand-lime' : 'bg-slate-700'"
                            class="relative w-12 h-6 rounded-full transition-colors p-0.5">
                        <span class="block w-5 h-5 rounded-full bg-slate-950 shadow transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                    <span class="text-xs font-bold flex items-center gap-2" :class="annual ? 'text-white' : 'text-slate-400'">
                        {{ __('Annual') }}
                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase border border-emerald-500/30">{{ __('Save 20%') }}</span>
                    </span>
                </div>

                @php
                    $sorted = $plans->sortBy('price')->values();
                    $popular = $sorted->count() >= 2 ? $sorted[1]->name : null;
                    $pricingCols = $sorted->count() >= 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2';
                @endphp
                <div class="grid sm:grid-cols-2 {{ $pricingCols }} gap-6 items-stretch">
                    @foreach ($sorted as $plan)
                        @php
                            $price = (float) $plan->price;
                            $annualPrice = round($price * 12 * 0.8);
                            $isPopular = $plan->name === $popular;
                        @endphp
                        <div class="relative rounded-2xl p-7 flex flex-col justify-between border {{ $isPopular ? 'border-brand-lime bg-slate-900' : 'border-white/10 bg-slate-900/60' }}">
                            @if ($isPopular)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-brand-lime text-slate-950 text-[10px] font-black uppercase tracking-widest">{{ __('Most Popular') }}</span>
                            @endif
                            <div>
                                <div class="text-xs font-black uppercase tracking-widest text-emerald-400">{{ $plan->display_name }}</div>
                                <div class="mt-3 flex items-baseline gap-1.5">
                                    <span class="text-3xl font-black text-white">
                                        <span x-show="!annual">{{ $plan->currency }}{{ number_format($price, 0) }}</span>
                                        <span x-show="annual" x-cloak>{{ $plan->currency }}{{ number_format($annualPrice, 0) }}</span>
                                    </span>
                                    <span class="text-xs text-slate-400" x-text="annual ? '{{ __('/year') }}' : '/{{ $plan->billing_cycle }}'"></span>
                                </div>
                                <p class="mt-3 text-xs text-slate-400">
                                    {{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Staff Logins') }} ·
                                    {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('POS Devices') }}
                                </p>
                            </div>
                            <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                               class="mt-6 w-full text-center py-2.5 rounded-xl text-xs font-black transition {{ $isPopular ? 'bg-brand-lime hover:bg-brand-lime-dark text-slate-950' : 'bg-white/10 hover:bg-white/20 text-white' }}">
                                {{ __('Choose') }} {{ $plan->display_name }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 10. Contact -------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('contact'))
        <section id="contact" class="scroll-mt-20 border-t border-white/10 bg-slate-900/40">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
                <div class="text-center mb-10">
                    <span class="inline-block px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">{{ __('Get In Touch') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Questions before you sign up?') }}</h2>
                    <p class="mt-2 text-sm text-slate-400">{{ __("Send our team a note and we'll reply within 24 hours.") }}</p>
                </div>
                <x-landing.contact-form />
            </div>
        </section>
    @endif

    {{-- 11. Final CTA ----------------------------------------------------------}}
    @if ($branding->isSectionEnabled('cta'))
        <section class="border-t border-white/10">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Ready to speed up your counter?') }}</h2>
                <p class="mt-3 text-sm text-slate-400">{{ __('Create your store workspace in minutes. No card required.') }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('tenant.register') }}" class="px-6 py-3 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-sm font-black shadow-lg shadow-brand-lime/25 transition active:scale-95">
                        {{ $ctaPrimaryText }} →
                    </a>
                    <a href="{{ route('tenant.login') }}" class="text-sm font-bold text-white hover:text-emerald-400 transition">{{ __('Sign in') }}</a>
                </div>
            </div>
        </section>
    @endif

</div>
@endsection
