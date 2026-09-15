@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name)
@section('content')
    <main class="landing-page landing-custom min-h-screen">
        {!! clean_html((string) data_get($branding->landing_content ?? [], 'html', '')) !!}
    </main>
@endsection
