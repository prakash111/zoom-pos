@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name)
@section('meta_description', $branding->platform_name . ' — an all-in-one POS, inventory, dining table KOT, and finance platform for retail and restaurant businesses.')

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

    {{-- Custom Page Content (authored via TinyMCE if selected) --}}
    @if (!empty($page->content))
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
            <div class="page-content rounded-3xl bg-slate-900/80 border border-white/10 p-8 sm:p-12 text-white">
                {!! clean_html($page->content) !!}
            </div>
        </div>
    @endif

@endsection
