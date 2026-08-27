<div>
    <div class="mb-5">
        <h2 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Step 1 — Server Requirements & Directory Permissions") }}</h2>
        <p class="text-xs text-slate-500 mt-1">{{ __("Verify PHP version, required PHP extensions, and writable storage paths before proceeding.") }}</p>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden mb-6 shadow-xs">
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($checks as $check)
                <div class="flex items-center justify-between p-3.5 text-xs sm:text-sm">
                    <div class="flex items-center gap-2.5">
                        @if ($check['pass'])
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 flex items-center justify-center font-black text-xs">✓</span>
                        @else
                            <span class="w-5 h-5 rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400 flex items-center justify-center font-black text-xs">✕</span>
                        @endif
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $check['label'] }}</span>
                    </div>

                    <div>
                        @if ($check['pass'])
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                                {{ __("PASS") }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/60">
                                {{ __("FAILED") }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if (! $allPassed)
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs font-semibold mb-6 flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ __("Please resolve the failed requirements above (enable PHP extensions / adjust directory permissions via chmod 775) before continuing.") }}</span>
        </div>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('install.environment') }}"
           @class([
               'inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-xs sm:text-sm font-extrabold shadow-lg transition active:scale-95',
               'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-500/25 cursor-pointer' => $allPassed,
               'bg-slate-200 dark:bg-slate-800 text-slate-400 pointer-events-none cursor-not-allowed' => ! $allPassed,
           ])>
            <span>{{ __("Next: Database Configuration") }} &rarr;</span>
        </a>
    </div>
</div>
