@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Modern Cloud POS & Smart Inventory SaaS')
@section('meta_description', $branding->platform_name . ' — an all-in-one POS, multi-branch inventory, restaurant KOT, and finance automation platform for modern retail stores and food businesses.')

@section('content')

    {{-- 1. Modern Aurora SaaS Hero --}}
    <x-landing.hero :branding="$branding" />

    {{-- 2. Social Proof & Hardware Trust Bar --}}
    @if ($branding->isSectionEnabled('trust_bar'))
        <x-landing.trust-bar />
    @endif

    {{-- 3. Interactive Operations Suite (POS, Stock, Dining KOT, Finance) --}}
    @if ($branding->isSectionEnabled('features'))
        <x-landing.features />
    @endif

    {{-- 4. Scale & Architecture Value Pillars --}}
    @if ($branding->isSectionEnabled('solutions'))
        <x-landing.pillars />
    @endif

    {{-- 5. Custom CMS Page Body Content (If authoring via TinyMCE) --}}
    @if (!empty($page->content))
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content rounded-3xl bg-slate-900/90 border border-white/10 p-8 sm:p-12 text-white shadow-2xl backdrop-blur-xl">
                {!! clean_html($page->content) !!}
            </div>
        </div>
    @endif

    {{-- 6. Performance Telemetry & Counter Stats --}}
    @if ($branding->isSectionEnabled('stats'))
        <x-landing.stats />
    @endif

    {{-- 7. Platform Architecture & Story --}}
    @if ($branding->isSectionEnabled('about'))
        <x-landing.about :branding="$branding" />
    @endif

    {{-- 8. Customer Reviews & Trust Badges --}}
    @if ($branding->isSectionEnabled('testimonials'))
        <x-landing.testimonials :branding="$branding" />
    @endif

    {{-- 9. Subscription Plans & Billing Tiers --}}
    @if ($branding->isSectionEnabled('pricing') && $plans->isNotEmpty())
        <x-landing.pricing :plans="$plans" />
    @endif

    {{-- 10. Direct Sales & Technical Support Contact --}}
    @if ($branding->isSectionEnabled('contact'))
        <x-landing.contact />
    @endif

    {{-- 11. Final Conversion CTA Banner --}}
    @if ($branding->isSectionEnabled('cta'))
        <x-landing.cta />
    @endif

@endsection
