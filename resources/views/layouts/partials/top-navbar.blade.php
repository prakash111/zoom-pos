<div class="px-4 sm:px-6 py-2.5 flex items-center justify-between">
    <div class="flex items-center gap-3">
        @if(Route::has('tenant.dashboard'))
            <a href="{{ route('tenant.dashboard') }}" wire:navigate class="flex items-center gap-2.5 font-black text-sm text-white hover:opacity-90 transition">
                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-base shadow-sm">⚡</span>
                <span class="tracking-tight">{{ auth()->user()?->company?->name ?? config('app.name', 'Zoom POS & Market') }}</span>
            </a>
        @else
            <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2.5 font-black text-sm text-white hover:opacity-90 transition">
                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-base shadow-sm">⚡</span>
                <span class="tracking-tight">{{ config('app.name', 'Zoom POS & Market') }}</span>
            </a>
        @endif
    </div>

    <div class="flex items-center gap-3">
        @if(Route::has('tenant.sales.create'))
            <a href="{{ route('tenant.sales.create') }}" wire:navigate class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm transition">
                <span>🛒</span>
                <span>{{ __('POS Terminal') }}</span>
            </a>
        @endif

        @auth
            <div class="flex items-center gap-2 text-xs text-slate-300">
                <span class="font-bold text-slate-200">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ Route::has('tenant.logout') ? route('tenant.logout') : (Route::has('logout') ? route('logout') : '#') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-950/60 hover:text-rose-400 text-slate-400 font-bold text-[11px] transition cursor-pointer">
                        {{ __('Sign Out') }}
                    </button>
                </form>
            </div>
        @endauth
    </div>
</div>
