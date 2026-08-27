@props([
    'name' => null,
    'maxWidth' => '3xl', // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl
    'title' => '',
    'subtitle' => '',
    'icon' => '📄',
])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
][$maxWidth] ?? 'sm:max-w-3xl';

$wireModel = $attributes->wire('model')->value();
@endphp

<div x-data="{ 
        open: @if($wireModel) $wire.entangle('{{ $wireModel }}') @else false @endif,
        get show() { return this.open; },
        set show(val) { this.open = val; }
     }"
     x-show="open"
     x-on:keydown.escape.window="open = false"
     x-on:close.stop="open = false"
     x-cloak
     role="dialog"
     aria-modal="true"
     @if($title) aria-label="{{ $title }}" @endif
     class="no-print fixed inset-0 z-50 overflow-y-auto"
     style="display: none;"
     {{ $attributes->except(['wire:model', 'wire:model.live']) }}>
    
    <!-- Centered Modal Stage -->
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-6 text-center">
        
        <!-- Backdrop with Blur -->
        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="open = false"
             class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Card Wrapper -->
        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
             class="relative w-full {{ $maxWidthClass }} bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-2xl overflow-hidden text-left flex flex-col max-h-[88vh] z-10">
            
            <!-- Header -->
            @if(isset($header))
                <div class="px-6 py-4 border-b border-slate-150 dark:border-slate-800 flex items-center justify-between flex-shrink-0 bg-white dark:bg-slate-900">
                    {{ $header }}
                </div>
            @elseif($title || $subtitle || $icon)
                <div class="px-6 py-4 border-b border-slate-150 dark:border-slate-800 flex items-center justify-between flex-shrink-0 bg-white dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        @if($icon)
                            <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg shadow-sm border border-blue-100 dark:border-blue-900/30 flex-shrink-0">
                                {{ $icon }}
                            </div>
                        @endif
                        <div>
                            <h3 class="text-sm font-black tracking-tight text-slate-800 dark:text-slate-100">
                                {{ $title }}
                            </h3>
                            @if($subtitle)
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>
                    <button type="button" @click="open = false" aria-label="{{ __('Close dialog') }}" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 flex items-center justify-center transition cursor-pointer">
                        ✕
                    </button>
                </div>
            @endif

            <!-- Body Container with Scroll -->
            <div class="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                {{ $slot }}
            </div>

            <!-- Optional Footer -->
            @if(isset($footer))
                <div class="px-6 py-4 border-t border-slate-150 dark:border-slate-800 flex items-center justify-end gap-3 flex-shrink-0 bg-slate-50/70 dark:bg-slate-900/70">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
