<div class="space-y-6">
    <form wire:submit="saveGeneral" class="space-y-6">
        <!-- System Configuration Details -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏢</span> {{ __('Platform General Configuration') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Base application identity, currency symbols, and runtime timezone defaults.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- App Name -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Platform Title / App Name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="appName"
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('appName') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- App Currency -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Platform Default Currency') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="appCurrency"
                           placeholder="USD, EUR, INR, GBP, BRL..."
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('appCurrency') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- App Timezone -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('System Timezone') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="appTimezone"
                           placeholder="UTC, America/New_York, Asia/Kolkata..."
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('appTimezone') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Min Client Build Version -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Minimum Client Build Version') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="minClientBuildVersion"
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('minClientBuildVersion') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- App Version -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Current Platform Version') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="appVersion"
                           class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    @error('appVersion') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Maintenance Mode & Emergency Lock -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🚧</span> {{ __('System Maintenance Mode') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('When active, non-superadmin tenants and public landing visitors will be served a friendly maintenance splash screen.') }}
                    </p>
                </div>
                
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="maintenanceMode" class="sr-only peer">
                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                </label>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Maintenance Banner Message') }}
                </label>
                <textarea wire:model="maintenanceMessage"
                          rows="3"
                          placeholder="{{ __('We are performing scheduled core maintenance. System operations will resume shortly.') }}"
                          class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                @error('maintenanceMessage') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Receipt Branding -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🏷️</span> {{ __('"Powered By" Receipt Branding') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('When enabled, receipts, invoices, and quotations across every tenant show a "Powered by" footer crediting the platform.') }}
                    </p>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="showPoweredBy" class="sr-only peer">
                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer active:scale-95">
                    <span wire:loading.remove wire:target="saveGeneral">💾 {{ __('Save Platform Settings') }}</span>
                    <span wire:loading wire:target="saveGeneral">⏳ {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
