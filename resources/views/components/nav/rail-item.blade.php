{{--
    One slim-rail / macOS-dock-adjacent nav item shape used by the "slim"
    layout mode in layouts/tenant.blade.php. Every item in that mode
    previously hand-duplicated this exact same position-aware :class binding
    and active/inactive color pair — this component is a mechanical
    extraction of that identical markup, not a redesign: passing the same
    props back in reproduces byte-identical output to what was inline
    before, verified against tests/Feature/Navigation/DockableMultiPositionNavigationTest.php.
--}}
@props([
    'route',
    'active',
    'itemKey' => null,
    'label',
    'title' => null,
    'variant' => 'blue', // 'blue' (default) | 'lime' (restaurant POS) | 'white' (home)
    'labelSize' => 'text-[10px] sm:text-[11px]',
])
@php
    $activeClasses = match ($variant) {
        'lime' => 'bg-[#a3e635] text-slate-950 shadow-xl font-extrabold',
        default => 'bg-white text-blue-600 shadow-xl font-extrabold',
    };
    $inactiveClasses = match ($variant) {
        'lime' => 'text-lime-200 hover:text-white hover:bg-white/20 font-medium',
        'white' => 'text-white/80 hover:text-white hover:bg-white/20 font-medium',
        default => 'text-blue-100 hover:text-white hover:bg-white/20 font-medium',
    };
@endphp
<a @if($itemKey) x-show="isItemVisible('{{ $itemKey }}')" @endif wire:navigate.hover href="{{ $route }}"
   class="dockable-nav-item"
   aria-selected="{{ $active ? 'true' : 'false' }}"
   :class="{
       'w-full py-3.5 sm:py-4 px-1 rounded-2xl sm:rounded-3xl flex flex-col items-center gap-1.5': position === 'left' || position === 'right',
       'px-3 sm:px-3.5 py-2 rounded-2xl flex flex-row items-center gap-2 shrink-0': position === 'top' || position === 'bottom',
       'p-2.5 rounded-2xl flex flex-col items-center gap-1 shrink-0': position === 'floating'
   }"
   @class([
       'transition-all group duration-200 cursor-pointer',
       $activeClasses => $active,
       $inactiveClasses => ! $active,
   ])
   title="{{ $title ?? $label }}">
    {{ $slot }}
    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label {{ $labelSize }}' : 'text-xs whitespace-nowrap font-bold'">{{ $label }}</span>
</a>
