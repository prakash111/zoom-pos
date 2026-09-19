@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Fast Cloud POS, Omnichannel Commerce & Inventory')
@section('meta_description', $branding->platform_name . ' — Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp invoicing into one lightning-fast cloud POS. Built to maximize sales online and in-store.')

@section('content')

    @foreach ($branding->landingSectionOrder() as $section)
        @if ($branding->isSectionEnabled($section))
            @switch($section)
                @case('hero')
                    <x-landing.hero :branding="$branding" />
                    @break
                @case('trust_bar')
                    <x-landing.trust-bar :branding="$branding" />
                    @break
                @case('features')
                    <x-landing.features :branding="$branding" />
                    @break
                @case('solutions')
                    <x-landing.pillars :branding="$branding" />
                    @break
                @case('downloads')
                    <x-landing.downloads :branding="$branding" />
                    @break
                @case('stats')
                    <x-landing.stats :branding="$branding" />
                    @break
                @case('about')
                    <x-landing.about :branding="$branding" />
                    @break
                @case('testimonials')
                    <x-landing.testimonials :branding="$branding" />
                    @break
                @case('pricing')
                    @if ($plans->isNotEmpty())
                        <x-landing.pricing :plans="$plans" :branding="$branding" />
                    @endif
                    @break
                @case('faq')
                    <x-landing.faq :branding="$branding" />
                    @break
                @case('contact')
                    <x-landing.contact :branding="$branding" />
                    @break
                @case('cta')
                    <x-landing.cta :branding="$branding" />
                    @break
            @endswitch
        @endif
    @endforeach

    {{-- Custom CMS Page Body Content (If authoring via TinyMCE) --}}
    @if (!empty($page->content))
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content rounded-3xl bg-slate-900/90 border border-white/10 p-8 sm:p-12 text-white shadow-2xl backdrop-blur-xl">
                {!! clean_html($page->content) !!}
            </div>
        </div>
    @endif

@endsection
