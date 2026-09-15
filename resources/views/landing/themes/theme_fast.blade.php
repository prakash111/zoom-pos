@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Fast Cloud POS, Inventory & Restaurant Management')
@section('meta_description', $branding->platform_name . ' — a fast, offline-ready POS with multi-branch inventory, restaurant KOT and automated invoicing for retail stores and food businesses.')

@php
    $heroBadge = $branding->getHeroBadge();
    $heroTitle = $branding->getSectionTitle('hero', $branding->getHeroTitle());
    $heroSubtitle = $branding->getSectionSubtitle('hero', $branding->getHeroSubtitle());
    $ctaPrimaryText = $branding->getHeroCtaPrimaryText();
    $ctaPrimaryUrl = $branding->getHeroCtaPrimaryUrl();
    $ctaSecondaryText = $branding->getHeroCtaSecondaryText();
    $ctaSecondaryUrl = $branding->getHeroCtaSecondaryUrl();
    $hasDownloads = $branding->hasAnyDownloadLink();

    $features = $branding->landingFeatures();

    $stats = $branding->landingList('stats', [
        ['2,500,000+', __('Transactions Processed')],
        ['1,200+', __('Active Business Outlets')],
        ['99.99%', __('Platform Uptime SLA')],
        ['< 20ms', __('Auth & Checkout Latency')],
    ]);
    $highlights = $branding->landingList('hero.highlights', [__('Barcode & Touch POS'), __('Live Stock Alerts'), __('Restaurant Floor KOT'), __('Offline First Sync')]);
    $hardware = $branding->landingList('trust.hardware', [['Barcode Scanners', 'Instant Scan'], ['Thermal Receipt Printers', '80mm / 58mm'], ['Card Readers & Terminals', 'EMV & NFC'], ['Smart Cash Drawers', 'Auto Kick'], ['Kitchen Display Screens', 'Live KDS']]);
    $solutions = $branding->landingList('solutions.items', [
        ['⚡', __('Sub-Second Speed & Offline-Ready'), __('Checkout keeps running if the internet drops. Sales queue safely and sync automatically on reconnect.')],
        ['💳', __('Direct Card Issuing & Split Payments'), __('Issue virtual and physical cards, set spend controls, and take multi-tender checkouts without extra merchant accounts.')],
        ['📊', __('Real-Time Financial & Ledger Control'), __('Automated register X/Z reconciliation, payable/receivable balances and compliance-ready tax invoices.')],
        ['🏢', __('Multi-Location Enterprise Workspaces'), __('Isolated tenant databases, custom domains and granular role permissions from one till to a national franchise.')],
    ]);
    $heroProducts = $branding->landingList('hero.products', [
        ['☕ Artisan Coffee Roast 1kg', '$14.50', __('In Stock'), 'emerald'],
        ['🫒 Gourmet Truffle Oil 500ml', '$18.20', __('In Stock'), 'emerald'],
        ['🌾 Organic Almond Flour 1kg', '$8.90', __('Low Stock'), 'amber'],
    ]);

    $faqs = $branding->landingFaqs();

    $card = 'rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-none';
    $muted = 'text-slate-600 dark:text-slate-400';
    $rule = 'border-slate-200 dark:border-white/10';
    $altBg = 'bg-slate-50 dark:bg-slate-900/40';
    $badge = 'inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/25';
@endphp

@section('content')
<div class="landing-page flex flex-col bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">

    {{-- 1. Hero --------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('hero'))
        <section id="showcase" class="border-b {{ $rule }} bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 grid lg:grid-cols-12 gap-10 lg:gap-8 items-center">
                <div class="lg:col-span-7">
                    @if ($heroBadge)
                        <span class="{{ $badge }} !mb-5 inline-flex items-center gap-2">{{ $heroBadge }}</span>
                    @endif
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.08] text-slate-900 dark:text-white">
                        {{ $heroTitle }}
                    </h1>
                    <p class="mt-5 text-sm sm:text-base {{ $muted }} leading-relaxed max-w-xl">
                        {{ $heroSubtitle }}
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ $ctaPrimaryUrl }}" class="px-6 py-3 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-sm font-black shadow-lg shadow-brand-lime/25 transition active:scale-95">
                            {{ $ctaPrimaryText }} →
                        </a>
                        <a href="{{ $ctaSecondaryUrl }}" class="text-sm font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                            {{ $ctaSecondaryText }} →
                        </a>
                    </div>

                    @if ($hasDownloads)
                        <x-landing.download-buttons :branding="$branding" class="mt-6" />
                    @endif

                    <div class="mt-8 pt-6 border-t {{ $rule }} flex flex-wrap gap-x-6 gap-y-2 text-xs font-bold {{ $muted }}">
                        @foreach ($highlights as $highlight)
                            <span>✓ {{ is_array($highlight) ? ($highlight['label'] ?? '') : $highlight }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="{{ $card }} shadow-xl p-5 sm:p-6">
                        <div class="flex items-center justify-between pb-3 border-b {{ $rule }} text-xs">
                            <span class="font-black text-slate-900 dark:text-white">{{ $branding->landingText('hero.dashboard_title', __('Smart POS & Inventory')) }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">● {{ $branding->landingText('hero.dashboard_status', __('Live')) }}</span>
                        </div>
                        <div class="mt-4 space-y-2">
                            @foreach ($heroProducts as $product)
                                @php($name = $product[0] ?? ($product['name'] ?? ''))
                                @php($price = $product[1] ?? ($product['price'] ?? ''))
                                @php($tag = $product[2] ?? ($product['status'] ?? ''))
                                @php($c = $product[3] ?? 'emerald')
                                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5 text-xs">
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $name }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ $price }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $c === 'amber' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' }}">{{ $tag }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex items-center justify-between px-3 py-2.5 rounded-lg bg-brand-lime/15 border border-brand-lime/30 text-xs">
                            <span class="font-bold text-emerald-700 dark:text-brand-lime">{{ $branding->landingText('hero.total_label', __('Total')) }} ({{ $branding->landingText('hero.payment_label', __('Split Cash / Card')) }})</span>
                            <span class="font-black text-slate-900 dark:text-white">{{ $branding->landingText('hero.total_amount', '$41.60') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- 2. Hardware trust strip ----------------------------------------------}}
    @if ($branding->isSectionEnabled('trust_bar'))
        <section id="trust_bar" class="border-b {{ $rule }}">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <p class="text-center text-[11px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-5">
                    {{ __('Works out of the box with your existing retail & dining hardware') }}
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-center">
                    @foreach ($hardware as $item)
                        @php($label = $item[0] ?? ($item['label'] ?? ''))
                        @php($tag = $item[1] ?? ($item['tag'] ?? ''))
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                            <div class="text-xs font-black text-slate-900 dark:text-white leading-tight">{{ __($label) }}</div>
                            <div class="text-[10px] {{ $muted }} mt-1">{{ __($tag) }}</div>
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
                    <span class="{{ $badge }}">{{ __('Unified Operations Suite') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ $branding->getSectionTitle('features', __('Everything your business needs, in one engine')) }}
                    </h2>
                    <p class="mt-3 text-sm {{ $muted }} max-w-2xl mx-auto">
                        {{ $branding->getSectionSubtitle('features', __('From warehouse stock to front-counter POS to the kitchen display — one synchronized system.')) }}
                    </p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($features as $f)
                        <div class="p-6 {{ $card }}">
                            <div class="text-2xl mb-3">{{ $f['icon'] }}</div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $f['title'] }}</h3>
                            <p class="mt-2 text-sm {{ $muted }} leading-relaxed">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 4. App downloads -------------------------------------------------------}}
    @if ($branding->isSectionEnabled('downloads') && $hasDownloads)
        <section id="download" class="scroll-mt-20 border-y {{ $rule }} {{ $altBg }}">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
                <span class="{{ $badge }}">{{ __('Native Apps') }}</span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ $branding->getSectionTitle('downloads', __('Take the counter anywhere')) }}
                </h2>
                <p class="mt-3 text-sm {{ $muted }} max-w-xl mx-auto">
                    {{ $branding->getSectionSubtitle('downloads', __('Install the native Android or Windows app for offline-first speed, hardware integration and a full-screen terminal experience.')) }}
                </p>
                <x-landing.download-buttons :branding="$branding" size="lg" class="mt-8 justify-center" />
            </div>
        </section>
    @endif

    {{-- 5. Value pillars -----------------------------------------------------}}
    @if ($branding->isSectionEnabled('solutions'))
        <section id="solutions" class="scroll-mt-20 border-y {{ $rule }} {{ $altBg }}">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <div class="max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-lime-100 text-lime-800 border border-lime-200 dark:bg-brand-lime/10 dark:text-brand-lime dark:border-brand-lime/20">{{ __('Architected For Scale') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">{{ $branding->getSectionTitle('solutions', __('Engineered for reliability under peak pressure')) }}</h2>
                </div>
                <div class="mt-10 grid sm:grid-cols-2 gap-6">
                    @foreach ($solutions as $item)
                        @php($icon = $item[0] ?? ($item['icon'] ?? '✨'))
                        @php($title = $item[1] ?? ($item['title'] ?? ''))
                        @php($body = $item[2] ?? ($item['body'] ?? ''))
                        <div class="p-6 {{ $card }}">
                            <div class="text-xl mb-3">{{ $icon }}</div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $title }}</h3>
                            <p class="mt-2 text-sm {{ $muted }} leading-relaxed">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 6. CMS page body (TinyMCE) ------------------------------------------------}}
    @if (!empty($page->content))
        <section class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content p-8 sm:p-10 {{ $card }} text-slate-800 dark:text-slate-100">
                {!! clean_html($page->content) !!}
            </div>
        </section>
    @endif

    {{-- 7. Stats ------------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('stats'))
        <section id="stats" class="border-y {{ $rule }}">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid grid-cols-2 sm:grid-cols-4 gap-8 text-center">
                @foreach ($stats as [$value, $label])
                    <div>
                        <div class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $value }}</div>
                        <div class="w-8 h-1 rounded-full bg-brand-lime mx-auto mt-3 mb-2"></div>
                        <div class="text-xs {{ $muted }} font-semibold">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 8. About ------------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('about'))
        <section id="about" class="scroll-mt-20">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
                <span class="{{ $badge }}">{{ __('Our Mission') }}</span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->getSectionTitle('about', __('Built for high-velocity stores & modern commerce')) }}</h2>
                <p class="mt-5 text-sm sm:text-base text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ $branding->getSectionSubtitle('about', $branding->platform_name.' '. __('gives retailers, restaurateurs and growing enterprises point-of-sale and inventory infrastructure that keeps executing under peak pressure — from a single busy counter to nationwide multi-terminal operations.')) }}
                </p>
            </div>
        </section>
    @endif

    {{-- 9. Testimonials --------------------------------------------------------}}
    @if ($branding->isSectionEnabled('testimonials'))
        <section id="testimonials" class="border-y {{ $rule }} {{ $altBg }}">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <h2 class="text-center text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-12">{{ $branding->getSectionTitle('testimonials', __('Trusted by market leaders worldwide')) }}</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach ($branding->landingTestimonials() as $t)
                        <figure class="p-6 {{ $card }} flex flex-col">
                            <div class="text-amber-500 dark:text-brand-lime text-sm mb-3">★★★★★</div>
                            <blockquote class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed flex-1">&ldquo;{{ $t['quote'] }}&rdquo;</blockquote>
                            <figcaption class="mt-6 pt-4 border-t {{ $rule }}">
                                <div class="text-sm font-black text-slate-900 dark:text-white">{{ $t['name'] }}</div>
                                <div class="text-xs {{ $muted }}">{{ $t['role'] }}</div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 10. Pricing -----------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('pricing') && $plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24" x-data="{ annual: false }">
                <div class="text-center mb-10">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-lime-100 text-lime-800 border border-lime-200 dark:bg-brand-lime/10 dark:text-brand-lime dark:border-brand-lime/20">{{ __('Predictable Investment') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ $branding->getSectionTitle('pricing', __('Simple, transparent pricing for every tier')) }}
                    </h2>
                    <p class="mt-3 text-sm {{ $muted }} max-w-xl mx-auto">
                        {{ $branding->getSectionSubtitle('pricing', __('Launch in minutes with zero setup fees.')) }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-3 mb-12">
                    <span class="text-xs font-bold" :class="!annual ? 'text-slate-900 dark:text-white' : 'text-slate-400'">{{ __('Monthly') }}</span>
                    <button type="button" x-on:click="annual = !annual" role="switch" :aria-checked="annual.toString()"
                            :class="annual ? 'bg-brand-lime' : 'bg-slate-300 dark:bg-slate-700'"
                            class="relative w-12 h-6 rounded-full transition-colors p-0.5">
                        <span class="block w-5 h-5 rounded-full bg-white dark:bg-slate-950 shadow transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                    <span class="text-xs font-bold flex items-center gap-2" :class="annual ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                        {{ __('Annual') }}
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase border border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30">{{ __('Save 20%') }}</span>
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
                        <div class="relative rounded-2xl p-7 flex flex-col justify-between border {{ $isPopular ? 'border-brand-lime bg-white dark:bg-slate-900 shadow-lg' : 'border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none' }}">
                            @if ($isPopular)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-brand-lime text-slate-950 text-[10px] font-black uppercase tracking-widest">{{ __('Most Popular') }}</span>
                            @endif
                            <div>
                                <div class="text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ $plan->display_name }}</div>
                                <div class="mt-3 flex items-baseline gap-1.5">
                                    <span class="text-3xl font-black text-slate-900 dark:text-white">
                                        <span x-show="!annual">{{ $plan->currency }}{{ number_format($price, 0) }}</span>
                                        <span x-show="annual" x-cloak>{{ $plan->currency }}{{ number_format($annualPrice, 0) }}</span>
                                    </span>
                                    <span class="text-xs {{ $muted }}" x-text="annual ? '{{ __('/year') }}' : '/{{ $plan->billing_cycle }}'"></span>
                                </div>
                                <p class="mt-3 text-xs {{ $muted }}">
                                    {{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Staff Logins') }} ·
                                    {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('POS Devices') }}
                                </p>
                            </div>
                            <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                               class="mt-6 w-full text-center py-2.5 rounded-xl text-xs font-black transition {{ $isPopular ? 'bg-brand-lime hover:bg-brand-lime-dark text-slate-950' : 'bg-slate-100 hover:bg-slate-200 text-slate-900 dark:bg-white/10 dark:hover:bg-white/20 dark:text-white' }}">
                                {{ __('Choose') }} {{ $plan->display_name }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 11. FAQ --------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('faq') && count($faqs))
        <section id="faq" class="scroll-mt-20 border-y {{ $rule }} {{ $altBg }}">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
                <div class="text-center mb-10">
                    <span class="{{ $badge }}">{{ __('Answers') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ $branding->getSectionTitle('faq', __('Frequently asked questions')) }}
                    </h2>
                    <p class="mt-3 text-sm {{ $muted }}">
                        {{ $branding->getSectionSubtitle('faq', __('Everything you need to know before getting started.')) }}
                    </p>
                </div>
                <div class="space-y-3">
                    @foreach ($faqs as $faq)
                        <details class="group p-5 {{ $card }}">
                            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none text-sm font-black text-slate-900 dark:text-white">
                                {{ $faq['q'] }}
                                <span class="shrink-0 text-slate-400 transition-transform group-open:rotate-45 text-lg leading-none">+</span>
                            </summary>
                            <p class="mt-3 text-sm {{ $muted }} leading-relaxed">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 12. Contact -------------------------------------------------------------}}
    @if ($branding->isSectionEnabled('contact'))
        <section id="contact" class="scroll-mt-20 border-t {{ $rule }}">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
                <div class="text-center mb-10">
                    <span class="{{ $badge }}">{{ __('Get In Touch') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->getSectionTitle('contact', __('Questions before you sign up?')) }}</h2>
                    <p class="mt-2 text-sm {{ $muted }}">{{ $branding->getSectionSubtitle('contact', __("Send our team a note and we'll reply within 24 hours.")) }}</p>
                </div>
                <x-landing.contact-form />
            </div>
        </section>
    @endif

    {{-- 13. Final CTA ----------------------------------------------------------}}
    @if ($branding->isSectionEnabled('cta'))
        <section id="cta" class="border-t {{ $rule }} {{ $altBg }}">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->getSectionTitle('cta', __('Ready to speed up your counter?')) }}</h2>
                <p class="mt-3 text-sm {{ $muted }}">{{ $branding->getSectionSubtitle('cta', __('Create your store workspace in minutes. No card required.')) }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('tenant.register') }}" class="px-6 py-3 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-sm font-black shadow-lg shadow-brand-lime/25 transition active:scale-95">
                        {{ $ctaPrimaryText }} →
                    </a>
                    <a href="{{ route('tenant.login') }}" class="text-sm font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ __('Sign in') }}</a>
                </div>
            </div>
        </section>
    @endif

</div>
@endsection
