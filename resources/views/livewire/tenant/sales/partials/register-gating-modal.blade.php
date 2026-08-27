<x-modal wire:model="showRegisterGatingModal" maxWidth="lg" :title="! $isStaleMidnightRegister ? __('Open Daily Cash Register') : __('Previous Day Shift Settlement Required')" :subtitle="! $isStaleMidnightRegister ? __('Mandatory shift initialization before checkout operations.') : __('Midnight session rollover: Settle previous shift before continuing.')" :icon="! $isStaleMidnightRegister ? '🔓' : '⏰'">
    
    @if (! $isStaleMidnightRegister)
        <!-- 1. OPEN DAILY CASH REGISTER FORM -->
        <div class="space-y-4 text-xs font-sans">
            <!-- Cashier & Terminal Info -->
            <div class="grid grid-cols-2 gap-3 p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Active Cashier") }}</span>
                    <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ auth('web')->user()?->name ?? 'Cashier' }}</div>
                </div>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Date / Shift") }}</span>
                    <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ now()->format('d M Y, H:i') }}</div>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Terminal / Workstation Name") }}</label>
                <input type="text" wire:model="gatingTerminalId" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[11px] font-semibold text-slate-500">{{ __("Opening Cash Float Amount ($)") }}</label>
                    <button type="button" wire:click="$toggle('showGatingDenominations')" class="text-[11px] font-extrabold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                        {{ $showGatingDenominations ? __('Hide Calculator') : __('Denomination Calculator') }}
                    </button>
                </div>
                <input type="number" min="0" step="0.01" wire:model="gatingOpeningBalance" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xl font-mono font-black text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                @error('gatingOpeningBalance') <p class="text-rose-600 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
            </div>

            @if ($showGatingDenominations)
                <div class="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-2">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Physical Cash Denomination Counter") }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['500', '200', '100', '50', '20', '10', '5', '2', '1'] as $denom)
                            <div class="flex items-center gap-1.5">
                                <span class="w-10 text-right font-mono font-bold text-xs text-slate-600 dark:text-slate-300">${{ $denom }}:</span>
                                <input type="number" min="0" wire:model.live="gatingDenominations.{{ $denom }}" class="w-14 py-1 px-1.5 text-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-mono text-xs font-bold">
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Opening Shift Remarks (Optional)") }}</label>
                <textarea wire:model="gatingOpeningNotes" rows="2" placeholder="{{ __('e.g. Received change drawer from safe') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <a href="{{ route('tenant.dashboard') }}" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs active:scale-[0.97] transition duration-150 ease-out">
                    &larr; {{ __("Exit POS") }}
                </a>
                <button type="button" wire:click="openRegisterFromPos" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:via-indigo-500 hover:to-blue-600 text-white font-black text-xs shadow-md shadow-indigo-500/20 active:scale-[0.97] transition duration-150 ease-out cursor-pointer">
                    {{ __("Confirm & Open Shift") }} &rarr;
                </button>
            </div>
        </x-slot:footer>

    @else
        <!-- 2. STALE MIDNIGHT SHIFT SETTLEMENT LOCK -->
        <div class="space-y-4 text-xs font-sans">
            <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200">
                {{ __("An active cash register session from a previous day is still open. Financial compliance requires closing yesterday's register and recording physical drawer counts before opening today's session.") }}
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("System Expected Cash") }}</span>
                    <div class="text-lg font-mono font-black text-slate-900 dark:text-white">${{ number_format($staleExpectedCash, 2) }}</div>
                </div>
                <span class="text-xs text-slate-400 font-semibold">{{ __("Previous Day Shift") }}</span>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Physical Counted Cash in Drawer ($)") }}</label>
                <input type="number" min="0" step="0.01" wire:model.live="staleCountedCash" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-lg font-mono font-black text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>

            @php
                $staleVariance = round($staleCountedCash - $staleExpectedCash, 2);
            @endphp
            <div @class([
                'p-3 rounded-2xl flex items-center justify-between border',
                'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 text-emerald-800 dark:text-emerald-200' => $staleVariance == 0,
                'bg-rose-50 dark:bg-rose-950/40 border-rose-200 text-rose-800 dark:text-rose-200' => $staleVariance < 0,
                'bg-amber-50 dark:bg-amber-950/40 border-amber-200 text-amber-800 dark:text-amber-200' => $staleVariance > 0,
            ])>
                <span class="font-bold text-xs uppercase">{{ $staleVariance == 0 ? __('Balanced') : ($staleVariance < 0 ? __('Cash Shortage') : __('Cash Overage')) }}</span>
                <span class="font-mono font-black text-sm">{{ $staleVariance >= 0 ? '+' : '' }}${{ number_format($staleVariance, 2) }}</span>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Settlement Remarks") }}</label>
                <textarea wire:model="staleClosingNotes" rows="2" placeholder="{{ __('e.g. Midnight rollover settlement; safe drop done') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-end w-full">
                <button type="button" wire:click="settleStaleRegisterFromPos" class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs shadow-lg shadow-amber-600/25 active:scale-[0.97] transition duration-150 ease-out cursor-pointer">
                    {{ __("Settle & Close Previous Shift") }} &rarr;
                </button>
            </div>
        </x-slot:footer>
    @endif

</x-modal>
