@props(['plans'])

@php
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

<div id="pricing" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 scroll-mt-20">
    <div class="text-center mb-10">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">
            {{ __('Predictable Investment') }}
        </span>
        <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white">{{ __('Simple, transparent pricing for every tier') }}</h2>
        <p class="mt-3 text-sm sm:text-base text-slate-400">{{ __('Launch in minutes with zero setup fees and immediate card issuing.') }}</p>
    </div>

    <div x-data="{ annual: false }">
        <!-- Monthly / Annual Toggle Switch -->
        <div class="flex items-center justify-center gap-3 mb-14">
            <span class="text-xs sm:text-sm font-bold" :class="!annual ? 'text-white' : 'text-slate-400'">{{ __('Monthly Billing') }}</span>
            <button type="button" x-on:click="annual = !annual"
                    :class="annual ? 'bg-brand-lime' : 'bg-slate-700'"
                    class="relative w-13 h-7 rounded-full transition-colors p-1" role="switch" :aria-checked="annual.toString()">
                <span class="block w-5 h-5 rounded-full bg-slate-950 shadow transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs sm:text-sm font-bold flex items-center gap-2" :class="annual ? 'text-white' : 'text-slate-400'">
                {{ __('Annual Billing') }}
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-wider border border-emerald-500/30">{{ __('Save 20%') }}</span>
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
                        ? 'bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-brand-lime shadow-2xl shadow-brand-lime/10 scale-[1.02] z-10'
                        : 'bg-slate-900/70 backdrop-blur-xl border border-white/10 hover:border-white/20 shadow-xl' }}">

                    @if ($card['popular'])
                        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-brand-lime text-slate-950 text-[10px] font-black uppercase tracking-widest shadow-md">
                            {{ __('Most Popular Plan') }}
                        </span>
                    @endif

                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-emerald-400">{{ $plan->display_name }}</div>

                        <div class="mt-4 flex items-baseline gap-1.5 min-h-[3rem]">
                            <span x-show="!annual" class="text-4xl font-black text-white">{{ $card['monthly']['price'] }}</span>
                            <span x-show="!annual" class="text-xs text-slate-400 font-medium">{{ $card['monthly']['suffix'] }}</span>
                            <span x-show="annual" x-cloak class="text-4xl font-black text-white">{{ $card['annual']['price'] }}</span>
                            <span x-show="annual" x-cloak class="text-xs text-slate-400 font-medium">{{ $card['annual']['suffix'] }}</span>
                        </div>

                        @if ($card['discountBadge'])
                            <div x-show="annual" x-cloak class="mt-1 text-[11px] font-bold text-brand-lime">{{ __('Billed annually with 20% bonus discount') }}</div>
                        @endif

                        <ul class="mt-8 space-y-3 text-xs text-slate-300">
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
    </div>
</div>
