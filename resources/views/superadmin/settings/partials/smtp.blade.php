<div class="space-y-6">
    <!-- Global SMTP Form -->
    <form wire:submit="saveSmtp" class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>✉️</span> {{ __('Global Platform SMTP Email Configuration') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Configure primary mail server settings used for platform notifications, registration verification, tenant onboarding, and default invoice deliveries.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Host -->
            <div class="space-y-2 md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('SMTP Host Server') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="smtpHost"
                       placeholder="smtp.mailgun.org, smtp.sendgrid.net, smtp.gmail.com..."
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                @error('smtpHost') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Port -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Port') }} <span class="text-rose-500">*</span>
                </label>
                <input type="number"
                       wire:model="smtpPort"
                       placeholder="587"
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                @error('smtpPort') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Encryption -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Encryption Protocol') }}
                </label>
                <select wire:model="smtpEncryption"
                        class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <option value="tls">{{ __('TLS (Port 587 - Recommended)') }}</option>
                    <option value="ssl">{{ __('SSL (Port 465)') }}</option>
                    <option value="">{{ __('None / Unencrypted (Port 25)') }}</option>
                </select>
                @error('smtpEncryption') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Username -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('SMTP Username / API Key') }}
                </label>
                <input type="text"
                       wire:model="smtpUsername"
                       placeholder="apikey, username@domain.com..."
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                @error('smtpUsername') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                    <span>{{ __('SMTP Password') }}</span>
                    @if ($hasStoredPassword)
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">✓ {{ __('Stored Securely') }}</span>
                    @endif
                </label>
                <input type="password"
                       wire:model="smtpPassword"
                       placeholder="{{ $hasStoredPassword ? '•••••••••••• (Leave blank to keep current)' : 'Enter SMTP password' }}"
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                @error('smtpPassword') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- From Address -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Email From Address (Sender Email)') }} <span class="text-rose-500">*</span>
                </label>
                <input type="email"
                       wire:model="smtpFromAddress"
                       placeholder="notifications@yourdomain.com"
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <p class="text-[10px] text-slate-400">{{ __('This email address will appear in the "From:" header of all outgoing system emails.') }}</p>
                @error('smtpFromAddress') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- From Name -->
            <div class="space-y-2 md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Email From Name (Sender Name)') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="smtpFromName"
                       placeholder="Smart Inventory & Sales"
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <p class="text-[10px] text-slate-400">{{ __('Brand or company name displayed to recipients.') }}</p>
                @error('smtpFromName') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer active:scale-95">
                <span wire:loading.remove wire:target="saveSmtp">💾 {{ __('Save SMTP Configuration') }}</span>
                <span wire:loading wire:target="saveSmtp">⏳ {{ __('Saving...') }}</span>
            </button>
        </div>
    </form>

    <!-- Test Email Verification Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-4">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🧪</span> {{ __('Send Test Verification Email') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Send an immediate test dispatch to verify that your SMTP host credentials and sender address are working properly.') }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="w-full sm:flex-1 space-y-1">
                <input type="email"
                       wire:model="testEmailTo"
                       placeholder="your-email@domain.com"
                       class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                @error('testEmailTo') <p class="text-[11px] text-rose-500 font-bold">{{ $message }}</p> @enderror
            </div>

            <button type="button"
                    wire:click="sendSmtpTest"
                    wire:loading.attr="disabled"
                    class="w-full sm:w-auto px-6 py-2.5 rounded-2xl bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-md active:scale-95 shrink-0">
                <span wire:loading.remove wire:target="sendSmtpTest">🚀 {{ __('Send Test Email') }}</span>
                <span wire:loading wire:target="sendSmtpTest">⏳ {{ __('Sending...') }}</span>
            </button>
        </div>
    </div>
</div>
