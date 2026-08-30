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
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $ringClasses = $variant === 'lime'
        ? 'bg-[#a3e635] text-slate-950 font-bold ring-2 ring-lime-400'
        : 'bg-white text-blue-600 font-bold ring-2 ring-blue-400';
@endphp
{{--
    Active/inactive styling is decided client-side via isCurrentRoute(), not
    the server-computed $active prop: this nav chrome is @persist'ed across
    wire:navigate visits (see layouts/tenant.blade.php), so it's no longer
    re-rendered by the server on every page — only a reactive Alpine binding
    stays correct as the URL changes underneath a persisted DOM node.
--}}
<a @if($itemKey) x-show="isItemVisible('{{ $itemKey }}')" @endif wire:navigate.hover href="{{ $route }}"
   class="dockable-nav-item group relative flex flex-col items-center hover:scale-125 transition-transform duration-200 origin-bottom shrink-0 cursor-pointer"
   :aria-selected="isCurrentRoute('{{ $route }}') ? 'true' : 'false'"
   title="{{ $title }}">
    <div :class="{
        'w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-md transition': true,
        '{{ $ringClasses }}': isCurrentRoute('{{ $route }}'),
        'bg-white/15 text-white hover:bg-white/30': !isCurrentRoute('{{ $route }}')
    }">
        {{ $slot }}
    </div>
</a>
