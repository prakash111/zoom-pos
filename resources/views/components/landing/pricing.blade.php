@props(['plans', 'branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('pricing', __('Predictable Investment'));
    $title = $branding->getSectionTitle('pricing', __('Simple, transparent pricing for every tier'));
    $subtitle = $branding->getSectionSubtitle('pricing', __('Launch in minutes with zero setup fees.'));
    $annualDiscountText = $branding->landingText('pricing.discount_badge', __('Save 20%'));
    $sorted = $plans->sortBy('price')->values();
    $popularName = $sorted->count() >= 2 ? $sorted[1]->name : null;

    $cards = $sorted->map(function ($plan) use ($popularName) {
        $days = $plan->duration_days;
        $isFlat = in_array($plan->billing_cycle, ['trial', 'lifetime'], true);
        $isYearlyOrLonger = ! $isFlat && $days && $days >= 300;

        $monthlyEquivalent = $days ? round(((float) $plan->price) * 30 / $days) : (float) $plan->price;
        $annualEquivalent = $days ? round(((float) $plan->price) * 365 / $days) : (float) $plan->price * 12;

        if ($plan->billing_cycle === 'trial') {
            $monthly = ['price' => __('Free'), 'suffix' => $days ? "{$days}-" . __('day trial') : __('trial')];
            $annual = $monthly;
            $discountBadge = false;
        } elseif ($plan->billing_cycle === 'lifetime') {
            $flat = $plan->currency.number_format((float) $plan->price, 0);
            $monthly = ['price' => $flat, 'suffix' => __('one-time payment')];
            $annual = $monthly;
            $discountBadge = false;
        } elseif ($isYearlyOrLonger) {
            $monthly = ['price' => $plan->currency.number_format($monthlyEquivalent, 0), 'suffix' => __('/mo, billed yearly')];
            $annual = ['price' => $plan->currency.number_format((float) $plan->price, 0), 'suffix' => __('/year')];
            $discountBadge = false;
        } else {
            $discountedAnnual = round($annualEquivalent * 0.8);
            $monthly = ['price' => $plan->currency.number_format((float) $plan->price, 0), 'suffix' => __('/month')];
            $annual = ['price' => $plan->currency.number_format($discountedAnnual, 0), 'suffix' => __('/year, billed annually')];
            $discountBadge = true;
        }

        return [
            'plan' => $plan,
            'monthly' => $monthly,
            'annual' => $annual,
            'discountBadge' => $discountBadge,
            'popular' => $plan->name === $popularName,
        ];
    });
@endphp

<!-- Pricing Section -->
<section id="pricing" class="landing-sec-pricing landing-dark-section py-20 border-t border-white/5 transition-colors duration-300 scroll-mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">
            {{ $badge }}
        </span>
        <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>

    <div x-data="{ annual: false }">
        <!-- Monthly / Annual Toggle Switch -->
        <div class="flex items-center justify-center gap-3 mb-14">
            <span class="text-xs sm:text-sm font-bold" :class="!annual ? 'text-slate-900 dark:text-white' : 'text-slate-400'">{{ __('Monthly Billing') }}</span>
            <button type="button" x-on:click="annual = !annual"
                    :class="annual ? 'bg-brand-lime' : 'bg-slate-300 dark:bg-slate-700'"
                    class="relative w-13 h-7 rounded-full transition-colors p-1" role="switch" :aria-checked="annual.toString()">
                <span class="block w-5 h-5 rounded-full bg-white dark:bg-slate-950 shadow transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs sm:text-sm font-bold flex items-center gap-2" :class="annual ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                {{ __('Annual Billing') }}
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-[10px] font-black uppercase tracking-wider border border-emerald-200 dark:border-emerald-500/30">{{ $annualDiscountText }}</span>
            </span>
        </div>

        @if ($cards->isNotEmpty())
            @php
                $gridColsClass = match (true) {
                    $cards->count() >= 3 => 'lg:grid-cols-3',
                    default => 'lg:grid-cols-2',
                };
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 {{ $gridColsClass }} gap-6 lg:gap-8 items-stretch">
                @foreach ($cards as $card)
                @php $plan = $card['plan']; @endphp
                <div class="relative rounded-3xl p-7 sm:p-8 flex flex-col justify-between transition-all duration-300
                    {{ $card['popular']
                        ? 'bg-white dark:bg-gradient-to-b dark:from-slate-900 dark:to-slate-950 border-2 border-brand-lime shadow-2xl shadow-brand-lime/10 scale-[1.02] z-10'
                        : 'bg-white dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20 shadow-md dark:shadow-xl' }}">

                    @if ($card['popular'])
                        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-brand-lime text-slate-950 text-[10px] font-black uppercase tracking-widest shadow-md">
                            {{ __('Most Popular Plan') }}
                        </span>
                    @endif

                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ $plan->display_name }}</div>

                        <div class="mt-4 flex items-baseline gap-1.5 min-h-[3rem]">
                            <span x-show="!annual" class="text-4xl font-black text-slate-900 dark:text-white">{{ $card['monthly']['price'] }}</span>
                            <span x-show="!annual" class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $card['monthly']['suffix'] }}</span>
                            <span x-show="annual" x-cloak class="text-4xl font-black text-slate-900 dark:text-white">{{ $card['annual']['price'] }}</span>
                            <span x-show="annual" x-cloak class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $card['annual']['suffix'] }}</span>
                        </div>

                        @if ($card['discountBadge'])
                            <div x-show="annual" x-cloak class="mt-1 text-[11px] font-bold text-emerald-600 dark:text-brand-lime">{{ __('Billed annually with 20% bonus discount') }}</div>
                        @endif

                        <ul class="mt-8 space-y-3 text-xs text-slate-600 dark:text-slate-300">
                            <li class="flex items-start gap-2.5">
                                <span class="text-brand-lime font-black shrink-0">✓</span>
                                <span>{{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Staff Logins') }} &middot; {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('Active POS Devices') }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-brand-lime font-black shrink-0">✓</span>
                                <span>{{ $plan->limits['armazenamento_mb'] ?? '∞' }} {{ __('MB High-Speed Cloud Storage') }}</span>
                            </li>
                            @if (!empty($plan->features['multi_location']))
                                <li class="flex items-start gap-2.5">
                                    <span class="text-brand-lime font-black shrink-0">✓</span>
                                    <span>{{ __('Multi-Location & Warehouse Sync') }}</span>
                                </li>
                            @endif
                            @if (!empty($plan->features['automatic_backup']))
                                <li class="flex items-start gap-2.5">
                                    <span class="text-brand-lime font-black shrink-0">✓</span>
                                    <span>{{ __('Continuous Automated Cloud Backups') }}</span>
                                </li>
                            @endif
                            <li class="flex items-start gap-2.5">
                                <span class="text-brand-lime font-black shrink-0">✓</span>
                                <span>{{ __('Full Retail & Dining Engines + Digital Invoicing') }}</span>
                            </li>
                        </ul>
                    </div>

                    <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                       class="mt-8 block text-center px-5 py-3.5 rounded-full font-black text-xs transition active:scale-95
                           {{ $card['popular']
                                ? 'bg-brand-lime hover:bg-brand-lime-dark text-slate-950 shadow-lg shadow-brand-lime/25'
                                : 'bg-white/10 hover:bg-white/20 text-white border border-white/10' }}">
                        {{ __('Select') }} {{ $plan->display_name }}
                    </a>
                </div>
                @endforeach
            </div>
        @endif

        @php
            $pricingNote = $branding->landingText('pricing.note', '');
        @endphp
        @if ($pricingNote)
            <p class="text-center text-xs text-slate-400 mt-8">{{ $pricingNote }}</p>
        @endif
    </div>
    </div>
</section>
