<div class="max-w-xl mx-auto rounded-3xl bg-slate-900/90 backdrop-blur-xl border border-white/15 p-7 sm:p-9 shadow-2xl space-y-6">
    
    <!-- Card Header with Lock & Shield Badge -->
    <div class="border-b border-white/10 pb-4">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">{{ __('Verify Your Account') }}</h1>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-lime/10 text-brand-lime text-[10px] font-bold border border-brand-lime/20">
                🔒 {{ __('Email OTP') }}
            </span>
        </div>
        <p class="text-xs text-slate-400 mt-1">
            {{ __('Please enter the 6-digit verification code sent to') }} <span class="font-bold text-brand-lime">{{ $userEmail }}</span> {{ __('to access your dashboard.') }}
        </p>
    </div>

    <!-- Error Alert Banner -->
    @if ($errorMessage)
        <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <!-- Status Alert Banner -->
    @if ($statusMessage)
        <div class="p-3.5 rounded-2xl bg-emerald-500/10 text-emerald-300 text-xs font-semibold flex items-center gap-2.5 border border-emerald-500/30">
            <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ $statusMessage }}</span>
        </div>
    @endif

    <!-- OTP Input Form -->
    <div class="space-y-4">
        <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-300 mb-1 text-center">{{ __('Enter 6-Digit Code *') }}</label>
            <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition max-w-xs mx-auto">
                <input type="text"
                       wire:model="otp"
                       wire:keydown.enter="verify"
                       maxlength="6"
                       autofocus
                       placeholder="••••••"
                       class="w-full border-none bg-transparent text-center text-xl sm:text-2xl font-black tracking-[0.5em] text-brand-lime placeholder-slate-600 focus:ring-0 py-3.5 px-4 font-mono">
            </div>
            @error('otp') <p class="text-rose-400 text-[11px] mt-0.5 text-center">{{ $message }}</p> @enderror
        </div>

        <div class="text-center pt-2">
            <button type="button"
                    wire:click="resend"
                    wire:loading.attr="disabled"
                    class="text-xs font-bold text-slate-400 hover:text-brand-lime transition inline-flex items-center gap-1 cursor-pointer">
                <span>🔄 {{ __('Didn\'t receive code? Resend Code') }}</span>
            </button>
        </div>
    </div>

    <!-- Verify Action Button -->
    <div>
        <button type="button"
                wire:click="verify"
                wire:loading.attr="disabled"
                class="w-full py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-brand-lime/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
            <span wire:loading.remove>{{ __('Verify & Access Dashboard →') }}</span>
            <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('Verifying Code...') }}</span>
            </span>
        </button>
    </div>

    <!-- Log Out link -->
    <div class="pt-4 border-t border-white/10 text-center">
        <button type="button" wire:click="logout" class="text-xs font-bold text-slate-400 hover:text-rose-400 transition inline-flex items-center gap-1 cursor-pointer">
            <span>← {{ __('Log Out & Sign in with different account') }}</span>
        </button>
    </div>

</div>
