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

        <!-- Global Default Localization & Region -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🌐</span> {{ __('Global Default Localization & Region') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Configure platform-wide baseline currency, primary language, and timezone inherited automatically by newly registered tenant stores.') }}
                </p>
            </div>

            <!-- Behavior / Clarification Note -->
            <div class="rounded-2xl p-4 bg-sky-50 dark:bg-sky-950/40 border border-sky-200/80 dark:border-sky-800/60 flex items-start gap-3">
                <span class="text-base sm:text-lg shrink-0">ℹ️</span>
                <div class="text-xs text-sky-800 dark:text-sky-300 font-medium leading-relaxed">
                    <strong>{{ __('Inheritance & Isolation Notice:') }}</strong>
                    {{ __('These defaults will automatically apply to all newly registered stores. Existing active tenants retain their current configuration and can override their currency, language, and timezone individually in their Store Settings.') }}
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Default Platform Currency -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Default Platform Currency') }} <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model.live="platformDefaultCurrency"
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        @foreach ($currencyOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('platformDefaultCurrency') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Auto-sets currency symbol, decimal precision, and formatting for new stores.') }}
                    </p>
                </div>

                <!-- Default Platform Country -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Default Platform Country') }} <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model.live="platformDefaultCountryIso"
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        @foreach ($countryOptions as $iso => $name)
                            <option value="{{ $iso }}">{{ $name }} ({{ $iso }})</option>
                        @endforeach
                    </select>
                    @error('platformDefaultCountryIso') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Default operating country for region presets and contact forms.') }}
                    </p>
                </div>

                <!-- Default Country Dial Code -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Default Phone Country Dial Code') }} <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model="platformDefaultDialCode"
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        @foreach ($dialCodeOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('platformDefaultDialCode') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Pre-selected dial code (e.g. +91) on public forms, contact us, and phone inputs.') }}
                    </p>
                </div>

                <!-- Default Platform Language -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Default Platform Language') }} <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model="platformDefaultLanguage"
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        @foreach ($languageOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('platformDefaultLanguage') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Initial UI locale for new tenant administrators and storefront default.') }}
                    </p>
                </div>

                <!-- Default Platform Timezone -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Default Platform Timezone') }} <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model="platformDefaultTimezone"
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        @foreach ($timezoneOptions as $tz => $tzLabel)
                            <option value="{{ $tz }}">{{ $tzLabel }}</option>
                        @endforeach
                    </select>
                    @error('platformDefaultTimezone') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Standard IANA timezone for order timestamps, daily shifts, and sales analytics.') }}
                    </p>
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

        <!-- Auto-Seed Demo Data on Tenant Signup -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📦</span> {{ __('Auto-Seed Demo Data on Signup') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        {{ __('When enabled, a newly registered tenant automatically has their store populated with sample products, categories, floor tables, and demo transactions. When disabled, new tenants start with a completely clean, empty store — regardless of what the signup form itself requested.') }}
                    </p>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="autoSeedDemoDataOnRegistration" class="sr-only peer">
                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
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
                @foreach (\App\Services\Modular\ModuleRegistry::operatingModules() as $modKey => $mod)
                    @php
                        $g = $moduleGuard[$modKey] ?? ['premium' => false, 'licensed' => true, 'store_link' => null];
                        $locked = $g['premium'] && ! $g['licensed'];
                        $isEnabled = ! $locked && in_array($modKey, $enabledRegistrationModules, true);
                    @endphp
                    <label class="flex items-start justify-between p-4 rounded-2xl border transition-all {{ $locked ? 'border-amber-300/70 dark:border-amber-800/60 bg-amber-50/40 dark:bg-amber-950/20 cursor-default' : 'cursor-pointer' }} {{ $isEnabled ? 'border-indigo-500/80 bg-indigo-50/60 dark:bg-indigo-950/30 ring-1 ring-indigo-500/40' : (! $locked ? 'border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 opacity-75 hover:opacity-100' : '') }}">
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
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ __($mod['title']) }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wide {{ $isEnabled ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $modKey }}
                                    </span>
                                    @if ($locked)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wide bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">{{ __('Not purchased') }}</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __($mod['description']) }}
                                </p>
                                @if ($locked && ! empty($g['store_link']))
                                    <a href="{{ $g['store_link'] }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex mt-1.5 items-center gap-1 px-3 py-1.5 rounded-xl text-[11px] font-extrabold bg-amber-600 hover:bg-amber-700 text-white">
                                        {{ __('Purchase to activate') }} ↗
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="pt-0.5 shrink-0">
                            <input type="checkbox"
                                   wire:model.live="enabledRegistrationModules"
                                   value="{{ $modKey }}"
                                   @disabled($locked)
                                   class="w-5 h-5 rounded-lg border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 {{ $locked ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}">
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
                        class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                    <span wire:loading.remove wire:target="saveGeneral">💾 {{ __('Save Platform Settings') }}</span>
                    <span wire:loading wire:target="saveGeneral">⏳ {{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
