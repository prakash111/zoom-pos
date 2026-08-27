@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name)
@section('meta_description', $branding->platform_name . ' — an all-in-one POS, inventory, dining table KOT, and finance platform for retail and restaurant businesses.')

@section('content')

    <x-landing.hero :branding="$branding" />

    @if ($branding->isSectionEnabled('trust_bar'))
        <x-landing.trust-bar />
    @endif

    @if ($branding->isSectionEnabled('features'))
        <x-landing.features />
    @endif

    @if ($branding->isSectionEnabled('solutions'))
        <x-landing.pillars />
    @endif

    {{-- Custom Page Content (authored via TinyMCE if selected) --}}
    @if (!empty($page->content))
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content rounded-3xl bg-slate-900/80 border border-white/10 p-8 sm:p-12 text-white">
                {!! clean_html($page->content) !!}
            </div>
        </div>
    @endif

    @if ($branding->isSectionEnabled('stats'))
        <x-landing.stats />
    @endif

    @if ($branding->isSectionEnabled('about'))
        <x-landing.about :branding="$branding" />
    @endif

    @if ($branding->isSectionEnabled('testimonials'))
        <x-landing.testimonials :branding="$branding" />
    @endif

    @if ($branding->isSectionEnabled('pricing') && $plans->isNotEmpty())
        <x-landing.pricing :plans="$plans" />
    @endif

    @if ($branding->isSectionEnabled('contact'))
        <x-landing.contact />
    @endif

    @if ($branding->isSectionEnabled('cta'))
        <x-landing.cta />
    @endif

@endsection
