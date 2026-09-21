@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', ($contactSettings['page_title'] ?: __('Get in Touch')) . ' — ' . $branding->platform_name)
@section('meta_description', $contactSettings['page_subtitle'] ?: __('Contact our cloud POS solutions team for onboarding, inquiries, and technical support.'))

@section('content')
<div class="min-h-screen bg-white dark:bg-[#0b0f19] text-slate-900 dark:text-white transition-colors duration-300">
    <x-landing.contact :branding="$branding" :contact-settings="$contactSettings" :fields="$fields" />
</div>
@endsection
