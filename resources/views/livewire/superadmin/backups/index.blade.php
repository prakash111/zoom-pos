<div class="space-y-6">
    
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Backup Policy Configuration Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>💾 {{ __("Automated Database Backup & Retention Policy") }}</span>
            </h3>
            <p class="text-xs text-slate-400">{{ __("Configure schedule frequency and retention lifecycle for database snapshots") }}</p>
        </div>

        <div class="flex flex-wrap gap-4 items-end pt-2">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Backup Frequency") }}</label>
                <select wire:model="frequency" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    <option value="manual">Manual Execution Only</option>
                    <option value="daily">{{ __("Daily Automated Snapshot") }}</option>
                    <option value="weekly">{{ __("Weekly Automated Snapshot") }}</option>
                    <option value="monthly">{{ __("Monthly Snapshot") }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Retention Window (Days)") }}</label>
                <input type="number" wire:model="retentionDays" class="w-36 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <button wire:click="savePolicy" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                Save Policy
            </button>
        </div>
    </div>

    <!-- Snapshots Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">Database Snapshots</h4>
                <p class="text-xs text-slate-400">{{ __("Available database dumps stored on disk") }}</p>
            </div>

            <button wire:click="createSnapshot" wire:loading.attr="disabled" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span wire:loading.remove wire:target="createSnapshot">⚡ Create Snapshot Now</span>
                <span wire:loading wire:target="createSnapshot">Creating Snapshot…</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Filename</th>
                        <th class="px-6 py-3.5">Size</th>
                        <th class="px-6 py-3.5">Timestamp</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($snapshots as $snap)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $snap['name'] }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300 font-bold">
                                {{ number_format($snap['size'] / 1024, 1) }} KB
                            </td>
                            <td class="px-6 py-4 text-slate-400 text-xs font-mono">
                                {{ $snap['created_at']->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button wire:click="download('{{ $snap['name'] }}')" type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">Download</button>
                                <button wire:click="deleteSnapshot('{{ $snap['name'] }}')" wire:confirm="{{ __("Delete this snapshot?") }}" type="button" class="text-rose-600 hover:underline font-bold">{{ __("Delete") }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                                No database snapshots created yet. Click "Create Snapshot Now" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
