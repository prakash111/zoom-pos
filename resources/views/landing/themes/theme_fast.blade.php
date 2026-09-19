@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages, 'referenceLanding' => true])

@section('title', $branding->platform_name . ' — Fast Cloud POS, Omnichannel Commerce & Inventory')
@section('meta_description', $branding->platform_name . ' — Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp invoicing into one lightning-fast cloud POS. Built to maximize sales online and in-store.')

@php
    $heroBadge = $branding->getHeroBadge();
    $heroTitle = $branding->getSectionTitle('hero', $branding->getHeroTitle());
    $heroSubtitle = $branding->getSectionSubtitle('hero', $branding->getHeroSubtitle());
    $ctaPrimaryText = $branding->getHeroCtaPrimaryText();
    $ctaPrimaryUrl = $branding->getHeroCtaPrimaryUrl();
    $ctaSecondaryText = $branding->getHeroCtaSecondaryText();
    $ctaSecondaryUrl = $branding->getHeroCtaSecondaryUrl();
    $customBannerUrl = $branding->landing_hero_banner_image_url;
    $hasDownloads = $branding->hasAnyDownloadLink();

    $features = $branding->landingFeatures();
    $stats = $branding->landingStatsList();
    $highlights = $branding->landingList('hero.highlights', [
        __('Instant Online Store & WhatsApp Orders'),
        __('Sub-Second Barcode Checkout (100% Offline-Ready)'),
        __('Live Multi-Store Inventory Sync'),
        __('Automated GST & VAT Tax Invoicing'),
    ]);
    $hardware = $branding->landingHardware();
    $solutions = $branding->landingSolutionsList();
    $heroProducts = $branding->landingList('hero.products', [
        ['name' => '☕ Single-Origin Ethiopian Roast 1kg', 'price' => '$28.50', 'status' => __('In Stock'), 'tone' => 'emerald'],
        ['name' => '🎧 Wireless Noise-Canceling Pro', 'price' => '$149.00', 'status' => __('In Stock'), 'tone' => 'emerald'],
        ['name' => '🫒 Organic Cold-Pressed Olive Oil 500ml', 'price' => '$18.90', 'status' => __('Low Stock (3 left)'), 'tone' => 'amber'],
    ]);

    $faqs = $branding->landingFaqs();
    $overviewFeatures = $branding->landingList('features.overview', [
        ['icon' => 'cart', 'color' => 'green', 'title' => __('Point of Sale (POS)'), 'body' => __('Quick billing, multiple payment methods, receipts & more.')],
        ['icon' => 'box', 'color' => 'purple', 'title' => __('Inventory Management'), 'body' => __('Track stock, low alerts, barcode & more.')],
        ['icon' => 'users', 'color' => 'pink', 'title' => __('Customer Management'), 'body' => __('Keep your customers happy and coming back.')],
        ['icon' => 'chart', 'color' => 'orange', 'title' => __('Reports & Analytics'), 'body' => __('Sales, profit, inventory, top products and custom reports.')],
        ['icon' => 'cloud', 'color' => 'cyan', 'title' => __('Cloud & Offline Sync'), 'body' => __('Work online or offline. Your data is always safe.')],
        ['icon' => 'settings', 'color' => 'blue', 'title' => __('Multi-Branch Support'), 'body' => __('Manage multiple stores from one account.')],
    ]);
    $heroModules = $branding->landingList('hero.modules', [
        ['icon' => 'cart', 'color' => 'green', 'title' => __('Retail & POS'), 'body' => __('Fast, easy and secure sales')],
        ['icon' => 'restaurant', 'color' => 'purple', 'title' => __('Restaurant'), 'body' => __('Tables, orders, kitchen management')],
        ['icon' => 'box', 'color' => 'cyan', 'title' => __('Inventory'), 'body' => __('Track stock in real-time')],
        ['icon' => 'chart', 'color' => 'orange', 'title' => __('Reports & Analytics'), 'body' => __('Make smarter decisions')],
        ['icon' => 'shield', 'color' => 'teal', 'title' => __('Secure & Reliable'), 'body' => __('Your data, always protected')],
    ]);
    $referencePreset = $branding->landingText('design.reference') === '1';
    $heroTitleParts = explode(' to ', $heroTitle, 2);
    $featureTitleParts = explode(' in ', $branding->getSectionTitle('features', __('Everything You Need in One Platform')), 2);


    $card = 'rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-none';
    $muted = 'text-slate-600 dark:text-slate-400';
    $rule = 'border-slate-200 dark:border-white/10';
    $altBg = 'bg-slate-50 dark:bg-slate-900/40';
    $badge = 'inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/25';
@endphp

@section('content')
<div class="landing-page reference-landing flex flex-col bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">

@foreach ($branding->landingSectionOrder() as $section)
    @if ($branding->isSectionEnabled($section))
        @switch($section)
            @case('hero')
                <section id="showcase" class="landing-sec-hero reference-hero" x-data>
                    <div class="reference-container reference-hero-grid">
                        <div class="reference-hero-copy">
                            @if ($heroBadge)
                                <span class="reference-badge reference-hero-badge"><x-landing.icon name="bolt" width="18" height="18" /> {{ $heroBadge }}</span>
                            @endif
                            <h1>
                                @if (count($heroTitleParts) === 2)
                                    {{ $heroTitleParts[0] }}<br>{{ __('to') }} <span class="reference-gradient-text">{{ $heroTitleParts[1] }}</span>
                                @else
                                    {{ $heroTitle }}
                                @endif
                            </h1>
                            <p class="reference-hero-description">{{ $heroSubtitle }}</p>
                            <div class="reference-hero-actions">
                                <a href="{{ $ctaPrimaryUrl }}" class="reference-button reference-button--primary"><x-landing.icon name="user-plus" width="20" height="20" /> {{ $ctaPrimaryText }} <x-landing.icon name="arrow" width="20" height="20" /></a>
                                <a href="{{ $ctaSecondaryUrl }}" @if($ctaSecondaryUrl === '#demo') x-on:click.prevent="$refs.demo.showModal()" @endif class="reference-button reference-button--outline"><x-landing.icon name="play" width="22" height="22" /> {{ $ctaSecondaryText }}</a>
                            </div>
                            @if ($hasDownloads && !$referencePreset)
                                <x-landing.download-buttons :branding="$branding" class="mt-5" />
                            @endif
                            <div class="reference-hero-modules">
                                @foreach ($heroModules as $module)
                                    <div class="reference-hero-module">
                                        <span class="reference-icon reference-icon--{{ $module['color'] ?? 'blue' }}"><x-landing.icon :name="$module['icon'] ?? 'shield'" /></span>
                                        <h2>{{ $module['title'] ?? '' }}</h2>
                                        <p>{{ $module['body'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                            @if (!$referencePreset)
                                <div class="reference-extra-highlights">
                                    @foreach ($highlights as $highlight)
                                        <span>✓ {{ is_array($highlight) ? ($highlight['label'] ?? ($highlight[0] ?? '')) : $highlight }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="reference-hero-art">
                            <div class="reference-hero-wave" aria-hidden="true"></div>
                            <img src="{{ $customBannerUrl ?: asset('assets/images/landing-device-showcase.png') }}"
                                 alt="{{ __('POS dashboard on a laptop, mobile app, touchscreen checkout terminal and receipt printer') }}"
                                 width="1536" height="1024" fetchpriority="high" decoding="async">
                            <span class="reference-device-note">{{ __('Works on') }}<br>{{ __('Web, Desktop') }}<br>{{ __('& Mobile') }}
                                <svg width="62" height="54" viewBox="0 0 62 54" fill="none" aria-hidden="true"><path d="M3 47c28 3 38-15 41-39m-8 10 8-10 8 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </div>
                    </div>
                    @if ($ctaSecondaryUrl === '#demo')
                        <dialog id="demo" x-ref="demo" class="reference-demo" x-on:click="if ($event.target === $el) $el.close()" aria-labelledby="reference-demo-title">
                            <div class="reference-demo-heading">
                                <h2 id="reference-demo-title">{{ __('Your business, in one place') }}</h2>
                                <button type="button" x-on:click="$refs.demo.close()" aria-label="{{ __('Close demo') }}">&times;</button>
                            </div>
                            <p>{{ __('Explore a preview of sales, inventory and customers across your laptop, checkout terminal and phone.') }}</p>
                            <img src="{{ asset('assets/images/landing-device-showcase.png') }}" alt="{{ __('Preview of the POS dashboard and mobile app') }}" width="1536" height="1024" decoding="async">
                            <a href="{{ $ctaPrimaryUrl }}" class="reference-button reference-button--primary">{{ $ctaPrimaryText }} <x-landing.icon name="arrow" width="20" height="20" /></a>
                        </dialog>
                    @endif
                </section>
                @break

            @case('trust_bar')
                {{-- 2. Hardware trust strip --}}
                <section id="trust_bar" class="landing-sec-trust border-b {{ $rule }} transition-colors duration-300">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <p class="text-center text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-5">
                            {{ $branding->getSectionTitle('trust_bar', __('Works out of the box with your existing retail & dining hardware')) }}
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-center">
                            @foreach ($hardware as $item)
                                @php
                                    $label = $item['label'] ?? ($item[0] ?? '');
                                    $tag = $item['tag'] ?? ($item[1] ?? '');
                                @endphp
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200/80 dark:border-white/5 shadow-sm dark:shadow-none">
                                    <div class="text-xs font-black text-slate-900 dark:text-white leading-tight">{{ __($label) }}</div>
                                    @if ($tag)
                                        <div class="text-[10px] {{ $muted }} mt-1">{{ __($tag) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
                @break

            @case('features')
                <section id="features" class="landing-sec-features reference-features" x-data="{ allFeatures: false }">
                    <div class="reference-container">
                        <div class="reference-features-grid">
                            <div class="reference-features-intro">
                                <span class="reference-badge">{{ $branding->getSectionBadge('features', __('Powerful Features')) }}</span>
                                <h2>
                                    @if (count($featureTitleParts) === 2)
                                        {{ $featureTitleParts[0] }}<br>{{ __('in') }} <span class="reference-text-blue">{{ $featureTitleParts[1] }}</span>
                                    @else
                                        {{ $featureTitleParts[0] }}
                                    @endif
                                </h2>
                                <p>{{ $branding->getSectionSubtitle('features', __('From point of sale to advanced reporting, our platform gives you the tools to work smarter, serve better and grow faster.')) }}</p>
                                <button type="button" class="reference-button reference-button--outline" x-on:click="allFeatures = !allFeatures" :aria-expanded="allFeatures.toString()" aria-controls="all-features">
                                    {{ __('Explore All Features') }} <x-landing.icon name="arrow" width="20" height="20" />
                                </button>
                            </div>
                            <div class="reference-feature-cards">
                                @foreach ($overviewFeatures as $feature)
                                    <article class="reference-feature-card">
                                        <span class="reference-icon reference-icon--{{ $feature['color'] ?? 'blue' }}"><x-landing.icon :name="$feature['icon'] ?? 'shield'" /></span>
                                        <div><h3>{{ $feature['title'] ?? '' }}</h3><p>{{ $feature['body'] ?? '' }}</p></div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        <div id="all-features" class="reference-all-features" x-show="allFeatures" x-cloak>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                @foreach ($features as $f)
                                    <article class="reference-full-feature p-6 {{ $card }}">
                                        <span class="reference-icon reference-icon--{{ ['green', 'purple', 'cyan', 'orange', 'pink', 'blue'][$loop->index % 6] }}"><x-landing.icon :name="['cart', 'box', 'cloud', 'chart', 'users', 'settings'][$loop->index % 6]" /></span>
                                        <h3 class="text-base font-bold mt-4">{{ $f['title'] }}</h3>
                                        <p class="text-sm mt-2 leading-relaxed whitespace-pre-line">{{ $f['body'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                            @if (!$referencePreset)
                                <div class="reference-dashboard-preview">
                                    <h3>{{ $branding->landingText('hero.dashboard_title', __('Smart POS & Inventory')) }}</h3>
                                    <span>{{ $branding->landingText('hero.dashboard_status', __('Live')) }}</span>
                                    @foreach ($heroProducts as $product)
                                        <p>{{ is_array($product) ? ($product['name'] ?? ($product[0] ?? '')) : $product }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
                @break

            @case('downloads')
                {{-- 4. App downloads --}}
                @if ($hasDownloads)
                    <section id="download" class="landing-sec-downloads scroll-mt-20 border-y {{ $rule }} {{ $altBg }} transition-colors duration-300">
                        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
                            <span class="{{ $badge }}">{{ $branding->getSectionBadge('downloads', __('Native Apps')) }}</span>
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
                @break

            @case('solutions')
                {{-- 5. Value pillars --}}
                <section id="solutions" class="landing-sec-solutions scroll-mt-20 border-y {{ $rule }} {{ $altBg }} transition-colors duration-300">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                        <div class="max-w-2xl">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-lime-100 text-lime-800 border border-lime-200 dark:bg-brand-lime/10 dark:text-brand-lime dark:border-brand-lime/20">{{ $branding->getSectionBadge('solutions', __('Engineered For Maximum Conversion')) }}</span>
                            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">{{ $branding->getSectionTitle('solutions', __('Why Modern Online Stores & Retailers Choose Our Platform')) }}</h2>
                            <p class="mt-2 text-sm {{ $muted }}">{{ $branding->getSectionSubtitle('solutions', __('Designed from the ground up to boost online revenue, eliminate inventory discrepancies, and keep counter checkouts flying during peak rushes.')) }}</p>
                        </div>
                        <div class="mt-10 grid sm:grid-cols-2 gap-6">
                            @foreach ($solutions as $item)
                                @php
                                    $icon = $item['icon'] ?? ($item[0] ?? '⚡');
                                    $title = $item['title'] ?? ($item[1] ?? '');
                                    $body = $item['body'] ?? ($item[2] ?? '');
                                @endphp
                                <div class="p-6 {{ $card }}">
                                    <div class="text-xl mb-3">{{ $icon }}</div>
                                    <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $title }}</h3>
                                    <p class="mt-2 text-sm {{ $muted }} leading-relaxed">{{ $body }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
                @break

            @case('stats')
                <section id="stats" class="landing-sec-stats reference-stats">
                    <div class="reference-container">
                        @if (!$referencePreset)
                            <div class="reference-stats-heading">
                                <h2>{{ $branding->getSectionTitle('stats', __('Trusted by growing businesses')) }}</h2>
                                <p>{{ $branding->getSectionSubtitle('stats') }}</p>
                            </div>
                        @endif
                        <div class="reference-stats-strip">
                            @foreach ($stats as $stat)
                                <div class="reference-stat">
                                    <x-landing.icon :name="['users', 'star', 'shield', 'headphones'][$loop->index % 4]" width="36" height="36" />
                                    <span><strong>{{ $stat['value'] }}</strong><small>{{ $stat['label'] }}</small></span>
                                </div>
                            @endforeach
                            <div class="reference-stat-trust"><span>{{ __('Trusted by businesses') }}<br>{{ __('across the world') }}</span><x-landing.icon name="globe" width="38" height="38" /></div>
                        </div>
                    </div>
                </section>
                @break

            @case('about')
                {{-- 7. About --}}
                <section id="about" class="landing-sec-mission landing-sec-about landing-dark-section scroll-mt-20 py-20 transition-colors duration-300">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
                        <span class="{{ $badge }}">{{ $branding->getSectionBadge('about', __('Our Mission')) }}</span>
                        <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->getSectionTitle('about', __('Built to Turn Every Online Store & Counter into a High-Revenue Machine')) }}</h2>
                        <p class="mt-5 text-sm sm:text-base text-slate-700 dark:text-slate-300 leading-relaxed">
                            {{ $branding->getSectionBody('about', $branding->getSectionSubtitle('about', __('We exist to empower online merchants, retailers, and food businesses with enterprise-grade commerce infrastructure without enterprise complexity or exorbitant fees. By uniting your online storefront, front-counter barcode checkout, multi-warehouse inventory, and automated tax invoicing into one synchronized engine, we remove software friction so you can focus on what matters: acquiring customers, expanding your catalog, and scaling your profit.'))) }}
                        </p>
                    </div>
                </section>
                @break

            @case('testimonials')
                {{-- 8. Testimonials --}}
                <section id="testimonials" class="landing-sec-testimonials border-y {{ $rule }} {{ $altBg }} transition-colors duration-300">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                        <div class="text-center mb-12">
                            <span class="{{ $badge }}">{{ $branding->getSectionBadge('testimonials', __('Proven Results')) }}</span>
                            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->getSectionTitle('testimonials', __('Trusted by Leading Online Brands & Retailers Worldwide')) }}</h2>
                            <p class="mt-2 text-sm {{ $muted }}">{{ $branding->getSectionSubtitle('testimonials', __('See how omnichannel businesses use our cloud commerce engine to drive revenue and save hours every single day.')) }}</p>
                        </div>
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
                @break

            @case('pricing')
                {{-- 9. Pricing --}}
                @if ($plans->isNotEmpty())
                    <section id="pricing" class="landing-sec-pricing landing-dark-section scroll-mt-20 py-20 border-t {{ $rule }} transition-colors duration-300">
                        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24" x-data="{ annual: false }">
                            <div class="text-center mb-10">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-lime-100 text-lime-800 border border-lime-200 dark:bg-brand-lime/10 dark:text-brand-lime dark:border-brand-lime/20">{{ $branding->getSectionBadge('pricing', __('Predictable Investment')) }}</span>
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
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase border border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30">{{ $branding->landingText('pricing.discount_badge', __('Save 20%')) }}</span>
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

                            @php
                                $pricingNote = $branding->landingText('pricing.note', '');
                            @endphp
                            @if ($pricingNote)
                                <p class="text-center text-xs {{ $muted }} mt-8">{{ $pricingNote }}</p>
                            @endif
                        </div>
                    </section>
                @endif
                @break

            @case('faq')
                {{-- 10. FAQ --}}
                @if (count($faqs))
                    <section id="faq" class="landing-sec-faq scroll-mt-20 border-y {{ $rule }} transition-colors duration-300">
                        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
                            <div class="text-center mb-10">
                                <span class="{{ $badge }}">{{ $branding->getSectionBadge('faq', __('Answers')) }}</span>
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
                                            {{ $faq['q'] ?? ($faq['question'] ?? '') }}
                                            <span class="shrink-0 text-slate-400 transition-transform group-open:rotate-45 text-lg leading-none">+</span>
                                        </summary>
                                        <p class="mt-3 text-sm {{ $muted }} leading-relaxed">{{ $faq['a'] ?? ($faq['answer'] ?? '') }}</p>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('contact')
                {{-- 11. Contact --}}
                <section id="contact" class="landing-sec-contact scroll-mt-20 border-t {{ $rule }} py-16 sm:py-24 transition-colors duration-300">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6">
                        <div class="text-center mb-10">
                            <span class="{{ $badge }}">{{ $branding->getSectionBadge('contact', __('Get In Touch')) }}</span>
                            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">{{ $branding->getSectionTitle('contact', __('Speak with an Omnichannel POS Specialist')) }}</h2>
                            <p class="text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">{{ $branding->getSectionSubtitle('contact', __("Need a tailored setup for multi-location retail, restaurant chains, or online catalog migration? Our solutions engineering team replies within 24 hours.")) }}</p>
                        </div>
                        <div class="contact-form-card rounded-2xl p-6 sm:p-10 border shadow-sm transition-colors duration-300">
                            <x-landing.contact-form />
                        </div>
                    </div>
                </section>
                @break

            @case('cta')
                <section id="cta" class="landing-sec-cta reference-cta">
                    <div class="reference-container reference-cta-inner">
                        <x-landing.icon name="rocket" width="38" height="38" />
                        <div>
                            <h2>{{ $branding->getSectionTitle('cta', __('Ready to take your business to the next level?')) }}</h2>
                            <p>{{ $branding->getSectionSubtitle('cta', __('Join successful businesses using our POS & Inventory platform.')) }}</p>
                        </div>
                        <a href="{{ $branding->landingText('cta.primary_url', $ctaPrimaryUrl) }}" class="reference-button reference-button--white">{{ $branding->landingText('cta.primary_text', __('Start Free Trial')) }} <x-landing.icon name="arrow" width="20" height="20" /></a>
                    </div>
                </section>
                @break
        @endswitch
    @endif
@endforeach

{{-- CMS page body (TinyMCE) --}}
@if (!empty($page->content))
    <section class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
        <div class="page-content p-8 sm:p-10 {{ $card }} text-slate-800 dark:text-slate-100">
            {!! clean_html($page->content) !!}
        </div>
    </section>
@endif

</div>
@endsection
