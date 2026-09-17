@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $hardware = $branding->landingHardware();
    $title = $branding->getSectionTitle('trust_bar', __('Works out of the box with your existing retail & dining hardware'));
@endphp

<div id="trust_bar" class="landing-sec-trust max-w-6xl mx-auto px-4 sm:px-6 -mt-3 sm:-mt-5 relative z-30">
    <div class="rounded-3xl bg-white dark:bg-slate-950/90 backdrop-blur-2xl border border-slate-200 dark:border-slate-800 shadow-xl dark:shadow-2xl px-6 sm:px-10 py-6 sm:py-7">
        <p class="text-center text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-6 flex items-center justify-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-lime animate-pulse"></span>
            <span>{{ $title }}</span>
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-6">
            @foreach ($hardware as $item)
                <div class="flex flex-col items-center justify-center text-center p-3.5 rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-slate-100 dark:hover:bg-white/10 border border-slate-200/80 dark:border-white/5 hover:border-brand-lime/30 transition-all group shadow-sm dark:shadow-none">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/10 text-emerald-600 dark:text-brand-lime flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                        @if ($item['icon'] === 'printer')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v5M6 18H4a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2M6 14h12v7H6z" /></svg>
                        @elseif ($item['icon'] === 'card')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5.5" width="19" height="13" rx="2" /><path stroke-linecap="round" d="M2.5 9.5h19" /><path stroke-linecap="round" d="M6 14.5h4" /></svg>
                        @elseif ($item['icon'] === 'drawer')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="1.5" /><path stroke-linecap="round" d="M9 12.5h6M3 11h18" /></svg>
                        @elseif ($item['icon'] === 'display')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4.5" width="18" height="12" rx="1.5" /><path stroke-linecap="round" d="M8 20h8M12 16.5V20" /></svg>
                        @else
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h2v12H4zm4 0h1v12H8zm3 0h2v12h-2zm4 0h1v12h-1zm3 0h2v12h-2z" /></svg>
                        @endif
                    </div>
                    <span class="text-xs font-black text-slate-900 dark:text-white leading-tight">{{ __($item['label']) }}</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium mt-1">{{ __($item['tag']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
