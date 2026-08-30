{{--
    One speed-dial tray item ("layout === 'speed-dial'"). Mechanical
    extraction — see nav/rail-item.blade.php for the same caveat. Unlike the
    other three renderings this one has no active/inactive color state at
    all in the source — every item always renders the same badge color.
--}}
@props([
    'route',
    'itemKey' => null,
    'label',
    'variant' => 'blue', // 'blue' (default) | 'lime' (restaurant POS)
])
@php
    $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
@endphp
<a @if($itemKey) x-show="isItemVisible('{{ $itemKey }}')" @endif wire:navigate.hover href="{{ $route }}" class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl hover:bg-white/20 transition">
    <span>{{ $label }}</span>
    <span @class([
        'w-8 h-8 rounded-full flex items-center justify-center',
        'bg-lime-500 text-slate-950' => $variant === 'lime',
        'bg-blue-600 text-white' => $variant !== 'lime',
    ])>{{ $slot }}</span>
</a>
