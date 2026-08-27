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
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Action-Level Granular Permissions Matrix") }}</h2>
                @if ($targetUser)
                    <span class="px-3 py-0.5 rounded-full text-xs font-extrabold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                        {{ $targetUser->name }} ({{ ucfirst($targetUser->role) }})
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Configure operational actions, workflow gates, price overrides, quotes-to-sales conversion, and audit permissions.") }}</p>
        </div>

        <a wire:navigate.hover href="{{ route('tenant.users.index') }}"
           class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white transition">
            &larr; {{ __("Back to Users & Team") }}
        </a>
    </div>

    <!-- User Selector & Role Profile Presets -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
        
        <!-- User Selection Dropdown -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
            <div class="flex-1 max-w-sm space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Select User to Configure:") }}</label>
                <select wire:model.live="selectedUserId" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }} &bull; {{ ucfirst($u->role) }})</option>
                    @endforeach
                </select>
            </div>

            @if ($targetUser && $targetUser->isPrivilegedRole())
                <div class="px-4 py-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs font-bold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ __("This user has Administrator role and automatically enjoys full bypass access to all operational actions.") }}</span>
                </div>
            @endif
        </div>

        <!-- Role Profile Quick Presets -->
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-2.5">
                {{ __("Apply Role Profile Preset:") }}
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
                    {{ __("Full Access") }}
                </button>
                <button type="button"
                        wire:click="applyPreset('view_only')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 hover:bg-blue-100 transition shadow-2xs">
                    {{ __("View Only") }}
                </button>
                <button type="button"
                        wire:click="applyPreset('clear')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 hover:bg-rose-100 transition shadow-2xs">
                    {{ __("Clear All") }}
                </button>
            </div>
        </div>
    </div>

    <!-- Permissions Matrix List -->
    @if ($targetUser)
        <div class="space-y-4">
            @foreach ($modules as $modKey => $modLabel)
                @php
                    $modActions = \App\Services\Auth\PermissionChecker::getActionsForModule($modKey);
                    $allChecked = collect(array_keys($modActions))->every(fn ($a) => ! empty($grid[$modKey][$a]));
                @endphp
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4 transition">
                    <!-- Module Header Bar -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                                ⚙️
                            </span>
                            <div>
                                <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">{{ __($modLabel) }}</h3>
                                <p class="text-[11px] text-slate-400 font-mono">module: {{ $modKey }}</p>
                            </div>
                        </div>

                        <button type="button"
                                wire:click="toggleRow('{{ $modKey }}')"
                                class="px-3 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950/60 hover:text-blue-600 dark:hover:text-blue-400 text-slate-600 dark:text-slate-300 transition">
                            {{ $allChecked ? __('Deselect All') : __('Select All') }}
                        </button>
                    </div>

                    <!-- Action Checkbox Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($modActions as $actKey => $actDesc)
                            @php
                                $isChecked = ! empty($grid[$modKey][$actKey]);
                                $isGranular = in_array($actKey, ['convert_to_sale', 'process_payment', 'apply_discount', 'void', 'reconcile', 'finalize_invoice', 'approve']);
                            @endphp
                            <label @class([
                                'p-3 rounded-2xl border transition-all cursor-pointer flex items-start gap-3 select-none',
                                'bg-blue-50/50 border-blue-200 dark:bg-blue-950/30 dark:border-blue-800/80 shadow-2xs' => $isChecked,
                                'bg-slate-50/50 border-slate-150 dark:bg-slate-800/30 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' => ! $isChecked,
                            ])>
                                <input type="checkbox"
                                       wire:model="grid.{{ $modKey }}.{{ $actKey }}"
                                       class="mt-0.5 w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500 transition">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ strtoupper($actKey) }}</span>
                                        @if ($isGranular)
                                            <span class="px-1.5 py-0.2 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                {{ __('Action') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-400 mt-0.5 leading-snug">
                                        {{ __($actDesc) }}
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <!-- Fixed Bottom Save Bar -->
            <div class="sticky bottom-4 z-20 p-4 sm:p-5 bg-slate-900/95 text-white backdrop-blur-md rounded-3xl shadow-2xl border border-slate-700 flex items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-bold text-slate-200">{{ __("Ready to update permissions for") }} {{ $targetUser->name }}?</div>
                    <div class="text-[11px] text-slate-400">{{ __("Changes apply immediately across all modules.") }}</div>
                </div>

                <button type="button"
                        wire:click="save"
                        class="px-8 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-black text-xs sm:text-sm shadow-lg shadow-blue-500/30 active:scale-95 transition-all flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ __("Save Permissions") }}</span>
                </button>
            </div>
        </div>
    @endif

</div>
