@php
    $showAuthBanner = (bool) \App\Models\DynamicSetting::get('show_auth_banner', true);
    $hasBanner = $showAuthBanner;
    $enableDomainSetup = (bool) \App\Models\DynamicSetting::get('enable_registration_domain_setup', true);
@endphp

<div class="auth-screen auth-screen--register {{ !$hasBanner ? 'auth-screen--solo' : '' }}">

    <!-- Left Column: Registration Card (Matching Reference Design 2 in /read) -->
    <div class="auth-form-column">

        <div class="auth-card auth-register-card bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none space-y-5">

            @if ($step === 2)
                <!-- Step 2: OTP Verification Card Header -->
                <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Verify Your Email') }}</h2>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 text-[10px] font-bold border border-blue-200/80 dark:border-blue-800">
                                🔒 {{ __('Security Check') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('We have sent a 6-digit verification code to') }} <span class="font-bold text-blue-600 dark:text-blue-400">{{ $email }}</span>
                        </p>
                    </div>

                    <button type="button" wire:click="backToForm" class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition shrink-0 text-right">
                        <span>← {{ __('Edit Details') }}</span>
                    </button>
                </div>

                <!-- Status Banner -->
                @if ($otpStatusMessage)
                    <div class="p-3.5 rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 border border-emerald-500/30">
                        <svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $otpStatusMessage }}</span>
                    </div>
                @endif

                <!-- Error Banner -->
                @if ($errorMessage)
                    <div class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <!-- OTP Input Form -->
                <form wire:submit.prevent="verifyOtp" class="space-y-5 pt-2">
                    <div class="space-y-1">
                        <label for="register_otp" class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1 text-center">{{ __('Enter 6-Digit Code *') }}</label>
                        <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 overflow-hidden focus-within:ring-2 focus-within:ring-blue-600 transition max-w-xs mx-auto">
                            <input type="text"
                                   id="register_otp"
                                       name="otp"
                                       autocomplete="one-time-code"
                                       wire:model="otp"
                                   inputmode="numeric"
                                   pattern="[0-9]{6}"
                                   required
                                   maxlength="6"
                                   autofocus
                                   placeholder="••••••"
                                   class="w-full border-none bg-transparent text-center text-xl sm:text-2xl font-black tracking-[0.5em] text-blue-600 dark:text-blue-400 placeholder-slate-400 focus:ring-0 py-3 px-4 font-mono">
                        </div>
                        @error('otp') <p class="text-rose-500 text-[11px] mt-0.5 text-center">{{ $message }}</p> @enderror
                    </div>

                    <div class="text-center">
                        <button type="button"
                                wire:click="resendOtp"
                                wire:loading.attr="disabled"
                                class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition inline-flex items-center gap-1 cursor-pointer">
                            <span>🔄 {{ __('Didn\'t receive code? Resend Code') }}</span>
                        </button>
                    </div>

                    <div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="auth-primary w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-sm tracking-wide shadow-lg shadow-blue-600/30 active:scale-[0.99] transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                            <span wire:loading.remove>{{ __('Verify & Launch Store →') }}</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('Verifying Code & Provisioning...') }}</span>
                            </span>
                        </button>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
                        <button type="button" wire:click="backToForm" class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition inline-flex items-center gap-1 cursor-pointer">
                            <span>← {{ __('Change Registration Information') }}</span>
                        </button>
                    </div>
                </form>

            @else
                <!-- Step 1: Card Header with User Icon Badge & Trial Tag -->
                <div class="auth-register-heading flex items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-start gap-3">
                        <div class="auth-account-icon w-11 h-11 rounded-2xl bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 border border-blue-200/60 dark:border-blue-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Create Your Store') }}</h2>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Start your 14-day free trial and experience the power of ZoomNearby POS & Inventory Management.') }}
                            </p>
                        </div>
                    </div>

                    <div class="shrink-0 hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12v9H4v-9M2 7h20v5H2zM12 7v14M12 7H7.5A2.5 2.5 0 1 1 10 4.5L12 7Zm0 0h4.5A2.5 2.5 0 1 0 14 4.5L12 7Z"/></svg>
                        <span>{{ __('14-Day Free Trial') }}</span>
                    </div>
                </div>

                <!-- Error Banner -->
                @if ($errorMessage)
                    <div class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold space-y-1 border border-rose-500/30">
                        <div class="font-bold flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                            <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ __('Please correct the highlighted fields:') }}</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit.prevent="register" class="space-y-4">

                    <!-- Row 1: Owner Name & Business Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label for="full_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Admin / Owner Name') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <input type="text"
                                       id="full_name"
                                       name="ownerName"
                                       autocomplete="name"
                                       wire:model="ownerName"
                                       required
                                       placeholder="e.g. Alex Morgan"
                                       class="w-full h-11 pl-10 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                            </div>
                            @error('ownerName') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="business_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Store / Business Name') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <input type="text"
                                       id="business_name"
                                       name="storeName"
                                       autocomplete="organization"
                                       wire:model="storeName"
                                       required
                                       placeholder="e.g. Zenith Coffee Bar"
                                       class="w-full h-11 pl-10 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                            </div>
                            @error('storeName') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 2: Email & Phone -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label for="register_email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Work Email Address') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <input type="email"
                                       id="register_email"
                                       name="email"
                                       autocomplete="email"
                                       wire:model="email"
                                       required
                                       placeholder="you@yourstore.com"
                                       class="w-full h-11 pl-10 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                            </div>
                            @error('email') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register_phone" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Phone Number') }}
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </div>
                                <input type="tel"
                                       id="register_phone"
                                       name="phone"
                                       autocomplete="tel"
                                       wire:model="phone"
                                       placeholder="+91 98765 43210"
                                       class="w-full h-11 pl-10 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                            </div>
                            @error('phone') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 3: Password & Confirm Password -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div x-data="{ showPass: false }">
                            <label for="register_password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Create Password') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <input type="password" :type="showPass ? 'text' : 'password'"
                                       id="register_password"
                                       name="password"
                                       autocomplete="new-password"
                                       wire:model="password"
                                       required
                                       placeholder="{{ __('Create a strong password') }}"
                                       class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                                <button type="button"
                                        x-on:click="showPass = !showPass"
                                        :aria-label="showPass ? @js(__('Hide password')) : @js(__('Show password'))"
                                        :aria-pressed="showPass.toString()"
                                        class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 flex items-center justify-center focus:outline-none cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path x-show="showPass" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        <g x-show="!showPass">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </g>
                                    </svg>
                                </button>
                            </div>
                            @error('password') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <div x-data="{ showPassConfirm: false }">
                            <label for="register_password_confirmation" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                {{ __('Confirm Password') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <input type="password" :type="showPassConfirm ? 'text' : 'password'"
                                       id="register_password_confirmation"
                                       name="password_confirmation"
                                       autocomplete="new-password"
                                       wire:model="password_confirmation"
                                       placeholder="{{ __('Re-enter your password') }}"
                                       class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                                <button type="button"
                                        x-on:click="showPassConfirm = !showPassConfirm"
                                        :aria-label="showPassConfirm ? @js(__('Hide password')) : @js(__('Show password'))"
                                        :aria-pressed="showPassConfirm.toString()"
                                        class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 flex items-center justify-center focus:outline-none cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path x-show="showPassConfirm" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        <g x-show="!showPassConfirm">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </g>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Business Operating Mode Selection -->
                    @php
                        $activeMods = $this->activeRegistrationModules;
                        $normalizedPosMode = $posMode === 'general' ? 'retail' : $posMode;
                    @endphp
                    <div>
                        <label for="business_type" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ __('Business Type / Operating Mode') }} <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <select id="business_type"
                                       name="posMode"
                                       wire:model="posMode"
                                    class="w-full h-11 pl-10 pr-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs cursor-pointer">
                                @foreach ($activeMods as $mKey => $m)
                                    @php $modeVal = ($mKey === 'retail') ? 'general' : $mKey; @endphp
                                    <option value="{{ $modeVal }}" @selected($normalizedPosMode === $mKey)>
                                        {{ __($m['title']) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('posMode') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    @if ($enableDomainSetup)
                        <!-- Optional Subdomain Setup Accordion -->
                        @php $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'yourdomain.com'; @endphp
                        <div class="pt-1">
                            <button type="button"
                                    wire:click="toggleDomainSettings"
                                    aria-expanded="{{ $showDomainSettings ? 'true' : 'false' }}"
                                    class="w-full py-2.5 px-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold flex items-center justify-between transition cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <span>🌐</span>
                                    <span>{{ __('Subdomain & Custom Domain Setup') }}</span>
                                    <span class="text-[10px] text-slate-400 font-normal">({{ __('Optional') }})</span>
                                </span>
                                <span class="text-xs text-blue-600 dark:text-blue-400 font-mono">{{ $showDomainSettings ? __('▲ Hide') : __('▼ Configure') }}</span>
                            </button>

                            @if ($showDomainSettings)
                                <div class="mt-2 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 space-y-3">
                                    <div>
                                        <label for="store_subdomain" class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">
                                            {{ __('Store Subdomain') }}
                                        </label>
                                        <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden focus-within:ring-2 focus-within:ring-blue-600 transition">
                                            <span class="pl-3 pr-1 text-slate-400 text-xs">🔗</span>
                                            <input type="text"
                                                   id="store_subdomain"
                                       name="slug"
                                       wire:model.live.debounce.300ms="slug"
                                                   placeholder="my-store"
                                                   class="w-full border-none bg-transparent text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-0 py-2 pr-1 font-mono">
                                            <span class="pr-3 pl-1 text-[11px] font-mono text-slate-400 shrink-0">.{{ $appHost }}</span>
                                        </div>
                                        @error('slug') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Activation License Code (Optional) -->
                    @if ($hasActivationCode)
                        <div class="space-y-1 pt-1">
                            <label for="activation_code" class="block text-xs font-bold text-blue-600 dark:text-blue-400 mb-1">{{ __('Enter Activation / License Key *') }}</label>
                            <div class="relative flex items-center rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/30 overflow-hidden focus-within:ring-2 focus-within:ring-blue-600 transition">
                                <span class="pl-3.5 pr-2 text-blue-600 text-xs">🔑</span>
                                <input type="text"
                                       id="activation_code"
                                       name="activationCode"
                                       wire:model="activationCode"
                                       placeholder="AGY-XXXX-XXXX-XXXX"
                                       class="w-full uppercase tracking-wider font-mono border-none bg-transparent text-xs font-black text-slate-900 dark:text-white placeholder-blue-400 focus:ring-0 py-2.5 pr-3">
                            </div>
                            @error('activationCode') <p class="text-rose-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <!-- Plan Toggle / License Switcher -->
                    <div class="flex items-center justify-between text-xs pt-0.5">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">
                            {{ $hasActivationCode ? __('License Key Activation') : __('14-Day Free Evaluation (No Card Required)') }}
                        </span>
                        <button type="button"
                                wire:click="toggleActivationCode"
                                class="font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                            {{ $hasActivationCode ? __('Switch to Free Trial') : __('I have a License Key') }}
                        </button>
                    </div>

                    <!-- Terms & Conditions Checkbox -->
                    <div class="flex items-center gap-2 pt-1" x-data="{ agreeTerms: true }">
                        <input type="checkbox" id="agree_terms" required checked x-model="agreeTerms"
                               class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                        <label for="agree_terms" class="text-xs text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                            {{ __('I agree to the') }}
                            <a href="/terms" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">{{ __('Terms & Conditions') }}</a>
                            {{ __('and') }}
                            <a href="/privacy" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">{{ __('Privacy Policy') }}</a>
                        </label>
                    </div>

                    <!-- Primary Action Button: Launch Store -->
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="auth-primary w-full h-12 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-60 active:scale-[0.99]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span wire:loading.remove>{{ __('Create Store & Launch POS →') }}</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __('Provisioning your store environment...') }}</span>
                        </span>
                    </button>

                    <!-- OR Divider -->
                    <div class="relative flex py-1 items-center">
                        <div class="grow border-t border-slate-200 dark:border-slate-800"></div>
                        <span class="shrink mx-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">{{ __('OR') }}</span>
                        <div class="grow border-t border-slate-200 dark:border-slate-800"></div>
                    </div>

                    @php($socialProviders = collect(\App\Http\Controllers\Auth\SocialAuthController::enabledProviders()))
                    @if ($socialProviders->isNotEmpty())
                        <div class="auth-social grid gap-2">
                            @foreach ($socialProviders as $key => $label)
                                <a href="{{ route('social.redirect', ['provider' => $key, 'intent' => 'register']) }}" class="h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200 text-center text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-center gap-2 transition shadow-2xs">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                    <span>{{ __('Sign up with :provider', ['provider' => $label]) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <!-- Reciprocal Login Switcher -->
                    <div class="text-center text-xs text-slate-500 dark:text-slate-400 pt-1">
                        <span>{{ __('Already have an active store account?') }}</span>
                        <a href="{{ route('tenant.login') }}" class="font-bold text-blue-600 dark:text-blue-400 hover:underline ml-1">
                            {{ __('Log In to your Store') }} &rarr;
                        </a>
                    </div>

                </form>

            @endif

        </div>

        <!-- Sub-card Secure & Portal Switcher Footer -->
        <div class="auth-security flex items-center justify-center gap-3 text-xs text-slate-400 dark:text-slate-500 mt-5">
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                {{ __('Secure & Encrypted') }}
            </span>
            <span>&bull;</span>
            <a href="{{ route('superadmin.login') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition">
                {{ __('Platform Administrator Portal') }} &rarr;
            </a>
        </div>

    </div>

    @if ($hasBanner)
        <!-- Right Column: Marketing Showcase Banner matching reference design -->
        @include('auth.partials.auth-marketing-banner', ['type' => 'register'])
    @endif

</div>
