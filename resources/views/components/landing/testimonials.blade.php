@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $rawTestimonials = $branding ? $branding->landingTestimonials() : [];
    $testimonials = array_map(function ($t) {
        return [
            'quote' => $t['quote'],
            'name' => $t['name'],
            'role' => $t['role'],
            'avatar' => \App\Models\PlatformBranding::testimonialInitials($t['name']),
        ];
    }, $rawTestimonials);
    $badge = $branding->getSectionBadge('testimonials', __('Proven Results'));
    $title = $branding->getSectionTitle('testimonials', __('Trusted by Leading Online Brands & Retailers Worldwide'));
    $subtitle = $branding->getSectionSubtitle('testimonials', __('See how omnichannel businesses use our cloud commerce engine to drive revenue and save hours every single day.'));
@endphp

<div id="testimonials" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28">
    <div class="text-center mb-12 sm:mb-16">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
            {{ $badge }}
        </span>
        <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
        @foreach ($testimonials as $t)
            <div class="rounded-3xl bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-7 sm:p-8 flex flex-col justify-between hover:border-emerald-500/30 transition-all shadow-lg dark:shadow-xl">
                <div>
                    <!-- Star rating -->
                    <div class="flex items-center gap-1 text-brand-lime text-sm mb-4">
                        ★★★★★
                    </div>
                    <p class="text-sm sm:text-base text-slate-700 dark:text-slate-300 leading-relaxed font-normal">
                        &ldquo;{{ $t['quote'] }}&rdquo;
                    </p>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-200 dark:border-white/10 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-lime to-emerald-400 text-slate-950 font-black text-xs flex items-center justify-center shrink-0">
                        {{ $t['avatar'] }}
                    </div>
                    <div>
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ $t['name'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{!! $t['role'] !!}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Security & Trust Badges -->
    <div class="mt-16 pt-8 border-t border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-xs font-bold text-slate-600 dark:text-slate-400">
        <span class="flex items-center gap-2"><span class="text-brand-lime">🔒</span> {{ __('PCI-DSS Certified') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">☁️</span> {{ __('Auto Cloud Redundancy') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">🌍</span> {{ __('150+ Currencies Supported') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">📶</span> {{ __('Zero-Downtime Offline Mode') }}</span>
    </div>
</div>
