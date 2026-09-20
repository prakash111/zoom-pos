<!-- =========================================================================
     TAB: STOREFRONT PAYMENT GATEWAYS CONFIGURATION
     ========================================================================= -->
<div x-show="activeTab === 'payments'" x-cloak class="space-y-6">

    <!-- Overview Banner -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl">
                💳
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Storefront Payment Gateways') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Enable and configure the payment options presented to customers during storefront checkout. Only active gateways appear in the checkout payment selector.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-[11px] font-extrabold text-slate-700 dark:text-slate-300">
                ⚡ {{ __('Real-Time Checkout Filtering') }}
            </span>
        </div>
    </div>

    <!-- Gateways Grid -->
    <div class="space-y-5">

        <!-- 1. Cash on Delivery (COD) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['cod']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                        🚚
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Cash on Delivery (COD)') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Customer pays physically with cash upon courier delivery.') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.cod.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['cod']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label at Checkout') }}</label>
                        <input type="text" wire:model="storefrontGateways.cod.name" placeholder="Cash on Delivery" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer Instructions') }}</label>
                        <input type="text" wire:model="storefrontGateways.cod.instructions" placeholder="{{ __('Pay in cash upon physical delivery.') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                    </div>
                </div>
            @endif
        </div>

        <!-- 2. Store Pickup / Pay at Counter -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['store_pickup']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                        🏪
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Store Pickup / Pay at Counter') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Customer reserves items online and settles payment in person at your physical outlet.') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.store_pickup.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['store_pickup']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label at Checkout') }}</label>
                        <input type="text" wire:model="storefrontGateways.store_pickup.name" placeholder="Pay at Counter" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer Instructions') }}</label>
                        <input type="text" wire:model="storefrontGateways.store_pickup.instructions" placeholder="{{ __('Collect items at our counter and pay via cash or card.') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                    </div>
                </div>
            @endif
        </div>

        <!-- 3. Razorpay (Online / UPI / Cards / NetBanking) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['razorpay']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-sm">
                        Rzp
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Razorpay') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Accept UPI, Debit/Credit Cards, Wallets, and NetBanking.') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.razorpay.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['razorpay']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label') }}</label>
                            <input type="text" wire:model="storefrontGateways.razorpay.name" placeholder="Razorpay (Online Payment)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Razorpay Key ID') }}</label>
                            <input type="text" wire:model="storefrontGateways.razorpay.key_id" placeholder="rzp_live_..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                                <span>{{ __('Razorpay Key Secret') }}</span>
                                @if (!empty($storefrontGateways['razorpay']['has_secret']))
                                    <span class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">✓ {{ __('Saved') }}</span>
                                @endif
                            </label>
                            <input type="password" wire:model="storefrontGateways.razorpay.key_secret" placeholder="{{ !empty($storefrontGateways['razorpay']['has_secret']) ? '••••••••••••••••' : 'Enter Key Secret' }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Checkout Instructions') }}</label>
                            <input type="text" wire:model="storefrontGateways.razorpay.instructions" placeholder="Fast and secure online checkout" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- 4. Stripe (Credit / Debit Cards) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['stripe']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center font-black text-sm">
                        S
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Stripe') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Global credit/debit card processing (Visa, Mastercard, AMEX, Apple Pay, Google Pay).') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.stripe.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['stripe']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label') }}</label>
                            <input type="text" wire:model="storefrontGateways.stripe.name" placeholder="Credit / Debit Card" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Stripe Publishable Key') }}</label>
                            <input type="text" wire:model="storefrontGateways.stripe.publishable_key" placeholder="pk_live_..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                                <span>{{ __('Stripe Secret Key') }}</span>
                                @if (!empty($storefrontGateways['stripe']['has_secret']))
                                    <span class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">✓ {{ __('Saved') }}</span>
                                @endif
                            </label>
                            <input type="password" wire:model="storefrontGateways.stripe.secret_key" placeholder="{{ !empty($storefrontGateways['stripe']['has_secret']) ? '••••••••••••••••' : 'sk_live_...' }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Checkout Instructions') }}</label>
                            <input type="text" wire:model="storefrontGateways.stripe.instructions" placeholder="Pay securely with card" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- 5. PayPal -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['paypal']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center font-black text-sm">
                        PP
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('PayPal') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Allow customers worldwide to pay with their PayPal wallet or stored credit card.') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.paypal.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['paypal']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label') }}</label>
                            <input type="text" wire:model="storefrontGateways.paypal.name" placeholder="PayPal" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Mode') }}</label>
                            <select wire:model="storefrontGateways.paypal.mode" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                                <option value="live">{{ __('Live Production') }}</option>
                                <option value="sandbox">{{ __('Sandbox Testing') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PayPal Client ID') }}</label>
                            <input type="text" wire:model="storefrontGateways.paypal.client_id" placeholder="AXXX..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center justify-between">
                                <span>{{ __('PayPal Client Secret') }}</span>
                                @if (!empty($storefrontGateways['paypal']['has_secret']))
                                    <span class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">✓ {{ __('Saved') }}</span>
                                @endif
                            </label>
                            <input type="password" wire:model="storefrontGateways.paypal.client_secret" placeholder="{{ !empty($storefrontGateways['paypal']['has_secret']) ? '••••••••••••••••' : 'Enter Secret' }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Checkout Instructions') }}</label>
                            <input type="text" wire:model="storefrontGateways.paypal.instructions" placeholder="Safe online payment with PayPal" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- 6. Direct UPI / QR Code -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border {{ !empty($storefrontGateways['upi']['enabled']) ? 'border-blue-500/50 shadow-sm' : 'border-slate-100 dark:border-slate-800' }} transition space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-sm">
                        UPI
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Direct UPI / QR Code') }}</h4>
                        <p class="text-xs text-slate-400">{{ __('Display merchant UPI ID or dynamic payment prompt directly on storefront checkout.') }}</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="storefrontGateways.upi.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>

            @if (!empty($storefrontGateways['upi']['enabled']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Display Label') }}</label>
                        <input type="text" wire:model="storefrontGateways.upi.name" placeholder="Instant UPI / QR" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Merchant UPI ID / VPA *') }}</label>
                        <input type="text" wire:model="storefrontGateways.upi.upi_id" placeholder="merchant@okhdfcbank" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-bold text-blue-600 dark:text-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Checkout Instructions') }}</label>
                        <input type="text" wire:model="storefrontGateways.upi.instructions" placeholder="{{ __('Scan QR or pay directly via any UPI app.') }}" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium">
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
