@props(['title', 'type', 'items' => [], 'fields' => []])
@php($property = ['highlight'=>'landingHeroHighlights','product'=>'landingHeroProducts','hardware'=>'landingHardwareItems','stat'=>'landingStats','solution'=>'landingSolutions'][$type] ?? null)
@if ($property)
<div class="space-y-2">
    <div class="flex items-center justify-between">
        <label class="text-xs font-black uppercase tracking-wider text-slate-500">{{ __($title) }}</label>
        <button type="button" wire:click="addLandingItem('{{ $type }}')" class="px-3 py-1 rounded-lg bg-indigo-600 text-white text-[11px] font-bold">+ {{ __('Add') }}</button>
    </div>
    @foreach ($items as $i => $item)
        <div wire:key="landing-{{ $type }}-{{ $i }}" class="grid grid-cols-1 sm:grid-cols-{{ min(count($fields), 4) }} gap-2 items-center">
            @foreach ($fields as $j => $field)
                @if ($type === 'highlight')
                    <input type="text" wire:model="{{ $property }}.{{ $i }}" placeholder="{{ __($field) }}" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                @else
                    <input type="text" wire:model="{{ $property }}.{{ $i }}.{{ $j }}" placeholder="{{ __($field) }}" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                @endif
            @endforeach
            <button type="button" wire:click="removeLandingItem('{{ $type }}', {{ $i }})" class="text-rose-600 text-xs font-bold sm:col-span-1">{{ __('Remove') }}</button>
        </div>
    @endforeach
</div>
@endif
