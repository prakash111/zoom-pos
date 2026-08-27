@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Enterprise Retail & Hardware POS Platform')
@section('meta_description', 'High-volume Multi-Store POS, 80mm/58mm Thermal Receipt Printing, Multi-Jurisdiction Fiscal Tax Engine, Cash Register Shift Sessions (Sangria/Suprimento), and Accounts Receivable.')

@section('content')
<div x-data="{
    activeTab: 'multistore',
    activeHardware: 'desktop',
    showTourModal: false,
    annualBilling: false
}" class="bg-slate-950 text-slate-100 selection:bg-emerald-500 selection:text-slate-950">

    {{-- ========================================================================= --}}
    {{-- 1. HERO SECTION: HIGH-CONVERTING ENTERPRISE HEADLINE & FLOATING POS MOCKUP --}}
    {{-- ========================================================================= --}}
    <section class="relative pt-12 pb-20 sm:pt-20 sm:pb-32 overflow-hidden border-b border-slate-800">
        <!-- Ambient Grid Background Pattern -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] pointer-events-none"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-emerald-500/20 via-teal-500/10 to-transparent blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-4xl mx-auto">
                <!-- Enterprise Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-950/80 border border-emerald-500/40 text-emerald-400 text-xs font-black uppercase tracking-widest mb-6 shadow-lg shadow-emerald-950/50">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>{{ __('Enterprise Multi-Store Suite · Offline First & Hardware Native') }}</span>
                </div>

                <!-- Headline -->
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-white tracking-tight leading-[1.08]">
                    {{ __('Enterprise POS & Retail Cloud Engine for') }}
                    <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">
                        {{ __('High-Volume Stores') }}
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="mt-6 text-base sm:text-xl text-slate-400 max-w-3xl mx-auto font-normal leading-relaxed">
                    {{ __('Unify multi-store inventory matrices, sub-second 80mm/58mm thermal receipts, fiscal tax engines, daily register float sessions (Sangria/Suprimento), and accounts receivable into one unified system.') }}
                </p>

                <!-- Dual Action Triggers -->
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('tenant.register') }}"
                       class="px-8 py-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-sm font-black transition-all shadow-xl shadow-emerald-500/25 active:scale-95 flex items-center gap-2.5">
                        <span>🚀 {{ __('Start Free Trial / Live Demo') }}</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>

                    <button type="button"
                            @click="showTourModal = true"
                            class="px-7 py-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 text-slate-200 hover:text-white text-sm font-bold border border-slate-700 transition shadow-lg flex items-center gap-2.5 cursor-pointer">
                        <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">▶</span>
                        <span>{{ __('Watch Tour & Architecture Video') }}</span>
                    </button>
                </div>

                <!-- Live Metrics Bar -->
                <div class="mt-12 pt-8 border-t border-slate-800/80 grid grid-cols-2 sm:grid-cols-4 gap-4 text-left">
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] uppercase font-bold text-slate-400">{{ __('Checkout Latency') }}</div>
                        <div class="text-lg font-black text-emerald-400 mt-0.5">&lt; 15ms</div>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] uppercase font-bold text-slate-400">{{ __('Thermal Printing') }}</div>
                        <div class="text-lg font-black text-white mt-0.5">80mm / 58mm ESC</div>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] uppercase font-bold text-slate-400">{{ __('Register Sessions') }}</div>
                        <div class="text-lg font-black text-teal-400 mt-0.5">X/Z Auto Balance</div>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] uppercase font-bold text-slate-400">{{ __('Tax Compliance') }}</div>
                        <div class="text-lg font-black text-white mt-0.5">Multi-Jurisdiction</div>
                    </div>
                </div>
            </div>

            <!-- Interactive Floating POS Interface Mockup -->
            <div class="mt-14 relative max-w-5xl mx-auto">
                <div class="rounded-3xl bg-slate-900/90 border border-slate-700/80 shadow-2xl p-4 sm:p-6 backdrop-blur-2xl relative overflow-hidden">
                    
                    <!-- Top POS Window Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="flex gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            </div>
                            <div class="h-4 w-[1px] bg-slate-800"></div>
                            <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-300">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                <span>POS-TILL #01 · MAIN STORE CASH REGISTER</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[11px] font-mono font-bold">
                                💵 Cash Drawer: $450.00 Float
                            </span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-[11px] font-mono">
                                Session #2089
                            </span>
                        </div>
                    </div>

                    <!-- POS Grid Content -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-4">
                        <!-- Left 7 cols: Catalog & Live Barcode Scan -->
                        <div class="lg:col-span-7 space-y-3">
                            <!-- Scanner Bar -->
                            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-xs text-slate-400 font-mono flex-1">
                                    <span class="text-emerald-400 font-bold">🔍 SCAN:</span>
                                    <span class="text-slate-200">COF-482910 | High-Grade Espresso Roast 1kg</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-bold text-[10px]">+ Added (12ms)</span>
                            </div>

                            <!-- Cart Items -->
                            <div class="space-y-2 max-h-56 overflow-y-auto no-scrollbar">
                                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-white">Artisan Coffee Roast 1kg</div>
                                        <div class="text-[10px] text-slate-400 font-mono">SKU: COF-4829 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-400">2 × $14.50 = $29.00</div>
                                        <div class="text-[10px] text-slate-500">Tax: $2.46</div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-white">Gourmet Extra Virgin Olive Oil 500ml</div>
                                        <div class="text-[10px] text-slate-400 font-mono">SKU: OIL-9104 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-400">1 × $18.20 = $18.20</div>
                                        <div class="text-[10px] text-slate-500">Tax: $1.55</div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-white">Cold-Pressed Juice Bottle 330ml</div>
                                        <div class="text-[10px] text-slate-400 font-mono">SKU: JUC-0012 · Tax: 8.5% VAT</div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="font-bold text-emerald-400">3 × $4.50 = $13.50</div>
                                        <div class="text-[10px] text-slate-500">Tax: $1.15</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right 5 cols: Split Payment & Live Slip Preview -->
                        <div class="lg:col-span-5 rounded-2xl bg-slate-950 border border-slate-800 p-4 flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between text-xs text-slate-400 pb-2 border-b border-slate-800">
                                    <span>{{ __('Subtotal (3 lines)') }}</span>
                                    <span class="font-mono text-white font-bold">$60.70</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-400 py-1.5">
                                    <span>{{ __('Fiscal VAT (8.5%)') }}</span>
                                    <span class="font-mono text-emerald-400 font-bold">$5.16</span>
                                </div>
                                <div class="flex items-center justify-between text-sm text-white pt-2 border-t border-slate-800">
                                    <span class="font-black">{{ __('TOTAL DUE') }}</span>
                                    <span class="font-mono text-xl font-black text-emerald-400">$65.86</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-[10px] font-mono uppercase text-slate-400 font-bold">{{ __('Split Tender Tendered') }}:</div>
                                <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                                    <div class="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-300">
                                        💵 Cash: <strong class="text-white">$30.00</strong>
                                    </div>
                                    <div class="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-300">
                                        💳 Card: <strong class="text-white">$35.86</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-800 flex items-center justify-between gap-2">
                                <button type="button" class="flex-1 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition">
                                    🖨️ {{ __('Print 80mm ESC Receipt') }}
                                </button>
                                <span class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-400" title="Fiscal QR Code">📱 QR</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Floating Thermal Receipt Card Badge -->
                <div class="absolute -right-4 -bottom-8 hidden md:block w-72 rounded-2xl bg-white text-slate-950 p-4 shadow-2xl border border-slate-300 font-mono text-[11px] rotate-1 hover:rotate-0 transition-transform">
                    <div class="text-center font-bold pb-2 border-b border-dashed border-slate-400">
                        <div>=== {{ $branding->platform_name }} ===</div>
                        <div class="text-[9px] text-slate-600">STORE #01 · FISCAL RECEIPT #2089</div>
                    </div>
                    <div class="py-2 space-y-1">
                        <div class="flex justify-between"><span>2x Espresso Roast</span><span>$29.00</span></div>
                        <div class="flex justify-between"><span>1x Olive Oil 500ml</span><span>$18.20</span></div>
                        <div class="flex justify-between"><span>3x Fresh Juice</span><span>$13.50</span></div>
                    </div>
                    <div class="pt-2 border-t border-dashed border-slate-400 flex justify-between font-bold text-xs">
                        <span>TOTAL PAID</span>
                        <span>$65.86</span>
                    </div>
                    <div class="mt-2 text-center text-[8px] text-slate-500">
                        [||||||||||||||||||||||||||||||||||||||]
                        <div>AUT: 9812-4910-8401</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 2. MODULE CAPABILITIES TOUR: 5 CORE ENTERPRISE PILLARS FROM /read SPECS   --}}
    {{-- ========================================================================= --}}
    <section id="tour" class="py-20 sm:py-28 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-black uppercase tracking-wider mb-3">
                {{ __('Deep Architecture Tour') }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                {{ __('5 Enterprise Capabilities Built for Scale') }}
            </h2>
            <p class="mt-4 text-slate-400 max-w-2xl mx-auto text-sm sm:text-base">
                {{ __('Engineered specifically according to retail franchise workflows: multi-branch syncing, hardware printing, fiscal engine, drawer sessions, and credit ledgers.') }}
            </p>
        </div>

        <!-- Capability Tabs Navigation -->
        <div class="flex items-center justify-center gap-2 flex-wrap mb-10">
            <button type="button" @click="activeTab = 'multistore'"
                    :class="activeTab === 'multistore' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-black transition cursor-pointer flex items-center gap-2">
                <span>🏢</span> <span>{{ __('Multi-Store POS') }}</span>
            </button>

            <button type="button" @click="activeTab = 'thermal'"
                    :class="activeTab === 'thermal' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-black transition cursor-pointer flex items-center gap-2">
                <span>🖨️</span> <span>{{ __('80mm/58mm Thermal Printing') }}</span>
            </button>

            <button type="button" @click="activeTab = 'fiscal'"
                    :class="activeTab === 'fiscal' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-black transition cursor-pointer flex items-center gap-2">
                <span>🏛️</span> <span>{{ __('Fiscal Tax Engine') }}</span>
            </button>

            <button type="button" @click="activeTab = 'cash'"
                    :class="activeTab === 'cash' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-black transition cursor-pointer flex items-center gap-2">
                <span>💵</span> <span>{{ __('Cash Drawer & Sangria / Suprimento') }}</span>
            </button>

            <button type="button" @click="activeTab = 'ar'"
                    :class="activeTab === 'ar' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800'"
                    class="px-5 py-3 rounded-2xl text-xs font-black transition cursor-pointer flex items-center gap-2">
                <span>📊</span> <span>{{ __('Accounts Receivable (AR)') }}</span>
            </button>
        </div>

        <!-- Capability Tab Contents -->
        <div class="rounded-3xl bg-slate-900/80 border border-slate-800 p-6 sm:p-10 shadow-2xl relative overflow-hidden">
            
            <!-- 1. MULTI-STORE POS -->
            <div x-show="activeTab === 'multistore'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl">🏢</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Centralized Multi-Location Catalog & Stock Matrix') }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        {{ __('Manage unified SKU barcodes across 100+ stores. Control regional pricing, transfer inventory between branches with transfer receipt audit trails, and view consolidated sales analytics in real-time.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Store-to-store stock transfer workflows with transit reconciliation') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Granular employee permissions per terminal & branch') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Synchronized promotions, discount coupons & customer loyalty') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 space-y-3">
                    <div class="flex justify-between items-center text-[10px] text-emerald-400 pb-2 border-b border-slate-800">
                        <span>MULTI-STORE SYNC MONITOR</span>
                        <span>4 LOCATIONS ACTIVE</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between p-2 rounded bg-slate-900 border border-slate-800">
                            <span>Main Warehouse HQ</span>
                            <span class="text-emerald-400 font-bold">14,200 SKUs (100% Synced)</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-slate-900 border border-slate-800">
                            <span>Branch 01 (Downtown)</span>
                            <span class="text-emerald-400 font-bold">3,890 SKUs (Live)</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-slate-900 border border-slate-800">
                            <span>Branch 02 (Uptown Mall)</span>
                            <span class="text-emerald-400 font-bold">2,940 SKUs (Live)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. THERMAL PRINTING -->
            <div x-show="activeTab === 'thermal'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl">🖨️</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Driverless 80mm & 58mm Thermal Printing') }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        {{ __('Direct hardware integration for USB, Bluetooth, and Ethernet POS printers. Instant raw ESC/POS receipt generation with store logos, itemized tax breakdowns, and electronic invoice QR codes.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Support for Epson, Star, Xprinter, Sunmi & standard ESC/POS hardware') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Sub-second raw print dispatch without OS print dialog popups') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Automatic cash drawer kick trigger upon receipt completion') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-400 pb-2 border-b border-slate-800">HARDWARE PRINTER ROUTER</div>
                    <div class="p-3 rounded bg-slate-900 border border-slate-800 space-y-1.5 text-[11px]">
                        <div>PRINTER: EPSON TM-T88VI (80mm Thermal)</div>
                        <div>INTERFACE: USB / Raw ESC/POS Buffer</div>
                        <div>STATUS: <span class="text-emerald-400 font-bold">CONNECTED · 0.04s SPEED</span></div>
                    </div>
                </div>
            </div>

            <!-- 3. FISCAL TAX ENGINE -->
            <div x-show="activeTab === 'fiscal'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl">🏛️</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Multi-Jurisdiction Fiscal Tax Engine') }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        {{ __('Compliant tax calculation engine accommodating compound taxes, VAT, GST, state sales taxes, and zero-rated export rules with continuous sequence numbering and audit logs.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Inclusive vs. Exclusive tax computation per product category') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Automated tax reports ready for monthly accountant submission') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Tamper-proof sequential invoice IDs and audit trail records') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-400 pb-2 border-b border-slate-800">TAX RULE MATRIX (ACTIVE)</div>
                    <div class="space-y-2">
                        <div class="flex justify-between p-2 rounded bg-slate-900 border border-slate-800">
                            <span>Standard Retail VAT (8.5%)</span>
                            <span class="text-white font-bold">Auto-Calculated</span>
                        </div>
                        <div class="flex justify-between p-2 rounded bg-slate-900 border border-slate-800">
                            <span>Municipal Hospitality Surcharge (2.0%)</span>
                            <span class="text-white font-bold">Category Dining</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. CASH REGISTER & SANGRIA / SUPRIMENTO -->
            <div x-show="activeTab === 'cash'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl">💵</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Daily Cash Register Sessions: Float, Sangria & Suprimento') }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        {{ __('Complete cash drawer audit controls. Record morning opening floats, handle midday cash bleed (Sangria / Cash Drop), inject small change (Suprimento), and perform blind shift closing with automated X & Z report slips.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Sangria (Cash Drop / Payout) with reason & manager PIN authentication') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Suprimento (Cash Injection) tracking for initial till floats') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Blind cash count reconciliation: System balance vs. counted cash variance') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 space-y-3">
                    <div class="flex justify-between text-[10px] text-emerald-400 pb-2 border-b border-slate-800">
                        <span>SHIFT SESSION #2089</span>
                        <span>STATUS: OPEN</span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span>Opening Float (Suprimento):</span><span class="text-white font-bold">$200.00</span></div>
                        <div class="flex justify-between"><span>Cash Sales:</span><span class="text-emerald-400 font-bold">+$1,450.20</span></div>
                        <div class="flex justify-between"><span>Midday Safe Drop (Sangria):</span><span class="text-rose-400 font-bold">-$800.00</span></div>
                        <div class="flex justify-between pt-2 border-t border-slate-800 font-bold text-white"><span>Expected Drawer Cash:</span><span class="text-emerald-400">$850.20</span></div>
                    </div>
                </div>
            </div>

            <!-- 5. ACCOUNTS RECEIVABLE -->
            <div x-show="activeTab === 'ar'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl">📊</div>
                    <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Accounts Receivable (AR) & Customer Credit Lines') }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        {{ __('Issue store credit, track customer outstanding tabs, record partial installment payments, and automatically send statement summaries via WhatsApp & email.') }}
                    </p>
                    <ul class="space-y-2.5 text-xs text-slate-300 pt-2">
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Customer credit limits with automatic checkout lockout when exceeded') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('Aging balance reports (30 / 60 / 90 days overdue)') }}</li>
                        <li class="flex items-center gap-2"><span class="text-emerald-400 font-bold">✓</span> {{ __('One-click digital payment links dispatched directly to customer phones') }}</li>
                    </ul>
                </div>
                <div class="lg:col-span-6 p-5 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 space-y-3">
                    <div class="text-[10px] text-emerald-400 pb-2 border-b border-slate-800">CUSTOMER LEDGER OVERVIEW</div>
                    <div class="p-3 rounded bg-slate-900 border border-slate-800 space-y-2">
                        <div class="flex justify-between font-bold text-white">
                            <span>Apex Commercial Group</span>
                            <span class="text-amber-400">Balance: $2,400.00</span>
                        </div>
                        <div class="text-[10px] text-slate-400">Credit Limit: $5,000.00 · Last Payment: $1,000.00 (3 days ago)</div>
                        <div class="text-[10px] text-emerald-400 font-bold">Status: In Good Standing (Prompt Payer)</div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 3. HARDWARE ECOSYSTEM: DESKTOP, TABLET STAND & MOBILE TOUCH HANDHELD      --}}
    {{-- ========================================================================= --}}
    <section class="py-20 sm:py-28 bg-slate-900/40 border-y border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 text-xs font-black uppercase tracking-wider mb-3">
                    {{ __('Hardware Freedom') }}
                </span>
                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                    {{ __('Universal Hardware Compatibility') }}
                </h2>
                <p class="mt-4 text-slate-400 max-w-2xl mx-auto text-sm sm:text-base">
                    {{ __('Run on any existing device without expensive proprietary hardware lock-ins.') }}
                </p>
            </div>

            <!-- Hardware Mode Switcher -->
            <div class="flex items-center justify-center gap-3 mb-10">
                <button type="button" @click="activeHardware = 'desktop'"
                        :class="activeHardware === 'desktop' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2">
                    <span>🖥️</span> <span>{{ __('Desktop Browser & POS Barcode Gun') }}</span>
                </button>
                <button type="button" @click="activeHardware = 'tablet'"
                        :class="activeHardware === 'tablet' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2">
                    <span>📱</span> <span>{{ __('Tablet Stand Countertop Mode') }}</span>
                </button>
                <button type="button" @click="activeHardware = 'mobile'"
                        :class="activeHardware === 'mobile' ? 'bg-emerald-500 text-slate-950 font-black' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                        class="px-5 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-2">
                    <span>📲</span> <span>{{ __('Mobile Touch & PDA Handheld') }}</span>
                </button>
            </div>

            <!-- Hardware Showcase Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- 1. Desktop -->
                <div class="p-6 rounded-3xl bg-slate-900/90 border transition-all duration-300"
                     :class="activeHardware === 'desktop' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-800'">
                    <div class="text-3xl mb-4">🖥️</div>
                    <h4 class="text-lg font-black text-white">{{ __('Desktop & Countertop PC') }}</h4>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        {{ __('Full keyboard navigation, instant USB barcode gun integration, multi-monitor customer displays, and dual cash drawer support.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-400">
                        <span>●</span> <span>{{ __('Windows, macOS & Linux Ready') }}</span>
                    </div>
                </div>

                <!-- 2. Tablet Stand -->
                <div class="p-6 rounded-3xl bg-slate-900/90 border transition-all duration-300"
                     :class="activeHardware === 'tablet' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-800'">
                    <div class="text-3xl mb-4">📱</div>
                    <h4 class="text-lg font-black text-white">{{ __('Countertop Tablet Stand') }}</h4>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        {{ __('Sleek, touch-optimized POS interface. Ideal for boutiques, specialty coffee shops, and dining floor servers.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-400">
                        <span>●</span> <span>{{ __('iPad, Android Tablet & ChromeOS') }}</span>
                    </div>
                </div>

                <!-- 3. Mobile Touch Handheld -->
                <div class="p-6 rounded-3xl bg-slate-900/90 border transition-all duration-300"
                     :class="activeHardware === 'mobile' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-800'">
                    <div class="text-3xl mb-4">📲</div>
                    <h4 class="text-lg font-black text-white">{{ __('Mobile Handheld POS') }}</h4>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        {{ __('Ring up sales on the sales floor, scan barcodes via built-in camera, and print mobile receipts on handheld wireless terminals.') }}
                    </p>
                    <div class="mt-4 pt-4 border-t border-slate-800 flex items-center gap-2 text-[11px] font-mono text-emerald-400">
                        <span>●</span> <span>{{ __('Sunmi, PAX, Android PDA & iPhone') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ========================================================================= --}}
    {{-- 4. SUBSCRIPTION TIERS: MONTHLY VS. YEARLY SWITCH & DIRECT CHECKOUT HOOKS --}}
    {{-- ========================================================================= --}}
    <section id="pricing" class="py-20 sm:py-28 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-black uppercase tracking-wider mb-3">
                {{ __('Transparent SaaS Investment') }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                {{ __('Enterprise Capacity for Every Business Scale') }}
            </h2>
            <p class="mt-4 text-slate-400 max-w-2xl mx-auto text-sm sm:text-base">
                {{ __('Instant workspace activation with zero setup fees. Direct checkout integration.') }}
            </p>
        </div>

        <!-- Monthly / Annual Toggle -->
        <div class="flex items-center justify-center gap-3 mb-14">
            <span class="text-xs sm:text-sm font-bold" :class="!annualBilling ? 'text-white' : 'text-slate-400'">{{ __('Monthly Billing') }}</span>
            <button type="button" @click="annualBilling = !annualBilling"
                    :class="annualBilling ? 'bg-emerald-500' : 'bg-slate-800'"
                    class="relative w-14 h-8 rounded-full transition-colors p-1 cursor-pointer">
                <span class="block w-6 h-6 rounded-full bg-slate-950 shadow transition-transform"
                      :class="annualBilling ? 'translate-x-6' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs sm:text-sm font-bold flex items-center gap-2" :class="annualBilling ? 'text-white' : 'text-slate-400'">
                {{ __('Annual Billing') }}
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-wider border border-emerald-500/30">{{ __('Save 20%') }}</span>
            </span>
        </div>

        <!-- Plan Cards Grid -->
        @if ($plans->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                @foreach ($plans as $plan)
                    @php
                        $price = (float) $plan->price;
                        $annualPrice = round($price * 12 * 0.8);
                        $isPopular = $plan->name === 'professional' || $plan->name === 'starter';
                    @endphp
                    <div class="rounded-3xl p-8 flex flex-col justify-between transition-all duration-300 relative
                        {{ $isPopular 
                            ? 'bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-emerald-500 shadow-2xl shadow-emerald-500/10 scale-[1.02]' 
                            : 'bg-slate-900/70 border border-slate-800 hover:border-slate-700' }}">
                        
                        @if ($isPopular)
                            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-emerald-500 text-slate-950 text-[10px] font-black uppercase tracking-widest shadow">
                                {{ __('Recommended For Multi-Store') }}
                            </span>
                        @endif

                        <div>
                            <div class="text-xs font-black uppercase tracking-widest text-emerald-400">{{ $plan->display_name }}</div>
                            <div class="mt-4 flex items-baseline gap-1.5">
                                <span x-show="!annualBilling" class="text-4xl font-black text-white">{{ $plan->currency }}{{ number_format($price, 2) }}</span>
                                <span x-show="!annualBilling" class="text-xs text-slate-400 font-medium">/{{ $plan->billing_cycle }}</span>
                                <span x-show="annualBilling" x-cloak class="text-4xl font-black text-white">{{ $plan->currency }}{{ number_format($annualPrice, 2) }}</span>
                                <span x-show="annualBilling" x-cloak class="text-xs text-slate-400 font-medium">/{{ __('year') }}</span>
                            </div>

                            <ul class="mt-8 space-y-3 text-xs text-slate-300">
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400 font-bold">✓</span>
                                    <span>{{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Cashier & Staff Logins') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400 font-bold">✓</span>
                                    <span>{{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('Active POS Terminals') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400 font-bold">✓</span>
                                    <span>{{ $plan->limits['filiais'] ?? '1' }} {{ __('Store Locations / Branches') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400 font-bold">✓</span>
                                    <span>{{ __('80mm/58mm Thermal Printing & X/Z Shifts') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-emerald-400 font-bold">✓</span>
                                    <span>{{ __('Multi-Jurisdiction Fiscal Tax Engine') }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Direct Tenant Registration Hook -->
                        <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                           class="mt-8 block text-center px-5 py-3.5 rounded-xl font-black text-xs transition active:scale-95
                               {{ $isPopular 
                                   ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20' 
                                   : 'bg-slate-800 hover:bg-slate-700 text-white' }}">
                            {{ __('Activate') }} {{ $plan->display_name }} →
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ========================================================================= --}}
    {{-- 5. FOOTER & CLEAR BRAND SEPARATION: SAAS PLATFORM VS. TENANT STORE       --}}
    {{-- ========================================================================= --}}
    <footer class="border-t border-slate-800 bg-slate-950 text-slate-400 pt-16 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-12 border-b border-slate-800/80">
                <!-- Platform Brand Identity -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-base border border-emerald-500/30">
                            🏪
                        </div>
                        <span class="text-lg font-black text-white">{{ $branding->platform_name }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-800 text-slate-300 border border-slate-700">Enterprise Edition</span>
                    </div>
                    <p class="text-xs text-slate-400 max-w-md leading-relaxed">
                        {{ __('Unified Multi-Tenant SaaS platform powering retail businesses, grocery supermarkets, and restaurants worldwide with zero-latency POS checkout and automated financial bookkeeping.') }}
                    </p>
                    <div class="text-[11px] text-slate-500 font-mono">
                        {{ __('Powered by') }} <strong class="text-slate-300">{{ $branding->platform_name }} Cloud SaaS Infrastructure</strong> &middot; {{ __('100% White-label Tenant Ready') }}
                    </div>
                </div>

                <!-- Navigation Links -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-200 mb-3">{{ __('Platform Capabilities') }}</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#tour" class="hover:text-emerald-400 transition">{{ __('Multi-Store Sync') }}</a></li>
                        <li><a href="#tour" class="hover:text-emerald-400 transition">{{ __('Thermal Printing') }}</a></li>
                        <li><a href="#tour" class="hover:text-emerald-400 transition">{{ __('Fiscal Tax Engine') }}</a></li>
                        <li><a href="#tour" class="hover:text-emerald-400 transition">{{ __('Register Cash Sessions') }}</a></li>
                        <li><a href="#tour" class="hover:text-emerald-400 transition">{{ __('Accounts Receivable') }}</a></li>
                    </ul>
                </div>

                <!-- Direct Portals & Login -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-200 mb-3">{{ __('Portals & Access') }}</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ route('tenant.login') }}" class="text-emerald-400 hover:underline font-bold">{{ __('Tenant Merchant Sign In') }}</a></li>
                        <li><a href="{{ route('tenant.register') }}" class="hover:text-emerald-400 transition">{{ __('Register New Store') }}</a></li>
                        @if ($branding->support_email)
                            <li><a href="mailto:{{ $branding->support_email }}" class="hover:text-emerald-400 transition">{{ __('Enterprise Support') }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>

            <!-- Bottom Copyright & SLA Attribution -->
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div>
                    &copy; {{ now()->year }} {{ $branding->platform_name }}. {{ __('All rights reserved.') }}
                </div>
                <div class="flex items-center gap-4 text-[11px] font-mono">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> 99.99% Cloud Uptime SLA</span>
                    <span>&middot;</span>
                    <span>Bank-Grade Encryption</span>
                </div>
            </div>
        </div>
    </footer>

    {{-- Tour Modal Triggered by 'Watch Tour' --}}
    <div x-show="showTourModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div @click.outside="showTourModal = false"
             class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-2xl w-full shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 class="text-base font-black text-white flex items-center gap-2">
                    <span>🎬</span> {{ __('Architecture & POS Hardware Tour') }}
                </h3>
                <button type="button" @click="showTourModal = false" class="text-slate-400 hover:text-white text-lg font-black">&times;</button>
            </div>
            
            <div class="aspect-video rounded-2xl bg-slate-950 border border-slate-800 flex flex-col items-center justify-center p-6 text-center space-y-3">
                <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl">
                    ▶
                </div>
                <div class="text-sm font-bold text-white">{{ __('Interactive Hardware & Multi-Store Video Tour') }}</div>
                <p class="text-xs text-slate-400 max-w-md">
                    {{ __('Demonstrating 80mm ESC/POS thermal printing, multi-store stock transfers, and daily register float sessions.') }}
                </p>
                <a href="{{ route('tenant.register') }}" class="px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition">
                    {{ __('Launch Free Interactive Trial Now') }} →
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
