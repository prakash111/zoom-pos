<div class="space-y-6">

    <!-- Top Flash Messages -->
    @if (session('status') || $successMessage)
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-xs border border-emerald-200 dark:border-emerald-800">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') ?: $successMessage }}</span>
        </div>
    @endif

    @if (session('error') || $errorMessage)
        <div class="px-5 py-3.5 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-xs border border-rose-200 dark:border-rose-800">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ session('error') ?: $errorMessage }}</span>
        </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>💳 {{ __("Subscription & Tax Billing") }}</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Manage your store subscription plan, redeem activation keys, and access compliant tax invoices") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.settings.index') }}" class="px-4 py-2 rounded-2xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-xs border border-slate-200 dark:border-slate-700 transition">
                ⚙️ {{ __("Store Settings") }}
            </a>
        </div>
    </div>

    <!-- 1. Current Subscription Status & Activation Key Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Subscription Status Card (2 cols) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-slate-400">{{ __("Current Store Plan") }}</div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1 capitalize">
                        {{ $company->plan_name ? ucfirst($company->plan_name) . ' ' . __('Plan') : __('Free Evaluation') }}
                    </h3>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __("Store Account:") }} <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ $company->unique_account_id }}</span> ({{ $company->name }})
                    </div>
                </div>

                <div>
                    @if ($company->isSubscriptionExpired())
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                            {{ __("Expired") }}
                        </span>
                    @elseif ($company->isSubscriptionActive())
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                            ● {{ __("Active") }}
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                            {{ __("Suspended") }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-750">
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase">{{ __("Expiry Date") }}</div>
                    <div class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white mt-0.5">
                        {{ $company->expires_at ? $company->expires_at->format('d M Y') : __('Perpetual / Lifetime') }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase">{{ __("Days Remaining") }}</div>
                    <div class="text-xs sm:text-sm font-extrabold text-blue-600 dark:text-blue-400 mt-0.5">
                        {{ $company->days_remaining !== null ? $company->days_remaining . ' ' . __('Days') : __('Unlimited') }}
                    </div>
                </div>

                <div class="col-span-2 sm:col-span-1">
                    <div class="text-[10px] font-bold text-slate-400 uppercase">{{ __("Operating Mode") }}</div>
                    <div class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white mt-0.5">
                        {{ $company->isRestaurantMode() ? '🍽️ ' . __('Food & Restaurant') : '🏪 ' . __('General Retail') }}
                    </div>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 flex items-center justify-between">
                <span>{{ __('Registered on') }} {{ $company->registered_at ? $company->registered_at->format('d M Y') : __('N/A') }}</span>
                <span>{{ __('Tax/GSTIN:') }} {{ $company->tax_id ?: __('Not Set') }}</span>
            </div>
        </div>

        <!-- Activation Code Redemption Card (1 col) -->
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-3xl p-6 shadow-xl shadow-blue-500/20 flex flex-col justify-between space-y-4">
            <div>
                <div class="w-10 h-10 rounded-2xl bg-white/20 text-white text-xl flex items-center justify-center font-black mb-2">
                    🔑
                </div>
                <h4 class="font-extrabold text-base">{{ __("Redeem License Key") }}</h4>
                <p class="text-xs text-blue-100 mt-1 leading-relaxed">
                    {{ __("Enter your offline activation code or subscription license key to renew or upgrade your store.") }}
                </p>
            </div>

            <form wire:submit.prevent="redeemCode" class="space-y-3">
                <div>
                    <input type="text"
                           wire:model="activationCode"
                           placeholder="AGY-XXXX-XXXX-XXXX"
                           class="w-full uppercase tracking-wider font-mono rounded-2xl bg-white/10 border-white/20 text-white placeholder-blue-200 text-xs sm:text-sm font-bold focus:ring-2 focus:ring-white">
                    @error('activationCode') <p class="text-rose-200 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        class="w-full py-3 rounded-2xl bg-white text-blue-900 hover:bg-blue-50 font-black text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-1.5">
                    <span wire:loading.remove>⚡ {{ __("Redeem & Activate") }}</span>
                    <span wire:loading>{{ __("Verifying Code...") }}</span>
                </button>
            </form>
        </div>

    </div>

    <!-- 2. Subscription Plans Catalog -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
                <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ __("Available Subscription Plans") }}</h3>
                <p class="text-xs text-slate-400">{{ __("Upgrade or renew your plan with instant tax invoice generation") }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = strtolower($company->plan_name ?? '') === strtolower($plan->name);
                    $isPro = strtolower($plan->name) === 'professional';
                    $isStarter = strtolower($plan->name) === 'starter';
                    $isTrial = strtolower($plan->name) === 'trial';
                @endphp
                <div @class([
                    'p-6 rounded-3xl border-2 flex flex-col justify-between relative transition-all duration-200 space-y-5',
                    'border-blue-500 bg-blue-50/50 dark:bg-slate-800/90 dark:border-blue-500 shadow-xl shadow-blue-500/10 ring-2 ring-blue-500/20' => $isCurrent,
                    'border-slate-200 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/60 hover:bg-white dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600 shadow-xs' => !$isCurrent,
                ])>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span @class([
                                'px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200' => $isCurrent,
                                'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' => !$isCurrent,
                            ])>
                                {{ $plan->billing_cycle ? strtoupper($plan->billing_cycle) : 'PLAN' }}
                            </span>

                            @if ($isCurrent)
                                <span class="px-3 py-1 rounded-full text-[10px] font-black bg-blue-600 text-white shadow-xs">
                                    {{ __("Current Plan") }}
                                </span>
                            @elseif ($isPro)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                    {{ __("Best Value") }} ⭐
                                </span>
                            @endif
                        </div>

                        <div>
                            <h4 class="font-black text-xl text-slate-900 dark:text-white">{{ $plan->display_name }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                @if ($isTrial) {{ __("14-day zero-risk trial evaluation") }} @elseif ($isStarter) {{ __("Perfect for small retail stores") }} @else {{ __("All-inclusive for growing businesses") }} @endif
                            </p>
                        </div>

                        <div class="pt-2 pb-1 border-y border-slate-200/80 dark:border-slate-700/60">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                                    ${{ number_format((float) $plan->price, 2) }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 dark:text-slate-400">
                                    / {{ $plan->duration_days ? $plan->duration_days . ' ' . __('days') : ($plan->billing_cycle === 'monthly' ? __('month') : __('year')) }}
                                </span>
                            </div>
                        </div>

                        <!-- Features & Plan Limits -->
                        <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-2 pt-1">
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-black">✓</span>
                                <span><strong>{{ $plan->limits['usuarios'] ?? ($isPro ? 25 : ($isStarter ? 5 : 2)) }}</strong> {{ __("Staff User Accounts") }}</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-black">✓</span>
                                <span><strong>{{ $plan->limits['dispositivos'] ?? ($isPro ? 10 : ($isStarter ? 3 : 1)) }}</strong> {{ __("Authorized POS Devices") }}</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-black">✓</span>
                                <span>{{ __("Itemized GST/VAT Tax Invoices") }}</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-black">✓</span>
                                <span>{{ __("Email & WhatsApp Invoice Sharing") }}</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-black">✓</span>
                                <span>{{ __("Unlimited Inventory & Sales History") }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-2">
                        @if ($isCurrent)
                            <button type="button" disabled class="w-full py-3 rounded-2xl bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-extrabold text-xs cursor-default text-center">
                                ✓ {{ __("Active Store Plan") }}
                            </button>
                        @elseif ((float)$plan->price <= 0)
                            <button type="button"
                                    wire:click="selectPlanToUpgrade('{{ $plan->name }}')"
                                    class="w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-lg shadow-emerald-500/25 active:scale-98 transition text-center cursor-pointer flex items-center justify-center gap-1.5">
                                <span>{{ __("Activate Free Trial") }} &rarr;</span>
                            </button>
                        @else
                            <button type="button"
                                    wire:click="selectPlanToUpgrade('{{ $plan->name }}')"
                                    class="w-full py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-lg shadow-blue-500/25 active:scale-98 transition text-center cursor-pointer flex items-center justify-center gap-1.5">
                                <span>🔒 {{ __("Pay & Upgrade") }} (${{ number_format((float)$plan->price, 2) }}) &rarr;</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 3. Subscription Tax Invoices Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
                <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ __("Official Tax Invoices & Payment Receipts") }}</h3>
                <p class="text-xs text-slate-400">{{ __("Itemized tax receipts for store subscriptions, license activations, and renewals") }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                        <th class="pb-3 pl-2">{{ __("Invoice #") }}</th>
                        <th class="pb-3">{{ __("Date") }}</th>
                        <th class="pb-3">{{ __("Plan / Description") }}</th>
                        <th class="pb-3">{{ __("Taxable Base") }}</th>
                        <th class="pb-3">{{ __("Tax (GST/VAT)") }}</th>
                        <th class="pb-3">{{ __("Total Amount") }}</th>
                        <th class="pb-3">{{ __("Payment Method") }}</th>
                        <th class="pb-3">{{ __("Status") }}</th>
                        <th class="pb-3 text-right pr-2">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                            <td class="py-3.5 pl-2 font-mono font-black text-blue-600 dark:text-blue-400">
                                {{ $inv->invoice_number }}
                            </td>
                            <td class="py-3.5 text-slate-600 dark:text-slate-300 font-medium">
                                {{ $inv->invoice_date->format('d M Y') }}
                            </td>
                            <td class="py-3.5 font-bold text-slate-900 dark:text-white">
                                {{ $inv->plan_name }} <span class="text-[10px] font-normal text-slate-400">({{ $inv->billing_cycle }})</span>
                            </td>
                            <td class="py-3.5 text-slate-600 dark:text-slate-300 font-bold">
                                ${{ $inv->getFormattedSubtotal() }}
                            </td>
                            <td class="py-3.5 text-slate-600 dark:text-slate-300 font-bold">
                                ${{ $inv->getFormattedTax() }} <span class="text-[10px] text-slate-400">({{ (float)$inv->tax_rate }}%)</span>
                            </td>
                            <td class="py-3.5 font-black text-slate-900 dark:text-white">
                                ${{ $inv->getFormattedTotal() }} {{ $inv->currency }}
                            </td>
                            <td class="py-3.5 font-semibold text-slate-600 dark:text-slate-400 capitalize">
                                {{ str_replace('_', ' ', $inv->payment_method) }}
                            </td>
                            <td class="py-3.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 uppercase">
                                    {{ $inv->status }}
                                </span>
                            </td>
                            <td class="py-3.5 text-right pr-2 space-x-1.5 whitespace-nowrap">
                                <!-- PDF Download -->
                                <a href="{{ route('tenant.billing.invoices.pdf', $inv) }}"
                                   target="_blank"
                                   class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-blue-600 hover:text-white text-slate-700 dark:text-slate-200 font-bold text-[11px] transition inline-flex items-center gap-1 shadow-2xs"
                                   title="{{ __("View & Download PDF Tax Invoice") }}">
                                    <span>📄 PDF</span>
                                </a>

                                <!-- Email Dispatch -->
                                <button type="button"
                                        wire:click="openSendEmailModal('{{ $inv->id }}')"
                                        class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-700 dark:text-slate-200 font-bold text-[11px] transition shadow-2xs"
                                        title="{{ __("Email Invoice Receipt") }}">
                                    <span>✉️</span>
                                </button>

                                <!-- WhatsApp Share -->
                                <a href="{{ $this->getWhatsAppUrl($inv->id) }}"
                                   target="_blank"
                                   class="px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white font-bold text-[11px] transition shadow-2xs"
                                   title="{{ __("Share via WhatsApp") }}">
                                    <span>💬</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                {{ __("No subscription tax invoices generated yet.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Email Dispatch Modal -->
    @if ($showEmailModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✉️ {{ __("Send Tax Invoice Receipt") }}</span>
                    </h3>
                    <button type="button" wire:click="$set('showEmailModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Recipient Email Address *") }}</label>
                        <input type="email"
                               wire:model="emailRecipient"
                               placeholder="finance@mystore.com"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                        @error('emailRecipient') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showEmailModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button"
                            wire:click="sendInvoiceEmail"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center gap-1.5">
                        <span wire:loading.remove>{{ __("Send Invoice Email") }}</span>
                        <span wire:loading>{{ __("Sending...") }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Subscription Checkout & Payment Modal -->
    @if ($showPaymentModal)
        @php
            $activePlan = $plans->firstWhere('name', $selectedPlanId);
            $basePrice = (float) ($activePlan?->price ?? 0.00);
            $taxRate = 18.00;
            $taxAmount = round(($basePrice * $taxRate) / 100, 2);
            $totalPayable = $basePrice + $taxAmount;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 animate-in fade-in zoom-in-95 my-8">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black">
                            🔒
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-tight">
                                {{ __("Secure Subscription Checkout") }}
                            </h3>
                            <p class="text-[11px] text-slate-400">{{ __("Payment required to activate services & generate tax invoice") }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold p-1 cursor-pointer">&times;</button>
                </div>

                @if ($paymentError)
                    <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 dark:bg-rose-950/50 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
                        <span>⚠️</span>
                        <span>{{ $paymentError }}</span>
                    </div>
                @endif

                <!-- Plan Summary & Tax Breakdown Box -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700/80 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-black text-slate-900 dark:text-white">{{ $activePlan?->display_name }} {{ __("Plan") }}</span>
                            <span class="text-[11px] text-slate-400">({{ ucfirst($activePlan?->billing_cycle ?? 'monthly') }})</span>
                        </div>
                        <span class="text-xs font-extrabold text-slate-900 dark:text-white">${{ number_format($basePrice, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ __("Platform GST / Tax (18% • CGST 9% + SGST 9%)") }}</span>
                        <span class="font-semibold">+${{ number_format($taxAmount, 2) }}</span>
                    </div>

                    <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ __("Total Amount Due") }}</span>
                        <span class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400">
                            ${{ number_format($totalPayable, 2) }} <span class="text-xs text-slate-400">{{ $company->currency ?: 'USD' }}</span>
                        </span>
                    </div>
                </div>

                <!-- Payment Method Selector Tabs -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Select Payment Method") }}</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @if (isset($enabledGateways['razorpay']))
                            <button type="button"
                                    wire:click="$set('paymentGateway', 'razorpay')"
                                    @class([
                                        'py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                                        'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 shadow-2xs' => $paymentGateway === 'razorpay',
                                        'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' => $paymentGateway !== 'razorpay',
                                    ])>
                                <span>⚡ {{ __("Razorpay") }}</span>
                            </button>
                        @endif
                        @if (isset($enabledGateways['mercadopago']))
                            <button type="button" wire:click="$set('paymentGateway', 'mercadopago')" @class(['py-2 px-3 rounded-xl border text-xs font-bold transition', 'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' => $paymentGateway === 'mercadopago', 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400' => $paymentGateway !== 'mercadopago'])>🟦 {{ __('Mercado Pago') }}</button>
                        @endif

                        <button type="button"
                                wire:click="$set('paymentGateway', 'credit_card')"
                                @class([
                                    'py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                                    'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 shadow-2xs' => $paymentGateway === 'credit_card',
                                    'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' => $paymentGateway !== 'credit_card',
                                ])>
                            <span>💳 {{ __("Credit / Debit Card") }}</span>
                        </button>

                        <button type="button"
                                wire:click="$set('paymentGateway', 'activation_key')"
                                @class([
                                    'py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                                    'border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 shadow-2xs' => $paymentGateway === 'activation_key',
                                    'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' => $paymentGateway !== 'activation_key',
                                ])>
                            <span>🔑 {{ __("License Voucher") }}</span>
                        </button>
                    </div>
                </div>

                <!-- Razorpay Gateway Info Form -->
                @if ($paymentGateway === 'razorpay')
                    <div class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 space-y-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-blue-900 dark:text-blue-200">
                            <span>⚡ {{ __("Official Razorpay Payment Gateway") }}</span>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
                            {{ __("Clicking proceed will securely launch the Razorpay checkout dialog supporting Cards, UPI, Netbanking, Google Pay, Apple Pay & Wallets.") }}
                        </p>
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                            <span>Gateway Mode: {{ strtoupper($enabledGateways['razorpay']['mode'] ?? 'LIVE') }}</span>
                            <span>&bull;</span>
                            <span>Merchant Key: {{ substr($enabledGateways['razorpay']['public_key'] ?? '', 0, 12) }}...</span>
                        </div>
                    </div>
                @endif

                <!-- Card Details Form -->
                @if ($paymentGateway === 'credit_card')
                    <div class="space-y-3 pt-1">
                        <!-- Cardholder Name -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Cardholder Name *") }}</label>
                            <input type="text"
                                   wire:model="cardHolder"
                                   placeholder="{{ __("Full Name as on Card") }}"
                                   class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                            @error('cardHolder') <p class="text-rose-600 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <!-- Card Number -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Card Number *") }}</label>
                            <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                                <div class="pl-3 pr-1 text-slate-400 text-xs font-bold">💳</div>
                                <input type="text"
                                       wire:model="cardNumber"
                                       maxlength="19"
                                       placeholder="4242 &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242"
                                       class="w-full border-none bg-transparent text-xs font-mono font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-0 py-2.5 px-2">
                                <div class="pr-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Visa / MC</div>
                            </div>
                            @error('cardNumber') <p class="text-rose-600 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <!-- Expiry & CVV Row -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Expiry Date (MM/YY) *") }}</label>
                                <input type="text"
                                       wire:model="cardExpiry"
                                       maxlength="5"
                                       placeholder="12/28"
                                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                                @error('cardExpiry') <p class="text-rose-600 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("CVV / CVC *") }}</label>
                                <input type="password"
                                       wire:model="cardCvv"
                                       maxlength="4"
                                       placeholder="•••"
                                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                                @error('cardCvv') <p class="text-rose-600 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 pt-1">
                            <span>🔒 {{ __("256-Bit SSL Encrypted Payment") }} &bull; {{ __("Instant Tax Receipt Generated") }}</span>
                        </div>
                    </div>
                @endif

                <!-- License Voucher Form -->
                @if ($paymentGateway === 'activation_key')
                    <div class="space-y-2 pt-1">
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ __("Activation / License Key *") }}</label>
                        <input type="text"
                               wire:model="paymentActivationCode"
                               placeholder="AGY-XXXX-XXXX-XXXX"
                               class="w-full uppercase tracking-wider font-mono rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 py-2.5">
                        <p class="text-[10px] text-slate-400">{{ __("Enter a pre-purchased platform license key to activate this plan without card payment.") }}</p>
                        @error('paymentActivationCode') <p class="text-rose-600 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                    </div>
                @endif

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button"
                            wire:click="closePaymentModal"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
                        Cancel
                    </button>

                    <button type="button"
                            wire:click="processSubscriptionPayment"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                        <span wire:loading.remove>
                            @if ($paymentGateway === 'razorpay')
                                ⚡ {{ __("Pay") }} ${{ number_format($totalPayable, 2) }} {{ __("with Razorpay") }}
                            @elseif ($paymentGateway === 'mercadopago')
                                🟦 {{ __("Pay") }} ${{ number_format($totalPayable, 2) }} {{ __("with Mercado Pago") }}
                            @elseif ($paymentGateway === 'activation_key')
                                {{ __("Verify Key & Upgrade") }}
                            @else
                                🔒 {{ __("Pay") }} ${{ number_format($totalPayable, 2) }} & {{ __("Activate") }}
                            @endif
                        </span>
                        <span wire:loading class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __("Connecting Gateway...") }}</span>
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Razorpay Checkout Integration Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('redirect-to-mercadopago', (data) => window.location.assign((Array.isArray(data) ? data[0] : data).url));
            Livewire.on('initiate-razorpay-checkout', (data) => {
                const config = Array.isArray(data) ? data[0] : data;
                const options = {
                    key: config.key_id,
                    amount: config.amount,
                    currency: config.currency,
                    name: config.company_name || 'Smart SaaS Inventory',
                    description: config.description || 'Subscription Payment',
                    order_id: config.order_id,
                    handler: function (response) {
                        @this.verifyAndActivateRazorpayPayment(
                            response.razorpay_payment_id,
                            response.razorpay_order_id,
                            response.razorpay_signature
                        );
                    },
                    prefill: {
                        name: config.user_name || '',
                        email: config.user_email || '',
                        contact: config.user_phone || ''
                    },
                    theme: {
                        color: config.color || '#2563eb'
                    },
                    modal: {
                        ondismiss: function() {
                            console.log('Razorpay checkout window closed by user.');
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.on('payment.failed', function (response) {
                    alert('Payment Failed: ' + (response.error.description || 'Transaction declined by bank.'));
                });
                rzp.open();
            });
        });
    </script>

</div>
