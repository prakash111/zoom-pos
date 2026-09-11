<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
    
    <!-- Left Column: Modern Registration Form Card -->
    <div class="lg:col-span-7 w-full">
        <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/90 backdrop-blur-xl border border-slate-300 dark:border-white/15 p-6 sm:p-8 shadow-2xl space-y-5">
            
            @if ($step === 2)
                <!-- Step 2: OTP Verification Card Header -->
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-white/10 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Verify Your Email') }}</h2>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-lime/20 text-brand-lime text-[10px] font-black border border-brand-lime/30">
                                🔒 {{ __('Security Check') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('We have sent a 6-digit verification code to') }} <span class="font-bold text-brand-lime">{{ $email }}</span>
                        </p>
                    </div>
                    
                    <button type="button" wire:click="backToForm" class="text-xs font-black text-slate-500 dark:text-slate-400 hover:text-brand-lime transition shrink-0 text-right">
                        <span>← {{ __('Edit Details') }}</span>
                    </button>
                </div>

                <!-- Status Banner -->
                @if ($otpStatusMessage)
                    <div class="p-3.5 rounded-2xl bg-emerald-500/10 text-emerald-300 text-xs font-semibold flex items-center gap-2.5 border border-emerald-500/30">
                        <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $otpStatusMessage }}</span>
                    </div>
                @endif

                <!-- Error Banner -->
                @if ($errorMessage)
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <!-- OTP Input Form -->
                <form wire:submit.prevent="verifyOtp" class="space-y-5 pt-2">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1 text-center">{{ __('Enter 6-Digit Code *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition max-w-xs mx-auto">
                            <input type="text"
                                   wire:model="otp"
                                   maxlength="6"
                                   autofocus
                                   placeholder="••••••"
                                   class="w-full border-none bg-transparent text-center text-xl sm:text-2xl font-black tracking-[0.5em] text-brand-lime placeholder-slate-600 focus:ring-0 py-3.5 px-4 font-mono">
                        </div>
                        @error('otp') <p class="text-rose-400 text-[11px] mt-0.5 text-center">{{ $message }}</p> @enderror
                    </div>

                    <div class="text-center">
                        <button type="button"
                                wire:click="resendOtp"
                                wire:loading.attr="disabled"
                                class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-brand-lime transition inline-flex items-center gap-1 cursor-pointer">
                            <span>🔄 {{ __('Didn\'t receive code? Resend Code') }}</span>
                        </button>
                    </div>

                    <div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="w-full py-4 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-brand-lime/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                            <span wire:loading.remove>{{ __('Verify & Launch Store →') }}</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('Verifying Code & Provisioning...') }}</span>
                            </span>
                        </button>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-white/10 text-center">
                        <button type="button" wire:click="backToForm" class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition inline-flex items-center gap-1 cursor-pointer">
                            <span>← {{ __('Change Registration Information') }}</span>
                        </button>
                    </div>
                </form>

            @else
                <!-- Step 1: Card Header with Reciprocal Switching Link -->
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-white/10 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Create Your Store') }}</h2>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-lime/20 text-brand-lime text-[10px] font-black border border-brand-lime/30">
                                {{ __('14-Day Free Trial') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Deploy your POS & smart inventory environment in under 60 seconds') }}</p>
                    </div>
                    
                    <a href="{{ route('tenant.login') }}" class="text-xs font-black text-brand-lime hover:underline shrink-0 text-right">
                        <span>{{ __('Log In') }}</span>
                        <span>→</span>
                    </a>
                </div>

                <!-- Error Banner -->
                @if ($errorMessage)
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                        <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold space-y-1 border border-rose-500/30">
                        <div class="font-extrabold flex items-center gap-1.5 text-rose-300">
                            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ __('Please correct the highlighted fields:') }}</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-rose-200">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit.prevent="register" class="space-y-4">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Store Name Input -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Store / Business Name *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                            <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                                <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                    🏬
                                </div>
                            </div>
                            <input type="text"
                                   wire:model="storeName"
                                   placeholder="e.g. Zenith Coffee Bar"
                                   class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2.5 pr-3">
                        </div>
                        @error('storeName') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <!-- Owner Name Input -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Admin / Owner Name *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                            <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                                <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                    👤
                                </div>
                            </div>
                            <input type="text"
                                   wire:model="ownerName"
                                   placeholder="e.g. Alex Morgan"
                                   class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2.5 pr-3">
                        </div>
                        @error('ownerName') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Email Input -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Work Email Address *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                            <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                                <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                    ✉️
                                </div>
                            </div>
                            <input type="email"
                                   wire:model="email"
                                   placeholder="alex@zenithcoffee.com"
                                   class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2.5 pr-3">
                        </div>
                        @error('email') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <!-- Password Input with Show/Hide Toggle -->
                    <div class="space-y-1" x-data="{ showPass: false }">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Create Password *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                            <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                                <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                    🔒
                                </div>
                            </div>
                            <input :type="showPass ? 'text' : 'password'"
                                   wire:model="password"
                                   placeholder="{{ __('Min. 6 characters') }}"
                                   class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2.5 pr-10">
                            <button type="button"
                                    x-on:click="showPass = !showPass"
                                    class="absolute right-3 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs cursor-pointer">
                                <span x-show="!showPass">👁️</span>
                                <span x-show="showPass">🙈</span>
                            </button>
                        </div>
                        @error('password') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Operating Mode Selector Pills -->
                @php
                    $activeMods = $this->activeRegistrationModules;
                    $normalizedPosMode = $posMode === 'general' ? 'retail' : $posMode;
                @endphp
                <div class="space-y-2 pt-1">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300">
                        <span>{{ __('Select Operating Mode:') }}</span>
                        <span class="text-[11px] text-brand-lime uppercase font-black">
                            {{ $activeMods[$normalizedPosMode]['title'] ?? $posMode }}
                        </span>
                    </div>

                    @if (count($activeMods) > 1)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach ($activeMods as $mKey => $m)
                                @php
                                    $isSelected = ($normalizedPosMode === $mKey);
                                    $modeVal = ($mKey === 'retail') ? 'general' : $mKey;
                                @endphp
                                <button type="button"
                                        wire:click="$set('posMode', '{{ $modeVal }}')"
                                        @class([
                                            'py-2.5 px-3 rounded-2xl border text-xs font-bold transition flex items-center justify-start gap-2.5 text-left cursor-pointer',
                                            'border-brand-lime bg-brand-lime/20 text-slate-900 dark:text-white shadow-lg shadow-brand-lime/10 ring-1 ring-brand-lime/40' => $isSelected,
                                            'border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white' => ! $isSelected,
                                        ])>
                                    <span class="text-base shrink-0">
                                        @if ($mKey === 'restaurant') 🍽️ @elseif ($mKey === 'pharmacy') 💊 @elseif ($mKey === 'service_booking') ✂️ @else 🏪 @endif
                                    </span>
                                    <div class="overflow-hidden">
                                        <span class="block truncate text-xs font-bold">{{ __($m['title']) }}</span>
                                        <span class="block truncate text-[10px] opacity-75 font-normal">{{ __($m['description']) }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @elseif (count($activeMods) === 1)
                        @php $single = reset($activeMods); @endphp
                        <div class="p-3 rounded-2xl border border-brand-lime/40 bg-brand-lime/10 text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>
                                @if ($single['id'] === 'restaurant') 🍽️ @elseif ($single['id'] === 'pharmacy') 💊 @elseif ($single['id'] === 'service_booking') ✂️ @else 🏪 @endif
                            </span>
                            <span>{{ __($single['title']) }} ({{ __('Pre-selected by Platform') }})</span>
                        </div>
                    @endif
                </div>
                @error('posMode') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror

                <!-- Domain & Subdomain Setup (Optional Accordion) -->
                @php
                    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'yourdomain.com';
                @endphp
                <div class="pt-1 space-y-2">
                    <button type="button"
                            wire:click="toggleDomainSettings"
                            class="w-full py-2.5 px-4 rounded-2xl bg-slate-50 dark:bg-white/5 hover:bg-slate-100 dark:hover:bg-white/10 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 text-xs font-bold flex items-center justify-between transition cursor-pointer">
                        <span class="flex items-center gap-2">
                            <span>🌐</span>
                            <span>{{ __('Subdomain & Custom Domain Setup') }}</span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">({{ __('Optional') }})</span>
                        </span>
                        <span class="text-xs text-brand-lime font-mono">{{ $showDomainSettings ? __('▲ Hide') : __('▼ Configure') }}</span>
                    </button>

                    @if ($showDomainSettings)
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 space-y-3">
                            <!-- Subdomain / Slug Field -->
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300">
                                    {{ __('Store Subdomain') }}
                                </label>
                                <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-950 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                                    <div class="pl-3 pr-1 text-slate-500 dark:text-slate-400 text-xs font-bold">🔗</div>
                                    <input type="text"
                                           wire:model.live.debounce.300ms="slug"
                                           placeholder="my-store"
                                           class="w-full border-none bg-transparent text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2 pr-1 font-mono">
                                    <div class="pr-3 pl-1 text-[11px] font-mono text-slate-500 dark:text-slate-400 shrink-0">.{{ $appHost }}</div>
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ __('Portal URL:') }} <span class="font-mono text-brand-lime">https://{{ $slug ?: 'your-store' }}.{{ $appHost }}</span>
                                </p>
                                @error('slug') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                            </div>

                            <!-- Custom Domain Field -->
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300">
                                    {{ __('Custom Domain (e.g. pos.mystore.com)') }}
                                </label>
                                <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-950 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                                    <div class="pl-3 pr-1 text-slate-500 dark:text-slate-400 text-xs font-bold">🌍</div>
                                    <input type="text"
                                           wire:model="customDomain"
                                           placeholder="pos.yourcompany.com"
                                           class="w-full border-none bg-transparent text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-2 pr-3 font-mono">
                                </div>
                                @error('customDomain') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Activation License Code (Optional) -->
                @if ($hasActivationCode)
                    <div class="space-y-1 pt-1">
                        <label class="block text-xs font-bold text-brand-lime mb-1">{{ __('Enter Activation / License Key *') }}</label>
                        <div class="relative flex items-center rounded-2xl border border-brand-lime/40 bg-brand-lime/10 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                            <div class="pl-3.5 pr-2 text-brand-lime">
                                <div class="w-7 h-7 rounded-xl bg-brand-lime/20 flex items-center justify-center text-xs font-bold">
                                    🔑
                                </div>
                            </div>
                            <input type="text"
                                   wire:model="activationCode"
                                   placeholder="AGY-XXXX-XXXX-XXXX"
                                   class="w-full uppercase tracking-wider font-mono border-none bg-transparent text-xs sm:text-sm font-black text-slate-900 dark:text-white placeholder-brand-lime/50 focus:ring-0 py-2.5 pr-3">
                        </div>
                        @error('activationCode') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>
                @endif

                <!-- Plan Toggle / License Switcher -->
                <div class="flex items-center justify-between pt-1">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        {{ $hasActivationCode ? __('License Key Activation') : __('14-Day Free Evaluation (No Card Required)') }}
                    </span>
                    
                    <button type="button"
                            wire:click="toggleActivationCode"
                            class="text-xs font-bold text-brand-lime hover:underline cursor-pointer">
                        {{ $hasActivationCode ? __('Switch to Free Trial') : __('I have a License Key') }}
                    </button>
                </div>

                @php($socialProviders = collect(\App\Http\Controllers\Auth\SocialAuthController::enabledProviders()))
                @if ($socialProviders->isNotEmpty())
                    <div class="grid grid-cols-{{ $socialProviders->count() }} gap-2">
                        @foreach ($socialProviders as $key => $label)<a href="{{ route('social.redirect', ['provider' => $key, 'intent' => 'register']) }}" class="py-2.5 rounded-xl bg-white text-slate-800 text-center text-xs font-bold">{{ __('Continue with :provider', ['provider' => $label]) }}</a>@endforeach
                    </div>
                @endif

                <!-- Primary Action Button: Launch Store -->
                <div class="pt-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-4 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-brand-lime/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                        <span wire:loading.remove>{{ __('Create Store & Launch POS →') }}</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __('Provisioning your store environment...') }}</span>
                        </span>
                    </button>
                </div>

            </form>

            <!-- Reciprocal Login Switcher -->
            <div class="pt-4 border-t border-slate-200 dark:border-white/10 text-center">
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Already have an active store account?') }}</span>
                <a href="{{ route('tenant.login') }}" class="font-extrabold text-brand-lime hover:underline ml-1.5 text-xs">
                    {{ __('Log In to your Store →') }}
                </a>
            </div>

            @endif

        </div>
    </div>

    <!-- Right Column: Instant Provisioning & Feature Highlights -->
    <div class="lg:col-span-5 space-y-5">
        
        <!-- Guarantee Card -->
        <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/60 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-6 sm:p-7 space-y-5 shadow-2xl">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-brand-lime/20 border border-brand-lime/30 flex items-center justify-center text-xl">
                    🚀
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('Instant Cloud Provisioning') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Ready in <60s with full hardware support') }}</p>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex items-start gap-2.5 text-slate-600 dark:text-slate-300">
                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">✓</span>
                    <span><strong>{{ __('Multi-Warehouse & SKU Barcodes:') }}</strong> {{ __('Real-time inventory sync and low-stock alerts.') }}</span>
                </div>

                <div class="flex items-start gap-2.5 text-slate-600 dark:text-slate-300">
                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">✓</span>
                    <span><strong>{{ __('High-Speed POS & Split Tenders:') }}</strong> {{ __('Cash, card, and digital payment registers.') }}</span>
                </div>

                <div class="flex items-start gap-2.5 text-slate-600 dark:text-slate-300">
                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">✓</span>
                    <span><strong>{{ __('Restaurant Dining & KOT:') }}</strong> {{ __('Table layout management and kitchen screen tickets.') }}</span>
                </div>

                <div class="flex items-start gap-2.5 text-slate-600 dark:text-slate-300">
                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5">✓</span>
                    <span><strong>{{ __('Tax Invoices & WhatsApp PDF:') }}</strong> {{ __('80mm/58mm thermal printing and instant dispatch.') }}</span>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300">
                <span>💳 {{ __('Credit Card Required') }}</span>
                <span class="font-bold text-brand-lime">{{ __('None · 100% Free Trial') }}</span>
            </div>
        </div>

        <!-- Hardware Trust Badge Card -->
        <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/40 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-5 space-y-2.5 text-xs">
            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🔌 {{ __('Plug & Play Hardware Integration') }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ __('Compatible out-of-the-box with Epson/Star thermal receipt printers, Honeywell/Zebra barcode laser scanners, and USB/RJ11 cash drawers.') }}
            </p>
        </div>

    </div>

</div>
