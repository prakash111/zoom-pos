<!-- Custom Tax Rule Creation / Edit Modal -->
<x-modal wire:model="showTaxRuleModal" maxWidth="lg" :title="$editingTaxRuleId ? __('Edit Tax Rule') : __('Create Custom Tax Rule')" :subtitle="__('Configure tax rates, inclusive/exclusive calculation, and sub-component splits.')" icon="🧾">
    <div class="space-y-4 text-xs">
        <!-- Tax Name -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Tax Rule Name *') }}</label>
            <input type="text" wire:model="taxRuleName" placeholder="{{ __('e.g. GST 18%, State VAT 5%, Zero-Rated Export') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
            @error('taxRuleName') <p class="text-rose-600 text-[10px] mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Rate & Code -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Tax Rate (%) *') }}</label>
                <input type="number" step="0.01" wire:model="taxRuleRate" placeholder="18.00" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 font-mono">
                @error('taxRuleRate') <p class="text-rose-600 text-[10px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Tax Code (Optional)') }}</label>
                <input type="text" wire:model="taxRuleCode" placeholder="GST_18" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 font-mono uppercase">
            </div>
        </div>

        <!-- Inclusive vs Exclusive -->
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1.5">{{ __('Tax Calculation Method') }}</label>
            <div class="grid grid-cols-2 gap-2">
                <button type="button"
                        wire:click="$set('taxRuleIsInclusive', false)"
                        class="p-3 rounded-2xl border text-xs font-bold text-left transition cursor-pointer {{ !$taxRuleIsInclusive ? 'bg-blue-50/80 dark:bg-blue-950/60 border-blue-500 text-blue-700 dark:text-blue-300 ring-1 ring-blue-500/30' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400' }}">
                    <span class="block font-black text-xs">{{ __('Exclusive') }}</span>
                    <span class="text-[10px] text-slate-400">{{ __('Tax added on top of product price') }}</span>
                </button>
                <button type="button"
                        wire:click="$set('taxRuleIsInclusive', true)"
                        class="p-3 rounded-2xl border text-xs font-bold text-left transition cursor-pointer {{ $taxRuleIsInclusive ? 'bg-purple-50/80 dark:bg-purple-950/60 border-purple-500 text-purple-700 dark:text-purple-300 ring-1 ring-purple-500/30' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400' }}">
                    <span class="block font-black text-xs">{{ __('Inclusive') }}</span>
                    <span class="text-[10px] text-slate-400">{{ __('Price already includes tax') }}</span>
                </button>
            </div>
        </div>

        <!-- Sub-Components Builder (Split Tax) -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-900 dark:text-white block">{{ __('Sub-Component Split (Optional)') }}</span>
                    <span class="text-[10px] text-slate-400">{{ __('e.g. Split 18% into 9% CGST + 9% SGST') }}</span>
                </div>
                <button type="button"
                        wire:click="addTaxSubComponent"
                        class="px-2.5 py-1 rounded-xl bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 text-xs font-bold hover:bg-blue-200 transition cursor-pointer">
                    + {{ __('Add Component') }}
                </button>
            </div>

            @foreach ($taxRuleSubComponents as $idx => $comp)
                <div class="flex items-center gap-2">
                    <input type="text"
                           wire:model="taxRuleSubComponents.{{ $idx }}.name"
                           placeholder="CGST"
                           class="w-1/2 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs">
                    <input type="number"
                           step="0.01"
                           wire:model="taxRuleSubComponents.{{ $idx }}.rate"
                           placeholder="9.0"
                           class="w-1/3 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                    <button type="button"
                            wire:click="removeTaxSubComponent({{ $idx }})"
                            class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold cursor-pointer">
                        ✕
                    </button>
                </div>
            @endforeach
        </div>

        <!-- Default Rule Checkbox -->
        <div class="flex items-center gap-2 pt-1">
            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="taxRuleIsDefault" class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>{{ __('Set as Default Tax Rule for all new products & POS orders') }}</span>
            </label>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
            {{ __('Cancel') }}
        </button>
        <x-ui.button wire:click="saveTaxRule">
            {{ __('Save Tax Rule') }}
        </x-ui.button>
    </x-slot:footer>
</x-modal>
