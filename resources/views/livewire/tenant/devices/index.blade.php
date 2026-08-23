<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif

    <div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Active Devices & POS Terminals</h2>
        <p class="text-xs text-slate-400">Manage signed-in sessions across browsers and POS registers</p>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">User / Terminal</th>
                    <th class="px-5 py-3.5">IP Address</th>
                    <th class="px-5 py-3.5">Signed In</th>
                    <th class="px-5 py-3.5">Expires</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ $session->user->name ?? '—' }}</div>
                            @if ($session->isImpersonation())
                                <span class="text-[10px] text-amber-600 font-bold">(impersonated)</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $session->ip }}</td>
                        <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 text-xs">{{ $session->created_at->diffForHumans() }}</td>
                        <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 text-xs">{{ $session->expires_at->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <button wire:click="revoke('{{ $session->token }}')"
                                    wire:confirm="Sign out this device?"
                                    type="button"
                                    class="text-xs font-bold text-rose-600 hover:underline px-2.5 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                Sign out
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-xs">
                            No active sessions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
