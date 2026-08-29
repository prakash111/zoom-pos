@props(['variant' => 'neutral'])
@php
    $variants = [
        'neutral' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300',
        'success' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400',
        'warning' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400',
        'danger' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400',
        'info' => 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-400',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold whitespace-nowrap '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
