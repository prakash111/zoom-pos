<div class="space-y-6">
    <form wire:submit="saveBranding" class="space-y-6">
        <!-- Platform Brand Identity -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🛡️</span> {{ __('Platform Identity & Logos') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Customize the platform brand title, header logo, and browser favicon.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Platform Name -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Platform Display Name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="platformName"
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('platformName') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Logo URL & File Upload -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Platform Logo') }}
                        </label>
                        @if ($logoImage)
                            <span class="text-[10px] font-black text-amber-500">Staged</span>
                        @elseif ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="h-5 w-auto max-w-[70px] object-contain rounded">
                        @endif
                    </div>
                    <input type="text"
                           wire:model="logoUrl"
                           placeholder="https://example.com/logo.png"
                           class="w-full px-4 py-2 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                    <input type="file" wire:model="logoImage" accept="image/*" class="w-full text-[11px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 dark:file:bg-indigo-950/40 dark:file:text-indigo-300 cursor-pointer">
                    @error('logoUrl') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    @error('logoImage') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Favicon URL -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Favicon Icon URL') }}
                    </label>
                    <input type="text"
                           wire:model="faviconUrl"
                           placeholder="https://example.com/favicon.ico"
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('faviconUrl') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Color Palette -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🎨</span> {{ __('Brand Color Palette') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Configure system accents, sidebar navigation colors, and landing theme highlights.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <!-- Primary Color -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Primary UI Accent') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="primaryColor" class="w-8 h-8 rounded-xl border-none cursor-pointer p-0 bg-transparent">
                        <input type="text" wire:model="primaryColor" class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                    </div>
                </div>

                <!-- Sidebar Color -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('SuperAdmin Sidebar') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="superadminSidebarColor" class="w-8 h-8 rounded-xl border-none cursor-pointer p-0 bg-transparent">
                        <input type="text" wire:model="superadminSidebarColor" class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                    </div>
                </div>

                <!-- Landing Primary -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Landing Primary') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="landingPrimaryColor" class="w-8 h-8 rounded-xl border-none cursor-pointer p-0 bg-transparent">
                        <input type="text" wire:model="landingPrimaryColor" class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                    </div>
                </div>

                <!-- Landing Accent -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Landing Accent') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="landingAccentColor" class="w-8 h-8 rounded-xl border-none cursor-pointer p-0 bg-transparent">
                        <input type="text" wire:model="landingAccentColor" class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                    </div>
                </div>
            </div>
        </div>

        <!-- Support & Security -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📞</span> {{ __('Support Contacts & Auth Security') }}
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Public Support Email') }}</label>
                    <input type="email" wire:model="supportEmail" placeholder="support@yourdomain.com" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Support Contact Phone') }}</label>
                    <input type="text" wire:model="supportPhone" placeholder="+1 (555) 019-2834" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white">
                </div>

                <div class="space-y-2 flex flex-col justify-center">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('OTP Email Verification') }}</label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="otpRegistrationEnabled" class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Require Email OTP on Tenant Sign-up') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Auth Screens & Registration Configuration -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🖼️</span> {{ __('Auth Screens & Registration Configuration') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Configure tenant login and registration screen banners, layout behavior, and domain configuration steps.') }}
                </p>
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

        <!-- Landing Page CMS Settings -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🌐</span> {{ __('Public Landing Page & Hero CMS') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Configure high-converting marketing hero banners, CTA buttons, and modular sections.') }}
                    </p>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="landingPageEnabled" class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Enable Landing Page') }}</span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Landing Hero Badge Text') }}</label>
                    <input type="text" wire:model="landingHeroBadge" placeholder="All-in-one POS, Inventory & Restaurant Platform" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Custom Landing CMS Page') }}</label>
                    <select wire:model="landingPageId" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                        <option value="">{{ __('Default Built-in Marketing Landing Page') }}</option>
                        @foreach ($availablePages as $p)
                            <option value="{{ $p->id }}">{{ $p->title }} (/p/{{ $p->slug }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hero Main Heading') }}</label>
                    <input type="text" wire:model="landingHeroTitle" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Hero Subtitle / Description') }}</label>
                    <textarea wire:model="landingHeroSubtitle" rows="2" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"></textarea>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Primary CTA Button Text') }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryText" placeholder="Start Free Trial" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Primary CTA URL') }}</label>
                    <input type="text" wire:model="landingHeroCtaPrimaryUrl" placeholder="/register" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Secondary CTA Button Text') }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryText" placeholder="Explore Features" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Secondary CTA URL') }}</label>
                    <input type="text" wire:model="landingHeroCtaSecondaryUrl" placeholder="#features" class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
                </div>
            </div>

            <!-- Mobile & Desktop App Download Links -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('App Download Links') }}</label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="landingPlaystoreEnabled" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">▶ {{ __('Show Google Play Store button') }}</span>
                        </label>
                        <input type="url" wire:model="landingPlaystoreUrl" placeholder="https://play.google.com/store/apps/details?id=..."
                               class="w-full px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                        @error('landingPlaystoreUrl') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="landingWindowsEnabled" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">🪟 {{ __('Show Windows App download button') }}</span>
                        </label>
                        <input type="url" wire:model="landingWindowsUrl" placeholder="https://cdn.example.com/YourApp-Setup.exe"
                               class="w-full px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                        @error('landingWindowsUrl') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">{{ __('Buttons appear in the Hero and the Downloads section. A button is hidden when its toggle is off or its URL is blank.') }}</p>
            </div>

            <!-- Editable Section Titles & Subtitles -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('Section Headings') }}</label>
                <p class="text-[11px] text-slate-400 -mt-2">{{ __('Leave blank to use the built-in default text.') }}</p>

                <div class="p-3 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 space-y-2">
                    <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Section display order') }}</label>
                    <input type="text" wire:model="landingSectionOrder" placeholder="hero,trust_bar,features,..."
                           class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono">
                    <p class="text-[11px] text-slate-500">{{ __('Use section slugs separated by commas. Any omitted section is appended automatically.') }}</p>
                </div>
                @foreach ([
                    'hero' => __('Hero'), 'trust_bar' => __('Trust Bar'), 'features' => __('Features'),
                    'solutions' => __('Solutions'), 'downloads' => __('Downloads'), 'stats' => __('Stats'),
                    'about' => __('About'), 'testimonials' => __('Testimonials'), 'pricing' => __('Pricing'),
                    'faq' => __('FAQ'), 'contact' => __('Contact'), 'cta' => __('Call to Action'),
                ] as $key => $label)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                        <div class="md:col-span-2 text-xs font-black text-slate-600 dark:text-slate-300">{{ $label }}</div>
                        <input type="text" wire:model="sectionMeta.{{ $key }}.title" placeholder="{{ __('Title') }}"
                               class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                        <input type="text" wire:model="sectionMeta.{{ $key }}.subtitle" placeholder="{{ __('Subtitle') }}"
                               class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs">
                        <input type="text" wire:model="sectionMeta.{{ $key }}.body" placeholder="{{ __('Body / supporting copy (optional)') }}"
                               class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs md:col-span-2">
                        <div class="flex items-center gap-2"><label class="text-[11px] text-slate-500">{{ __('Background') }}</label><input type="color" wire:model="sectionMeta.{{ $key }}.background" class="h-8 w-12 rounded cursor-pointer"></div>
                        <div class="flex items-center gap-2"><label class="text-[11px] text-slate-500">{{ __('Accent') }}</label><input type="color" wire:model="sectionMeta.{{ $key }}.accent" class="h-8 w-12 rounded cursor-pointer"></div>
                    </div>
                @endforeach
            </div>

            <!-- FAQ Entries -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('FAQ Entries') }}</label>
                    <button type="button" wire:click="addFaq"
                            class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-300 text-[11px] font-black hover:bg-indigo-100 transition">
                        + {{ __('Add Question') }}
                    </button>
                </div>
                @forelse ($landingFaqs as $i => $faq)
                    <div wire:key="faq-{{ $i }}" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                        <div class="flex items-center gap-2">
                            <input type="text" wire:model="landingFaqs.{{ $i }}.q" placeholder="{{ __('Question') }}"
                                   class="flex-1 px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">
                            <button type="button" wire:click="removeFaq({{ $i }})" class="px-2.5 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 text-xs font-black hover:bg-rose-100 transition">✕</button>
                        </div>
                        <textarea wire:model="landingFaqs.{{ $i }}.a" rows="2" placeholder="{{ __('Answer') }}"
                                  class="w-full px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs"></textarea>
                    </div>
                @empty
                    <p class="text-[11px] text-slate-400">{{ __('No custom FAQ entries — a default set is shown on the landing page. Add entries here to override it.') }}</p>
                @endforelse
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-5">
                <x-superadmin.landing-repeater title="Hero highlights" type="highlight" :items="$landingHeroHighlights" :fields="['Label']" />
                <x-superadmin.landing-repeater title="Hero dashboard products" type="product" :items="$landingHeroProducts" :fields="['Product','Price','Status','Tone']" />
                <x-superadmin.landing-repeater title="Hardware trust cards" type="hardware" :items="$landingHardwareItems" :fields="['Label','Tag']" />
                <x-superadmin.landing-repeater title="Stats & metrics" type="stat" :items="$landingStats" :fields="['Value','Label']" />
                <x-superadmin.landing-repeater title="Solution cards" type="solution" :items="$landingSolutions" :fields="['Icon','Title','Description']" />
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('All landing-page copy overrides (JSON)') }}</label>
                <textarea wire:model="landingContentJson" rows="12" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" placeholder='{"hero.badge":"Your text","nav.platform":"Platform"}'></textarea>
                <p class="text-[11px] text-slate-500">{{ __('Use dot-separated keys. Any text not listed keeps its built-in translation. This editor covers navigation, labels, headings, buttons, cards and footer copy.') }}</p>
                @error('landingContentJson') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('Complete landing page HTML override (optional)') }}</label>
                <textarea wire:model="landingCustomHtml" rows="10" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" placeholder="<section>...</section>"></textarea>
                <p class="text-[11px] text-slate-500">{{ __('When provided, this replaces the built-in landing sections. HTML is sanitized before publishing, so every word and layout element can be edited from this panel.') }}</p>
            </div>

            <!-- Landing Page Section Toggles -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('Enabled Landing Page Sections') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionHero" class="rounded text-indigo-600"> <span>{{ __('Hero') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionTrustBar" class="rounded text-indigo-600"> <span>{{ __('Trust Bar & Logos') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionFeatures" class="rounded text-indigo-600"> <span>{{ __('Key Features') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionSolutions" class="rounded text-indigo-600"> <span>{{ __('Solutions by Business') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionDownloads" class="rounded text-indigo-600"> <span>{{ __('App Downloads') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionStats" class="rounded text-indigo-600"> <span>{{ __('Stats & Metrics') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionAbout" class="rounded text-indigo-600"> <span>{{ __('About Platform') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionTestimonials" class="rounded text-indigo-600"> <span>{{ __('Customer Reviews') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionPricing" class="rounded text-indigo-600"> <span>{{ __('SaaS Pricing Plans') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionFaq" class="rounded text-indigo-600"> <span>{{ __('FAQ') }}</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" wire:model="sectionContact" class="rounded text-indigo-600"> <span>{{ __('Contact Support') }}</span></label>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer active:scale-95">
                    <span wire:loading.remove wire:target="saveBranding">💾 {{ __('Save White-label Branding') }}</span>
                    <span wire:loading wire:target="saveBranding">⏳ {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
