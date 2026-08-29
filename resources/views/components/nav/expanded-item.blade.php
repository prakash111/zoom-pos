{{--
    One expanded-sidebar nav item ("layout === 'expanded'" in
    layouts/tenant.blade.php). Mechanical extraction of the identical
    per-item markup every item in that mode hand-duplicated — same caveat as
    nav/rail-item.blade.php: reproduces byte-identical output, not a
    redesign. Note the expanded sidebar shows a DIFFERENT subset of items
    than the slim rail (e.g. no consignments/service-orders/sales-targets
    here, no categories/catalog there) — that's existing behavior, not
    something this component changes.
--}}
@props([
    'route',
    'active',
    'itemKey' => null,
    'title',
    'subtitle',
    'navTitle' => null,
    'variant' => 'blue', // 'blue' (default) | 'lime' (restaurant POS)
])
@php
    $activeClasses = $variant === 'lime' ? 'bg-[#a3e635] text-slate-950 shadow-md' : 'bg-white text-blue-700 shadow-md';
@endphp
<a @if($itemKey) x-show="isItemVisible('{{ $itemKey }}')" @endif wire:navigate.hover href="{{ $route }}"
   :class="{
       'w-full px-3 py-2 rounded-2xl flex items-center gap-3 transition font-bold': position === 'left' || position === 'right',
       'px-3 py-1.5 rounded-2xl flex items-center gap-2 shrink-0 transition font-bold text-xs whitespace-nowrap': position === 'top' || position === 'bottom',
       'px-3 py-2 rounded-2xl flex items-center gap-2.5 transition font-bold text-xs': position === 'floating'
   }"
   @class([
       $activeClasses => $active,
       'text-white/80 hover:text-white hover:bg-white/15' => ! $active,
   ])
   title="{{ $navTitle ?? $title }}">
    {{ $slot }}
    <div :class="{ 'flex-1 min-w-0': position === 'left' || position === 'right', 'shrink-0': position === 'top' || position === 'bottom' }">
        <div class="text-xs truncate">{{ $title }}</div>
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-normal opacity-70 truncate">{{ $subtitle }}</div>
    </div>
</a>
