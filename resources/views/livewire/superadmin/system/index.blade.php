<div class="w-full space-y-6">
    
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🛠️ {{ __("System Health & Global Maintenance Mode") }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                Control platform accessibility, maintenance lockouts, client build compatibility, and app releases.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 flex items-start gap-3">
            <input type="checkbox" wire:model="maintenanceMode" id="maint_mode" class="mt-0.5 rounded-lg text-indigo-600 focus:ring-indigo-500">
            <div>
                <label for="maint_mode" class="text-xs font-black text-amber-900 dark:text-amber-200 cursor-pointer">Enable Platform Maintenance Mode</label>
                <p class="text-[11px] text-amber-700 dark:text-amber-300/80 mt-0.5">
                    When active, all tenant logins and sync APIs will return a 503 service unavailable response with your customized message. Platform Super Admins retain full access.
                </p>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Maintenance Lockout Message") }}</label>
                <textarea wire:model="maintenanceMessage" rows="3" placeholder="Our systems are undergoing scheduled maintenance and upgrades. We'll be back shortly." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Minimum Client Build Version") }}</label>
                    <input type="text" wire:model="minClientBuildVersion" placeholder="1.0.0" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Platform Application Version") }}</label>
                    <input type="text" wire:model="appVersion" placeholder="2.5.0" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
            <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
                Save System Configuration
            </button>
        </div>

    </div>

</div>
