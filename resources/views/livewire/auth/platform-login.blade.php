@php
    $hasBanner = (bool) \App\Models\DynamicSetting::get('show_auth_banner', true);
    $branding = \App\Models\PlatformBranding::current();
    $brandName = \App\Models\DynamicSetting::get('platform_brand_name') ?: ($branding?->platform_name ?? config('app.name', 'ZoomNearby'));
    $logoUrl = \App\Models\DynamicSetting::get('platform_logo_url') ?: $branding?->getLogoPublicUrl();
@endphp

<div class="auth-screen auth-screen--login {{ !$hasBanner ? 'auth-screen--solo' : '' }}">
    <div class="auth-form-column">
        <div class="auth-login-brand">
            @include('auth.partials.brand', ['brandName' => $brandName, 'logoUrl' => $logoUrl])
        </div>

        <div class="auth-intro mb-6">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="auth-title text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Super Admin') }}</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/80 dark:border-blue-800">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6-4A12 12 0 0 1 12 3a12 12 0 0 1-8.6 3A12 12 0 0 0 12 21a12 12 0 0 0 8.6-15Z"/></svg>
                    {{ __('Root Control') }}
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">{{ __('Platform management, tenant provisioning & system branding') }}</p>
        </div>

        <div class="auth-card bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none space-y-4">
            @if ($error)
                <div role="alert" class="p-3.5 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (config('app.demo_mode'))
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-amber-900 dark:text-amber-300">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m13 2-10 12h7l-1 8 10-12h-7l1-8Z"/></svg>
                            {{ __('Demo Mode Active') }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-900 dark:text-amber-200 text-[10px] font-mono font-bold">{{ __('1-Click') }}</span>
                    </div>
                    <p class="text-xs text-amber-900 dark:text-amber-200">{{ __('Super Admin credentials pre-filled for instant testing') }}</p>
                    <button type="button" wire:click="fillDemo('superadmin')"
                            class="w-full p-2.5 rounded-xl bg-white/70 dark:bg-white/10 hover:bg-amber-100 dark:hover:bg-white/20 border border-amber-500/30 text-start transition flex items-center gap-2.5 cursor-pointer"
                            title="{{ __('Quick Demo Credentials (1-Click Fill)') }}">
                        <svg class="w-5 h-5 shrink-0 text-amber-700 dark:text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6-4A12 12 0 0 1 12 3a12 12 0 0 1-8.6 3A12 12 0 0 0 12 21a12 12 0 0 0 8.6-15Z"/></svg>
                        <span class="min-w-0">
                            <span class="block text-xs font-bold text-amber-900 dark:text-amber-100">{{ __('Super Administrator') }}</span>
                            <span class="block text-[10px] text-amber-800 dark:text-amber-200 break-all">superadmin@gmail.com &bull; password123</span>
                        </span>
                    </button>
                </div>
            @endif

            <form wire:submit.prevent="login" class="space-y-4">
                <div>
                    <label for="platform_email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Admin Email Address') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 0 0 2.2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                        </span>
                        <input id="platform_email" type="email" name="email" autocomplete="username" wire:model="email" required
                               placeholder="admin@yourdomain.com"
                               class="w-full h-12 pl-11 pr-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all">
                    </div>
                    @error('email') <p class="text-rose-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ showPass: false }">
                    <label for="platform_password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('Admin Password') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-slate-400 pointer-events-none flex items-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4h8Z"/></svg>
                        </span>
                        <input id="platform_password" type="password" :type="showPass ? 'text' : 'password'"
                               name="password" autocomplete="current-password" wire:model="password" required
                               placeholder="{{ __('Enter your password') }}"
                               class="w-full h-12 pl-11 pr-11 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all">
                        <button type="button" x-on:click="showPass = !showPass"
                                :aria-label="showPass ? @js(__('Hide password')) : @js(__('Show password'))"
                                :aria-pressed="showPass.toString()"
                                class="absolute right-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 cursor-pointer flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path x-show="showPass" stroke-linecap="round" stroke-linejoin="round" d="M13.9 18.8A10 10 0 0 1 12 19c-4.5 0-8.3-2.9-9.5-7A10 10 0 0 1 4 9m5.9.9a3 3 0 1 1 4.2 4.2M9.9 9.9l4.2 4.2M3 3l18 18"/>
                                <g x-show="!showPass">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C3.7 7.9 7.5 5 12 5s8.3 2.9 9.5 7C20.3 16.1 16.5 19 12 19s-8.3-2.9-9.5-7Z"/>
                                </g>
                            </svg>
                        </button>
                    </div>
                    @error('password') <p class="text-rose-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2 pt-1" x-data="{ remember: true }">
                    <input type="checkbox" id="platform_remember" checked x-model="remember"
                           class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 cursor-pointer">
                    <label for="platform_remember" class="text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer select-none">{{ __('Keep admin session active') }}</label>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="login"
                        class="auth-primary w-full h-12 rounded-xl text-sm font-bold text-white flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-60 transition-all active:scale-[0.99]">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11 16-4-4 4-4M7 12h14m-5 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1"/></svg>
                    <span wire:loading.remove wire:target="login">{{ __('Sign In to Control Panel') }}</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0A12 12 0 0 0 0 12h4Z"/></svg>
                        <span>{{ __('Authenticating...') }}</span>
                    </span>
                </button>
            </form>
        </div>

        <div class="auth-security flex items-center justify-center gap-3 text-xs text-slate-400 dark:text-slate-500 mt-5">
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6-4A12 12 0 0 1 12 3a12 12 0 0 1-8.6 3A12 12 0 0 0 12 21a12 12 0 0 0 8.6-15Z"/></svg>
                {{ __('Secure & Encrypted') }}
            </span>
            <span aria-hidden="true">&bull;</span>
            <a href="{{ route('tenant.login') }}" class="hover:text-blue-700 dark:hover:text-blue-300 transition">{{ __('Return to Store Cashier / Admin Login') }} &rarr;</a>
        </div>
    </div>

    @if ($hasBanner)
        @include('auth.partials.auth-marketing-banner', ['type' => 'login'])
    @endif
</div>
