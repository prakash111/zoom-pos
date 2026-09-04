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
        </div>

        <!-- Allowed Registration Modes & Module Governance -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🧩</span> {{ __('SuperAdmin Module Governance & Registration Modes') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Globally enable or disable modules available across the platform. Only active modules configured here will appear as selectable store types during new tenant signups on web and mobile.') }}
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 w-fit">
                    {{ count($enabledRegistrationModules) }} {{ __('Active') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                @foreach (\App\Services\Modular\ModuleRegistry::allModules() as $modKey => $mod)
                    @php
                        $isEnabled = in_array($modKey, $enabledRegistrationModules, true);
                    @endphp
                    <label class="flex items-start justify-between p-4 rounded-2xl border transition-all cursor-pointer {{ $isEnabled ? 'border-indigo-500/80 bg-indigo-50/60 dark:bg-indigo-950/30 ring-1 ring-indigo-500/40' : 'border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 opacity-75 hover:opacity-100' }}">
                        <div class="flex items-start gap-3.5 pr-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $isEnabled ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">
                                @if ($modKey === 'restaurant')
                                    <span class="text-lg">🍽️</span>
                                @elseif ($modKey === 'pharmacy')
                                    <span class="text-lg">💊</span>
                                @elseif ($modKey === 'service_booking')
                                    <span class="text-lg">✂️</span>
                                @else
                                    <span class="text-lg">🏪</span>
                                @endif
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ __($mod['title']) }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wide {{ $isEnabled ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $modKey }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __($mod['description']) }}
                                </p>
                            </div>
                        </div>
                        <div class="pt-0.5 shrink-0">
                            <input type="checkbox"
                                   wire:model.live="enabledRegistrationModules"
                                   value="{{ $modKey }}"
                                   class="w-5 h-5 rounded-lg border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        </div>
                    </label>
                @endforeach
            </div>
            @error('enabledRegistrationModules') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
        </div>

        <!-- Platform AI Product Image Generation -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✨</span> {{ __('Platform AI Product Image Generation') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('When enabled, tenants without their own AI API key (Store Settings > Integrations) can still generate product images using this platform-wide key.') }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" wire:model.live="aiImageEnabled" class="sr-only peer">
                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            @if ($aiImageEnabled)
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Default Provider') }}</label>
                    <select wire:model="aiImageProvider" class="w-full sm:w-64 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                        <option value="openai">OpenAI (DALL-E 3)</option>
                        <option value="gemini">Google Gemini (Imagen)</option>
                        <option value="claude">Claude (+ DALL-E synthesis)</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('OpenAI API Key') }}</label>
                        <input type="password" wire:model="aiImageOpenaiApiKey" placeholder="{{ $hasAiImageOpenaiApiKey ? '••••••••••••••••' : 'sk-proj-...' }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Gemini API Key') }}</label>
                        <input type="password" wire:model="aiImageGeminiApiKey" placeholder="{{ $hasAiImageGeminiApiKey ? '••••••••••••••••' : 'AIzaSy...' }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Claude API Key') }}</label>
                        <input type="password" wire:model="aiImageClaudeApiKey" placeholder="{{ $hasAiImageClaudeApiKey ? '••••••••••••••••' : 'sk-ant-...' }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>
                </div>
            @endif

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
