@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'full' => false,
])
@php
    $sizes = [
        'sm' => 'px-3 py-2 text-xs gap-1.5',
        'md' => 'px-4 py-2.5 text-sm gap-2',
        'lg' => 'px-5 py-3.5 text-base gap-2.5',
    ];

    // Flat, high-contrast, no gradients/glass — matches the approved
    // "clean, minimal, touch-friendly" direction. bg-theme-primary /
    // text-theme-primary resolve against the tenant's own branded accent
    // color (--color-primary, set per-request in the layout).
    $variants = [
        'primary' => 'bg-theme-primary text-white hover:opacity-90 shadow-sm',
        'secondary' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700',
        'ghost' => 'bg-transparent text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 shadow-sm',
        'outline' => 'bg-transparent border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800',
    ];

    $classes = 'inline-flex items-center justify-center rounded-xl font-bold transition active:scale-[0.97] disabled:opacity-50 disabled:pointer-events-none cursor-pointer select-none '
        .($sizes[$size] ?? $sizes['md']).' '
        .($variants[$variant] ?? $variants['primary'])
        .($full ? ' w-full' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
