@extends('tenants.store.layout')

@section('title', ($page->meta_title ?: $page->title) . ' — ' . ($company->name ?? 'Storefront'))

@section('head')
@if(!empty($page->meta_description))
    <meta name="description" content="{{ $page->meta_description }}">
@endif
@endsection

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-6">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-400">
        <a href="{{ route('tenant.store') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition flex items-center gap-1">
            <span>🏠</span> {{ __('Home') }}
        </a>
        <span>/</span>
        <span class="text-slate-700 dark:text-slate-300 font-bold truncate">{{ $page->title }}</span>
    </nav>

    <!-- Main Content Card -->
    <article class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-10 shadow-sm space-y-6">
        <header class="border-b border-slate-100 dark:border-slate-800 pb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ $page->title }}
            </h1>
            <div class="flex items-center gap-3 mt-3 text-xs text-slate-400">
                <span>{{ __('Updated') }} {{ $page->updated_at?->format('M d, Y') ?? date('M d, Y') }}</span>
                <span>&bull;</span>
                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $company->name }}</span>
            </div>
        </header>

        <!-- Page Body -->
        <div class="prose dark:prose-invert max-w-none text-sm sm:text-base leading-relaxed text-slate-700 dark:text-slate-300 space-y-4">
            @if(str_contains($page->content ?? '', '<p>') || str_contains($page->content ?? '', '<div>') || str_contains($page->content ?? '', '<br>'))
                {!! $page->content !!}
            @else
                {!! nl2br(e($page->content ?? '')) !!}
            @endif
        </div>

        <footer class="pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs">
            <a href="{{ route('tenant.store') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition">
                <span>←</span> {{ __('Back to Store') }}
            </a>
            <a href="{{ route('tenant.store') }}#products-section" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition shadow-xs">
                <span>🛍️</span> {{ __('Browse Products') }}
            </a>
        </footer>
    </article>
</div>
@endsection
