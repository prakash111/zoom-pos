@props(['branding' => null])
@php($branding = $branding ?? \App\Models\PlatformBranding::current())

@php
    $pillars = [
        [
            'icon' => '⚡',
            'tint' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
            'title' => __('Sub-Second Speed & Offline-Ready'),
            'body' => __('Checkout keeps running even if the internet drops. Sales queue safely in local storage and sync automatically the moment connection is restored.'),
        ],
        [
            'icon' => '💳',
            'tint' => 'bg-brand-lime/10 text-brand-lime border border-brand-lime/20',
            'title' => __('Direct Card Issuing & Split Payments'),
            'body' => __('Generate virtual and physical debit cards, manage spend controls, and process multi-tender checkouts without juggling separate merchant accounts.'),
        ],
        [
            'icon' => '📊',
            'tint' => 'bg-teal-500/10 text-teal-400 border border-teal-500/20',
            'title' => __('Real-Time Financial & Ledger Control'),
            'body' => __('Automated register X/Z shift reconciliation, payable/receivable balance sheets, and tax invoices ready for compliance without extra plugins.'),
        ],
        [
            'icon' => '🏢',
            'tint' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20',
            'title' => __('Multi-Location Enterprise Workspaces'),
            'body' => __('Scale from a single boutique till to a nationwide multi-store franchise with isolated tenant databases, custom domains, and granular role permissions.'),
        ],
    ];
@endphp

<div id="solutions" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 scroll-mt-20">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-center">
        <!-- Floating Visual Card Composition -->
        <div class="lg:col-span-5 relative mx-auto max-w-sm lg:max-w-none w-full">
            <div class="rounded-3xl bg-slate-900/90 backdrop-blur-2xl border border-white/15 shadow-2xl p-6 sm:p-7 relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-48 h-48 bg-emerald-500/20 rounded-full blur-2xl"></div>
                
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-400">{{ __('Total Net Cashflow') }}</span>
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-[10px] flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('Live Sync') }}
                    </span>
                </div>

                <div class="flex items-baseline gap-2 mb-6">
                    <span class="text-3xl sm:text-4xl font-black text-white">$128,490.50</span>
                    <span class="text-xs font-bold text-emerald-400">+24.8% {{ __('vs last month') }}</span>
                </div>

                <!-- Animated Bar Chart -->
                <div class="h-28 flex items-end gap-1.5 pt-4 border-t border-white/10">
                    @foreach ([35, 55, 45, 75, 60, 90, 70, 95, 80, 100, 85, 92] as $h)
                        <div class="flex-1 rounded-t bg-gradient-to-t from-emerald-600 to-brand-lime transition-all hover:brightness-125" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
            </div>

            <!-- Floating Mini Card 1 -->
            <div class="absolute -right-4 -bottom-6 w-48 rounded-2xl bg-slate-950/95 border border-white/20 shadow-2xl p-4 hidden sm:block animate-float-slow">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-lime/20 text-brand-lime flex items-center justify-center text-lg">🛡️</div>
                    <div class="leading-tight">
                        <div class="text-xs font-black text-white">{{ __('Encrypted Ledger') }}</div>
                        <div class="text-[10px] text-emerald-400 font-bold">100% {{ __('Reconciled') }}</div>
                    </div>
                </div>
            </div>

            <!-- Floating Mini Card 2 -->
            <div class="absolute -left-4 -top-6 w-44 rounded-2xl bg-slate-950/95 border border-white/20 shadow-2xl p-3.5 hidden sm:block animate-float-slow" style="animation-delay: 1.5s;">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm">⚡</div>
                    <div class="leading-tight">
                        <div class="text-[11px] font-black text-white">{{ __('Offline Engine') }}</div>
                        <div class="text-[9px] text-slate-400 font-mono">{{ __('Zero downtime') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2x2 Value Pillars -->
        <div class="lg:col-span-7">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-lime/10 border border-brand-lime/20 text-brand-lime text-xs font-bold uppercase tracking-wider mb-3">
                {{ __('Architected For Scale') }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                {{ $branding->getSectionTitle('solutions', __('Engineered for maximum reliability under peak pressure')) }}
            </h2>

            <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8">
                @foreach ($pillars as $pillar)
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-emerald-500/30 hover:bg-white/[0.07] transition-all group">
                        <div class="w-12 h-12 rounded-xl {{ $pillar['tint'] }} flex items-center justify-center text-xl shrink-0 mb-4 group-hover:scale-110 transition-transform">
                            {{ $pillar['icon'] }}
                        </div>
                        <h3 class="text-base font-black tracking-tight text-white">{{ $pillar['title'] }}</h3>
                        <p class="mt-2 text-xs sm:text-sm text-slate-400 leading-relaxed">{{ $pillar['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
