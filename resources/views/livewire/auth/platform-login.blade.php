<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
    
    <!-- Left Column: Super Admin Form Card -->
    <div class="lg:col-span-6 w-full">
        <div class="rounded-3xl bg-slate-50 dark:bg-slate-900/90 backdrop-blur-xl border border-slate-300 dark:border-white/15 p-7 sm:p-9 shadow-2xl space-y-6">
            
            <!-- Card Header with Superadmin Shield Badge -->
            <div class="flex items-start justify-between border-b border-slate-200 dark:border-white/10 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Super Admin') }}</h2>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-bold border border-indigo-500/30">
                            🛡️ {{ __('Root Control') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Platform management, tenant provisioning & system branding') }}</p>
                </div>
                
                <a href="{{ route('tenant.login') }}" class="text-xs font-black text-brand-lime hover:underline shrink-0 text-right">
                    <span>{{ __('Store Login') }}</span>
                    <span>→</span>
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
                <!-- Demo Mode Visual Notification Badge -->
                <div class="p-3.5 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg">⚡</span>
                        <div>
                            <span class="font-extrabold text-indigo-200 uppercase tracking-wider text-[10px] block">{{ __('Demo Mode Active') }}</span>
                            <span class="text-[11px] text-indigo-300/90">{{ __('Super Admin credentials pre-filled for instant testing') }}</span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-400/20 text-indigo-200 text-[10px] font-mono font-bold">1-Click</span>
                </div>

                <!-- Quick Demo Account Pill -->
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Quick Demo Credentials (1-Click Fill)') }}</label>
                    <button type="button"
                            wire:click="fillDemo('superadmin')"
                            class="w-full p-2.5 rounded-2xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 border border-slate-300 dark:border-white/15 text-left transition flex items-center gap-2.5 cursor-pointer group active:scale-95">
                        <span class="w-7 h-7 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-sm font-bold shrink-0">🛡️</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-300 truncate">{{ __('Super Administrator') }}</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">superadmin@gmail.com &bull; password123</div>
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
                               placeholder="admin@zoomnearby.com"
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
                <div class="flex items-center justify-between pt-1" x-data="{ remember: true }">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Keep admin session active') }}</span>
                    
                    <button type="button"
                            x-on:click="remember = !remember"
                            :class="remember ? 'bg-indigo-600' : 'bg-slate-700'"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                            role="switch" :aria-checked="remember.toString()">
                        <span :class="remember ? 'translate-x-4 bg-white' : 'translate-x-0 bg-white'"
                              class="pointer-events-none inline-block h-4 w-4 transform rounded-full shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

            </div>

            <!-- Primary Action Button: Sign in -->
            <div>
                <button type="button"
                        wire:click="login"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 rounded-full bg-indigo-600 hover:bg-indigo-500 text-slate-900 dark:text-white font-black text-sm tracking-wide shadow-lg shadow-indigo-600/30 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                    <span wire:loading.remove>{{ __('Sign In to Control Panel →') }}</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-slate-900 dark:text-white" fill="none" viewBox="0 0 24 24">
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
