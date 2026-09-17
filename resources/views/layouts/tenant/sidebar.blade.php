
@if (auth()->check())
<div class="mt-auto border-t border-slate-200 dark:border-slate-800 p-3 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-slate-200 dark:border-slate-700">
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </div>
        <div class="text-xs min-w-0 flex-1">
            <p class="font-bold text-slate-800 dark:text-slate-200 truncate leading-tight">{{ auth()->user()->name }}</p>
            <p class="text-slate-400 truncate leading-normal">{{ auth()->user()->email }}</p>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0 m-0">
        @csrf
        <button type="submit" title="{{ __('Sign Out') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold whitespace-nowrap transition-colors">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>{{ __('Sign Out') }}</span>
        </button>
    </form>
</div>
@endif
