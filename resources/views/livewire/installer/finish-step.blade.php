<div>
    <div class="mb-5">
        <h2 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Step 5 — Complete Installation & Launch") }}</h2>
        <p class="text-xs text-slate-500 mt-1">{{ __("Finalize application encryption keys, storage symlinks, and lock the installer.") }}</p>
    </div>

    @if (! $done)
        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3 mb-6">
            <h3 class="text-xs font-black uppercase text-slate-400 tracking-wider">{{ __("Final System Checklist") }}</h3>
            <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300 font-medium">
                <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold">
                    <span>✓</span> {{ __("Database migrated and seeded with default SaaS plans & branding") }}
                </li>
                <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold">
                    <span>✓</span> {{ __("Primary Super Admin account registered") }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="text-blue-500">⚙</span> {{ __("Generate APP_KEY encryption secret (if empty)") }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="text-blue-500">📁</span> {{ __("Create public storage symlink (storage:link)") }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="text-blue-500">🔒</span> {{ __("Create storage/installed lock file to prevent re-installation") }}
                </li>
            </ul>
        </div>

        <div class="flex justify-end">
            <button wire:click="finish"
                    wire:loading.attr="disabled"
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/25 active:scale-95 transition cursor-pointer">
                <span wire:loading.remove>🚀 {{ __("Complete & Lock Installation") }}</span>
                <span wire:loading>{{ __("Locking Installation...") }}</span>
            </button>
        </div>
    @else
        <div class="text-center py-6 space-y-4">
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto text-3xl font-black shadow-lg shadow-emerald-500/20">
                ✓
            </div>

            <div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">{{ __("Installation Successfully Completed!") }}</h3>
                <p class="text-xs text-slate-500 mt-1">{{ __("The application is fully configured, secured, and ready for production use.") }}</p>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('superadmin.login') }}"
                   class="w-full sm:w-auto px-6 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md transition active:scale-95 flex items-center justify-center gap-2">
                    <span>👑 {{ __("Super Admin Console") }}</span>
                </a>

                <a href="{{ route('tenant.login') }}"
                   class="w-full sm:w-auto px-6 py-3 rounded-2xl text-xs sm:text-sm font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 transition flex items-center justify-center gap-2">
                    <span>🏪 {{ __("Store POS Login") }}</span>
                </a>
            </div>
        </div>
    @endif
</div>
