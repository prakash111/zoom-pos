@props(['plans' => null, 'branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $plans = $plans ?? \App\Models\SaaSPlan::where('is_active', true)->orderBy('price')->get();
    $badge = $branding->getSectionBadge('pricing', __('Predictable Investment'));
    $title = $branding->getSectionTitle('pricing', __('Simple, transparent pricing for every tier'));
    $subtitle = $branding->getSectionSubtitle('pricing', __('Launch in minutes with zero setup fees. Choose monthly or annual billing.'));
    $annualDiscountText = $branding->landingText('pricing.discount_badge', __('Save 20%'));
    $sorted = $plans->sortBy('price')->values();
    $popularName = $sorted->count() >= 2 ? $sorted[1]->name : null;
    $gridColsClass = match (true) {
        $sorted->count() >= 3 => 'lg:grid-cols-3',
        default => 'lg:grid-cols-2',
    };
    $extensionLabels = \App\Models\Plan::EXTENSION_LABELS ?? [
        'leadmanagement' => 'CRM & Leads',
        'crm_leads' => 'CRM & Leads',
        'whatsapp_api' => 'WhatsApp API',
        'custom_domain' => 'Custom Domain',
    ];
@endphp

@if ($sorted->isNotEmpty())
<!-- Pricing Section Synchronized with Flutter Layout & Dual Theme Support -->
<section id="pricing" class="landing-sec-pricing landing-dark-section py-20 border-t border-slate-200 dark:border-slate-800/80 transition-colors duration-300 scroll-mt-20" style="background-color: var(--landing-pricing-bg, #0b0f19);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ annual: false }">
        <div class="text-center mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-600 dark:text-blue-400 text-xs font-bold uppercase tracking-wider mb-3">
                {{ $badge }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">{{ $subtitle }}</p>
            @endif
        </div>

        <!-- Monthly / Annual Toggle Switch -->
        <div class="flex items-center justify-center gap-3 mb-14">
            <span class="text-xs sm:text-sm font-bold transition-colors" :class="!annual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">{{ __('Monthly Billing') }}</span>
            <button type="button" x-on:click="annual = !annual"
                    :class="annual ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-700'"
                    class="relative w-12 h-6 rounded-full transition-colors p-0.5 cursor-pointer" role="switch" :aria-checked="annual.toString()">
                <span class="block w-5 h-5 rounded-full bg-white shadow transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs sm:text-sm font-bold flex items-center gap-2 transition-colors" :class="annual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                {{ __('Annual Billing') }}
                <span class="px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 text-[10px] font-black uppercase tracking-wider border border-blue-200 dark:border-blue-500/30">{{ $annualDiscountText }}</span>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $gridColsClass }} gap-6 lg:gap-8 items-stretch">
            @foreach ($sorted as $plan)
                @php
                    $currencySymbol = match(strtoupper($plan->currency ?? 'USD')) {
                        'USD' => '$',
                        'EUR' => '€',
                        'GBP' => '£',
                        'INR' => '₹',
                        'BRL' => 'R$',
                        default => ($plan->currency ?: '$'),
                    };
                    $price = (float) $plan->price;
                    $isTrial = $plan->billing_cycle === 'trial';
                    $isYearly = in_array($plan->billing_cycle, ['yearly', 'annual', 'annually'], true);
                    $annualPrice = $isYearly ? $price : ($isTrial ? 0 : round($price * 12 * 0.8));
                    $isPopular = ($plan->name === $popularName) || ($plan->name === 'starter');
                @endphp

                <!-- Pricing Card -->
                <div class="pricing-card relative bg-white dark:bg-[#101726] border {{ $isPopular ? 'border-blue-500 shadow-xl shadow-blue-500/10 ring-2 ring-blue-500/20 dark:ring-0' : 'border-slate-200 dark:border-slate-800 hover:border-blue-500/40 shadow-sm hover:shadow-md dark:shadow-none' }} rounded-2xl p-6 flex flex-col justify-between transition-all duration-300">
                    @if ($isPopular)
                        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest shadow-md">
                            {{ __('MOST POPULAR') }}
                        </span>
                    @endif

                    <div>
                        <!-- Plan Header & Price -->
                        <div class="text-xs font-black uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $plan->display_name }}</div>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">
                                <span x-show="!annual">{{ $currencySymbol }}{{ number_format($price, 0) }}</span>
                                <span x-show="annual" x-cloak>{{ $currencySymbol }}{{ number_format($annualPrice, 0) }}</span>
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                <span x-show="!annual">/{{ $plan->billing_cycle }}</span>
                                <span x-show="annual" x-cloak>/{{ $isTrial ? 'trial' : 'yearly' }}</span>
                            </span>
                        </div>

                        <!-- Numerical Limits Badges (2x2 Grid) -->
                        <div class="grid grid-cols-2 gap-2 mt-4 mb-3">
                            <!-- Invoices Limit -->
                            <div class="pricing-limit-pill flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50/80 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 text-xs font-medium">
                                <svg class="w-3.5 h-3.5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>{{ $plan->invoice_limit == -1 ? 'Unlimited Invoices' : $plan->invoice_limit . ' Invoices/mo' }}</span>
                            </div>

                            <!-- Products Limit -->
                            <div class="pricing-limit-pill flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50/80 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 text-xs font-medium">
                                <svg class="w-3.5 h-3.5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <span>{{ $plan->product_limit == -1 ? 'Unlimited Products' : $plan->product_limit . ' Products' }}</span>
                            </div>

                            <!-- Devices Limit -->
                            <div class="pricing-limit-pill flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50/80 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 text-xs font-medium">
                                <svg class="w-3.5 h-3.5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>{{ $plan->device_limit == -1 ? 'Unlimited POS Devices' : $plan->device_limit . ' Devices' }}</span>
                            </div>

                            <!-- Staff Limit -->
                            <div class="pricing-limit-pill flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50/80 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 text-xs font-medium">
                                <svg class="w-3.5 h-3.5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span>{{ $plan->staff_limit == -1 ? 'Unlimited Staff' : $plan->staff_limit . ' Staff' }}</span>
                            </div>
                        </div>

                        <!-- Modular Extension Badges -->
                        @if(!empty($plan->enabled_extensions))
                            <div class="flex flex-wrap items-center gap-2 my-3">
                                @foreach($plan->enabled_extensions as $ext)
                                    <span class="pricing-extension-badge inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-md border border-amber-500/30 bg-amber-500/10 text-amber-300 tracking-wide">
                                        {{ $extensionLabels[$ext] ?? ucwords(str_replace('_', ' ', $ext)) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Feature Checklist -->
                        <ul class="space-y-3 mb-8 text-sm text-slate-700 dark:text-slate-300">
                            @foreach($plan->feature_list ?? [] as $feature)
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="capitalize">{{ is_array($feature) ? ($feature['label'] ?? $feature['name']) : $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- CTA Button -->
                    <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                       class="w-full block text-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white dark:bg-[#1E293B] dark:hover:bg-blue-600 dark:text-white font-medium py-3 shadow-md shadow-blue-500/20 transition duration-200">
                        {{ __('Select Plan') }}
                    </a>
                </div>
            @endforeach
        </div>

        @php
            $pricingNote = $branding->landingText('pricing.note', '');
        @endphp
        @if ($pricingNote)
            <p class="text-center text-xs text-slate-500 dark:text-slate-400 mt-8">{{ $pricingNote }}</p>
        @endif
    </div>

    <!-- Scoped Style Guarantees for Pricing Card and Extension Badges across Light and Dark Themes -->
    <style>
        .pricing-card {
            background-color: #ffffff;
            border-color: #e2e8f0;
            color: #0f172a;
        }
        html.dark .pricing-card, [data-theme="dark"] .pricing-card {
            background-color: #101726 !important;
            border-color: #1e293b !important;
            color: #f8fafc !important;
        }
        .pricing-extension-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 4px 11px !important;
            font-size: 11.5px !important;
            line-height: 1.25rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.025em !important;
            border-radius: 6px !important;
            background-color: #fef3c7 !important;
            border: 1px solid #fcd34d !important;
            color: #92400e !important;
        }
        html.dark .pricing-extension-badge, [data-theme="dark"] .pricing-extension-badge {
            background-color: rgba(245, 158, 11, 0.12) !important;
            border-color: rgba(245, 158, 11, 0.35) !important;
            color: #fcd34d !important;
        }
        .pricing-limit-pill {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 6px 10px !important;
            border-radius: 8px !important;
            font-size: 11.5px !important;
            font-weight: 500 !important;
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1d4ed8 !important;
        }
        html.dark .pricing-limit-pill, [data-theme="dark"] .pricing-limit-pill {
            background-color: rgba(23, 37, 84, 0.6) !important;
            border-color: rgba(30, 58, 138, 0.4) !important;
            color: #93c5fd !important;
        }
    </style>
</section>
@endif
