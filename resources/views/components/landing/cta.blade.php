@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('cta', __('Instant Store Provisioning'));
    $title = $branding->getSectionTitle('cta', __('Ready to Scale Your Online & In-Store Sales?'));
    $subtitle = $branding->getSectionSubtitle('cta', __('Launch your omnichannel store workspace in 60 seconds. Sell online, ring up counter checkouts offline, and sync stock across branches. No credit card required.'));
    $primaryText = $branding->landingText('cta.primary_text', $branding->getHeroCtaPrimaryText() ?: __('Create Your Workspace'));
    $primaryUrl = $branding->landingText('cta.primary_url', route('tenant.register'));
    $secondaryText = $branding->landingText('cta.secondary_text', __('Sign in'));
    $secondaryUrl = $branding->landingText('cta.secondary_url', route('tenant.login'));
@endphp

<!-- CTA Section -->
<section id="cta" class="landing-sec-cta landing-dark-section max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 transition-colors duration-300">
    <div class="relative overflow-hidden rounded-[2.5rem] sm:rounded-[3rem] bg-gradient-to-br from-[#0c5966] via-[#10707e] to-[#7dbf3d] px-8 sm:px-16 py-16 sm:py-20 text-center shadow-2xl border border-white/20">
        <!-- Glowing background orbs -->
        <div class="absolute -left-20 -top-20 w-80 h-80 rounded-full bg-teal-300/30 blur-3xl pointer-events-none"></div>
        <div class="absolute -right-20 -bottom-20 w-96 h-96 rounded-full bg-lime-300/35 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-2xl mx-auto">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-xs font-black uppercase tracking-wider mb-4 border border-white/20">
                {{ $badge }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                {{ $title }}
            </h2>
            <p class="mt-4 text-sm sm:text-base text-white/90 leading-relaxed max-w-xl mx-auto">
                {{ $subtitle }}
            </p>
            <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ $primaryUrl }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-slate-950 hover:bg-slate-900 text-white font-black text-sm shadow-2xl shadow-slate-950/40 transition active:scale-95 inline-flex items-center justify-center gap-2">
                    <span>{{ $primaryText }}</span>
                    <span>→</span>
                </a>
                <a href="{{ $secondaryUrl }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-white/20 hover:bg-white/30 backdrop-blur-md text-white font-bold text-sm transition border border-white/20">
                    {{ $secondaryText }}
                </a>
            </div>
        </div>
    </div>
</section>
