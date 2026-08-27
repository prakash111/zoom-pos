<div>
    @if ($isDesktop)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Desktop Printers') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('This device only — pick a printer to print silently, no dialog, for each paper size.') }}</p>
                </div>
            </div>

            @if (empty($printers))
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No printers detected on this machine. Leave blank to keep using the print dialog.') }}</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('58mm Thermal Receipts') }}</label>
                        <select wire:model="printer58mm" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                            <option value="">{{ __('Use print dialog') }}</option>
                            @foreach ($printers as $p)
                                <option value="{{ $p['name'] }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('80mm Thermal Receipts') }}</label>
                        <select wire:model="printer80mm" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                            <option value="">{{ __('Use print dialog') }}</option>
                            @foreach ($printers as $p)
                                <option value="{{ $p['name'] }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('A4 Invoices & Quotations') }}</label>
                        <select wire:model="printerA4" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                            <option value="">{{ __('Use print dialog') }}</option>
                            @foreach ($printers as $p)
                                <option value="{{ $p['name'] }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 rounded-xl text-xs font-black bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white transition cursor-pointer">
                    {{ __('Save Printer Preferences') }}
                </button>
            @endif
        </div>
    @endif
</div>
