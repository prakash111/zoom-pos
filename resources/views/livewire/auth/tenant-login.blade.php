@php
    $showAuthBanner = (bool) \App\Models\DynamicSetting::get('show_auth_banner', false);
    $authBannerUrl = \App\Models\DynamicSetting::get('auth_banner_image_url');
    $hasBanner = $showAuthBanner && !empty($authBannerUrl);
@endphp

<div class="{{ $hasBanner ? 'grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center' : 'max-w-xl mx-auto w-full' }}">
    
    <!-- Left Column / Modern Login Card -->
    <div class="{{ $hasBanner ? 'lg:col-span-6' : '' }} w-full">
        <div class="rounded-3xl bg-white dark:bg-slate-900/90 backdrop-blur-xl border border-slate-200 dark:border-white/15 p-6 sm:p-8 shadow-2xl space-y-6">

            <!-- Card Header: branding + Live POS (left), Register Store (top-right) -->
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Store Sign In') }}</h2>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ __('Live POS') }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">{{ __('Sign in with your staff or administrator credentials') }}</p>
                </div>

                <a href="{{ route('tenant.register') }}"
                   class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 text-xs font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('Register Store') }} <span aria-hidden="true">→</span>
                </a>
            </div>

            <!-- Error Banner -->
            @if ($error)
                <div class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (config('app.demo_mode') && ! \App\Support\Desktop::isRunning())
                <!-- Consolidated demo module switcher (high-contrast) -->
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z" />
                            </svg>
                            <div class="min-w-0">
                                <div class="text-[11px] font-black uppercase tracking-wider text-amber-900 dark:text-amber-300">{{ __('Demo Mode Active') }}</div>
                                <p class="text-xs text-amber-800 dark:text-amber-200/90 mt-0.5">{{ __('Pick a store to jump straight in — settings, uploads and credentials are view-only.') }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-900 dark:text-amber-200 text-[10px] font-mono font-bold">{{ __('1-Click') }}</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach (['retail' => 'Retail', 'cafe' => 'Cafe & Restaurant', 'pharmacy' => 'Pharmacy', 'repair' => 'Repair', 'salon' => 'Salon & Bookings'] as $slug => $label)
                            <a href="{{ url('/demo-login/'.$slug) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-amber-500/30 bg-white/70 dark:bg-white/10 text-amber-900 dark:text-amber-100 text-xs font-bold hover:bg-amber-500 hover:text-white hover:border-amber-500 transition">
                                {{ __($label) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Form Inputs -->
            <div class="space-y-4">

                <!-- Email / Login Input -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">{{ __('Email or Username *') }}</label>
                    <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                        <svg class="w-4 h-4 ml-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <input type="text"
                                wire:model="identifier"
                                wire:keydown.enter="login"
                                placeholder="you@yourstore.com"
                                class="w-full border-none bg-transparent text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-0 py-3 px-3">
                    </div>
                    @error('identifier') <p class="text-rose-500 text-[11px]">{{ $message }}</p> @enderror
                </div>

                <!-- Password Input -->
                <div class="space-y-1.5" x-data="{ showPass: false }">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">{{ __('Password *') }}</label>
                    <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                        <svg class="w-4 h-4 ml-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        <input :type="showPass ? 'text' : 'password'"
                                wire:model="password"
                                wire:keydown.enter="login"
                                placeholder="••••••••••••"
                                class="w-full border-none bg-transparent text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-0 py-3 pl-3 pr-10">
                        <button type="button" x-on:click="showPass = !showPass" class="absolute right-3 text-slate-400 hover:text-slate-700 dark:hover:text-white text-xs cursor-pointer">
                            <span x-show="!showPass">👁️</span>
                            <span x-show="showPass">🙈</span>
                        </button>
                    </div>
                    @error('password') <p class="text-rose-500 text-[11px]">{{ $message }}</p> @enderror
                    <div class="text-right">
                        <a href="{{ route('tenant.password.request') }}" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Forgot password?') }}</a>
                    </div>
                </div>

            </div>

            @php($socialProviders = collect(\App\Http\Controllers\Auth\SocialAuthController::enabledProviders()))
            @if ($socialProviders->isNotEmpty())
                <div class="grid grid-cols-{{ $socialProviders->count() }} gap-2">
                    @foreach ($socialProviders as $key => $label)<a href="{{ route('social.redirect', $key) }}" class="py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white text-slate-800 text-center text-xs font-bold hover:bg-slate-50">{{ __('Continue with :provider', ['provider' => $label]) }}</a>@endforeach
                </div>
                <div class="text-center text-[10px] text-slate-400 uppercase tracking-wide">{{ __('or use your password') }}</div>
            @endif

            <!-- Keep me signed in — balanced spacing above the submit button -->
            <div class="flex items-center justify-between py-1" x-data="{ remember: true }">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Keep me signed in on this device') }}</span>
                <button type="button"
                        x-on:click="remember = !remember"
                        :class="remember ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                        role="switch" :aria-checked="remember.toString()">
                    <span :class="remember ? 'translate-x-5' : 'translate-x-0.5'"
                          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>

            <!-- Primary Action Button: high-contrast brand primary (indigo #2563EB) -->
            <button type="button"
                    wire:click="login"
                    wire:loading.attr="disabled"
                    class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm tracking-wide shadow-lg shadow-indigo-600/25 active:scale-[0.98] transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                <span wire:loading.remove>{{ __('Sign in to your store') }}</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Authenticating...') }}</span>
                </span>
            </button>

            <!-- Reciprocal Registration Box & SuperAdmin Switcher -->
            <div class="pt-4 border-t border-slate-200 dark:border-white/10 flex flex-col items-center justify-center gap-3 text-center">
                <div class="text-xs text-slate-600 dark:text-slate-300">
                    {{ __("Don't have an account?") }}
                    <a href="{{ route('tenant.register') }}" class="font-extrabold text-indigo-600 dark:text-indigo-400 hover:underline ml-1">
                        {{ __('Register your Store now →') }}
                    </a>
                </div>

                <a href="{{ route('superadmin.login') }}" class="text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1.5">
                    <span>🛡️ {{ __('Platform Administrator Portal') }}</span>
                    <span>&rarr;</span>
                </a>
            </div>

        </div>
    </div>

    @if ($hasBanner)
        <!-- Right Column: Dynamic Auth Graphic / Banner -->
        <div class="lg:col-span-6 w-full flex items-center justify-center">
            <div class="w-full overflow-hidden rounded-3xl shadow-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/60 p-2 sm:p-3">
                <img src="{{ $authBannerUrl }}" alt="{{ __('Store Sign In Banner') }}" class="w-full h-auto object-cover rounded-2xl shadow-inner">
            </div>
        </div>
    @endif

</div>
