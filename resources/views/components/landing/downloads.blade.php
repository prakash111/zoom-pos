@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('downloads', __('Native Mobile & Desktop Apps'));
    $title = $branding->getSectionTitle('downloads', __('Take Your Counter and Store Anywhere'));
    $subtitle = $branding->getSectionSubtitle('downloads', __('Install our high-performance native Android or Windows apps for sub-second offline speed, instant USB/Bluetooth thermal printer integration, and a dedicated full-screen till experience.'));
    $hasDownloads = $branding->hasAnyDownloadLink();
@endphp

@if ($hasDownloads)
    <div id="download" class="scroll-mt-20 border-y border-white/10 bg-slate-900/50 py-16 sm:py-24 relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">
                {{ $badge }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white">
                {{ $title }}
            </h2>
            @if ($subtitle)
                <p class="mt-3 text-sm sm:text-base text-slate-400 max-w-xl mx-auto">
                    {{ $subtitle }}
                </p>
            @endif
            <x-landing.download-buttons :branding="$branding" size="lg" class="mt-8 justify-center" />
        </div>
    </div>
@endif
