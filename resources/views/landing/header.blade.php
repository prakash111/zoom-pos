@props(['branding' => null, 'navigation' => null])
@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $navigation = $navigation ?? [
        ['title' => __('Platform'), 'url' => '#showcase'],
        ['title' => __('Products'), 'url' => '#features'],
        ['title' => __('Solutions'), 'url' => '#solutions'],
        ['title' => __('Pricing'), 'url' => '#pricing'],
        ['title' => __('About'), 'url' => '#about'],
        ['title' => __('Contact'), 'url' => '#contact'],
    ];
@endphp

@include('landing.partials.reference-header', [
    'publicHeaderMenu' => $navigation,
    'publicBranding' => $branding,
    'publicLanguages' => \App\Models\Language::where('is_active', true)->orderBy('name')->get(),
    'publicActiveLang' => \App\Models\Language::where('code', app()->getLocale())->first(),
])
