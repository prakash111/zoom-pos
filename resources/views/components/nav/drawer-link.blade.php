{{--
    One compact dot-style drawer item, used only in the drawer's
    "Administration & Settings" section. Mechanical extraction — see
    nav/rail-item.blade.php for the same caveat.
--}}
@props([
    'route',
    'title',
    'dot' => 'slate', // 'slate' (default) | 'blue' | 'emerald'
    'bold' => false,
    'badge' => null,
    'badgeColor' => 'blue', // 'blue' | 'emerald'
])
@php
    $dotColors = ['blue' => 'bg-blue-500', 'slate' => 'bg-slate-400', 'emerald' => 'bg-emerald-500'];
    $badgeColors = [
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
    ];
@endphp
<a wire:navigate.hover href="{{ $route }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition">
    <div class="flex items-center gap-2.5">
        <span class="w-2 h-2 rounded-full {{ $dotColors[$dot] ?? $dotColors['slate'] }}"></span>
        <span @class(['font-bold' => $bold])>{{ $title }}</span>
    </div>
    @if ($badge)
        <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded {{ $badgeColors[$badgeColor] ?? $badgeColors['blue'] }}">{{ $badge }}</span>
    @endif
</a>
