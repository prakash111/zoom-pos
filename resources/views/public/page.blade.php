@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $page->title . ' — ' . $branding->platform_name)
@if ($page->meta_description)
    @section('meta_description', $page->meta_description)
@endif

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight mb-8">{{ $page->title }}</h1>
        <div class="page-content">
            {!! $page->content !!}
        </div>
    </div>
@endsection
