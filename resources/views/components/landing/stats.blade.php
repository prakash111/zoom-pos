@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $stats = $branding->landingStatsList();
    $title = $branding->getSectionTitle('stats', '');
    $subtitle = $branding->getSectionSubtitle('stats', '');
@endphp

<div id="stats" class="landing-sec-stats border-y border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-950/80 backdrop-blur-xl relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/5 via-lime-500/5 to-teal-500/5 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 relative z-10">
        @if ($title)
            <div class="text-center mb-10">
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">{{ $subtitle }}</p>
                @endif
            </div>
        @endif
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-8 sm:gap-6 text-center">
            @foreach ($stats as $index => $stat)
                <div class="p-4 rounded-2xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/5 shadow-sm dark:shadow-none">
                    <div class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white">{{ $stat['value'] }}</div>
                    <div class="w-8 h-1 rounded-full {{ $index % 2 === 0 ? 'bg-brand-lime' : 'bg-emerald-400' }} mx-auto mt-3 mb-2"></div>
                    <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 font-semibold">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
