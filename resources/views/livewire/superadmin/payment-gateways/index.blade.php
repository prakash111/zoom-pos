<div class="w-full space-y-6">
    
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="border-b border-slate-200 dark:border-slate-800 pb-2">
        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ __("Platform Payment Gateways") }}</h3>
        <p class="text-xs text-slate-400">{{ __("Configure global checkout processors for tenant subscription billing and renewals") }}</p>
    </div>

    @php
        $webhookMeta = [
            'stripe' => [
                'webhook' => route('webhooks.stripe'),
                'callback' => null,
                'events' => ['checkout.session.completed', 'invoice.payment_succeeded', 'customer.subscription.deleted'],
            ],
            'paypal' => [
                'webhook' => route('webhooks.paypal'),
                'callback' => route('subscription.payment.callback', ['gateway' => 'paypal']),
                'events' => ['PAYMENT.CAPTURE.COMPLETED', 'BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.CANCELLED'],
            ],
            'razorpay' => [
                'webhook' => route('webhooks.razorpay'),
                'callback' => route('subscription.payment.callback', ['gateway' => 'razorpay']),
                'events' => ['order.paid', 'payment.captured'],
            ],
            'mercadopago' => [
                'webhook' => route('webhooks.mercadopago'),
                'callback' => route('subscription.payment.callback', ['gateway' => 'mercadopago']),
                'events' => ['payment', 'subscription_preapproval'],
            ],
        ];
    @endphp

    @foreach (['stripe' => ['Stripe Payments', '💳', 'Instant Card & Wallet Processing'], 'paypal' => ['PayPal Commerce', '🅿️', 'Global Express Checkout'], 'razorpay' => ['Razorpay Gateway', '⚡', 'UPI, Cards & NetBanking'], 'mercadopago' => ['Mercado Pago', '🟦', 'Latin America cards, PIX and wallets']] as $key => [$label, $icon, $desc])
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
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Operating Mode") }}</label>
                    <select wire:model="gateways.{{ $key }}.mode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                        <option value="test">{{ __("Sandbox / Test Mode") }}</option>
                        <option value="live">{{ __("Production / Live Mode") }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Public / Publishable API Key") }}</label>
                    <input type="text" wire:model="gateways.{{ $key }}.public_key" placeholder="pk_test_..." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                        <span>Secret Key / Access Token</span>
                        @if ($this->hasStoredSecret($key))
                            <span class="text-[10px] text-emerald-600 font-normal">● {{ __("Saved securely") }}</span>
                        @endif
                    </label>
                    <input type="password" wire:model="gateways.{{ $key }}.secret_key"
                           placeholder="{{ $this->hasStoredSecret($key) ? '••••••••••••••••' : 'Enter Secret Key' }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                        <span>{{ __('Webhook Signing Secret') }}</span>
                        @if ($this->hasStoredWebhookSecret($key))
                            <span class="text-[10px] text-emerald-600 font-normal">● {{ __('Saved securely') }}</span>
                        @endif
                    </label>
                    <input type="password" wire:model="gateways.{{ $key }}.webhook_secret"
                           placeholder="{{ $this->hasStoredWebhookSecret($key) ? '••••••••••••••••' : ($key === 'stripe' ? 'whsec_...' : __('Signing secret from the provider webhook config')) }}"
                           class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">{{ __('Used to verify the authenticity of incoming webhook calls.') }}</p>
                </div>
            </div>

            {{-- Copyable Webhook / Callback URLs for the provider's developer console --}}
            @php($meta = $webhookMeta[$key] ?? null)
            @if ($meta)
                <div class="mt-1 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" /></svg>
                        <span class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">{{ __('Webhook & Callback URLs') }}</span>
                    </div>

                    @foreach (array_filter(['Webhook / IPN URL' => $meta['webhook'], 'Callback / Return URL' => $meta['callback']]) as $urlLabel => $urlValue)
                        @php($fieldId = 'gw-url-' . $key . '-' . \Illuminate\Support\Str::slug($urlLabel))
                        <div>
                            <label class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">{{ $urlLabel }}</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $urlValue }}" id="{{ $fieldId }}"
                                       onclick="this.select()"
                                       class="text-xs font-mono bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg px-2.5 py-1.5 w-full select-all text-slate-800 dark:text-slate-200">
                                <button type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('{{ $fieldId }}').value).then(()=>{const b=this;const t=b.textContent;b.textContent='Copied!';b.classList.add('bg-emerald-600');setTimeout(()=>{b.textContent=t;b.classList.remove('bg-emerald-600');},1400);});"
                                        class="shrink-0 px-3 py-1.5 bg-slate-800 dark:bg-slate-700 text-white rounded-lg text-xs font-bold hover:bg-slate-700 dark:hover:bg-slate-600 transition cursor-pointer">
                                    {{ __('Copy') }}
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Paste the URL(s) above into the') }} <span class="font-semibold">{{ $label }}</span> {{ __('developer dashboard.') }}
                        {{ __('Subscribe to these events:') }}
                        <span class="font-mono text-slate-700 dark:text-slate-300">{{ implode(', ', $meta['events']) }}</span>
                    </p>
                </div>
            @endif
        </div>
    @endforeach

    <div class="flex justify-end pt-2">
        <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
            {{ __('Save Gateway Settings') }}
        </button>
    </div>

</div>
