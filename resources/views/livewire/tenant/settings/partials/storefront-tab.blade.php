<!-- =========================================================================
     TAB: STOREFRONT PROMO BANNER & CUSTOMER SOCIAL AUTH
     ========================================================================= -->
<div x-show="activeTab === 'storefront'" x-cloak class="space-y-6">

    <!-- Storefront Promo Banner Configuration Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-xl">
                    🛍️
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Storefront Promo Banner') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Customize the announcement hero card displayed at the top of your public storefront.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button"
                        wire:click="resetStoreBannerToDefault"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    {{ __('Reset to Default') }}
                </button>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storeBannerIsActive" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                    <span class="ml-2.5 text-xs font-extrabold text-slate-800 dark:text-slate-200">
                        {{ $storeBannerIsActive ? __('Banner Visible') : __('Banner Hidden') }}
                    </span>
                </label>
            </div>
        </div>

        @if (!$storeBannerIsActive)
            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40 flex items-start gap-3">
                <span class="text-base">ℹ️</span>
                <p class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
                    {{ __('The promo banner is currently disabled. On your storefront, this entire hero section will be hidden cleanly with zero whitespace gap.') }}
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Tag & Title -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Badge / Tagline Text') }}
                    </label>
                    <input type="text"
                           wire:model="storeBannerTag"
                           placeholder="e.g. SPECIAL STORE DEALS"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white focus:ring-blue-500">
                    @error('storeBannerTag') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Main Promo Headline *') }}
                    </label>
                    <input type="text"
                           wire:model="storeBannerTitle"
                           placeholder="e.g. Grab Up To 50% Off On Selected Products"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white focus:ring-blue-500">
                    @error('storeBannerTitle') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Promotional Subtitle / Description') }}
                    </label>
                    <textarea rows="3"
                              wire:model="storeBannerSubtitle"
                              placeholder="{{ __('Explain your ongoing offer, discount code, or fast dispatch notice...') }}"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium text-slate-900 dark:text-white focus:ring-blue-500"></textarea>
                    @error('storeBannerSubtitle') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- CTA Button & Banner Image -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ __('Button CTA Text') }}
                        </label>
                        <input type="text"
                               wire:model="storeBannerCtaText"
                               placeholder="e.g. Shop Now"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white focus:ring-blue-500">
                        @error('storeBannerCtaText') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ __('CTA Target Link') }}
                        </label>
                        <input type="text"
                               wire:model="storeBannerCtaLink"
                               placeholder="e.g. #products-section"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono text-slate-900 dark:text-white focus:ring-blue-500">
                        @error('storeBannerCtaLink') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Banner Image URL (or upload below)') }}
                    </label>
                    <input type="text"
                           wire:model="storeBannerImageUrl"
                           placeholder="https://images.unsplash.com/..."
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono text-slate-900 dark:text-white focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Upload Banner Graphic / Poster') }}
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="file"
                               wire:model="storeBannerImageFile"
                               accept="image/*"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-950/60 dark:file:text-blue-400 hover:file:bg-blue-100 cursor-pointer">
                        @if ($storeBannerImageUrl || $storeBannerImageFile)
                            <button type="button"
                                    wire:click="removeStoreBannerImage"
                                    class="px-3 py-2 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition shrink-0">
                                {{ __('Remove') }}
                            </button>
                        @endif
                    </div>
                    @error('storeBannerImageFile') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Live Preview Card -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
                <span>👁️</span> {{ __('Storefront Live Preview') }}
            </h4>

            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 border border-white/10 shadow-xl">
                <div class="relative z-10 max-w-xl space-y-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black tracking-wider uppercase bg-blue-500/20 text-blue-400 border border-blue-400/30">
                        ⚡ {{ $storeBannerTag ?: 'SPECIAL STORE DEALS' }}
                    </span>
                    <h2 class="text-lg sm:text-2xl font-black tracking-tight text-white">
                        {{ $storeBannerTitle ?: 'Grab Up To 50% Off On Selected Products' }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-300 line-clamp-2">
                        {{ $storeBannerSubtitle ?: 'Order authentic items online with direct-to-door verified dispatch and real-time inventory.' }}
                    </p>
                    <div class="pt-2">
                        <button type="button" class="px-5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/30 transition">
                            {{ $storeBannerCtaText ?: 'Shop Now' }} →
                        </button>
                    </div>
                </div>

                @if ($storeBannerImageUrl)
                    <div class="hidden sm:block absolute right-0 top-0 bottom-0 w-1/3 opacity-40 mix-blend-screen overflow-hidden">
                        <img src="{{ $storeBannerImageUrl }}" alt="Banner preview" class="w-full h-full object-cover">
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Customer Social Login (Google OAuth) Configuration Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold text-xl">
                    <svg class="w-6 h-6" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Customer Google Social Login') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Allow customers to instantly log in or register at checkout using their Google account.') }}</p>
                </div>
            </div>

            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="enableGoogleLogin" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                <span class="ml-2.5 text-xs font-extrabold text-slate-800 dark:text-slate-200">
                    {{ $enableGoogleLogin ? __('Google Login Enabled') : __('Disabled') }}
                </span>
            </label>
        </div>

        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40 space-y-2">
            <div class="flex items-center gap-2 text-xs font-extrabold text-blue-900 dark:text-blue-200">
                <span>🔒</span> {{ __('Mandatory Checkout Authentication') }}
            </div>
            <p class="text-xs text-blue-800 dark:text-blue-300 leading-relaxed">
                {{ __('When mandatory checkout auth is enabled, shoppers are prompted to log in or create an account before finalizing their cart. Google OAuth provides frictionless 1-click authentication.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    {{ __('Google Client ID') }}
                </label>
                <input type="text"
                       wire:model="googleClientId"
                       placeholder="xxxx-xxxx.apps.googleusercontent.com"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono text-slate-900 dark:text-white focus:ring-blue-500">
                @error('googleClientId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
                    <span>{{ __('Google Client Secret') }}</span>
                    @if ($hasStoredGoogleClientSecret)
                        <span class="text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-md">
                            ✓ {{ __('Secret Saved') }}
                        </span>
                    @endif
                </label>
                <input type="password"
                       wire:model="googleClientSecret"
                       placeholder="{{ $hasStoredGoogleClientSecret ? '••••••••••••••••' : __('Enter Google Client Secret') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono text-slate-900 dark:text-white focus:ring-blue-500">
                @error('googleClientSecret') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Google Cloud Console Authorized Redirect URI Info -->
        <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2">
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">
                {{ __('Authorized Redirect URI for Google Cloud Console:') }}
            </label>
            <div class="flex items-center gap-2">
                <input type="text"
                       readonly
                       value="{{ url('/store/auth/google/callback') }}"
                       class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-800 dark:text-slate-200 select-all">
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ url('/store/auth/google/callback') }}'); alert('Redirect URI copied to clipboard!');"
                        class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-xs font-extrabold text-slate-800 dark:text-slate-200 transition shrink-0 cursor-pointer">
                    {{ __('Copy') }}
                </button>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ __('Paste this exact URI under "Authorized redirect URIs" in your Google Cloud Console OAuth 2.0 Client Credentials configuration.') }}
            </p>
        </div>
    </div>

    <!-- Product Ratings & Reviews Configuration Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xl">
                    ⭐
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Product Ratings & Reviews') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Allow verified customers to rate products 1-5 stars and submit feedback on your storefront.') }}</p>
                </div>
            </div>

            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="enableProductReviews" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                <span class="ml-2.5 text-xs font-extrabold text-slate-800 dark:text-slate-200">
                    {{ $enableProductReviews ? __('Reviews Enabled') : __('Reviews Disabled') }}
                </span>
            </label>
        </div>

        @if (!$enableProductReviews)
            <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                <span class="text-base">🚫</span>
                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    {{ __('Product reviews are currently disabled. Customer review submissions and star rating badges are hidden from product cards and product details on your storefront.') }}
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Moderation Policy Toggle -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>🛡️</span> {{ __('Require Admin Approval') }}
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ __('Reviews will remain hidden until you review and approve them in the Reviews Moderation Console.') }}
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model="requireReviewApproval" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Manage Reviews Console Link -->
                <div class="p-4 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="text-xs font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                            <span>📋</span> {{ __('Reviews Moderation Console') }}
                        </div>
                        <p class="text-[11px] text-amber-800/80 dark:text-amber-300/80 leading-relaxed">
                            {{ __('Inspect customer ratings, filter by product, approve pending submissions, or remove spam.') }}
                        </p>
                    </div>
                    <a href="{{ route('tenant.reviews.index') }}" wire:navigate class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs transition active:scale-95 shrink-0 shadow-sm">
                        {{ __('Open Console') }} →
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
