@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('contact', __('Get In Touch'));
    $title = $branding->getSectionTitle('contact', __('Speak with an Omnichannel POS Specialist'));
    $subtitle = $branding->getSectionSubtitle('contact', __("Need a tailored setup for multi-location retail, restaurant chains, or online catalog migration? Our solutions engineering team replies within 24 hours."));
@endphp

<!-- Contact Section -->
<section id="contact" class="landing-sec-contact py-20 sm:py-28 scroll-mt-20 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto rounded-3xl contact-form-card p-8 sm:p-12 shadow-2xl relative overflow-hidden border transition-colors duration-300">
            <div class="absolute -top-20 -right-20 w-60 h-60 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
                    {{ $badge }}
                </span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">{{ $title }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 max-w-xl mx-auto">{{ $subtitle }}</p>
            </div>
            <x-landing.contact-form />
        </div>
    </div>
</section>
