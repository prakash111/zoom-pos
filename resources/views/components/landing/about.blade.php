@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('about', __('Our Mission'));
    $title = $branding->getSectionTitle('about', __('Built to Turn Every Online Store & Counter into a High-Revenue Machine'));
    $defaultBody = $branding->platform_name . ' ' . __('is engineered to give digital brands, retail store chains, and restaurants a modern commerce operating system. Unify your online storefront, barcode checkout, multi-warehouse inventory, and WhatsApp tax invoicing into one zero-latency engine. Sell uninterrupted online and offline, eliminate inventory discrepancies, and maximize sales across every customer touchpoint.');
    $body = $branding->getSectionBody('about', $branding->getSectionSubtitle('about', $defaultBody));
@endphp

<!-- Mission Section -->
<section id="about" class="landing-sec-mission landing-sec-about landing-dark-section py-20 transition-colors duration-300 scroll-mt-20">
    <div class="max-w-6xl mx-auto px-4 text-center">
        <div class="rounded-3xl bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-8 sm:p-14 text-center max-w-4xl mx-auto relative overflow-hidden shadow-lg dark:shadow-2xl">
            <div class="absolute -top-16 -left-16 w-48 h-48 bg-teal-500/10 dark:bg-teal-500/20 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-16 -right-16 w-48 h-48 bg-brand-lime/10 dark:bg-brand-lime/20 rounded-full blur-2xl"></div>
            
            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 mb-4">
                {{ $badge }}
            </span>
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
            <p class="mt-5 text-sm sm:text-base text-slate-700 dark:text-slate-300 leading-relaxed">
                {{ $body }}
            </p>
        </div>
    </div>
</section>
