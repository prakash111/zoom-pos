<div class="w-full space-y-6"
     x-data="{ 
         activeTab: (window.location.hash ? window.location.hash.substring(1) : 'mode') || 'mode'
     }"
     x-init="
         const validTabs = ['mode', 'profile', 'receipts', 'financial', 'notifications'];
         if (!validTabs.includes(activeTab)) activeTab = 'mode';
         window.addEventListener('hashchange', () => {
             const h = window.location.hash ? window.location.hash.substring(1) : 'mode';
             if (validTabs.includes(h)) activeTab = h;
         });
     ">
    
    <!-- Flash Status Messages -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm animate-in fade-in">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm animate-in fade-in">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Modular Tab Navigation Bar -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar p-1.5 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200/60 dark:border-slate-700/60 shadow-2xs">
        
        <!-- Tab 1: Operating Mode -->
        <button type="button"
                @click="activeTab = 'mode'; window.location.hash = 'mode'"
                :class="activeTab === 'mode' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">⚡</span>
            <span>{{ __('Store Operating Mode') }}</span>
        </button>

        <!-- Tab 2: Profile & Branding -->
        <button type="button"
                @click="activeTab = 'profile'; window.location.hash = 'profile'"
                :class="activeTab === 'profile' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🏢</span>
            <span>{{ __('Store Profile & Branding') }}</span>
        </button>

        <!-- Tab 3: Receipt Prefixes & Terms -->
        <button type="button"
                @click="activeTab = 'receipts'; window.location.hash = 'receipts'"
                :class="activeTab === 'receipts' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">📄</span>
            <span>{{ __('Receipt Prefixes & Bank Terms') }}</span>
        </button>

        <!-- Tab 4: Financial & Currency -->
        <button type="button"
                @click="activeTab = 'financial'; window.location.hash = 'financial'"
                :class="activeTab === 'financial' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">💳</span>
            <span>{{ __('Financial & Currency') }}</span>
        </button>

        <!-- Tab 5: Notification & Dispatch -->
        <button type="button"
                @click="activeTab = 'notifications'; window.location.hash = 'notifications'"
                :class="activeTab === 'notifications' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                class="px-3.5 sm:px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🔔</span>
            <span>{{ __('Notification & Dispatch') }}</span>
        </button>
    </div>

    <!-- =========================================================================
         TAB 1: STORE OPERATING MODE (Retail POS vs Restaurant POS)
         ========================================================================= -->
    <div x-show="activeTab === 'mode'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl">
                    ⚡
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Store Operating Mode</h3>
                    <p class="text-xs text-slate-400">Choose between General Retail POS vs Food & Restaurant Mode with strict UI & route isolation</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Mode 1: General POS & Retail -->
                <div wire:click="setPosMode('general')"
                     @class([
                         'p-5 rounded-3xl border-2 cursor-pointer transition-all flex flex-col justify-between gap-3 relative',
                         'border-blue-600 bg-blue-50/50 dark:bg-blue-950/40 dark:border-blue-500 shadow-md shadow-blue-500/10' => $posMode === 'general',
                         'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-700' => $posMode !== 'general',
                     ])>
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-2xl bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl font-black">
                            🏪
                        </div>
                        <span @class([
                            'w-5 h-5 rounded-full border-2 flex items-center justify-center text-[10px] font-black',
                            'border-blue-600 bg-blue-600 text-white' => $posMode === 'general',
                            'border-slate-300 dark:border-slate-600' => $posMode !== 'general',
                        ])>
                            @if ($posMode === 'general') ✓ @endif
                        </span>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">General Retail POS (Default)</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            For supermarkets, clothing, electronics & retail shops. Includes barcode scanning, cash register, quotations, invoices, and standard stock management.
                        </p>
                    </div>
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-black">
                        Includes: Retail Counter &bull; Barcode POS &bull; Quotes
                    </div>
                </div>

                <!-- Mode 2: Food & Restaurant Mode -->
                <div wire:click="setPosMode('restaurant')"
                     @class([
                         'p-5 rounded-3xl border-2 cursor-pointer transition-all flex flex-col justify-between gap-3 relative',
                         'border-lime-500 bg-lime-50/50 dark:bg-lime-950/40 dark:border-lime-400 shadow-md shadow-lime-500/10' => $posMode === 'restaurant',
                         'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-700' => $posMode !== 'restaurant',
                     ])>
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-2xl bg-lime-100 dark:bg-lime-900/60 text-lime-700 dark:text-lime-400 flex items-center justify-center text-xl font-black">
                            🍽️
                        </div>
                        <span @class([
                            'w-5 h-5 rounded-full border-2 flex items-center justify-center text-[10px] font-black',
                            'border-lime-500 bg-lime-500 text-slate-950' => $posMode === 'restaurant',
                            'border-slate-300 dark:border-slate-600' => $posMode !== 'restaurant',
                        ])>
                            @if ($posMode === 'restaurant') ✓ @endif
                        </span>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">Food & Restaurant Mode</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            For restaurants, cafes, bars & food trucks. Includes floor plans & live tables, KOT tickets, Kitchen Display (KDS), Dine-In/Takeaway routing, and QR table ordering.
                        </p>
                    </div>
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-lime-600 dark:text-lime-400 font-black">
                        Includes: Tables &bull; KOT &bull; Kitchen KDS &bull; QR Menus
                    </div>
                </div>
            </div>

            <!-- Save Button for Operating Mode -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    Save Operating Mode
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 2: STORE PROFILE & BRANDING
         ========================================================================= -->
    <div x-show="activeTab === 'profile'" x-cloak class="space-y-6">
        
        <!-- Store Information -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Store Profile & Location</h3>
                    <p class="text-xs text-slate-400">Business details displayed on POS terminal, receipts, and invoices</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Company / Store Name *</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Trade / Brand Name</label>
                    <input type="text" wire:model="tradeName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tax ID / Business Reg</label>
                    <input type="text" wire:model="taxId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Phone / WhatsApp</label>
                    <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('phone') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Website URL</label>
                    <input type="text" wire:model="website" placeholder="e.g. yourstore.com or https://yourstore.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('website') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Subdomain / Slug -->
                @php
                    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'saas.zoomnearby.com';
                @endphp
                <div x-data="{ slugValue: @js($slug) }">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Subdomain / Slug</label>
                    <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                        <input type="text" wire:model="slug" x-on:input="slugValue = $event.target.value" placeholder="my-store" class="w-full border-none bg-transparent text-xs sm:text-sm font-mono focus:ring-0 py-2.5 px-3">
                        <span class="pr-3 text-xs font-mono text-slate-400">.{{ $appHost }}</span>
                    </div>
                    @error('slug') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <template x-if="slugValue">
                        <a :href="'https://' + slugValue.toLowerCase() + '.{{ $appHost }}'"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-mono text-blue-600 dark:text-blue-400 hover:underline">
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            <span x-text="'https://' + slugValue.toLowerCase() + '.{{ $appHost }}'"></span>
                        </a>
                    </template>
                </div>

                <!-- Custom Domain -->
                <div class="sm:col-span-2" x-data="{ customDomainValue: @js($customDomain) }">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
                        <span>🌐 Custom Domain Configuration</span>
                        <span class="text-[10px] text-slate-400 font-normal">(Optional)</span>
                    </label>
                    <input type="text" wire:model="customDomain" x-on:input="customDomainValue = $event.target.value" placeholder="pos.yourdomain.com or mycompany.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-[11px] text-slate-400 mt-1">
                        Point your domain's <span class="font-semibold text-slate-500 dark:text-slate-400">CNAME</span> (or <span class="font-semibold text-slate-500 dark:text-slate-400">A-record</span>, if CNAME isn't supported at your registrar for this name) to
                        <span class="font-mono text-slate-600 dark:text-slate-300">{{ $appHost }}</span>, save this form, then ask your platform admin to finish HTTPS setup for the domain &mdash; until that's done it may show a certificate warning.
                    </p>
                    @error('customDomain') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <template x-if="customDomainValue">
                        <a :href="'https://' + customDomainValue.toLowerCase().replace(/^https?:\/\//, '')"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-mono text-blue-600 dark:text-blue-400 hover:underline">
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            <span x-text="'https://' + customDomainValue.toLowerCase().replace(/^https?:\/\//, '')"></span>
                        </a>
                    </template>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Physical Address</label>
                    <input type="text" wire:model="address" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">City</label>
                    <input type="text" wire:model="city" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">State / Province</label>
                    <input type="text" wire:model="state" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Postal Code</label>
                    <input type="text" wire:model="postalCode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Country (2-letter ISO)</label>
                    <input type="text" wire:model="country" maxlength="2" class="w-full uppercase rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        <!-- Store Branding & Theme Styling -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Store Theme, Branding & POS Layout</h3>
                    <p class="text-xs text-slate-400">Application primary theme color, default POS layout design, logo, and document colors</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Application Theme Color Palette -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Application & Navigation Theme Color</label>
                        <p class="text-[11px] text-slate-400">Select the primary accent color for your navigation sidebar, buttons, and badges</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                        @php
                            $themePresets = [
                                'blue' => ['label' => 'Cobalt Blue', 'bg' => 'bg-blue-600', 'hex' => '#2563eb'],
                                'emerald' => ['label' => 'Emerald Green', 'bg' => 'bg-emerald-600', 'hex' => '#059669'],
                                'indigo' => ['label' => 'Royal Indigo', 'bg' => 'bg-indigo-600', 'hex' => '#4f46e5'],
                                'purple' => ['label' => 'Violet Purple', 'bg' => 'bg-purple-600', 'hex' => '#7c3aed'],
                                'amber' => ['label' => 'Warm Amber', 'bg' => 'bg-amber-600', 'hex' => '#d97706'],
                                'rose' => ['label' => 'Ruby Rose', 'bg' => 'bg-rose-600', 'hex' => '#e11d48'],
                                'slate' => ['label' => 'Midnight Slate', 'bg' => 'bg-slate-800', 'hex' => '#334155'],
                            ];
                        @endphp

                        @foreach ($themePresets as $key => $preset)
                            <button type="button"
                                    wire:click="setThemeColor('{{ $key }}')"
                                    @class([
                                        'p-3 rounded-2xl border-2 flex flex-col items-center gap-2 text-center transition-all cursor-pointer shadow-xs active:scale-95',
                                        'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $themeColor === $key,
                                        'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $themeColor !== $key,
                                    ])>
                                <span class="w-7 h-7 rounded-full {{ $preset['bg'] }} shadow-sm flex items-center justify-center text-white text-xs font-black">
                                    @if ($themeColor === $key) ✓ @endif
                                </span>
                                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate w-full">
                                    {{ $preset['label'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Default POS Layout Design Selector -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Default POS Register Layout Design</label>
                        <p class="text-[11px] text-slate-400">Choose the default interface style for your Point of Sale counter</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Layout 1: Standard Retail -->
                        <button type="button"
                                wire:click="setPosLayout('standard')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'standard',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'standard',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">🛒</span>
                                    @if ($posLayout === 'standard')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">Default</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">Standard Scanner POS</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Search bar, category chips, 3-column retail item cards with side cart.
                                </p>
                            </div>
                        </button>

                        <!-- Layout 2: Supermarket Touch -->
                        <button type="button"
                                wire:click="setPosLayout('touch')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'touch',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'touch',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">🏪</span>
                                    @if ($posLayout === 'touch')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">Default</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">Supermarket Touch POS</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Function toolbar, vibrant department tiles & right receipt checkout slip.
                                </p>
                            </div>
                        </button>

                        <!-- Layout 3: Square Stand Modern -->
                        <button type="button"
                                wire:click="setPosLayout('stand')"
                                @class([
                                    'p-4 rounded-2xl border-2 flex flex-col justify-between text-left transition-all cursor-pointer shadow-xs active:scale-98 space-y-2',
                                    'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => $posLayout === 'stand',
                                    'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => $posLayout !== 'stand',
                                ])>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl">📱</span>
                                    @if ($posLayout === 'stand')
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">Default</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">Square Stand Register</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Clean photo tiles, top category tabs, discounts bar & quick-charge pill.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Logo & Favicon & Accent Color -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Brand Logo URL / Image Path</label>
                        <input type="text" wire:model="logo" placeholder="https://example.com/logo.png" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">Displayed in sidebar, PDF invoices, and online catalog header</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Browser Favicon URL (.ico / .png)</label>
                        <input type="text" wire:model="favicon" placeholder="https://example.com/favicon.ico" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">Displayed in browser tabs for your tenant store</p>
                    </div>

                    <!-- Quotation & Invoice Document Accent Color -->
                    <div class="sm:col-span-2 space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Quotation & Invoice Document Accent Color</label>
                                <p class="text-[11px] text-slate-400">Choose a primary header & table theme color for Quotations and PDF Receipts</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm" style="background-color: {{ $primaryColor }};"></span>
                                <input type="color" wire:model.live="primaryColor" class="w-8 h-8 rounded-lg cursor-pointer border-0 p-0 bg-transparent">
                            </div>
                        </div>

                        <!-- Palette Quick Swatches -->
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            @php
                                $palettes = [
                                    '#2d7a58' => 'Forest Green',
                                    '#2563eb' => 'Cobalt Blue',
                                    '#7c3aed' => 'Royal Purple',
                                    '#e11d48' => 'Crimson Red',
                                    '#d97706' => 'Amber Gold',
                                    '#1e293b' => 'Midnight Slate',
                                    '#059669' => 'Emerald Mint',
                                    '#0284c7' => 'Ocean Sky',
                                ];
                            @endphp
                            @foreach ($palettes as $hex => $label)
                                <button type="button"
                                        wire:click="setQuotationColor('{{ $hex }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-2xs border cursor-pointer"
                                        style="border-color: {{ $hex }}; {{ $primaryColor === $hex ? "background-color: {$hex}; color: #ffffff;" : "color: {$hex}; background-color: transparent;" }}">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $hex }};"></span>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button for Profile & Branding -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    Save Profile & Branding
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 3: RECEIPT PREFIXES & BANK TERMS
         ========================================================================= -->
    <div x-show="activeTab === 'receipts'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Receipt Prefixes & Bank Terms</h3>
                    <p class="text-xs text-slate-400">Invoice prefixes, default quotation terms, and payment instructions</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Invoice Prefix</label>
                    <input type="text" wire:model="invoicePrefix" placeholder="INV-" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Quotation Prefix</label>
                    <input type="text" wire:model="quotationPrefix" placeholder="QUO-" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Invoice Terms & Conditions (Rich-Text)</label>
                    <x-rich-text-editor wire:model="invoiceTerms" placeholder="Enter invoice policy & terms..." height="150" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Quote Terms & Notes (Rich-Text)</label>
                    <p class="text-[11px] text-slate-400 mb-1">Default terms and notes auto-loaded into new Quotations</p>
                    <x-rich-text-editor wire:model="quoteTerms" placeholder="Enter default quotation policy, deliverables & terms..." height="150" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Bank & Payment Details (Rich-Text)</label>
                    <x-rich-text-editor wire:model="bankDetails" placeholder="Enter bank accounts & payment instructions..." height="150" />
                </div>
            </div>

            <!-- Save Button for Receipt Prefixes & Bank Terms -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    Save Receipt Prefixes & Bank Terms
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 4: FINANCIAL & CURRENCY SETTINGS
         ========================================================================= -->
    <div x-show="activeTab === 'financial'" x-cloak class="space-y-6">
        
        <!-- Currency & Price Formatting -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg">
                    💱
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Currency & Price Formatting</h3>
                    <p class="text-xs text-slate-400">Base currency code, symbol, decimal precision, and placement used across POS, cart, checkout, and invoices</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Base Currency Code</label>
                    <input type="text" wire:model="currency" maxlength="3" placeholder="USD" class="w-full uppercase rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                    @error('currency') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Currency Symbol</label>
                    <input type="text" wire:model="currencySymbol" maxlength="8" placeholder="$" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono font-bold focus:ring-blue-500 focus:border-blue-500">
                    @error('currencySymbol') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Decimal Places</label>
                    <select wire:model="currencyDecimals" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500 focus:border-blue-500">
                        <option value="0">0 (e.g. 1,235)</option>
                        <option value="1">1 (e.g. 1,234.5)</option>
                        <option value="2">2 (e.g. 1,234.50)</option>
                        <option value="3">3 (e.g. 1,234.500)</option>
                        <option value="4">4 (e.g. 1,234.5000)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Symbol Placement</label>
                    <select wire:model="currencySymbolPosition" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold focus:ring-blue-500 focus:border-blue-500">
                        <option value="prefix">Prefix ({{ $currencySymbol ?: '$' }}100.00)</option>
                        <option value="suffix">Suffix (100.00{{ $currencySymbol ?: '$' }})</option>
                    </select>
                </div>
            </div>

            <!-- Other Currencies Reference List -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Other Currencies (Reference Rates)</label>
                        <p class="text-[11px] text-slate-400">Exchange rates against your base currency, for reporting and multi-currency displays</p>
                    </div>

                    <button type="button" wire:click="addOtherCurrency" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] transition cursor-pointer">
                        + Add Currency
                    </button>
                </div>

                @if (empty($otherCurrencies))
                    <p class="text-xs text-slate-400 italic">No other currencies configured.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($otherCurrencies as $idx => $oc)
                            <div class="grid grid-cols-12 gap-2 items-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/50">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.code" maxlength="10" placeholder="EUR" class="col-span-3 text-xs uppercase rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 font-mono font-bold py-1.5 px-2">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.name" placeholder="Euro" class="col-span-4 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 py-1.5 px-2">
                                <input type="text" wire:model="otherCurrencies.{{ $idx }}.symbol" maxlength="8" placeholder="€" class="col-span-2 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 py-1.5 px-2">
                                <input type="number" step="0.0001" min="0" wire:model="otherCurrencies.{{ $idx }}.exchange_rate" placeholder="Rate" class="col-span-2 text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-900 font-mono py-1.5 px-2">
                                <button type="button" wire:click="removeOtherCurrency({{ $idx }})" class="col-span-1 w-7 h-7 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 hover:bg-rose-100 font-black text-xs transition cursor-pointer justify-self-center">
                                    ✕
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Dynamic Payment Methods (CRUD) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Payment Methods</h3>
                        <p class="text-xs text-slate-400">Add, edit, enable or remove custom payment methods for POS checkout</p>
                    </div>
                </div>

                <button type="button"
                        wire:click="openAddPaymentMethodModal"
                        class="px-4 py-2 rounded-2xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>Add Method</span>
                </button>
            </div>

            <!-- Payment Methods Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm text-left">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 font-extrabold border-b border-slate-100 dark:border-slate-800 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Method Name</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @forelse ($paymentMethods as $pm)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $pm->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    <span>{{ $pm->name }}</span>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-slate-500 text-xs">
                                    {{ $pm->code }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-400 text-xs truncate max-w-xs">
                                    {{ $pm->description ?: '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $pm->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $pm->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-2">
                                    <button type="button"
                                            wire:click="openEditPaymentMethodModal('{{ $pm->id }}')"
                                            class="text-blue-600 hover:underline font-bold text-xs cursor-pointer">
                                        Edit
                                    </button>
                                    <button type="button"
                                            wire:click="togglePaymentMethodStatus('{{ $pm->id }}')"
                                            class="text-amber-600 hover:underline font-bold text-xs cursor-pointer">
                                        {{ $pm->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <button type="button"
                                            wire:click="deletePaymentMethod('{{ $pm->id }}')"
                                            wire:confirm="Are you sure you want to delete payment method '{{ $pm->name }}'?"
                                            class="text-rose-500 hover:underline font-bold text-xs cursor-pointer">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                    No payment methods configured. Click "+ Add Method" to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Save Button for Financial Settings -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    Save Financial Settings
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 5: NOTIFICATION & DISPATCH CHANNELS (WhatsApp & SMTP)
         ========================================================================= -->
    <div x-show="activeTab === 'notifications'" x-cloak class="space-y-6">
        
        <!-- WhatsApp Settings -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">WhatsApp & Messaging Settings</h3>
                    <p class="text-xs text-slate-400">Default settings for 1-click WhatsApp receipts to customers</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Default Country Calling Code</label>
                    <input type="text" wire:model="whatsappPhonePrefix" placeholder="e.g. 1 for US/CA, 91 for IN, 44 for UK, 62 for ID" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Custom WhatsApp Footer Note</label>
                    <input type="text" wire:model="whatsappCustomNote" placeholder="e.g. For returns, keep this receipt within 7 days." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- SMTP Email Settings -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">SMTP Email Settings</h3>
                    <p class="text-xs text-slate-400">Configure outbound email delivery for sending invoices, quotations, and staff invitations</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SMTP Host</label>
                    <input type="text" wire:model="smtpHost" placeholder="e.g. smtp.mailgun.org, smtp.gmail.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SMTP Port</label>
                    <input type="number" wire:model="smtpPort" placeholder="587" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SMTP Username</label>
                    <input type="text" wire:model="smtpUsername" placeholder="postmaster@yourdomain.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SMTP Password</label>
                    <input type="password" wire:model="smtpPassword" placeholder="{{ $hasStoredSmtpPassword ? '••••••••••••' : 'Enter password' }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Encryption</label>
                    <select wire:model="smtpEncryption" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="">None</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Sender Email Address</label>
                    <input type="email" wire:model="smtpFromAddress" placeholder="billing@yourstore.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Sender Display Name</label>
                    <input type="text" wire:model="smtpFromName" placeholder="Store Invoicing & Billing" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>
            </div>

            <!-- Test Email Trigger -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="text-xs text-slate-400">
                    Send a test email to verify your SMTP configuration
                </div>

                <div class="flex items-center gap-2">
                    <input type="email" wire:model="testEmailTo" placeholder="your-email@example.com" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs py-2 px-3 focus:ring-blue-500">
                    <button type="button"
                            wire:click="sendTestEmail"
                            class="px-4 py-2 rounded-xl text-xs font-extrabold bg-slate-800 hover:bg-slate-700 text-white shadow-sm transition active:scale-95 whitespace-nowrap cursor-pointer">
                        Test SMTP
                    </button>
                </div>
            </div>

            <!-- Save Button for Notification Settings -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    Save Notification & Dispatch Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Unified Bottom Save Action Bar -->
    <div class="flex justify-between items-center pt-2">
        <span class="text-xs text-slate-400 font-medium">All store settings are scoped and isolated per tenant.</span>
        <button wire:click="save"
                type="button"
                class="px-8 py-3.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
            Save All Settings
        </button>
    </div>

    <!-- Add / Edit Payment Method Modal -->
    @if ($showPaymentMethodModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                        {{ $editingPaymentMethodId ? 'Edit Payment Method' : 'Add New Payment Method' }}
                    </h3>
                    <button type="button" wire:click="$set('showPaymentMethodModal', false)" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Method Name *</label>
                        <input type="text" wire:model="pmName" placeholder="e.g. Credit Card, UPI, PIX, Stripe" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        @error('pmName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Code / Key (Optional)</label>
                        <input type="text" wire:model="pmCode" placeholder="e.g. card, upi, pix, stripe" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Description (Optional)</label>
                        <input type="text" wire:model="pmDescription" placeholder="e.g. Pay via terminal or contactless" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                            <input type="checkbox" wire:model="pmIsActive" class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>Enable this payment method for POS checkout</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showPaymentMethodModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 cursor-pointer">Cancel</button>
                    <button type="button" wire:click="savePaymentMethod" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-blue-600 text-white shadow-sm cursor-pointer">Save Method</button>
                </div>
            </div>
        </div>
    @endif
</div>
