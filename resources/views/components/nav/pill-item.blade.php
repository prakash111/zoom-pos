{{--
    One macOS-dock-style pill item ("layout === 'macos-dock'"). Mechanical
    extraction — see nav/rail-item.blade.php for the same caveat.
--}}
@props([
    'route',
    'active',
    'itemKey' => null,
    'title',
    'variant' => 'blue', // 'blue' (default) | 'lime' (restaurant POS)
])
@php
    $ringClasses = $variant === 'lime'
        ? 'bg-[#a3e635] text-slate-950 font-bold ring-2 ring-lime-400'
        : 'bg-white text-blue-600 font-bold ring-2 ring-blue-400';
@endphp
<a @if($itemKey) x-show="isItemVisible('{{ $itemKey }}')" @endif wire:navigate.hover href="{{ $route }}"
   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
   aria-selected="{{ $active ? 'true' : 'false' }}"
   title="{{ $title }}">
    <div @class([
        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition',
        $ringClasses => $active,
        'bg-white/15 text-white hover:bg-white/30' => ! $active,
    ])>
        {{ $slot }}
    </div>
</a>
