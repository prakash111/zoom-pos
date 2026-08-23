@props([])

@php
    $showcaseFeatures = [
        'inventory' => [
            'icon' => '📦',
            'label' => __('Smart Inventory & Stock'),
            'title' => __('Real-time stock tracking, multi-warehouse sync & restock alerts'),
            'points' => [
                __('Instant barcode & SKU generator with one-click label printing'),
                __('Multi-warehouse & branch transfers with receiving audit trails'),
                __('Automated low-stock threshold triggers & re-order notifications'),
                __('Batch & lot tracking with expiry dates, weight & unit conversions'),
                __('Real-time valuation, cost-averaging & margin analytics per category'),
            ],
            'mockup' => 'inventory',
        ],
        'retail' => [
            'icon' => '🛒',
            'label' => __('Retail & Store POS'),
            'title' => __('A fast, flexible checkout built for peak rush hours'),
            'points' => [
                __('Barcode scanning with sub-second add-to-cart feedback'),
                __('Multi-payment tender split — cash, card, and digital transfers'),
                __('Seamless customer accounts with credit limits & payment histories'),
                __('Full cash register management with opening/closing shift balances & X/Z reports'),
                __('Zero-latency offline mode — ring up sales during network outages without interruption'),
            ],
            'mockup' => 'pos',
        ],
        'restaurant' => [
            'icon' => '🍽️',
            'label' => __('Restaurant & Food POS'),
            'title' => __('Dine-in floor, takeaway, and the kitchen — perfectly synchronized'),
            'points' => [
                __('Interactive dining floor plans with live occupied & billing status'),
                __('Kitchen Order Tickets (KOT) dispatched to live Kitchen Display Screens (KDS)'),
                __('Contactless Table QR menu ordering — guests scan, browse, and order from phones'),
                __('Per-seat item tracking, custom food modifiers, and course pacing'),
                __('Instant table merge, bill splitting, and takeaway queue management'),
            ],
            'mockup' => 'restaurant',
        ],
        'finance' => [
            'icon' => '🧾',
            'label' => __('Finance & Invoicing'),
            'title' => __('Automated tax invoicing and real-time ledger accounting'),
            'points' => [
                __('Multi-currency pricing with configurable precision & exchange rates'),
                __('Compliant automated tax invoices (VAT/GST/HSN) generated instantly'),
                __('One-click instant dispatch to customers via WhatsApp or Email'),
                __('Thermal receipt printing (80mm / 58mm) alongside full A4 PDF invoices'),
                __('Accounts Payable (AP) and Accounts Receivable (AR) ledgers built-in'),
            ],
            'mockup' => 'finance',
        ],
    ];
@endphp

<div id="features" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 scroll-mt-20">
    <div class="text-center mb-12 sm:mb-16">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-lime"></span>
            {{ __('Unified Operations Suite') }}
        </span>
        <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white">{{ __('Everything your business needs, in one unified engine') }}</h2>
        <p class="mt-3 text-sm sm:text-base text-slate-400 max-w-2xl mx-auto">{{ __("From real-time warehouse stock tracking to front-counter barcode POS and back-of-house kitchen display, it's all synchronized.") }}</p>
    </div>

    <div x-data="{ tab: 'inventory' }" class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6 lg:gap-8 items-start">
        <!-- Tab List -->
        <div class="flex lg:flex-col gap-2 overflow-x-auto no-scrollbar pb-2 lg:pb-0">
            @foreach ($showcaseFeatures as $key => $f)
                <button type="button"
                        x-on:click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-gradient-to-r from-emerald-500/20 to-lime-500/20 border-emerald-500/40 text-white shadow-lg shadow-emerald-500/10' : 'bg-slate-900/60 text-slate-400 border-white/5 hover:bg-slate-800 hover:text-slate-200'"
                        class="shrink-0 flex items-center gap-3 px-4 py-3.5 rounded-2xl text-xs sm:text-sm font-bold border transition text-left whitespace-nowrap lg:whitespace-normal">
                    <span class="text-lg">{{ $f['icon'] }}</span>
                    <span>{{ $f['label'] }}</span>
                </button>
            @endforeach
        </div>

        <!-- Tab Panels -->
        <div class="rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-6 sm:p-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-80 h-80 bg-gradient-to-bl from-brand-lime/10 to-transparent rounded-full blur-3xl pointer-events-none"></div>

            @foreach ($showcaseFeatures as $key => $f)
                <div x-show="tab === '{{ $key }}'" x-cloak x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10 items-center">
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-2xl shrink-0">{{ $f['icon'] }}</span>
                            <h3 class="text-xl sm:text-2xl font-black tracking-tight text-white">{!! $f['title'] !!}</h3>
                        </div>
                        <ul class="space-y-3.5">
                            @foreach ($f['points'] as $point)
                                <li class="flex items-start gap-3 text-sm text-slate-300 leading-relaxed">
                                    <div class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-black shrink-0 mt-0.5">✓</div>
                                    <span>{!! $point !!}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-8">
                            <a href="{{ route('tenant.register') }}" class="inline-flex items-center gap-2 text-xs font-black text-brand-lime hover:underline">
                                <span>{{ __('Get started with') }} {{ $f['label'] }}</span>
                                <span>→</span>
                            </a>
                        </div>
                    </div>

                    <!-- Per-tab High-Fidelity Mockup Frame -->
                    <div class="rounded-2xl bg-slate-950 border border-white/15 p-1 shadow-2xl overflow-hidden">
                        <div class="flex items-center gap-1.5 px-3.5 py-2.5 bg-slate-900/90 border-b border-white/10">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                            <span class="ml-2 text-[10px] font-mono text-slate-400">{{ $f['label'] }}</span>
                        </div>

                        @if ($f['mockup'] === 'inventory')
                            <div class="p-4 space-y-2.5">
                                <div class="rounded-xl bg-gradient-to-r from-emerald-600/20 to-teal-600/20 border border-emerald-500/30 p-3 text-white">
                                    <div class="flex justify-between items-center text-[10px] font-mono text-emerald-300">
                                        <span>{{ __('WAREHOUSE CENTRAL · SYNC ACTIVE') }}</span>
                                        <span class="px-2 py-0.5 bg-emerald-500/20 border border-emerald-500/40 rounded font-bold">1,840 SKUs</span>
                                    </div>
                                    <div class="mt-2 text-xs font-bold text-slate-200">{{ __('Stock Turnover Velocity:') }} <span class="text-brand-lime font-black">+18.4% {{ __('this week') }}</span></div>
                                </div>

                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/5 text-xs">
                                        <div>
                                            <div class="font-bold text-slate-200">Espresso Beans 1kg</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: COF-8821 &middot; 142 {{ __('in stock') }}</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 text-[10px] font-bold">{{ __('In Stock') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/5 text-xs">
                                        <div>
                                            <div class="font-bold text-slate-200">Almond Flour 1kg</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: FLR-0912 &middot; 3 {{ __('remaining') }}</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[10px] font-bold">{{ __('Low Stock') }}</span>
                                    </div>
                                </div>
                            </div>

                        @elseif ($f['mockup'] === 'pos')
                            <div class="p-4 space-y-2">
                                @foreach ([['Espresso Beans 1kg (x2)', '$28.00'], ['Barcode #058291 Almond Milk', '$4.50'], ['Reusable Bamboo Cup', '$8.20']] as [$item, $price])
                                    <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-white/5 border border-white/5">
                                        <span class="text-xs text-slate-300 font-medium">{{ $item }}</span>
                                        <span class="text-xs font-bold text-emerald-400">{{ $price }}</span>
                                    </div>
                                @endforeach
                                <div class="flex items-center justify-between px-3.5 py-3 rounded-xl bg-brand-lime/15 border border-brand-lime/30 mt-3">
                                    <span class="text-xs font-bold text-brand-lime">{{ __('Total') }} ({{ __('Split Cash / Card') }})</span>
                                    <span class="text-base font-black text-white">$40.70</span>
                                </div>
                            </div>

                        @elseif ($f['mockup'] === 'restaurant')
                            <div class="p-4 space-y-3">
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach (['T1 Table (4p)' => 'occupied', 'T2 Patio (2p)' => 'free', 'T3 VIP Booth' => 'occupied', 'T4 Bar (1p)' => 'free', 'T5 Terrace' => 'occupied', 'T6 Garden' => 'free'] as $table => $status)
                                        <div class="aspect-video rounded-lg flex flex-col items-center justify-center text-[11px] font-bold {{ $status === 'occupied' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' }}">
                                            <span>{{ $table }}</span>
                                            <span class="text-[9px] uppercase font-mono">{{ $status }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 font-bold flex items-center justify-between">
                                    <span>🍳 {{ __('Kitchen Ticket #402 Dispatched') }}</span>
                                    <span class="text-[10px] text-slate-400">00:42 ago</span>
                                </div>
                            </div>

                        @else
                            <div class="p-4 space-y-2.5">
                                <div class="rounded-xl bg-white/5 border border-white/5 p-3">
                                    <div class="flex justify-between text-[11px] text-slate-300 font-bold mb-1">
                                        <span>Invoice #INV-2026-089</span>
                                        <span class="text-emerald-400">{{ __('PAID') }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ __('Total') }}: $1,450.00 &middot; Tax: $130.50 (9%)</div>
                                </div>
                                <div class="flex items-center justify-between px-3 py-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold">
                                    <span class="flex items-center gap-1.5">
                                        <span>✓</span>
                                        <span>{{ __('Sent via WhatsApp & Email') }}</span>
                                    </span>
                                    <span class="text-[10px] bg-emerald-500/20 px-2 py-0.5 rounded">{{ __('Delivered') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
