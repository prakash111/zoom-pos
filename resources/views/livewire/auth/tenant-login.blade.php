@php
    $showAuthBanner = (bool) \App\Models\DynamicSetting::get('show_auth_banner', true);
    $hasBanner = $showAuthBanner;

    $guestTenantCompany = app()->bound('tenant.company_id')
        ? \App\Models\Company::withoutGlobalScopes()->find(app('tenant.company_id'))
        : null;
    $branding = \App\Models\PlatformBranding::current();
    $dynamicLogo = \App\Models\DynamicSetting::get('platform_logo_url');
    $dynamicName = \App\Models\DynamicSetting::get('platform_brand_name');
    $brandName = $guestTenantCompany?->trade_name ?: ($guestTenantCompany?->name ?: ($dynamicName ?: ($branding?->platform_name ?? 'ZoomNearby')));
    $logoUrl = $guestTenantCompany?->getLogoUrl() ?: ($dynamicLogo ?: $branding?->getLogoPublicUrl());
@endphp

<div class="auth-screen auth-screen--login {{ !$hasBanner ? 'auth-screen--solo' : '' }}">

    <!-- Left Column: Form Section (Matching Reference Design 1 in /read) -->
    <div class="auth-form-column">

        <div class="auth-login-brand">
            @include('auth.partials.brand', ['brandName' => $brandName, 'logoUrl' => $logoUrl])
        </div>

        <!-- Section Title & Subtitle -->
        <div class="auth-intro mb-6">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="auth-title text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Welcome Back!') }}</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/80 dark:border-blue-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                    {{ __('Store Sign In') }}
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">
                {{ __('Sign in to your store account to manage sales, inventory and grow your business.') }}
            </p>
        </div>

        <!-- Floating Form Card -->
        <div class="auth-card bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none space-y-4">

            <!-- Error Alert -->
            @if ($error)
                <div class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (config('app.demo_mode') && ! \App\Support\Desktop::isRunning())
                <!-- Demo Mode 1-Click active pill -->
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="text-[11px] font-black uppercase tracking-wider text-amber-900 dark:text-amber-300 flex items-center gap-1.5">
                            <span>⚡</span> {{ __('Demo Mode Active') }}
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-900 dark:text-amber-200 text-[10px] font-mono font-bold">{{ __('1-Click') }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['allmodules' => '⚡ All Modules / Enterprise', 'retail' => 'Retail', 'cafe' => 'Cafe & Restaurant', 'pharmacy' => 'Pharmacy', 'repair' => 'Repair', 'salon' => 'Salon'] as $slug => $label)
                            <a href="{{ url('/demo-login/'.$slug) }}"
                               class="px-2.5 py-1 rounded-lg border border-amber-500/30 bg-white/70 dark:bg-white/10 text-amber-900 dark:text-amber-100 text-xs font-bold hover:bg-amber-500 hover:text-white hover:border-amber-500 transition">
                                {{ __($label) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Small single button: Try Flutter Web Version Tenant Demo -->
            <div class="text-center">
                <a href="https://web.zoomnearby.com"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 transition shadow-2xs">
                    <span>🚀</span>
                    <span>{{ __('Try Flutter Web Version Tenant Demo') }} &rarr;</span>
                </a>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="login" class="space-y-4">

                <!-- Email or Username -->
                <div>
                    <label for="login_identifier" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Email or Username') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <input type="text"
                               id="login_identifier"
                               name="login"
                               autocomplete="username"
                               wire:model="identifier"
                               required
                               placeholder="demo@zoomnearby.com"
                               class="w-full h-12 pl-11 pr-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">
                    </div>
                    @error('identifier') <p class="text-rose-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Password -->
                <div x-data="{ showPass: false }">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="login_password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {{ __('Password') }} <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('tenant.password.request') }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                            {{ __('Forgot password?') }}
                        </a>
                    </div>
                    <div class="relative flex items-center">
                        <div class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <input id="login_password"
                               :type="showPass ? 'text' : 'password'"
                               type="password"
                               name="password"
                               autocomplete="current-password"
                               wire:model="password"
                               required
                               placeholder="••••••••••••"
                               class="w-full h-12 pl-11 pr-11 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm tracking-wide focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all shadow-2xs">

                        <!-- Strictly Centered Trailing Eye Button -->
                        <button type="button"
                                x-on:click="showPass = !showPass"
                                class="absolute right-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none p-1 cursor-pointer flex items-center justify-center"
                                :aria-label="showPass ? @js(__('Hide password')) : @js(__('Show password'))"
                                :aria-pressed="showPass.toString()">
                            <svg id="eye_icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="showPass" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                <g x-show="!showPass">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </g>
                            </svg>
                        </button>
                    </div>
                    @error('password') <p class="text-rose-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Keep Me Signed In -->
                <div class="flex items-center gap-2 pt-1" x-data="{ remember: true }">
                    <input type="checkbox" id="remember_me" name="remember" value="1" checked x-model="remember"
                           class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                    <label for="remember_me" class="text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                        {{ __('Keep me signed in on this device') }}
                    </label>
                </div>

                <!-- Primary Action Button: Gradient Blue / Indigo -->
                <button type="submit"
                        wire:loading.attr="disabled" wire:target="login"
                        class="auth-primary w-full h-12 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-60 active:scale-[0.99]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    <span wire:loading.remove wire:target="login">{{ __('Sign in to your store') }}</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>{{ __('Authenticating...') }}</span>
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
                            <a href="{{ route('social.redirect', $key) }}" class="h-11 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200 text-center text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-center gap-2 transition shadow-2xs">
                                <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <span>{{ __('Sign in with :provider', ['provider' => $label]) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <!-- Switch to Register Link -->
                <div class="text-center text-xs text-slate-500 dark:text-slate-400 pt-1">
                    {{ __("Don't have an account?") }}
                    <a href="{{ route('tenant.register') }}" class="font-bold text-blue-600 dark:text-blue-400 hover:underline ml-1">
                        {{ __('Register your Store now') }} &rarr;
                    </a>
                </div>

            </form>

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
        @include('auth.partials.auth-marketing-banner', ['type' => 'login'])
    @endif

</div>
