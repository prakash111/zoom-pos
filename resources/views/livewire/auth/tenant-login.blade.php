<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
    
    <!-- Left Column: Modern Login Card -->
    <div class="lg:col-span-6 w-full">
        <div class="rounded-3xl bg-slate-900/90 backdrop-blur-xl border border-white/15 p-7 sm:p-9 shadow-2xl space-y-6">
            
            <!-- Card Header with Reciprocal Switching Link -->
            <div class="flex items-start justify-between border-b border-white/10 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">{{ __('Store Sign In') }}</h2>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-lime animate-ping"></span>
                            {{ __('Live POS') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Sign in with your staff or administrator credentials') }}</p>
                </div>
                
                <a href="{{ route('tenant.register') }}" class="text-xs font-black text-brand-lime hover:underline shrink-0 text-right">
                    <span>{{ __('Register Store') }}</span>
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

            <!-- Form Inputs -->
            <div class="space-y-4">
                
                <!-- Email / Login Input -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-300 mb-1">{{ __('Email or Username *') }}</label>
                    <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                        <div class="pl-3.5 pr-2 text-slate-400">
                            <div class="w-7 h-7 rounded-xl bg-white/10 flex items-center justify-center text-xs">
                                👤
                            </div>
                        </div>
                        <input type="text"
                                wire:model="identifier"
                                wire:keydown.enter="login"
                                placeholder="you@yourstore.com"
                                class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-white placeholder-slate-500 focus:ring-0 py-3 pr-3">
                    </div>
                    @error('identifier') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>

                <!-- Password Input -->
                <div class="space-y-1" x-data="{ showPass: false }">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-300 mb-1">{{ __('Password *') }}</label>
                    </div>
                    <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime focus-within:border-brand-lime transition">
                        <div class="pl-3.5 pr-2 text-slate-400">
                            <div class="w-7 h-7 rounded-xl bg-white/10 flex items-center justify-center text-xs">
                                🔒
                            </div>
                        </div>
                        <input :type="showPass ? 'text' : 'password'"
                                wire:model="password"
                                wire:keydown.enter="login"
                                placeholder="••••••••••••"
                                class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-white placeholder-slate-500 focus:ring-0 py-3 pr-10">
                        <button type="button" x-on:click="showPass = !showPass" class="absolute right-3 text-slate-400 hover:text-white text-xs cursor-pointer">
                            <span x-show="!showPass">👁️</span>
                            <span x-show="showPass">🙈</span>
                        </button>
                    </div>
                    @error('password') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>

                <!-- Keep me signed in Toggle -->
                <div class="flex items-center justify-between pt-1" x-data="{ remember: true }">
                    <span class="text-xs font-semibold text-slate-400">{{ __('Keep me signed in on this device') }}</span>
                    
                    <button type="button"
                            x-on:click="remember = !remember"
                            :class="remember ? 'bg-brand-lime' : 'bg-slate-700'"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                            role="switch" :aria-checked="remember.toString()">
                        <span :class="remember ? 'translate-x-4 bg-slate-950' : 'translate-x-0 bg-white'"
                              class="pointer-events-none inline-block h-4 w-4 transform rounded-full shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

            </div>

            <!-- Primary Action Button (Vibrant Neon Lime Pill) -->
            <div>
                <button type="button"
                        wire:click="login"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-brand-lime/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                    <span wire:loading.remove>{{ __('Sign In to Store Register →') }}</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ __('Authenticating...') }}</span>
                    </span>
                </button>
            </div>

            <!-- Reciprocal Registration Box & SuperAdmin Switcher -->
            <div class="pt-4 border-t border-white/10 flex flex-col items-center justify-center gap-3 text-center">
                <div class="text-xs text-slate-300">
                    {{ __("Don't have an account?") }}
                    <a href="{{ route('tenant.register') }}" class="font-extrabold text-brand-lime hover:underline ml-1">
                        {{ __('Register your Store now →') }}
                    </a>
                </div>

                <a href="{{ route('superadmin.login') }}" class="text-[11px] font-bold text-slate-400 hover:text-white transition flex items-center gap-1.5">
                    <span>🛡️ {{ __('Platform Administrator Portal') }}</span>
                    <span>&rarr;</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Right Column: Visual POS & Store Features Showcase -->
    <div class="lg:col-span-6 space-y-6">
        
        <!-- Live Terminal Status Card -->
        <div class="rounded-3xl bg-slate-900/60 backdrop-blur-xl border border-white/10 p-6 sm:p-8 space-y-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-brand-lime to-emerald-400 p-0.5 flex items-center justify-center">
                        <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center text-brand-lime font-black text-base">
                            ⚡
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-black text-white">{{ __('Smart Inventory & POS Engine') }}</div>
                        <div class="text-xs text-emerald-400 font-bold">● {{ __('High Availability Cloud Active') }}</div>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full bg-white/10 text-white text-[10px] font-mono font-bold">
                    v2.5 Release
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <div class="text-slate-400 text-xs font-medium">{{ __('Barcode Scanning') }}</div>
                    <div class="text-sm font-black text-white mt-1">&lt;20ms {{ __('Latency') }}</div>
                    <div class="text-[10px] text-emerald-400 font-bold mt-0.5">{{ __('Instant Add-to-Cart') }}</div>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <div class="text-slate-400 text-xs font-medium">{{ __('Offline Checkout') }}</div>
                    <div class="text-sm font-black text-white mt-1">{{ __('Zero Downtime') }}</div>
                    <div class="text-[10px] text-brand-lime font-bold mt-0.5">{{ __('Local DB Cache & Sync') }}</div>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <div class="text-slate-400 text-xs font-medium">{{ __('Floor & Table KOT') }}</div>
                    <div class="text-sm font-black text-white mt-1">{{ __('Real-time KDS') }}</div>
                    <div class="text-[10px] text-emerald-400 font-bold mt-0.5">{{ __('Kitchen Dispatch Ready') }}</div>
                </div>
                <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
                    <div class="text-slate-400 text-xs font-medium">{{ __('Tax & Split Tender') }}</div>
                    <div class="text-sm font-black text-white mt-1">{{ __('Multi-Currency') }}</div>
                    <div class="text-[10px] text-brand-lime font-bold mt-0.5">{{ __('Thermal & WhatsApp PDF') }}</div>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-500/10 to-lime-500/10 border border-emerald-500/20 flex items-center gap-3">
                <span class="text-2xl">🔒</span>
                <div>
                    <div class="text-xs font-black text-white">{{ __('Encrypted Multi-Tenant Isolation') }}</div>
                    <div class="text-[11px] text-slate-300">{{ __('Your store database and financial ledgers are securely partitioned with automated cloud snapshots.') }}</div>
                </div>
            </div>
        </div>

    </div>

</div>
