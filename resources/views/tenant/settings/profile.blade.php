        <!-- Store Information -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Store Profile & Location') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Business details displayed on POS terminal, receipts, and invoices') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Company / Store Name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Trade / Brand Name') }}</label>
                    <input type="text" wire:model="tradeName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Tax ID / Business Reg') }}</label>
                    <input type="text" wire:model="taxId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Email') }}</label>
                    <input type="email" wire:model="email" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Phone / WhatsApp') }}</label>
                    <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('phone') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Website URL') }}</label>
                    <input type="text" wire:model="website" placeholder="{{ __('e.g. yourstore.com or https://yourstore.com') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('website') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Subdomain / Slug -->
                @php
                    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'yourdomain.com';
                @endphp
                <div x-data="{ slugValue: @js($slug) }">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Subdomain / Slug') }}</label>
                    <div class="relative flex items-center rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                        <input type="text" wire:model="slug" x-on:input="slugValue = $event.target.value" placeholder="{{ __('my-store') }}" class="w-full border-none bg-transparent text-xs sm:text-sm font-mono focus:ring-0 py-2.5 px-3">
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
                        <span>🌐 {{ __('Custom Domain Configuration') }}</span>
                        <span class="text-[10px] text-slate-400 font-normal">({{ __('Optional') }})</span>
                    </label>
                    <input type="text" wire:model="customDomain" x-on:input="customDomainValue = $event.target.value" placeholder="{{ __('pos.yourdomain.com or mycompany.com') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-[11px] text-slate-400 mt-1">
                        {{ __("Point your domain's") }} <span class="font-semibold text-slate-500 dark:text-slate-400">CNAME</span> ({{ __('or') }} <span class="font-semibold text-slate-500 dark:text-slate-400">A-record</span>, {{ __("if CNAME isn't supported at your registrar for this name) to") }}
                        <span class="font-mono text-slate-600 dark:text-slate-300">{{ $appHost }}</span>, {{ __("save this form, then ask your platform admin to finish HTTPS setup for the domain — until that's done it may show a certificate warning.") }}
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
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Physical Address') }}</label>
                    <input type="text" wire:model="address" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('City') }}</label>
                    <input type="text" wire:model="city" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('State / Province') }}</label>
                    <input type="text" wire:model="state" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Postal Code') }}</label>
                    <input type="text" wire:model="postalCode" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Country (2-letter ISO)') }}</label>
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
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Store Theme, Branding & POS Layout') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Application primary theme color, default POS layout design, logo, and document colors') }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Application Theme Color Palette -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Application & Navigation Theme Color') }}</label>
                        <p class="text-[11px] text-slate-400">{{ __('Select the primary accent color for your navigation sidebar, buttons, and badges') }}</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
                        @php
                            $themePresets = [
                                'blue' => ['label' => __('Cobalt Blue'), 'bg' => 'bg-blue-600', 'hex' => '#2563eb'],
                                'emerald' => ['label' => __('Emerald Green'), 'bg' => 'bg-emerald-600', 'hex' => '#059669'],
                                'indigo' => ['label' => __('Royal Indigo'), 'bg' => 'bg-indigo-600', 'hex' => '#4f46e5'],
                                'purple' => ['label' => __('Violet Purple'), 'bg' => 'bg-purple-600', 'hex' => '#7c3aed'],
                                'amber' => ['label' => __('Warm Amber'), 'bg' => 'bg-amber-600', 'hex' => '#d97706'],
                                'rose' => ['label' => __('Ruby Rose'), 'bg' => 'bg-rose-600', 'hex' => '#e11d48'],
                                'slate' => ['label' => __('Midnight Slate'), 'bg' => 'bg-slate-800', 'hex' => '#334155'],
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

                <!-- Dashboard Hero & UI Accent Color -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Dashboard Hero & UI Accent Color') }}</label>
                            <p class="text-[11px] text-slate-400">{{ __('Select the primary accent color applied to the dashboard welcome banner, action buttons, and active highlight pills') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live="primaryColor" class="w-8 h-8 rounded-lg border-none cursor-pointer p-0 bg-transparent" title="{{ __('Pick Custom Accent Color') }}">
                            <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $primaryColor }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-2.5">
                        @php
                            $accentPresets = [
                                '#2563eb' => ['name' => __('Cobalt Blue'), 'hex' => '#2563eb'],
                                '#059669' => ['name' => __('Emerald Green'), 'hex' => '#059669'],
                                '#4f46e5' => ['name' => __('Royal Indigo'), 'hex' => '#4f46e5'],
                                '#7c3aed' => ['name' => __('Violet Purple'), 'hex' => '#7c3aed'],
                                '#d97706' => ['name' => __('Warm Amber'), 'hex' => '#d97706'],
                                '#e11d48' => ['name' => __('Ruby Rose'), 'hex' => '#e11d48'],
                                '#0d9488' => ['name' => __('Teal Ocean'), 'hex' => '#0d9488'],
                                '#334155' => ['name' => __('Midnight Slate'), 'hex' => '#334155'],
                            ];
                        @endphp

                        @foreach ($accentPresets as $hex => $accent)
                            <button type="button"
                                    wire:click="setQuotationColor('{{ $hex }}')"
                                    @class([
                                        'p-2.5 rounded-2xl border-2 flex flex-col items-center gap-1.5 text-center transition-all cursor-pointer shadow-xs active:scale-95',
                                        'border-blue-500 bg-blue-50/40 dark:bg-blue-950/40 ring-2 ring-blue-500/20' => strtolower($primaryColor) === strtolower($hex),
                                        'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800/60' => strtolower($primaryColor) !== strtolower($hex),
                                    ])>
                                <span class="w-6 h-6 rounded-full shadow-xs flex items-center justify-center text-white text-[10px] font-black"
                                      style="background-color: {{ $hex }}">
                                    @if (strtolower($primaryColor) === strtolower($hex)) ✓ @endif
                                </span>
                                <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate w-full">
                                    {{ $accent['name'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Secondary Accent & Drawer / Sidebar Background -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="space-y-1.5 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Secondary Accent Color') }}</label>
                                <p class="text-[10px] text-slate-400">{{ __('Highlights, badges, and status pills') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="accentColor" class="w-8 h-8 rounded-lg border-none cursor-pointer p-0 bg-transparent" title="{{ __('Pick Secondary Accent Color') }}">
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $accentColor }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Sidebar / Drawer Background') }}</label>
                                <p class="text-[10px] text-slate-400">{{ __('Custom tint for mobile drawer & sidebar') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="drawerBg" class="w-8 h-8 rounded-lg border-none cursor-pointer p-0 bg-transparent" title="{{ __('Pick Sidebar / Drawer Background') }}">
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $drawerBg }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Default POS Layout Design Selector -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Default POS Register Layout Design') }}</label>
                        <p class="text-[11px] text-slate-400">{{ __('Choose the default interface style for your Point of Sale counter') }}</p>
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
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Standard Scanner POS') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Search bar, category chips, 3-column retail item cards with side cart.') }}
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
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Supermarket Touch POS') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Function toolbar, vibrant department tiles & right receipt checkout slip.') }}
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
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-600 text-white">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white mt-1">{{ __('Square Stand Register') }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Clean photo tiles, top category tabs, discounts bar & quick-charge pill.') }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Logo & Favicon Upload Section -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-800"
                     x-data="{
                         instantLogoPreview: null,
                         instantFaviconPreview: null,
                         logoError: '',
                         faviconError: '',
                         handleLogoChange(event) {
                             this.logoError = '';
                             const file = event.target.files[0];
                             if (!file) return;
                             const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
                             if (!allowed.includes(file.type)) {
                                 this.logoError = '{{ __('Invalid file format: Please select a valid image file (.png, .jpg, .jpeg, .webp, .svg)') }}';
                                 event.target.value = '';
                                 this.instantLogoPreview = null;
                                 return;
                             }
                             if (file.size > 2048 * 1024) {
                                 this.logoError = '{{ __('File size exceeds 2MB limit. Please choose a smaller image.') }}';
                                 event.target.value = '';
                                 this.instantLogoPreview = null;
                                 return;
                             }
                             const reader = new FileReader();
                             reader.onload = (e) => { this.instantLogoPreview = e.target.result; };
                             reader.readAsDataURL(file);
                         },
                         handleFaviconChange(event) {
                             this.faviconError = '';
                             const file = event.target.files[0];
                             if (!file) return;
                             const allowed = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/jpeg', 'image/webp'];
                             if (!allowed.includes(file.type) && !file.name.endsWith('.ico')) {
                                 this.faviconError = '{{ __('Invalid format: Please select an .ico, .png, or .svg favicon.') }}';
                                 event.target.value = '';
                                 this.instantFaviconPreview = null;
                                 return;
                             }
                             const reader = new FileReader();
                             reader.onload = (e) => { this.instantFaviconPreview = e.target.result; };
                             reader.readAsDataURL(file);
                         }
                     }">
                    <!-- Store Logo Upload -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Business / Store Logo') }}
                        </label>
                        <div class="flex items-start gap-4">
                            <!-- Preview Box -->
                            <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-center overflow-hidden shrink-0 relative group">
                                <template x-if="instantLogoPreview">
                                    <img :src="instantLogoPreview" alt="{{ __('Preview') }}" class="max-w-full max-h-full object-contain p-2">
                                </template>
                                <template x-if="!instantLogoPreview">
                                    <div class="w-full h-full flex items-center justify-center">
                                        @if ($logoFile && $this->logoPreviewUrl)
                                            <img src="{{ $this->logoPreviewUrl }}" alt="{{ __('Preview') }}" class="max-w-full max-h-full object-contain p-2">
                                        @elseif ($company && $company->getLogoUrl())
                                            <img src="{{ $company->getLogoUrl() }}" alt="{{ $company->name }}" class="max-w-full max-h-full object-contain p-2">
                                        @else
                                            <div class="text-center p-2 text-slate-400">
                                                <svg class="w-8 h-8 mx-auto stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-[9px] block mt-1 font-semibold">{{ __('No Logo') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </template>

                                @if ($logoFile || $company->getLogoUrl())
                                    <button type="button"
                                            wire:click="removeLogo"
                                            x-on:click="instantLogoPreview = null"
                                            wire:confirm="{{ __('Are you sure you want to remove the business logo?') }}"
                                            class="absolute inset-0 bg-red-950/70 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-[10px] font-bold">
                                        <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        {{ __('Remove') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <span>{{ __('Upload Logo File') }}</span>
                                    <input type="file" wire:model="logoFile" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" x-on:change="handleLogoChange($event)" class="hidden">
                                </label>
                                <div wire:loading wire:target="logoFile" class="text-[11px] text-blue-600 font-semibold block">
                                    {{ __('Uploading logo...') }}
                                </div>
                                <p class="text-[10px] text-slate-400">
                                    {{ __('PNG, JPG, SVG or WEBP up to 2MB. Printed on 58mm/80mm receipts and PDF Quotations.') }}
                                </p>
                                <template x-if="logoError">
                                    <p class="text-[11px] text-red-500 font-bold" x-text="logoError"></p>
                                </template>
                                @error('logoFile') <span class="text-[10px] text-red-500 font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Favicon Upload -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Browser Favicon') }}
                        </label>
                        <div class="flex items-start gap-4">
                            <!-- Preview Box -->
                            <div class="w-16 h-16 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-center overflow-hidden shrink-0 relative group">
                                <template x-if="instantFaviconPreview">
                                    <img :src="instantFaviconPreview" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                </template>
                                <template x-if="!instantFaviconPreview">
                                    <div class="w-full h-full flex items-center justify-center">
                                        @if ($faviconFile && $this->faviconPreviewUrl)
                                            <img src="{{ $this->faviconPreviewUrl }}" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                        @elseif ($company && $company->getFaviconUrl())
                                            <img src="{{ $company->getFaviconUrl() }}" alt="{{ __('Favicon') }}" class="w-8 h-8 object-contain">
                                        @else
                                            <span class="text-xl">🌐</span>
                                        @endif
                                    </div>
                                </template>

                                @if ($faviconFile || $company->getFaviconUrl())
                                    <button type="button"
                                            wire:click="removeFavicon"
                                            x-on:click="instantFaviconPreview = null"
                                            class="absolute inset-0 bg-red-950/70 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-[9px] font-bold">
                                        {{ __('Clear') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span>{{ __('Upload Favicon') }}</span>
                                    <input type="file" wire:model="faviconFile" accept="image/png, image/x-icon, image/vnd.microsoft.icon, image/svg+xml, image/jpeg, image/webp, .ico" x-on:change="handleFaviconChange($event)" class="hidden">
                                </label>
                                <p class="text-[10px] text-slate-400">
                                    {{ __('Small 32x32 icon (.ico, .png, .svg) displayed in browser tabs.') }}
                                </p>
                                <template x-if="faviconError">
                                    <p class="text-[11px] text-red-500 font-bold" x-text="faviconError"></p>
                                </template>
                                @error('faviconFile') <span class="text-[10px] text-red-500 font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thermal Receipt Printing & Commission Configuration -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <!-- Thermal Receipt Paper Width -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Thermal Receipt Paper Width') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mb-2">
                            {{ __('Default printer paper dimension for checkout printouts and order slips') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                    wire:click="setReceiptFormat('80mm')"
                                    @class([
                                        'p-3 rounded-xl border-2 flex items-center gap-3 transition cursor-pointer text-left',
                                        'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-100' => $receiptFormat === '80mm',
                                        'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300' => $receiptFormat !== '80mm',
                                    ])>
                                <span class="text-2xl">🧾</span>
                                <div>
                                    <div class="text-xs font-black">{{ __('80mm (Standard)') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ __('Full 3-inch POS printer') }}</div>
                                </div>
                            </button>

                            <button type="button"
                                    wire:click="setReceiptFormat('58mm')"
                                    @class([
                                        'p-3 rounded-xl border-2 flex items-center gap-3 transition cursor-pointer text-left',
                                        'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-100' => $receiptFormat === '58mm',
                                        'border-slate-200 dark:border-slate-700 hover:border-slate-300 text-slate-700 dark:text-slate-300' => $receiptFormat !== '58mm',
                                    ])>
                                <span class="text-2xl">📱</span>
                                <div>
                                    <div class="text-xs font-black">{{ __('58mm (Mini)') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ __('2-inch portable / bluetooth') }}</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Store Default Commission Scheme -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('Default Sales Commission Scheme') }}
                        </label>
                        <p class="text-[11px] text-slate-400 mb-2">
                            {{ __('Baseline commission rate auto-assigned to sales staff unless overridden per user') }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('Commission Type') }}</label>
                                <select wire:model="defaultCommissionType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                                    <option value="percentage">{{ __('Percentage (%) of Sale Total') }}</option>
                                    <option value="fixed">{{ __('Fixed Amount ($) per Sale') }}</option>
                                    <option value="profit_percentage">{{ __('Percentage (%) of Net Profit') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('Rate / Amount') }}</label>
                                <input type="number" step="0.01" min="0" wire:model="defaultCommissionRate" placeholder="0.00" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quotation & Invoice Document Accent Color -->
                <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Quotation & Invoice Document Accent Color') }}</label>
                            <p class="text-[11px] text-slate-400">{{ __('Choose a primary header & table theme color for Quotations and PDF Receipts') }}</p>
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
                                '#2d7a58' => __('Forest Green'),
                                '#2563eb' => __('Cobalt Blue'),
                                '#7c3aed' => __('Royal Purple'),
                                '#e11d48' => __('Crimson Red'),
                                '#d97706' => __('Amber Gold'),
                                '#1e293b' => __('Midnight Slate'),
                                '#059669' => __('Emerald Mint'),
                                '#0284c7' => __('Ocean Sky'),
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

            <!-- Save Button for Profile & Branding -->
            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="save"
                        type="button"
                        class="px-8 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                    {{ __('Save All Settings') }}
                </button>
            </div>
        </div>
