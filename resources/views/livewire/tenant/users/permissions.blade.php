<div class="space-y-6">
    
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Permissions Matrix</h2>
                @if ($targetUser)
                    <span class="px-3 py-0.5 rounded-full text-xs font-extrabold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                        {{ $targetUser->name }} ({{ ucfirst($targetUser->role) }})
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Configure fine-grained read, write, edit, delete, and export permissions per module.</p>
        </div>

        <a href="{{ route('tenant.users.index') }}"
           class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white transition">
            &larr; Back to Users & Team
        </a>
    </div>

    <!-- User Selector & Role Profile Presets -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
        
        <!-- User Selection Dropdown -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
            <div class="flex-1 max-w-sm space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Select User to Configure:</label>
                <select wire:model.live="selectedUserId" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }} &bull; {{ ucfirst($u->role) }})</option>
                    @endforeach
                </select>
            </div>

            @if ($targetUser && $targetUser->isPrivilegedRole())
                <div class="px-4 py-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs font-bold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>This user has Administrator role and automatically enjoys full bypass access to all modules.</span>
                </div>
            @endif
        </div>

        <!-- Role Profile Quick Presets matching Screenshot 2026-08-22 8.15.52 AM.png -->
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-2.5">
                Apply Role Profile Preset:
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($roles as $rKey => $rLabel)
                    <button type="button"
                            wire:click="applyPreset('{{ $rKey }}')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950/60 hover:text-blue-600 dark:hover:text-blue-400 text-slate-700 dark:text-slate-300 transition shadow-2xs">
                        {{ $rLabel }}
                    </button>
                @endforeach
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <button type="button"
                        wire:click="applyPreset('full')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 transition shadow-2xs">
                    Full Access
                </button>
                <button type="button"
                        wire:click="applyPreset('view_only')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 hover:bg-blue-100 transition shadow-2xs">
                    View Only
                </button>
                <button type="button"
                        wire:click="applyPreset('clear')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 hover:bg-rose-100 transition shadow-2xs">
                    Clear All
                </button>
            </div>
        </div>

    </div>

    <!-- Permissions Matrix Table -->
    @if ($targetUser)
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm text-left">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-extrabold border-b border-slate-100 dark:border-slate-800 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-6 py-4">Module</th>
                            @foreach ($actions as $actKey => $actLabel)
                                <th class="px-4 py-4 text-center">
                                    <button type="button"
                                            wire:click="toggleColumn('{{ $actKey }}')"
                                            class="hover:text-blue-600 dark:hover:text-blue-400 font-extrabold inline-flex items-center gap-1 group"
                                            title="Click to toggle {{ $actLabel }} across all modules">
                                        <span>{{ strtoupper($actKey) }}</span>
                                        <span class="text-[9px] text-slate-300 group-hover:text-blue-500 font-normal">(&#8645;)</span>
                                    </button>
                                </th>
                            @endforeach
                            <th class="px-4 py-4 text-right">Row</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach ($modules as $modKey => $modLabel)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition">
                                <td class="px-6 py-3.5 font-bold text-slate-800 dark:text-slate-200">
                                    {{ $modLabel }}
                                </td>

                                @foreach ($actions as $actKey => $actLabel)
                                    <td class="px-4 py-3.5 text-center">
                                        <label class="inline-flex items-center justify-center cursor-pointer">
                                            <input type="checkbox"
                                                   wire:model="grid.{{ $modKey }}.{{ $actKey }}"
                                                   class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500 transition">
                                        </label>
                                    </td>
                                @endforeach

                                <td class="px-4 py-3.5 text-right">
                                    <button type="button"
                                            wire:click="toggleRow('{{ $modKey }}')"
                                            class="text-[11px] font-extrabold text-slate-400 hover:text-blue-600 transition">
                                        All
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Bottom Save Bar -->
            <div class="p-5 sm:p-6 bg-slate-50/70 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="text-xs text-slate-400">
                    Changes apply immediately upon saving.
                </div>

                <button type="button"
                        wire:click="save"
                        class="px-8 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 active:scale-95 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>Save Permissions</span>
                </button>
            </div>
        </div>
    @endif

</div>
