@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Dark Studio POS & Inventory Command Center')
@section('meta_description', 'High-performance Dark Studio POS and real-time inventory management command center for tech-forward retail and dining stores.')

@section('content')
<div class="bg-[#0b0c14] text-slate-100 min-h-screen selection:bg-purple-500 selection:text-white" x-data="{ studioTab: 'pos', annual: false }">

    <!-- Studio Ambient Glows -->
    <div class="fixed top-0 left-1/4 w-[600px] h-[600px] bg-purple-600/10 rounded-full blur-[150px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-0 right-1/4 w-[600px] h-[600px] bg-cyan-600/10 rounded-full blur-[150px] pointer-events-none -z-10"></div>

    <!-- Studio Hero -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 text-center relative z-10">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-950/60 border border-purple-500/30 text-purple-300 text-xs font-mono font-bold uppercase tracking-wider mb-6 shadow-lg shadow-purple-950/50">
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-ping"></span>
            <span>{{ __('Dark Studio POS · Native Velocity Command Suite') }}</span>
        </div>

        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-white tracking-tight leading-[1.08]">
            {{ __('Next-Gen Cloud POS Built for') }}<br>
            <span class="bg-gradient-to-r from-purple-400 via-pink-400 to-cyan-400 bg-clip-text text-transparent">
                {{ __('Peak-Performance Retail') }}
            </span>
        </h1>

        <p class="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl mx-auto font-normal leading-relaxed">
            {{ __('An uncompromising dark-mode command center for high-velocity checkout, stock radar analytics, kitchen display queues, and multi-tender ledgers.') }}
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('tenant.register') }}"
               class="px-8 py-4 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-black text-sm transition shadow-xl shadow-purple-600/30 active:scale-95 flex items-center gap-2">
                <span>✨ {{ __('Deploy Studio Workspace') }}</span>
                <span>→</span>
            </a>
            <a href="{{ route('tenant.login') }}"
               class="px-7 py-4 rounded-2xl bg-slate-900/80 hover:bg-slate-800 text-slate-300 text-sm font-bold border border-slate-700/80 transition flex items-center gap-2">
                <span>⚡ {{ __('Live Terminal Login') }}</span>
            </a>
        </div>

        <x-landing.download-buttons :branding="$branding" class="mt-6 justify-center" />

        <!-- Studio Interactive Terminal Showcase -->
        <div class="mt-16 max-w-5xl mx-auto rounded-3xl bg-[#0f111e] border border-purple-500/20 shadow-2xl p-4 sm:p-6 backdrop-blur-2xl text-left relative overflow-hidden">
            <!-- Studio Tab Switcher -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span class="ml-3 text-xs font-mono text-slate-400">STUDIO://CORE.ENGINE</span>
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" @click="studioTab = 'pos'"
                            :class="studioTab === 'pos' ? 'bg-purple-600/20 text-purple-300 border border-purple-500/40' : 'text-slate-400 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs font-mono font-bold transition">
                        POS Terminal
                    </button>
                    <button type="button" @click="studioTab = 'matrix'"
                            :class="studioTab === 'matrix' ? 'bg-purple-600/20 text-purple-300 border border-purple-500/40' : 'text-slate-400 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs font-mono font-bold transition">
                        Live Matrix
                    </button>
                    <button type="button" @click="studioTab = 'kot'"
                            :class="studioTab === 'kot' ? 'bg-purple-600/20 text-purple-300 border border-purple-500/40' : 'text-slate-400 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs font-mono font-bold transition">
                        Kitchen KDS
                    </button>
                </div>
            </div>

            <!-- Tab 1: Studio POS Screen -->
            <div x-show="studioTab === 'pos'" class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-8 space-y-3">
                    <div class="p-3 rounded-xl bg-black/40 border border-purple-500/10 font-mono text-xs text-purple-300 flex justify-between">
                        <span>▶ SKU SCAN: PAS-7741</span>
                        <span class="text-cyan-400">12ms LATENCY</span>
                    </div>
                    <div class="space-y-2 text-xs font-mono">
                        <div class="flex justify-between p-2.5 rounded-lg bg-white/5 border border-white/5">
                            <span class="text-white">Handcrafted Penne Pasta (x2)</span>
                            <span class="text-purple-300 font-bold">$13.00</span>
                        </div>
                        <div class="flex justify-between p-2.5 rounded-lg bg-white/5 border border-white/5">
                            <span class="text-white">Artisan Roast Coffee (x1)</span>
                            <span class="text-purple-300 font-bold">$14.50</span>
                        </div>
                        <div class="flex justify-between p-2.5 rounded-lg bg-white/5 border border-white/5">
                            <span class="text-white">Cold-Pressed Truffle Oil (x1)</span>
                            <span class="text-purple-300 font-bold">$18.20</span>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-4 p-4 rounded-xl bg-purple-950/20 border border-purple-500/30 flex flex-col justify-between space-y-3">
                    <div class="font-mono text-xs space-y-1">
                        <div class="text-slate-400">{{ __('SUBTOTAL') }}: $45.70</div>
                        <div class="text-slate-400">{{ __('TAX (8.5%)') }}: $3.88</div>
                        <div class="text-base font-black text-white pt-2 border-t border-purple-500/30">{{ __('TOTAL DUE') }}: <span class="text-cyan-300">$49.58</span></div>
                    </div>
                    <button type="button" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-mono text-xs font-bold transition shadow-lg shadow-purple-600/30">
                        ⚡ {{ __('INSTANT PAY') }}
                    </button>
                </div>
            </div>

            <!-- Tab 2: Studio Live Matrix -->
            <div x-show="studioTab === 'matrix'" x-cloak class="py-4 space-y-3 font-mono text-xs">
                <div class="grid grid-cols-3 gap-3">
                    <div class="p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="text-slate-400 text-[10px]">TOTAL REVENUE (TODAY)</div>
                        <div class="text-lg font-black text-purple-300 mt-1">$4,892.40</div>
                    </div>
                    <div class="p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="text-slate-400 text-[10px]">TRANSACTIONS</div>
                        <div class="text-lg font-black text-cyan-300 mt-1">348 Orders</div>
                    </div>
                    <div class="p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="text-slate-400 text-[10px]">STOCK TURNOVER</div>
                        <div class="text-lg font-black text-pink-300 mt-1">99.4% Velocity</div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Studio KDS -->
            <div x-show="studioTab === 'kot'" x-cloak class="py-4 space-y-2 font-mono text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30">
                        <div class="flex justify-between font-bold text-rose-300">
                            <span>TICKET #402 (TABLE 4)</span>
                            <span>01:42</span>
                        </div>
                        <div class="mt-2 text-slate-300 text-[11px] space-y-0.5">
                            <div>• 2x Penne Arrabbiata (Extra Spicy)</div>
                            <div>• 1x Truffle Risotto</div>
                        </div>
                    </div>
                    <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <div class="flex justify-between font-bold text-emerald-300">
                            <span>TICKET #403 (BAR 1)</span>
                            <span>00:28</span>
                        </div>
                        <div class="mt-2 text-slate-300 text-[11px] space-y-0.5">
                            <div>• 2x Espresso Double</div>
                            <div>• 1x Iced Almond Latte</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Studio Pricing Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 border-t border-slate-800/80">
        <div class="text-center mb-12">
            <span class="px-3 py-1 rounded-full bg-purple-950/60 border border-purple-500/30 text-purple-300 text-xs font-mono font-bold">
                {{ __('STUDIO SUBSCRIPTION MATRIX') }}
            </span>
            <h2 class="text-3xl sm:text-4xl font-black text-white mt-3">{{ __('Flexible Studio Plans') }}</h2>
        </div>

        @if ($plans->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                @foreach ($plans as $plan)
                    @php
                        $price = (float) $plan->price;
                    @endphp
                    <div class="rounded-3xl bg-[#0f111e] border border-purple-500/20 p-7 flex flex-col justify-between hover:border-purple-500/50 transition-all hover:shadow-2xl hover:shadow-purple-500/10">
                        <div>
                            <div class="text-xs font-mono font-bold uppercase tracking-wider text-purple-400">{{ $plan->display_name }}</div>
                            <div class="mt-3 text-3xl font-mono font-black text-white">{{ $plan->currency }}{{ number_format($price, 2) }}<span class="text-xs font-normal text-slate-500">/{{ $plan->billing_cycle }}</span></div>
                            
                            <ul class="mt-6 space-y-2.5 text-xs text-slate-300 font-mono">
                                <li>✓ {{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Staff Seats') }}</li>
                                <li>✓ {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('POS Devices') }}</li>
                                <li>✓ {{ __('Full Studio POS & KDS') }}</li>
                            </ul>
                        </div>

                        <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                           class="mt-8 block text-center py-3 rounded-xl bg-purple-600/20 hover:bg-purple-600 border border-purple-500/40 text-purple-200 hover:text-white font-mono text-xs font-bold transition">
                            {{ __('DEPLOY') }} {{ $plan->display_name }} →
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <!-- Studio Footer -->
    <footer class="border-t border-slate-900 bg-[#07080d] py-10 text-xs text-slate-500 font-mono">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>&copy; {{ now()->year }} {{ $branding->platform_name }} Studio Edition.</div>
            <div class="flex items-center gap-6">
                <a href="{{ route('tenant.login') }}" class="hover:text-purple-400 transition">{{ __('Terminal Login') }}</a>
                <a href="{{ route('tenant.register') }}" class="hover:text-purple-400 transition">{{ __('Register') }}</a>
            </div>
        </div>
    </footer>

</div>
@endsection
