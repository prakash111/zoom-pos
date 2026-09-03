{{--
    One detailed slide-out-drawer item (icon badge + title + subtitle).
    Mechanical extraction — see nav/rail-item.blade.php for the same caveat.
    The drawer has no "active page" highlighting at all (unlike the other
    four renderings) — only a hover-color pair (blue everywhere except the
    restaurant-mode section, which hovers lime) and, for exactly two items
    (Restaurant POS Terminal, Cashier POS Terminal), a permanently-tinted
    resting badge instead of the neutral slate one everything else uses.
--}}
@props([
    'route',
    'title',
    'subtitle',
    'hover' => 'blue', // 'blue' (default) | 'lime' (restaurant-mode section)
    'highlighted' => false,
    // Opaque nav-customization key (Settings > Navigation Menu) — matches
    // the mobile app's _FeatureTile.key so both platforms' nav_config stay
    // in sync. Null for items not offered as customizable (rare).
    'itemKey' => null,
])
@php
    // Interpolated component attributes arrive HTML-encoded. Decode that
    // layer so the escaped output below renders entities exactly once.
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $subtitle = html_entity_decode($subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $linkHover = $hover === 'lime'
        ? 'hover:bg-lime-50 dark:hover:bg-lime-950/50 hover:text-lime-600'
        : 'hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600';
    $badgeHover = $hover === 'lime'
        ? 'group-hover:bg-[#a3e635] group-hover:text-slate-950'
        : 'group-hover:bg-blue-600 group-hover:text-white';
    $badgeRest = $highlighted
        ? ($hover === 'lime'
            ? 'bg-lime-50 dark:bg-lime-950/60 text-lime-600 dark:text-lime-400'
            : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400')
        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
@endphp
@if (!$itemKey || !in_array($itemKey, $hiddenNavKeys ?? [], true))
<a wire:navigate.hover href="{{ $route }}" @if($itemKey) data-item-key="{{ $itemKey }}" @endif class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-slate-700 dark:text-slate-300 {{ $linkHover }} transition group">
    <span class="w-7 h-7 rounded-xl {{ $badgeRest }} flex items-center justify-center text-xs {{ $badgeHover }} transition">
        {{ $slot }}
    </span>
    <div>
        <div class="font-bold">{{ $title }}</div>
        <div class="text-[10px] text-slate-400 font-normal">{{ $subtitle }}</div>
    </div>
</a>
@endif
