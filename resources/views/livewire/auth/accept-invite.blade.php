<div class="max-w-xl mx-auto rounded-3xl bg-slate-900/90 backdrop-blur-xl border border-white/15 p-7 sm:p-9 shadow-2xl space-y-6">
    <div class="border-b border-white/10 pb-4">
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">{{ __('Accept Team Invitation') }}</h1>
        <p class="text-xs text-slate-400 mt-1">{{ __('Enter your team invitation code and set your account password.') }}</p>
    </div>

    @if ($error)
        <div class="p-3.5 rounded-2xl bg-rose-500/10 text-rose-300 text-xs font-semibold flex items-center gap-2.5 border border-rose-500/30">
            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ $error }}</span>
        </div>
    @endif

    <div class="space-y-4">
        <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-300 mb-1">{{ __('Invite Code *') }}</label>
            <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                <input type="text" wire:model="code" placeholder="INV-XXXX-XXXX" class="w-full border-none bg-transparent text-xs sm:text-sm font-black uppercase tracking-wider text-brand-lime placeholder-slate-500 focus:ring-0 py-3 px-4 font-mono">
            </div>
            @error('code') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-300 mb-1">{{ __('Set Password *') }}</label>
            <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                <input type="password" wire:model="password" placeholder="••••••••••••" class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-white placeholder-slate-500 focus:ring-0 py-3 px-4">
            </div>
            @error('password') <p class="text-rose-400 text-[11px] mt-0.5">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-300 mb-1">{{ __('Confirm Password *') }}</label>
            <div class="relative flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden focus-within:ring-2 focus-within:ring-brand-lime transition">
                <input type="password" wire:model="password_confirmation" placeholder="••••••••••••" class="w-full border-none bg-transparent text-xs sm:text-sm font-semibold text-white placeholder-slate-500 focus:ring-0 py-3 px-4">
            </div>
        </div>
    </div>

    <button wire:click="accept" type="button" class="w-full py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-brand-lime/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer">
        <span>{{ __('Activate Account →') }}</span>
    </button>
</div>
