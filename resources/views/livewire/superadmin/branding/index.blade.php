<div class="w-full space-y-8">
    
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- 1. Platform Identity & Colors Studio -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex items-center justify-between">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🎨 Platform Identity &amp; Dynamic Colors</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Customize platform name, logos, and dynamic color themes for SuperAdmin and Landing Page.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-[11px]">
                Real-Time Theming
            </span>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Platform Brand Name *") }}</label>
                <input type="text" wire:model="platformName" placeholder="Smart Inventory & Sales" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                @error('platformName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Platform Logo") }}</label>
                        @if ($logoImage)
                            <span class="text-[10px] font-black text-amber-500">Staged</span>
                        @elseif ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="h-5 w-auto max-w-[70px] object-contain rounded">
                        @endif
                    </div>
                    <input type="text" wire:model="logoUrl" placeholder="https://example.com/logo.png" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    <input type="file" wire:model="logoImage" accept="image/*" class="w-full mt-1.5 text-[11px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 dark:file:bg-indigo-950/40 dark:file:text-indigo-300 cursor-pointer">
                    @error('logoUrl') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('logoImage') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Favicon URL") }}</label>
                    <input type="text" wire:model="faviconUrl" placeholder="https://example.com/favicon.ico" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>
            </div>

            <!-- Dynamic Color Palette Studio -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">Color Studio (SuperAdmin &amp; Landing Page)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <!-- SuperAdmin Sidebar Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __("SuperAdmin Sidebar Menu") }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="superadminSidebarColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="superadminSidebarColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                        <span class="text-[10px] text-slate-400">Controls SuperAdmin rail &amp; drawer color</span>
                    </div>

                    <!-- Landing Primary Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __("Landing Primary Emerald") }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="landingPrimaryColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="landingPrimaryColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                        <span class="text-[10px] text-slate-400">Main button &amp; badge highlights</span>
                    </div>

                    <!-- Landing Accent Lime Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __("Landing Neon Lime Accent") }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="landingAccentColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="landingAccentColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                        <span class="text-[10px] text-slate-400">Glow aura &amp; pill accent buttons</span>
                    </div>

                    <!-- Global Theme Color -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __("Tenant Brand Primary") }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="primaryColor" class="w-9 h-9 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0">
                            <input type="text" wire:model="primaryColor" class="w-full font-mono text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 p-1.5">
                        </div>
                        <span class="text-[10px] text-slate-400">{{ __("Tenant app fallback primary") }}</span>
                    </div>

                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Support Email") }}</label>
                    <input type="email" wire:model="supportEmail" placeholder="support@yourdomain.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Support Phone") }}</label>
                    <input type="text" wire:model="supportPhone" placeholder="+1 (555) 019-2834" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" wire:model="otpRegistrationEnabled" id="otp" class="rounded-lg text-indigo-600 focus:ring-indigo-500">
                <label for="otp" class="text-xs font-bold text-slate-700 dark:text-slate-300">Require OTP Email Verification on New Registrations</label>
            </div>
        </div>

    </div>

    <!-- 1.5. Auth Screens & Registration Configuration -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex items-center justify-between">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🖼️ Auth Screens &amp; Registration Configuration</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Configure tenant login and registration screen banners, layout behavior, and domain configuration steps.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-[11px]">
                Auth &amp; Domain Setup
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Subdomain & Custom Domain Setup Toggle -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                        <span>🌐</span> {{ __('Subdomain & Domain Setup on Registration') }}
                    </span>
                    <input type="checkbox" wire:model="enableRegistrationDomainSetup" class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                </label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('When enabled, registering stores can expand an accordion to customize their subdomain and custom domain. When disabled, the accordion is completely hidden and unique subdomains are automatically generated behind the scenes.') }}
                </p>
            </div>

            <!-- Show Auth Banner Toggle -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                        <span>🎨</span> {{ __('Show Auth Graphic / Banner on Login & Register') }}
                    </span>
                    <input type="checkbox" wire:model="showAuthBanner" class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                </label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('When enabled and an image is provided, a promotional graphic displays on the right side of login and registration. When disabled or empty, the forms expand cleanly into a centered layout.') }}
                </p>
            </div>
        </div>

        <!-- Auth Graphic Banner Image Upload & URL -->
        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Auth Graphic / Banner Image') }}
                    </label>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Upload a high-resolution banner image or specify an image URL to replace the static cards on login and registration.') }}
                    </p>
                </div>
                @if ($authBannerImageUrl || $authBannerImage)
                    <button type="button" wire:click="removeAuthBanner" class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-xs font-bold hover:bg-rose-100 transition shrink-0 cursor-pointer">
                        ✕ {{ __('Remove Banner') }}
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                <!-- Image Preview -->
                <div class="md:col-span-4">
                    <div class="w-full aspect-[4/3] rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center overflow-hidden bg-white dark:bg-slate-900 relative">
                        @if ($authBannerImage)
                            <img src="{{ $authBannerImage->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-amber-500 text-slate-950 text-[10px] font-black">{{ __('Staged Upload') }}</span>
                        @elseif ($authBannerImageUrl)
                            <img src="{{ $authBannerImageUrl }}" alt="Banner" class="w-full h-full object-cover">
                        @else
                            <div class="text-center p-4">
                                <span class="text-3xl block mb-1">🖼️</span>
                                <span class="text-[11px] font-bold text-slate-400">{{ __('No Banner Image Set') }}</span>
                                <span class="block text-[10px] text-slate-500 mt-0.5">{{ __('Forms will use centered layout') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Upload and URL inputs -->
                <div class="md:col-span-8 space-y-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Upload Banner File') }}
                        </label>
                        <input type="file" wire:model="authBannerImage" accept="image/*" class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 dark:file:bg-indigo-950/40 dark:file:text-indigo-300 cursor-pointer">
                        <div wire:loading wire:target="authBannerImage" class="text-[11px] text-indigo-500 font-bold">
                            ⏳ {{ __('Uploading banner image preview...') }}
                        </div>
                        @error('authBannerImage') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Or Image URL') }}
                        </label>
                        <input type="text" wire:model="authBannerImageUrl" placeholder="https://example.com/auth-banner.jpg" class="w-full px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        @error('authBannerImageUrl') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-[10px] text-slate-400">
                        {{ __('Recommended: 1000×1200px or 1200×800px. PNG, JPG, or WebP up to 5MB.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Landing Page Hero Section Customizer -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex items-center justify-between">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🚀 Landing Page Hero &amp; Banner Customizer</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Edit the headline, value proposition subtitle, action buttons, and hero image banner.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="landingPageEnabled" id="landingEnabled" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <label for="landingEnabled" class="text-xs font-black text-slate-800 dark:text-slate-200">Enable Landing Page</label>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Hero Top Badge Text") }}</label>
                <input type="text" wire:model="landingHeroBadge" placeholder="All-in-one POS, Inventory &amp; Restaurant Platform" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Main Headline Title *") }}</label>
                <input type="text" wire:model="landingHeroTitle" placeholder="The Most Modern POS &amp; Inventory Platform for Your Business" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Hero Value Proposition Subtitle") }}</label>
                <textarea wire:model="landingHeroSubtitle" rows="3" placeholder="Unified retail checkout, real-time stock inventory, dining floor KOT, and automated financial ledgers — all in one fast, offline-ready cloud platform." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Primary CTA Button Label") }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryText" placeholder="Start Free Trial" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Primary CTA URL") }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryUrl" placeholder="{{ route('tenant.register') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Secondary CTA Button Label") }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryText" placeholder="Explore Features" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Secondary CTA URL") }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryUrl" placeholder="#features" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Custom Hero Banner Image URL (Optional)") }}</label>
                <input type="text" wire:model="landingHeroBannerImageUrl" placeholder="https://example.com/custom-pos-banner.png (Leave empty to use built-in interactive POS UI)" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-emerald-500">
                <p class="text-[11px] text-slate-400 mt-1">If provided, this image will display on the right side of the hero instead of the default interactive POS &amp; Inventory dashboard.</p>
            </div>
        </div>

    </div>

    <!-- 3. Landing Page Sections Visibility Manager -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑 {{ __("Landing Page Sections Controller") }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                Toggle individual sections on or off to curate your exact marketing presentation.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionTrustBar" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Hardware Trust Bar") }}</div>
                    <div class="text-[10px] text-slate-400">Scanners, printers &amp; KDS</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionFeatures" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Feature Modules Suite") }}</div>
                    <div class="text-[10px] text-slate-400">{{ __("POS, Inventory, KOT, Finance") }}</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionSolutions" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Solution Pillars") }}</div>
                    <div class="text-[10px] text-slate-400">Reliability &amp; Cashflow card</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionStats" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Live Metric Counters") }}</div>
                    <div class="text-[10px] text-slate-400">{{ __("Volume, Outlets, Uptime") }}</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionAbout" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Mission &amp; About</div>
                    <div class="text-[10px] text-slate-400">{{ __("Company mission card") }}</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionTestimonials" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Customer Testimonials") }}</div>
                    <div class="text-[10px] text-slate-400">Reviews &amp; security badges</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionPricing" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Pricing Plans Grid") }}</div>
                    <div class="text-[10px] text-slate-400">{{ __("Active subscription plans") }}</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionContact" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Inquiry Contact Form") }}</div>
                    <div class="text-[10px] text-slate-400">{{ __("Lead capture form") }}</div>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 cursor-pointer hover:border-emerald-400/50 transition">
                <input type="checkbox" wire:model="sectionCta" class="rounded-lg text-emerald-600 focus:ring-emerald-500">
                <div>
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __("Bottom CTA Banner") }}</div>
                    <div class="text-[10px] text-slate-400">{{ __("Registration prompt") }}</div>
                </div>
            </label>

        </div>

    </div>

    <!-- 4. Optional Custom Authored Page Content -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📝 {{ __("Additional Custom Authored Content (Optional)") }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                Inject custom HTML/content authored via TinyMCE from <a wire:navigate.hover href="{{ route('superadmin.pages.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">Custom Pages</a> into the landing page.
            </p>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __("Select Extra Page to Embed") }}</label>
            <select wire:model="landingPageId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                <option value="">— {{ __("None (Use built-in modules only)") }} —</option>
                @foreach ($availablePages as $p)
                    <option value="{{ $p->id }}">{{ $p->title }} ({{ $p->is_active ? 'active' : 'inactive' }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Save Button -->
    <div class="flex items-center justify-between pt-2">
        <a href="{{ url('/') }}" target="_blank" class="px-5 py-3 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition inline-flex items-center gap-1.5">
            <span>👁️ {{ __("Preview Landing Page") }}</span>
            <span>↗</span>
        </a>

        <button wire:click="save" type="button" class="px-8 py-3.5 rounded-2xl text-xs sm:text-sm font-black bg-indigo-600 hover:bg-indigo-700 text-white shadow-xl shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
            Save All Settings
        </button>
    </div>

</div>

