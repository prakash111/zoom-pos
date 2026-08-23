@props(['branding'])

@php
    $testimonials = [
        [
            'quote' => __("We switched all our retail outlets and staff corporate registers over in one afternoon. Inventory clears immediately and end-of-day reconciliation takes seconds."),
            'name' => 'Alexander Hayes',
            'role' => __('Operations Director · Apex Retail Group'),
            'avatar' => 'AH',
        ],
        [
            'quote' => __("The offline checkout and instant stock sync saved us during a major fiber cut on a busy weekend. Not a single sale or customer was lost."),
            'name' => 'Elena Rostova',
            'role' => __('Founder & Owner · Metro Gourmet Markets'),
            'avatar' => 'ER',
        ],
        [
            'quote' => __("Having physical POS, inventory controls, and KOT kitchen displays in a single dashboard transformed our restaurant chain completely."),
            'name' => 'Tariq Mansour',
            'role' => __('Head of Operations · Urban Dine Hospitality'),
            'avatar' => 'TM',
        ],
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28">
    <div class="text-center mb-12 sm:mb-16">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
            {{ __('Customer Validation') }}
        </span>
        <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white">{{ __('Trusted by market leaders worldwide') }}</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
        @foreach ($testimonials as $t)
            <div class="rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-7 sm:p-8 flex flex-col justify-between hover:border-emerald-500/30 transition-all shadow-xl">
                <div>
                    <!-- Star rating -->
                    <div class="flex items-center gap-1 text-brand-lime text-sm mb-4">
                        ★★★★★
                    </div>
                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed font-normal">
                        &ldquo;{{ $t['quote'] }}&rdquo;
                    </p>
                </div>

                <div class="mt-8 pt-6 border-t border-white/10 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-lime to-emerald-400 text-slate-950 font-black text-xs flex items-center justify-center shrink-0">
                        {{ $t['avatar'] }}
                    </div>
                    <div>
                        <div class="text-sm font-black text-white">{{ $t['name'] }}</div>
                        <div class="text-xs text-slate-400">{!! $t['role'] !!}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Security & Trust Badges -->
    <div class="mt-16 pt-8 border-t border-white/10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-xs font-bold text-slate-400">
        <span class="flex items-center gap-2"><span class="text-brand-lime">🔒</span> {{ __('PCI-DSS Certified') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">☁️</span> {{ __('Auto Cloud Redundancy') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">🌍</span> {{ __('150+ Currencies Supported') }}</span>
        <span class="flex items-center gap-2"><span class="text-brand-lime">📶</span> {{ __('Zero-Downtime Offline Mode') }}</span>
    </div>
</div>
