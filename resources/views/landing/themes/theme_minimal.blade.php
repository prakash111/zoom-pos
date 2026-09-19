@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Quick Launch POS & Inventory Funnel')
@section('meta_description', 'Launch your store POS, inventory tracking, and sales checkout in under 60 seconds with ' . $branding->platform_name)

@php
    $heroBadge = filled($branding->landing_hero_badge) ? $branding->landing_hero_badge : __('Instant 60-Second Setup');
    $heroTitle = filled($branding->landing_hero_title) ? $branding->landing_hero_title : __('The Fastest Way to Ring Up Sales & Manage Store Inventory');
    $heroSubtitle = filled($branding->landing_hero_subtitle) ? $branding->landing_hero_subtitle : __('No credit card required. No hardware purchase needed. Ring up orders, track stock levels, and issue thermal receipts from your computer, tablet, or phone.');
    $ctaPrimaryText = filled($branding->landing_hero_cta_primary_text) ? $branding->landing_hero_cta_primary_text : __('Create Free Store Workspace');
    $ctaPrimaryUrl = filled($branding->landing_hero_cta_primary_url) ? $branding->landing_hero_cta_primary_url : route('tenant.register');
    $features = $branding->landingFeatures();
    $faqs = $branding->landingFaqs();
@endphp

@section('content')
<div class="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col justify-between transition-colors duration-300" x-data="{ annual: false }">

    <!-- Main Funnel Hero -->
    <section class="landing-sec-hero w-full py-16 sm:py-24 border-b border-slate-200 dark:border-slate-800/80">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-8">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-400/15 border border-amber-400/30 text-amber-600 dark:text-amber-400 text-xs font-black uppercase tracking-wider">
                <span>⚡ {{ $heroBadge }}</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                {{ $heroTitle }}
            </h1>

            <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto font-normal leading-relaxed">
                {{ $heroSubtitle }}
            </p>

            <!-- Direct Instant Registration Trigger Card -->
            <div class="max-w-md mx-auto p-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col sm:flex-row gap-2">
                <a href="{{ $ctaPrimaryUrl }}"
                   class="w-full py-3.5 px-6 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-sm transition text-center shadow-lg shadow-amber-400/20 active:scale-95 flex items-center justify-center gap-2">
                    <span>🚀 {{ $ctaPrimaryText }}</span>
                    <span>→</span>
                </a>
            </div>

            <x-landing.download-buttons :branding="$branding" class="justify-center" />

            <div class="flex items-center justify-center gap-6 text-xs text-slate-500 font-medium">
                <span class="flex items-center gap-1.5"><strong class="text-amber-500">✓</strong> {{ __('Instant Access') }}</span>
                <span class="flex items-center gap-1.5"><strong class="text-amber-500">✓</strong> {{ __('Zero Setup Fees') }}</span>
                <span class="flex items-center gap-1.5"><strong class="text-amber-500">✓</strong> {{ __('Works On Any Device') }}</span>
            </div>
        </div>
    </section>

    <!-- Features / Key Speed Pillars (Editable from SuperAdmin Studio) -->
    <section class="landing-sec-features w-full py-16 sm:py-20 border-b border-slate-200 dark:border-slate-800/80">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            @if (!empty($features))
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    @foreach (array_slice($features, 0, 6) as $feat)
                        <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-left space-y-2 shadow-sm">
                            <div class="text-2xl">{{ $feat['icon'] ?: '⚡' }}</div>
                            <div class="text-sm font-black text-slate-900 dark:text-white">{{ $feat['title'] ?? '' }}</div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $feat['body'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-left space-y-2 shadow-sm">
                        <div class="text-2xl">⚡</div>
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ __('Sub-Second Barcode Scan') }}</div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ __('Add items to cart in milliseconds. Works with any standard USB or Bluetooth barcode scanner.') }}</p>
                    </div>

                    <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-left space-y-2 shadow-sm">
                        <div class="text-2xl">🖨️</div>
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ __('One-Click Thermal Receipt') }}</div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ __('Instant 80mm & 58mm receipts with automatic cash drawer kick and WhatsApp receipt dispatch.') }}</p>
                    </div>

                    <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-left space-y-2 shadow-sm">
                        <div class="text-2xl">💵</div>
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ __('Daily Shift Reconcile') }}</div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ __('Track cash float, drops, and blind counts with automated X & Z report summaries.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- Streamlined Pricing Section -->
    <section class="landing-sec-pricing w-full py-16 sm:py-20 border-b border-slate-200 dark:border-slate-800/80">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ $branding->getSectionTitle('pricing', __('Simple, Transparent Tiers')) }}</h3>
            
            <div class="flex items-center justify-center gap-3 my-6">
                <span class="text-xs font-bold" :class="!annual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">{{ __('Monthly') }}</span>
                <button type="button" @click="annual = !annual"
                        :class="annual ? 'bg-amber-400' : 'bg-slate-200 dark:bg-slate-800'"
                        class="relative w-12 h-6 rounded-full transition-colors p-0.5 cursor-pointer">
                    <span class="block w-5 h-5 rounded-full bg-slate-950 shadow transition-transform"
                          :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
                </button>
                <span class="text-xs font-bold" :class="annual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">{{ __('Annual (20% Off)') }}</span>
            </div>

            @if ($plans->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    @foreach ($plans as $plan)
                        @php
                            $price = (float) $plan->price;
                            $annualPrice = round($price * 12 * 0.8);
                        @endphp
                        <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col justify-between text-left space-y-4 shadow-sm">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ $plan->display_name }}</div>
                                <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
                                    <span x-show="!annual">{{ $plan->currency }}{{ number_format($price, 2) }}</span>
                                    <span x-show="annual" x-cloak>{{ $plan->currency }}{{ number_format($annualPrice, 2) }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400" x-text="!annual ? '/{{ $plan->billing_cycle }}' : '/year'"></span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-2">{{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Users') }} · {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('POS Devices') }}</p>
                            </div>
                            <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                               class="w-full py-2.5 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-amber-400 hover:text-slate-950 dark:hover:bg-amber-400 dark:hover:text-slate-950 font-bold text-xs transition text-center text-slate-900 dark:text-white">
                                {{ __('Choose') }} {{ $plan->display_name }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- FAQs Section (Editable from SuperAdmin Studio) -->
    @if (!empty($faqs))
    <section class="landing-sec-faq w-full py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <h3 class="text-2xl font-black text-slate-900 dark:text-white text-center mb-8">{{ $branding->getSectionTitle('faq', __('Frequently Asked Questions')) }}</h3>
            <div class="space-y-3">
                @foreach ($faqs as $faq)
                    <details class="group p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition">
                        <summary class="flex items-center justify-between cursor-pointer font-bold text-sm text-slate-900 dark:text-white list-none">
                            <span>{{ $faq['q'] ?? ($faq['question'] ?? '') }}</span>
                            <span class="text-amber-500 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ $faq['a'] ?? ($faq['answer'] ?? '') }}
                        </p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Contact Inquiries Section -->
    @if ($branding->isSectionEnabled('contact'))
    <section id="contact" class="landing-sec-contact w-full py-16 border-t border-slate-200 dark:border-slate-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-400/15 border border-amber-400/30 text-amber-600 dark:text-amber-400 text-xs font-black uppercase tracking-wider mb-2">
                    ⚡ {{ $branding->getSectionBadge('contact', __('Get In Touch')) }}
                </span>
                <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                    {{ $branding->getSectionTitle('contact', __('Questions Before You Sign Up?')) }}
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 max-w-lg mx-auto">
                    {{ $branding->getSectionSubtitle('contact', __('Need help deciding which POS plan fits your business best? Our team responds within a few hours.')) }}
                </p>
            </div>
            <div class="p-6 sm:p-10 rounded-3xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-lg">
                <x-landing.contact-form />
            </div>
        </div>
    </section>
    @endif

</div>
@endsection
