<!-- =========================================================================
     TAB: TAXES & COMPLIANCE (Multi-Jurisdiction Rules & Component Splits)
     ========================================================================= -->
<div x-show="activeTab === 'taxes'" x-cloak class="space-y-6">
    
    <!-- Header Summary & Quick Seed -->
    <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>⚖️</span>
                <span>{{ __('Fiscal Tax Rules & Multi-Jurisdiction Engine') }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                {{ __('Configure sovereign tax classes, inclusive/exclusive calculation methods, and sub-component splits (CGST/SGST/VAT) for POS, Invoices & Quotations.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Multi-Country Jurisdiction Quick Pre-Seeder Dropdown -->
            <div class="relative inline-block text-left" x-data="{ open: false }">
                <button type="button" 
                        @click="open = !open" 
                        class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-extrabold flex items-center gap-2 transition active:scale-95 cursor-pointer shadow-2xs border border-slate-200 dark:border-slate-700">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>{{ __('Pre-Seed Country Tax Rules') }}</span>
                    <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <!-- Dropdown Menu -->
                <div x-show="open" 
                     @click.outside="open = false" 
                     x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     class="absolute right-0 mt-2 w-72 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 py-2 z-50 max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">

                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        {{ __('Select Sovereign Jurisdiction') }}
                    </div>

                    @php
                        $countries = [
                            ['code' => 'IN', 'name' => 'India', 'rule' => 'GST (18% - CGST 9% / SGST 9%)'],
                            ['code' => 'US', 'name' => 'United States', 'rule' => 'State & Local Sales Tax (8.25%)'],
                            ['code' => 'GB', 'name' => 'United Kingdom', 'rule' => 'HMRC Standard VAT (20%)'],
                            ['code' => 'AE', 'name' => 'United Arab Emirates', 'rule' => 'FTA VAT (5%)'],
                            ['code' => 'SA', 'name' => 'Saudi Arabia', 'rule' => 'ZATCA VAT (15%)'],
                            ['code' => 'CA', 'name' => 'Canada', 'rule' => 'CRA GST/HST (13%)'],
                            ['code' => 'AU', 'name' => 'Australia', 'rule' => 'ATO GST (10%)'],
                            ['code' => 'EU', 'name' => 'European Union', 'rule' => 'Standard VAT (21%)'],
                            ['code' => 'SG', 'name' => 'Singapore', 'rule' => 'IRAS GST (9%)'],
                            ['code' => 'BR', 'name' => 'Brazil', 'rule' => 'ICMS / PIS / COFINS (18%)'],
                            ['code' => 'MX', 'name' => 'Mexico', 'rule' => 'SAT IVA (16%)']
                        ];
                    @endphp

                    @foreach($countries as $c)
                        <button type="button" 
                                wire:click="preSeedTaxRules('{{ $c['code'] }}')" 
                                @click="open = false" 
                                class="w-full text-left px-3.5 py-2 hover:bg-blue-50 dark:hover:bg-slate-700/60 transition flex items-center justify-between group cursor-pointer">
                            <div>
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    <span class="inline-block w-6 text-[11px] font-bold font-mono text-slate-400">{{ $c['code'] }}</span> {{ $c['name'] }}
                                </div>
                                <div class="text-[10px] text-slate-400 pl-6">{{ $c['rule'] }}</div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button"
                    wire:click="newTaxRule"
                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md shadow-blue-500/20">
                <span>+</span>
                <span>{{ __('Create Custom Tax Rule') }}</span>
            </button>
        </div>
    </div>

    <!-- Active Tax Rules Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                {{ __('Store Tax Rates & Rules Matrix') }}
            </div>
            <span class="text-xs text-slate-400 font-mono">
                {{ count($taxRules) }} {{ __('rules configured') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('Rule Name & Code') }}</th>
                        <th class="px-6 py-3.5">{{ __('Rate') }}</th>
                        <th class="px-6 py-3.5">{{ __('Method') }}</th>
                        <th class="px-6 py-3.5">{{ __('Sub-Components (Tax Split)') }}</th>
                        <th class="px-6 py-3.5">{{ __('Default') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($taxRules as $rule)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4">
                                <div class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm">{{ $rule->tax_name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $rule->tax_code ?: 'TAX_RULE' }}</div>
                                @if ($rule->description)
                                    <div class="text-[10px] text-slate-400 italic mt-0.5">{{ $rule->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-mono font-black text-blue-600 dark:text-blue-400 text-sm">
                                {{ (float)$rule->rate }}%
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $rule->is_inclusive ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' }}">
                                    {{ $rule->is_inclusive ? __('Inclusive (Built-in)') : __('Exclusive (Added at Subtotal)') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if (!empty($rule->sub_components))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($rule->sub_components as $sub)
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold font-mono">
                                                {{ $sub['name'] ?? 'Tax' }}: {{ $sub['rate'] ?? 0 }}%
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">{{ __('Single Tax Rate') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($rule->is_default)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        ★ {{ __('Default Store Rule') }}
                                    </span>
                                @else
                                    <button type="button"
                                            wire:click="setDefaultTaxRule('{{ $rule->id }}')"
                                            class="text-[11px] font-bold text-slate-400 hover:text-blue-600 cursor-pointer">
                                        {{ __('Set as Default') }}
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button type="button"
                                        wire:click="editTaxRule('{{ $rule->id }}')"
                                        class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-bold transition cursor-pointer">
                                    {{ __('Edit') }}
                                </button>
                                <button type="button"
                                        wire:click="deleteTaxRule('{{ $rule->id }}')"
                                        wire:confirm="{{ __('Are you sure you want to delete this tax rule?') }}"
                                        class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-bold transition cursor-pointer">
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                                <p>{{ __('No custom tax rules configured yet.') }}</p>
                                <button type="button" wire:click="seedJurisdictionTaxRules" class="mt-2 text-xs text-blue-600 font-bold hover:underline">
                                    {{ __('Click to auto-populate standard :country tax presets', ['country' => $company->country ?: 'US']) }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
