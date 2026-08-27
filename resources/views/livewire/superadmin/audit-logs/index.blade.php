<div class="space-y-6">
    
    <div>
        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ __("Global Audit Trail & Security Logs") }}</h3>
        <p class="text-xs text-slate-400">{{ __("Immutable event record of administrative actions, config mutations, and security events") }}</p>
    </div>

    <!-- Filter Toolbar -->
    <div class="flex flex-col sm:flex-row gap-3">
        <input type="text"
               wire:model.live.debounce.300ms="action"
               placeholder="{{ __("Filter by event action (e.g. smtp.updated)…") }}"
               class="w-full sm:w-72 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-indigo-500 shadow-xs">
        
        <input type="text"
               wire:model.live.debounce.300ms="companyId"
               placeholder="{{ __("Filter by Tenant / Company ID…") }}"
               class="w-full sm:w-72 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-indigo-500 shadow-xs">
    </div>

    <!-- Logs Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Timestamp</th>
                        <th class="px-6 py-3.5">Action Event</th>
                        <th class="px-6 py-3.5">Tenant</th>
                        <th class="px-6 py-3.5">Actor User</th>
                        <th class="px-6 py-3.5">Result</th>
                        <th class="px-6 py-3.5">Client IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400 text-xs font-mono">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $log->action }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $log->company_id ? 'Store #'.$log->company_id : 'Platform' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $log->user_id ? 'User #'.$log->user_id : 'System' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $log->result ?? 'OK' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-400 text-xs font-mono">
                                {{ $log->ip ?? '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                No matching audit log events found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $logs->links() }}</div>

</div>
