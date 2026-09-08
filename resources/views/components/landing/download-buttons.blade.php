@props(['branding', 'size' => 'md'])

@php
    $play = $branding->playStoreLink();
    $win = $branding->windowsAppLink();
    $pad = $size === 'lg' ? 'px-5 py-3.5' : 'px-4 py-2.5';
    $iconBox = $size === 'lg' ? 'w-6 h-6' : 'w-5 h-5';
@endphp

@if ($play || $win)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3']) }}>
        @if ($play)
            <a href="{{ $play }}" target="_blank" rel="noopener"
               class="group inline-flex items-center gap-3 {{ $pad }} rounded-2xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 shadow-sm transition active:scale-95">
                <svg class="{{ $iconBox }} shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#00d2ff" d="M3.6 2.2 13 11.6 3.6 21A1.7 1.7 0 0 1 3 19.7V3.5c0-.5.2-1 .6-1.3z"/>
                    <path fill="#00e676" d="M13 11.6 3.6 2.2c.1-.1.3-.1.5-.1.3 0 .6.1.8.2l11.4 6.5-3.3 2.8z"/>
                    <path fill="#ffea00" d="m17 8.9 3 1.7c.6.4 1 .9 1 1.4s-.4 1-1 1.4l-3 1.7-3.5-3 3.5-3.2z"/>
                    <path fill="#ff3d00" d="M13 12.4 16.3 15.2 4.9 21.7c-.2.1-.5.2-.8.2s-.4 0-.5-.1L13 12.4z"/>
                </svg>
                <span class="text-left leading-tight">
                    <span class="block text-[10px] font-semibold opacity-70">{{ __('GET IT ON') }}</span>
                    <span class="block text-sm font-black">{{ __('Google Play') }}</span>
                </span>
            </a>
        @endif

        @if ($win)
            <a href="{{ $win }}"
               class="group inline-flex items-center gap-3 {{ $pad }} rounded-2xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 shadow-sm transition active:scale-95">
                <svg class="{{ $iconBox }} shrink-0" viewBox="0 0 24 24" fill="#0ea5e9" aria-hidden="true">
                    <path d="M3 5.1 10.4 4v7.3H3V5.1zm0 13.8 7.4 1v-7.2H3v6.2zM11.3 3.9 21 2.5v9H11.3V3.9zm0 16.2L21 21.5v-9H11.3v7.6z"/>
                </svg>
                <span class="text-left leading-tight">
                    <span class="block text-[10px] font-semibold opacity-70">{{ __('DOWNLOAD FOR') }}</span>
                    <span class="block text-sm font-black">{{ __('Windows') }}</span>
                </span>
            </a>
        @endif
    </div>
@endif
