<div class="w-full space-y-6">
    
    <!-- Flash Messages -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-rose-200 dark:border-rose-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- SMTP Configuration Card matching Tenant Settings rounded aesthetic -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>✉️ Global Platform SMTP Email Configuration</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                Configure primary mail server settings used for platform notifications, registration verification, tenant onboarding, and default invoice deliveries.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            
            <!-- SMTP Host -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Host Server *</label>
                <input type="text"
                       wire:model="smtpHost"
                       placeholder="smtp.mailgun.org, smtp.sendgrid.net, smtp.gmail.com…"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                @error('smtpHost') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- SMTP Port -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Port *</label>
                <input type="number"
                       wire:model="smtpPort"
                       placeholder="587"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                @error('smtpPort') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Encryption -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Encryption Protocol *</label>
                <select wire:model="smtpEncryption"
                        class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                    <option value="tls">TLS (Port 587 - Recommended)</option>
                    <option value="ssl">SSL (Port 465)</option>
                    <option value="">None (Port 25)</option>
                </select>
                @error('smtpEncryption') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Username -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">SMTP Username / API Key</label>
                <input type="text"
                       wire:model="smtpUsername"
                       placeholder="apikey, username@domain.com…"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                @error('smtpUsername') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
                    <span>SMTP Password</span>
                    @if ($hasStoredPassword)
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">● Saved securely</span>
                    @endif
                </label>
                <input type="password"
                       wire:model="smtpPassword"
                       placeholder="{{ $hasStoredPassword ? '••••••••••••••••' : 'Enter SMTP password' }}"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                @error('smtpPassword') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- FROM EMAIL ADDRESS -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                    <span>Email From Address (Sender Email) *</span>
                </label>
                <input type="email"
                       wire:model="smtpFromAddress"
                       placeholder="no-reply@saas.zoomnearby.com, billing@yourdomain.com"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                <p class="text-[11px] text-slate-400 mt-1">This email address will appear in the "From:" header of all outgoing system emails.</p>
                @error('smtpFromAddress') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- FROM NAME -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Email From Name (Sender Name) *</label>
                <input type="text"
                       wire:model="smtpFromName"
                       placeholder="Smart Inventory Platform, SaaS Billing…"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 font-medium">
                <p class="text-[11px] text-slate-400 mt-1">Brand or company name displayed to recipients.</p>
                @error('smtpFromName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
            <button wire:click="save"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                <span wire:loading.remove>Save SMTP Configuration</span>
                <span wire:loading>Saving...</span>
            </button>
        </div>

    </div>

    <!-- Live Test Email Dispatch Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
        <div>
            <h4 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🧪 Send Test Verification Email</span>
            </h4>
            <p class="text-xs text-slate-400">Send an immediate test dispatch to verify that your SMTP host credentials and sender address are working properly.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <input type="email"
                   wire:model="testEmailTo"
                   placeholder="admin@yourcompany.com"
                   class="flex-1 rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-indigo-500 focus:border-indigo-500 py-3 px-4 font-medium">
            
            <button wire:click="sendTest"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-6 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs sm:text-sm transition active:scale-95 flex items-center justify-center gap-2 shrink-0 cursor-pointer shadow-2xs">
                <span wire:loading.remove>Send Test Email</span>
                <span wire:loading>Sending Test...</span>
            </button>
        </div>
    </div>

</div>
