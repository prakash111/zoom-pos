@props(['branding'])

@php
    $heroBadge = $branding->getHeroBadge();
    $heroTitle = $branding->getHeroTitle();
    $heroSubtitle = $branding->getHeroSubtitle();
    $ctaPrimaryText = $branding->getHeroCtaPrimaryText();
    $ctaPrimaryUrl = $branding->getHeroCtaPrimaryUrl();
    $ctaSecondaryText = $branding->getHeroCtaSecondaryText();
    $ctaSecondaryUrl = $branding->getHeroCtaSecondaryUrl();
    $customBannerUrl = $branding->landing_hero_banner_image_url;
@endphp

<div id="showcase" class="relative py-6 sm:py-10 lg:py-14 px-3 sm:px-6 lg:px-8 overflow-hidden">
    <!-- Ambient Aurora Glow Canvas Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-[#0c5966] via-[#10707e] to-[#6da734] dark:from-[#06242a] dark:via-[#09353c] dark:to-[#2b4414] -z-20"></div>

    <!-- Soft radial glow orbs behind the main container -->
    <div class="landing-ambient absolute top-1/4 -left-20 w-96 h-96 rounded-full bg-teal-400/15 blur-3xl pointer-events-none -z-10"></div>
    <div class="landing-ambient absolute bottom-10 right-0 w-[500px] h-[500px] rounded-full bg-lime-400/15 blur-3xl pointer-events-none -z-10"></div>

    <!-- Main Outer Container: The High-End Rounded Frame from Reference Design -->
    <div class="max-w-7xl mx-auto rounded-[2.5rem] sm:rounded-[3rem] bg-white dark:bg-slate-950 shadow-[0_30px_90px_-15px_rgba(0,0,0,0.35)] border border-white/60 dark:border-slate-800/80 relative overflow-hidden">
        
        <!-- Top-Right Neon Lime Atmospheric Glow -->
        <div class="absolute -top-32 -right-32 w-[600px] h-[600px] bg-gradient-to-bl from-[#bef264]/40 via-[#86efac]/25 to-transparent rounded-full blur-3xl pointer-events-none -z-0"></div>
        <div class="absolute -top-10 -left-10 w-[400px] h-[400px] bg-gradient-to-br from-teal-300/20 via-cyan-200/10 to-transparent rounded-full blur-2xl pointer-events-none -z-0"></div>

        <!-- Integrated Top Nav Bar -->
        <div class="relative z-10 px-6 sm:px-10 lg:px-12 pt-6 sm:pt-8 pb-4 hidden md:flex items-center justify-between border-b border-slate-100/80 dark:border-slate-800/60">
            <!-- Brand Logo -->
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 group">
                @if ($branding->logo_url)
                    <img src="{{ $branding->logo_url }}" alt="{{ $branding->platform_name }}" class="h-8 w-auto object-contain" decoding="async" fetchpriority="high">
                @else
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-lime to-emerald-400 p-1.5 flex items-center justify-center shadow-sm shadow-emerald-400/30 group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5 text-slate-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round">
                            <path d="M7 17L17 7M11 17L17 11M7 13L13 7" />
                        </svg>
                    </div>
                @endif
                <span class="text-lg font-black tracking-tight text-slate-900 dark:text-white">{{ $branding->platform_name }}</span>
            </a>

            <!-- Center Nav Links -->
            <nav class="hidden md:flex items-center gap-6 lg:gap-8 text-xs font-semibold text-slate-700 dark:text-slate-300">
                <a href="#showcase" class="hover:text-slate-950 dark:hover:text-white transition">{{ __('Platform') }}</a>
                <a href="#features" class="hover:text-slate-950 dark:hover:text-white transition">{{ __('Products') }}</a>
                <a href="#solutions" class="hover:text-slate-950 dark:hover:text-white transition">{{ __('Solutions') }}</a>
                <a href="#pricing" class="hover:text-slate-950 dark:hover:text-white transition">{{ __('Pricing') }}</a>
                <a href="#about" class="hover:text-slate-950 dark:hover:text-white transition">{{ __('Company') }}</a>
            </nav>

            <!-- Right Actions -->
            <div class="flex items-center gap-4 text-xs font-bold">
                <a href="#contact" class="hidden sm:inline-block text-slate-600 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white transition">{{ __('Documentation') }}</a>
                <a href="{{ route('tenant.login') }}" class="text-slate-700 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white transition">{{ __('Sign in') }}</a>
                <a href="{{ $ctaPrimaryUrl }}" class="px-5 py-2.5 rounded-full bg-slate-950 dark:bg-white text-white dark:text-slate-950 hover:bg-slate-800 dark:hover:bg-slate-100 transition shadow-md shadow-slate-900/10 active:scale-95">
                    {{ $ctaPrimaryText }}
                </a>
            </div>
        </div>

        <!-- Main Hero Grid Content -->
        <div class="relative z-10 px-6 sm:px-10 lg:px-12 pt-8 sm:pt-16 pb-12 sm:pb-16 grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- Left Column: Typography & CTAs -->
            <div class="lg:col-span-6 xl:col-span-6 text-left">
                
                @if ($heroBadge)
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 text-xs font-black uppercase tracking-wider mb-5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>{{ $heroBadge }}</span>
                    </div>
                @endif

                <h1 class="text-4xl sm:text-5xl lg:text-[3.35rem] xl:text-[3.65rem] font-extrabold text-slate-900 dark:text-white tracking-tight leading-[1.1]">
                    {{ $heroTitle }}
                </h1>

                <p class="mt-6 text-sm sm:text-base text-slate-600 dark:text-slate-400 leading-relaxed max-w-lg font-normal">
                    {{ $heroSubtitle }}
                </p>

                <!-- CTA Buttons (Vibrant Lime Pill + Explore Link) -->
                <div class="mt-8 sm:mt-10 flex flex-wrap items-center gap-4">
                    <a href="{{ $ctaPrimaryUrl }}" class="px-6 sm:px-7 py-3 sm:py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 text-xs sm:text-sm font-black shadow-lg shadow-brand-lime/25 inline-flex items-center gap-2 transition-all active:scale-95 group">
                        <span>{{ $ctaPrimaryText }}</span>
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a href="{{ $ctaSecondaryUrl }}" class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 inline-flex items-center gap-1.5 transition-all group py-2 px-2">
                        <span>{{ $ctaSecondaryText }}</span>
                        <span class="group-hover:translate-x-1 transition-transform">→</span>
                    </a>
                </div>

                <!-- Quick highlights bullets -->
                <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="text-brand-lime font-black">✓</span> {{ __('Barcode & Touch POS') }}</span>
                    <span class="flex items-center gap-1.5"><span class="text-brand-lime font-black">✓</span> {{ __('Live Stock Alerts') }}</span>
                    <span class="flex items-center gap-1.5"><span class="text-brand-lime font-black">✓</span> {{ __('Restaurant Floor KOT') }}</span>
                    <span class="flex items-center gap-1.5"><span class="text-brand-lime font-black">✓</span> {{ __('Offline First Sync') }}</span>
                </div>
            </div>

            <!-- Right Column: Visual UI Showcase (Custom Image Banner OR Interactive Smart POS & Stock Dashboard) -->
            <div class="lg:col-span-6 xl:col-span-6 relative mt-4 lg:mt-0">
                <div class="relative max-w-lg lg:max-w-none mx-auto">
                    
                    @if ($customBannerUrl)
                        <!-- Custom Uploaded Banner Image from SuperAdmin -->
                        <div class="rounded-2xl sm:rounded-3xl overflow-hidden border border-slate-200/80 dark:border-slate-800 shadow-2xl bg-slate-900">
                            <img src="{{ $customBannerUrl }}" alt="{{ $heroTitle }}" class="w-full h-auto object-cover rounded-2xl sm:rounded-3xl" decoding="async" fetchpriority="high">
                        </div>
                    @else
                        <!-- Built-in Modern Smart Inventory & POS Live Dashboard Showcase -->
                        
                        <!-- Layer 1: POS & Stock Management Screen -->
                        <div class="rounded-2xl sm:rounded-3xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800 shadow-2xl p-5 sm:p-7 relative overflow-hidden transition hover:border-emerald-300/60">
                            
                            <!-- Top Sub-Header & Live Barcode Status -->
                            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800/80">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-brand-lime to-emerald-400 flex items-center justify-center shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-slate-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M4 7V4h3M17 4h3v3M4 17v3h3M20 17v3h-3M9 9h6M9 12h6M9 15h6" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-xs font-black text-slate-800 dark:text-slate-200">{{ __('Smart POS & Inventory') }}</span>
                                        <span class="hidden sm:inline text-[10px] text-emerald-600 dark:text-emerald-400 font-bold ml-1.5">● {{ __('Live') }}</span>
                                    </div>
                                </div>

                                <div class="relative flex items-center">
                                    <div class="w-36 sm:w-52 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 flex items-center gap-2 text-[11px] text-slate-400">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 18a7.5 7.5 0 006.15-3.35z"/>
                                        </svg>
                                        <span class="truncate">{{ __('Scan barcode / SKU...') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-navigation Tabs Row -->
                            <div class="flex items-center gap-4 sm:gap-6 pt-3 pb-3 border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold text-slate-500 overflow-x-auto no-scrollbar">
                                <span class="text-slate-950 dark:text-white border-b-2 border-emerald-500 pb-1 -mb-[13px] whitespace-nowrap">{{ __('Quick POS') }}</span>
                                <span class="hover:text-slate-900 dark:hover:text-white cursor-pointer whitespace-nowrap">{{ __('Live Stock') }}</span>
                                <span class="hover:text-slate-900 dark:hover:text-white cursor-pointer whitespace-nowrap">{{ __('Dining Floor') }}</span>
                                <span class="hover:text-slate-900 dark:hover:text-white cursor-pointer whitespace-nowrap">{{ __('KOT Queue') }}</span>
                                <span class="hover:text-slate-900 dark:hover:text-white cursor-pointer whitespace-nowrap">{{ __('Day Summary') }}</span>
                            </div>

                            <!-- Live Product & Inventory Data Rows -->
                            <div class="mt-4 space-y-2.5">
                                
                                <!-- Product Row 1 -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/60 text-xs">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-black text-xs flex items-center justify-center">
                                            ☕
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">Artisan Coffee Roast 1kg</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: COF-4829 &middot; 142 {{ __('in stock') }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">$14.50</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-[#dcfce7] text-[#15803d] dark:bg-[#14532d]/60 dark:text-[#86efac] font-bold text-[10px]">{{ __('In Stock') }}</span>
                                    </div>
                                </div>

                                <!-- Product Row 2 -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/60 text-xs">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-teal-100 dark:bg-teal-950 text-teal-700 dark:text-teal-300 font-black text-xs flex items-center justify-center">
                                            🫒
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">Gourmet Truffle Oil 500ml</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: OIL-9104 &middot; 88 {{ __('in stock') }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">$18.20</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-[#dcfce7] text-[#15803d] dark:bg-[#14532d]/60 dark:text-[#86efac] font-bold text-[10px]">{{ __('In Stock') }}</span>
                                    </div>
                                </div>

                                <!-- Product Row 3 (Low Stock Alert) -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/60 text-xs">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-black text-xs flex items-center justify-center">
                                            🌾
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">Organic Almond Flour 1kg</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: FLR-3318 &middot; 3 {{ __('remaining') }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">$8.90</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 font-bold text-[10px]">{{ __('Low Stock') }}</span>
                                    </div>
                                </div>

                                <!-- Product Row 4 -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/60 text-xs">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-black text-xs flex items-center justify-center">
                                            🍝
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">Handcrafted Penne Pasta</div>
                                            <div class="text-[10px] text-slate-400 font-mono">SKU: PAS-7741 &middot; 240 {{ __('in stock') }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">$6.50</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-[#dcfce7] text-[#15803d] dark:bg-[#14532d]/60 dark:text-[#86efac] font-bold text-[10px]">{{ __('In Stock') }}</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Layer 2: Floating 3D Smart POS Receipt & Live KOT Ticket -->
                        <div class="absolute -left-4 sm:-left-8 -bottom-6 sm:-bottom-8 z-20 w-64 sm:w-80 rounded-2xl bg-gradient-to-br from-white via-slate-50 to-slate-100/95 dark:from-slate-850 dark:via-slate-900 dark:to-slate-950 border border-white/90 dark:border-slate-700 shadow-xl p-4 sm:p-5 flex flex-col justify-between">
                            
                            <!-- Ticket Top Bar -->
                            <div class="flex items-start justify-between pb-2 border-b border-slate-200/80 dark:border-slate-800">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-black text-slate-900 dark:text-white">🧾 {{ __('Table #04 · Floor Main') }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ __('Order #1084 · Server Alex M.') }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-black">
                                    {{ __('KOT Sent') }}
                                </span>
                            </div>

                            <!-- Items List -->
                            <div class="py-2.5 space-y-1 text-xs text-slate-600 dark:text-slate-300 font-medium">
                                <div class="flex justify-between">
                                    <span>2x Artisan Coffee Roast</span>
                                    <span class="font-bold text-slate-900 dark:text-white">$29.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>1x Gourmet Truffle Oil</span>
                                    <span class="font-bold text-slate-900 dark:text-white">$18.20</span>
                                </div>
                            </div>

                            <!-- Split Tender & Total -->
                            <div class="pt-2 border-t border-dashed border-slate-200 dark:border-slate-800 flex items-end justify-between">
                                <div>
                                    <div class="text-[9px] uppercase font-bold text-slate-400">{{ __('Split Tender Paid') }}</div>
                                    <div class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        💵 $25.00 Cash + 💳 $22.20 Card
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[9px] uppercase font-bold text-slate-400">{{ __('Total') }}</div>
                                    <div class="text-base font-black text-slate-900 dark:text-white">$47.20</div>
                                </div>
                            </div>

                        </div>
                    @endif

                </div>
            </div>

        </div>

        <!-- Bottom Social Proof Hardware & Client Brands Bar -->
        <div class="relative z-10 px-6 sm:px-10 lg:px-12 py-6 border-t border-slate-100/80 dark:border-slate-800/60 bg-slate-50/40 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-6 sm:gap-8">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Trusted by retail & dining chains') }}</span>
            
            <div class="flex flex-wrap items-center gap-8 sm:gap-12 text-slate-500 dark:text-slate-400">
                <!-- Stretch Retail Logo -->
                <div class="flex items-center gap-1.5 font-black text-sm tracking-tight text-slate-700 dark:text-slate-300">
                    <span class="text-base tracking-tighter italic font-serif">S</span>
                    <span>Stretch Market</span>
                </div>

                <!-- MXK8 Logistics Logo -->
                <div class="font-black text-sm tracking-widest text-slate-700 dark:text-slate-300">
                    MXK8 RETAIL
                </div>

                <!-- GoDo Food Group Logo -->
                <div class="flex items-center gap-1.5 font-black text-sm text-slate-700 dark:text-slate-300">
                    <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5" />
                    </svg>
                    <span>GoDo Dining</span>
                </div>

                <!-- Chippe Gourmet Logo -->
                <div class="flex items-center gap-1.5 font-bold text-sm text-slate-700 dark:text-slate-300">
                    <div class="w-3.5 h-3.5 rounded-full border-2 border-slate-700 dark:border-slate-300 flex items-center justify-center text-[8px] font-black">C</div>
                    <span>Chippe Grocers</span>
                </div>

                <!-- FinScale Stores Logo -->
                <div class="hidden sm:flex items-center gap-1 font-bold text-sm text-slate-700 dark:text-slate-300">
                    <span class="text-brand-lime-dark font-black">▲</span>
                    <span>FinScale POS</span>
                </div>
            </div>
        </div>

    </div>
</div>
