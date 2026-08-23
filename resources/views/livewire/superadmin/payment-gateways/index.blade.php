<div class="w-full space-y-6">
    
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="border-b border-slate-200 dark:border-slate-800 pb-2">
        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Platform Payment Gateways</h3>
        <p class="text-xs text-slate-400">Configure global checkout processors for tenant subscription billing and renewals</p>
    </div>

    @foreach (['stripe' => ['Stripe Payments', '💳', 'Instant Card & Wallet Processing'], 'paypal' => ['PayPal Commerce', '🅿️', 'Global Express Checkout'], 'razorpay' => ['Razorpay Gateway', '⚡', 'UPI, Cards & NetBanking']] as $key => [$label, $icon, $desc])
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg shadow-sm">
                        {{ $icon }}
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">{{ $label }}</h4>
                        <p class="text-xs text-slate-400">{{ $desc }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="gateways.{{ $key }}.enabled" id="gw_{{ $key }}" class="rounded-lg text-indigo-600 focus:ring-indigo-500">
                    <label for="gw_{{ $key }}" class="text-xs font-bold text-slate-700 dark:text-slate-300">Enable Gateway</label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Operating Mode</label>
                    <select wire:model="gateways.{{ $key }}.mode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                        <option value="test">Sandbox / Test Mode</option>
                        <option value="live">Production / Live Mode</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Public / Publishable API Key</label>
                    <input type="text" wire:model="gateways.{{ $key }}.public_key" placeholder="pk_test_..." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                        <span>Secret Key / Access Token</span>
                        @if ($this->hasStoredSecret($key))
                            <span class="text-[10px] text-emerald-600 font-normal">● Saved securely</span>
                        @endif
                    </label>
                    <input type="password" wire:model="gateways.{{ $key }}.secret_key"
                           placeholder="{{ $this->hasStoredSecret($key) ? '••••••••••••••••' : 'Enter Secret Key' }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>
            </div>
        </div>
    @endforeach

    <div class="flex justify-end pt-2">
        <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
            Save Gateway Settings
        </button>
    </div>

</div>
