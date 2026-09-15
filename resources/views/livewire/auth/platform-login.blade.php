<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
    
    <!-- Left Column: Super Admin Form Card -->
    <div class="lg:col-span-6 w-full">
        <div class="rounded-3xl bg-white dark:bg-slate-900/90 backdrop-blur-xl border border-slate-200 dark:border-white/15 p-6 sm:p-8 shadow-2xl space-y-6">

            <!-- Card Header with Superadmin Shield Badge -->
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Super Admin') }}</h2>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-500/20 text-purple-900 dark:text-purple-200 text-xs font-semibold border border-purple-200 dark:border-purple-500/30">
                            🛡️ {{ __('Root Control') }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">{{ __('Platform management, tenant provisioning & system branding') }}</p>
                </div>

                <a href="{{ route('tenant.login') }}" class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 text-xs font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('Store Login') }} <span aria-hidden="true">→</span>
                </a>
            </div>

            <!-- Error Banner -->
            @if ($error)
                <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
                    <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $error }}</span>
                </div>
            @endif

            @if (config('app.demo_mode'))
                <!-- Demo Mode banner — high contrast -->
                <div class="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200/80 dark:border-indigo-500/30 p-4 flex items-start justify-between gap-3 shadow-sm">
                    <div class="flex items-start gap-2.5 min-w-0">
                        <svg class="w-5 h-5 shrink-0 mt-0.5 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z" />
                        </svg>
                        <div class="min-w-0">
                            <span class="block font-bold uppercase tracking-wider text-xs text-indigo-950 dark:text-indigo-300">{{ __('Demo Mode Active') }}</span>
                            <span class="block text-xs font-medium text-indigo-900 dark:text-indigo-200/90 mt-0.5">{{ __('Super Admin credentials pre-filled for instant testing') }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-400/20 text-indigo-900 dark:text-indigo-200 text-[10px] font-mono font-bold">{{ __('1-Click') }}</span>
                </div>

                <!-- Quick Demo Account Pill -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold tracking-wider uppercase text-slate-600 dark:text-slate-400">{{ __('Quick Demo Credentials (1-Click Fill)') }}</label>
                    <button type="button"
                            wire:click="fillDemo('superadmin')"
                            class="w-full p-2.5 rounded-xl bg-white dark:bg-white/10 hover:bg-slate-50 dark:hover:bg-white/20 border border-slate-200 dark:border-white/15 text-left transition flex items-center gap-2.5 cursor-pointer group active:scale-[0.98]">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-sm font-bold shrink-0">🛡️</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold text-[#0F172A] dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 truncate">{{ __('Super Administrator') }}</div>
                            <div class="text-[10px] text-[#475569] dark:text-slate-400 truncate">superadmin@gmail.com &bull; password123</div>
                        </div>
                    </button>
                </div>
            @endif

            <!-- Form Inputs -->
            <div class="space-y-4">
                
                <!-- Email Input -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Admin Email Address *') }}</label>
                    <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                        <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                            <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                👤
                            </div>
                        </div>
                        <input type="email"
                               wire:model="email"
                               wire:keydown.enter="login"
                               placeholder="admin@yourdomain.com"
                               class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-3 pr-3">
                    </div>
                    @error('email') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>

                <!-- Password Input with Show/Hide Toggle -->
                <div class="space-y-1" x-data="{ showPass: false }">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">{{ __('Admin Password *') }}</label>
                    </div>
                    <div class="relative flex items-center rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                        <div class="pl-3.5 pr-2 text-slate-500 dark:text-slate-400">
                            <div class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-white/10 flex items-center justify-center text-xs">
                                🔒
                            </div>
                        </div>
                        <input :type="showPass ? 'text' : 'password'"
                               wire:model="password"
                               wire:keydown.enter="login"
                               placeholder="••••••••••••"
                               class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-500 focus:ring-0 py-3 pr-10">
                        <button type="button" x-on:click="showPass = !showPass" class="absolute right-3 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs cursor-pointer">
                            <span x-show="!showPass">👁️</span>
                            <span x-show="showPass">🙈</span>
                        </button>
                    </div>
                    @error('password') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>

                <!-- Keep me logged in Toggle -->
                <div class="flex items-center justify-between py-1" x-data="{ remember: true }">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Keep admin session active') }}</span>
                    <button type="button"
                            x-on:click="remember = !remember"
                            :class="remember ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                            role="switch" :aria-checked="remember.toString()">
                        <span :class="remember ? 'translate-x-5' : 'translate-x-0.5'"
                              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

            </div>

            <!-- Primary Action Button: Sign in -->
            <div>
                <button type="button"
                        wire:click="login"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm tracking-wide shadow-lg shadow-indigo-600/25 active:scale-[0.98] transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                    <span wire:loading.remove>{{ __('Sign In to Control Panel →') }}</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ __('Authenticating...') }}</span>
                    </span>
                </button>
            </div>

            <!-- Switcher link to Store Login -->
            <div class="pt-4 border-t border-slate-200 dark:border-white/10 text-center">
                <a href="{{ route('tenant.login') }}" class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-brand-lime transition inline-flex items-center gap-1">
                    <span>🏪 {{ __('Return to Store Cashier / Admin Login') }}</span>
                    <span>&rarr;</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Right Column: Control Center Highlights -->
    <div class="lg:col-span-6 space-y-6">
        <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/60 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-6 sm:p-8 space-y-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center text-xl">
                        🛡️
                    </div>
                    <div>
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ __('Central Operations Node') }}</div>
                        <div class="text-xs text-indigo-400 font-bold">● {{ __('High Availability Cluster') }}</div>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-mono font-bold">
                    SuperAdmin
                </span>
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-300">{{ __('Tenant Provisioning & Life-Cycle') }}</span>
                    <span class="text-emerald-400 font-bold">{{ __('Automated') }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-300">{{ __('White-label & Theming Engine') }}</span>
                    <span class="text-brand-lime font-bold">{{ __('Real-Time') }}</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-300">{{ __('Global Tax, Smtp & Audit Logs') }}</span>
                    <span class="text-indigo-300 font-bold">{{ __('Encrypted') }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
