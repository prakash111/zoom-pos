<div class="space-y-6">

    <!-- Flash Messages -->
    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-emerald-100 dark:border-emerald-900/50 animate-in fade-in">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Header Section with Month Picker & Set Target Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xl shadow-xs">
                🎯
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Sales Targets & Goals") }}</h1>
                <p class="text-xs text-slate-400 mt-0.5">{{ __("Track monthly revenue milestones, salesperson quotas, and live achievement run-rate.") }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- Month & Year Selector -->
            <select wire:model.live="month" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200 py-2 px-3 focus:ring-blue-500">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}</option>
                @endfor
            </select>

            <select wire:model.live="year" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200 py-2 px-3 focus:ring-blue-500">
                @for ($y = now()->year - 2; $y <= now()->year + 2; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <button type="button"
                    wire:click="$set('showEditModal', true)"
                    class="px-4 py-2 rounded-xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span>⚙️ {{ __("Configure Goals") }}</span>
            </button>
        </div>
    </div>

    <!-- Store Milestone Hero Progress Card -->
    <div class="bg-theme-primary rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10 space-y-6">
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/10 text-[11px] font-mono font-bold tracking-wider uppercase text-blue-200 border border-white/10">
                        {{ \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y') }} Milestone
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black mt-2 tracking-tight">
                        {{ $company->formatMoney($overallProgress['achieved']) }}
                        <span class="text-sm sm:text-base font-medium opacity-75">/ {{ $company->formatMoney($overallProgress['target']) }}</span>
                    </h2>
                </div>

                <div class="text-right">
                    <div class="text-3xl sm:text-4xl font-black font-mono {{ $overallProgress['percentage'] >= 100 ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $overallProgress['percentage'] }}%
                    </div>
                    <div class="text-xs opacity-75 font-semibold mt-0.5">
                        {{ $overallProgress['percentage'] >= 100 ? '🎉 Milestone Achieved!' : $company->formatMoney($overallProgress['remaining']) . ' remaining' }}
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1.5">
                <div class="w-full h-4 bg-black/30 rounded-full overflow-hidden p-0.5 border border-white/10">
                    <div class="h-full rounded-full transition-all duration-500 {{ $overallProgress['percentage'] >= 100 ? 'bg-gradient-to-r from-emerald-400 to-teal-300' : 'bg-gradient-to-r from-blue-400 to-emerald-400' }}"
                         style="width: {{ min(100, $overallProgress['percentage']) }}%;"></div>
                </div>
            </div>

            <!-- Metric Run-Rate Pills -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-white/10 text-xs">
                <div class="bg-white/5 p-3 rounded-2xl border border-white/10">
                    <div class="text-[10px] uppercase font-bold opacity-70">{{ __("Transactions") }}</div>
                    <div class="text-base font-black font-mono mt-0.5">{{ $overallProgress['sales_count'] }}</div>
                </div>

                <div class="bg-white/5 p-3 rounded-2xl border border-white/10">
                    <div class="text-[10px] uppercase font-bold opacity-70">{{ __("Monthly Target") }}</div>
                    <div class="text-base font-black font-mono mt-0.5">{{ $company->formatMoney($overallProgress['target']) }}</div>
                </div>

                <div class="bg-white/5 p-3 rounded-2xl border border-white/10">
                    <div class="text-[10px] uppercase font-bold opacity-70">{{ __("Days Remaining") }}</div>
                    <div class="text-base font-black font-mono mt-0.5">{{ $remainingDays }} {{ __("days") }}</div>
                </div>

                <div class="bg-white/5 p-3 rounded-2xl border border-white/10">
                    <div class="text-[10px] uppercase font-bold opacity-70">{{ __("Required Daily Run-Rate") }}</div>
                    <div class="text-base font-black font-mono mt-0.5 text-amber-300">
                        {{ $company->formatMoney($dailyRunRateNeeded) }} / {{ __("day") }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Staff Performance Leaderboard -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Salesperson Quotas & Leaderboard") }}</h3>
                <p class="text-xs text-slate-400">{{ __("Individual staff targets and progress toward their monthly quota.") }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 uppercase tracking-wider text-[11px] font-extrabold border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">{{ __("Salesperson") }}</th>
                        <th class="px-4 py-3">{{ __("Individual Goal") }}</th>
                        <th class="px-4 py-3">{{ __("Achieved Revenue") }}</th>
                        <th class="px-4 py-3">{{ __("Orders") }}</th>
                        <th class="px-4 py-3 w-48">{{ __("Progress") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($userTargets as $ut)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $ut['name'] }}</div>
                                <div class="text-[11px] text-slate-400">{{ $ut['email'] }}</div>
                            </td>
                            <td class="px-4 py-3.5 font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ $company->formatMoney($ut['target_amount']) }}
                            </td>
                            <td class="px-4 py-3.5 font-mono font-black text-emerald-600 dark:text-emerald-400">
                                {{ $company->formatMoney($ut['achieved_amount']) }}
                            </td>
                            <td class="px-4 py-3.5 font-mono font-bold text-slate-500">
                                {{ $ut['sales_count'] }}
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all {{ $ut['percentage'] >= 100 ? 'bg-emerald-500' : 'bg-blue-600' }}"
                                             style="width: {{ min(100, $ut['percentage']) }}%;"></div>
                                    </div>
                                    <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300 shrink-0 min-w-[40px] text-right">
                                        {{ $ut['percentage'] }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                {{ __("No staff members found.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Configure Targets Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-xl w-full border border-slate-100 dark:border-slate-800 shadow-2xl space-y-5 animate-in fade-in">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">🎯</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">
                            {{ __("Configure Goals for") }} {{ \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="space-y-4 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <div>
                        <label class="block mb-1">{{ __("Total Store Monthly Revenue Target ($)") }}</label>
                        <input type="number" step="100" min="0" wire:model="companyTargetAmount"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold focus:ring-blue-500 py-2.5 px-3 text-slate-900 dark:text-white">
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block font-black text-slate-900 dark:text-white">{{ __("Individual Staff Targets") }}</label>
                            <button type="button" wire:click="splitEvenly" class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 hover:bg-blue-100 text-[11px] font-extrabold cursor-pointer">
                                ⚡ {{ __("Split Store Target Evenly") }}
                            </button>
                        </div>

                        <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                            @foreach ($userTargets as $idx => $ut)
                                <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">{{ $ut['name'] }}</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ $ut['email'] }}</div>
                                    </div>
                                    <div class="w-32 shrink-0">
                                        <input type="number" step="50" min="0" wire:model="userTargets.{{ $idx }}.target_amount"
                                               class="w-full text-right rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono font-bold py-1.5 px-2 text-slate-900 dark:text-white">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                        {{ __("Cancel") }}
                    </button>
                    <button type="button" wire:click="save" class="px-6 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 cursor-pointer">
                        {{ __("Save Targets") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
