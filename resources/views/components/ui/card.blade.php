@props(['padding' => 'md'])
@php
    $paddings = ['none' => '', 'sm' => 'p-3', 'md' => 'p-5', 'lg' => 'p-6 sm:p-7'];
@endphp
<div {{ $attributes->merge(['class' => 'bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] '.($paddings[$padding] ?? $paddings['md'])]) }}>
    {{ $slot }}
</div>
