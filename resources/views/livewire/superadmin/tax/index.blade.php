<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🌐</span>
                <span>{{ __("Global Tax & E-Invoicing Reference Matrix") }}</span>
            </h3>
            <p class="text-xs text-slate-400">{{ __("Standard multi-jurisdiction fiscal rate presets, component breakdowns (CGST/SGST/VAT), and e-invoicing compliance schemes") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-black bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                11 Sovereign Jurisdictions
            </span>
        </div>
    </div>

    <!-- Country Presets Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($jurisdictions as $code => $j)
            <div @class([
                    'p-5 rounded-3xl border transition-all cursor-pointer shadow-sm',
                    'bg-white dark:bg-slate-900 border-blue-500 ring-2 ring-blue-500/20 shadow-md' => $selectedCountry === $code,
                    'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800 hover:border-blue-400' => $selectedCountry !== $code,
                 ])
                 wire:click="selectCountry('{{ $code }}')">

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-mono font-black text-xs text-blue-600 dark:text-blue-400">
                            {{ $code }}
                        </span>
                        <div>
                            <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">{{ $j['country'] }}</h4>
                            <span class="text-[11px] text-slate-400 font-semibold">{{ $j['system'] }}</span>
                        </div>
                    </div>

                    <span class="px-2.5 py-1 rounded-full text-xs font-black bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 font-mono">
                        {{ $j['standard_rate'] }}%
                    </span>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ count($j['rules']) }} {{ __("Tax Slabs / Rules") }}</span>
                    <button type="button" class="font-bold text-blue-600 hover:underline">
                        {{ $selectedCountry === $code ? __('Viewing Details') : __('Inspect Rules →') }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Selected Jurisdiction Detailed Inspector Drawer / Modal -->
    @if ($selectedPreset)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-blue-200 dark:border-blue-800/80 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-black text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 rounded-md">{{ $selectedCountry }}</span>
                        <h4 class="text-base font-black text-slate-900 dark:text-white">{{ $selectedPreset['country'] }} — {{ $selectedPreset['system'] }}</h4>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __("Standard pre-seeded tax definitions and sub-component splits") }}</p>
                </div>
                <button type="button" wire:click="selectCountry(null)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold">
                    ✕
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">{{ __("Tax Name / Code") }}</th>
                            <th class="px-4 py-3">{{ __("Rate") }}</th>
                            <th class="px-4 py-3">{{ __("Inclusive/Exclusive") }}</th>
                            <th class="px-4 py-3">{{ __("Sub-Components (Split)") }}</th>
                            <th class="px-4 py-3">{{ __("Description & Scope") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach ($selectedPreset['rules'] as $rule)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200">
                                    <div>{{ $rule['name'] }}</div>
                                    <span class="font-mono text-[10px] text-slate-400">{{ $rule['code'] }}</span>
                                    @if ($rule['is_default'])
                                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Default
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono font-black text-blue-600 dark:text-blue-400">
                                    {{ $rule['rate'] }}%
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rule['is_inclusive'] ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ $rule['is_inclusive'] ? 'Inclusive' : 'Exclusive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if (!empty($rule['sub_components']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($rule['sub_components'] as $sub)
                                                <span class="px-2 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-bold font-mono">
                                                    {{ $sub['name'] }}: {{ $sub['rate'] }}%
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">{{ __("Single Component") }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-[11px] max-w-xs">
                                    {{ $rule['description'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
