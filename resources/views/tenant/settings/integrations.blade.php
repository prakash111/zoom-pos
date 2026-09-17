<!-- =========================================================================
     TENANT NOTIFICATION GATEWAYS & DEVELOPER INTEGRATIONS
     Mirroring SDUI Tabbed Layout (WhatsApp, SMS, SMTP, Webhook, API Tokens)
     ========================================================================= -->
<div x-data="{ integrationsTab: @js($integrationsSubTab ?? 'whatsapp') }" class="space-y-6">

    <!-- Sub-tab Navigation Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-200/80 dark:border-slate-800 scrollbar-none">
        <button type="button"
                @click="integrationsTab = 'whatsapp'"
                :class="integrationsTab === 'whatsapp' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold border border-slate-200/80 dark:border-slate-800'"
                class="px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">💬</span>
            <span>{{ __('WhatsApp Business') }}</span>
            @if($whatsappEnabled)
                <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
            @endif
        </button>

        <button type="button"
                @click="integrationsTab = 'sms'"
                :class="integrationsTab === 'sms' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold border border-slate-200/80 dark:border-slate-800'"
                class="px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">📱</span>
            <span>{{ __('SMS Gateways') }}</span>
            @if($smsEnabled)
                <span class="w-2 h-2 rounded-full bg-blue-300 animate-pulse"></span>
            @endif
        </button>

        <button type="button"
                @click="integrationsTab = 'smtp'"
                :class="integrationsTab === 'smtp' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold border border-slate-200/80 dark:border-slate-800'"
                class="px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">✉️</span>
            <span>{{ __('Custom SMTP') }}</span>
            @if($smtpEnabled)
                <span class="w-2 h-2 rounded-full bg-amber-300 animate-pulse"></span>
            @endif
        </button>

        <button type="button"
                @click="integrationsTab = 'webhook'"
                :class="integrationsTab === 'webhook' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold border border-slate-200/80 dark:border-slate-800'"
                class="px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🪝</span>
            <span>{{ __('Custom Webhook') }}</span>
            @if($webhookEnabled)
                <span class="w-2 h-2 rounded-full bg-purple-300 animate-pulse"></span>
            @endif
        </button>

        <button type="button"
                @click="integrationsTab = 'api_keys'"
                :class="integrationsTab === 'api_keys' ? 'bg-slate-900 text-white dark:bg-slate-700 shadow-md font-black' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold border border-slate-200/80 dark:border-slate-800'"
                class="px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 shrink-0 transition-all cursor-pointer">
            <span class="text-sm">🔑</span>
            <span>{{ __('REST API & AI Studio') }}</span>
        </button>
    </div>

    <!-- =========================================================================
         CARD 1: WHATSAPP BUSINESS GATEWAY
         ========================================================================= -->
    <div x-show="integrationsTab === 'whatsapp'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-7 shadow-sm space-y-6">
            <!-- Header & Toggle -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl font-bold">
                        💬
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ __('WhatsApp Business Gateway') }}</span>
                            @if($whatsappEnabled)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">{{ __('ACTIVE') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-500">{{ __('DISABLED') }}</span>
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Send digital receipts, invoices, quotations, and due reminders directly via WhatsApp. Activating this channel instantly enables the WhatsApp checkbox at POS checkout and document sharing.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="whatsappEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                        <span class="ml-2.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable WhatsApp') }}</span>
                    </label>
                </div>
            </div>

            <!-- Provider Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">{{ __('Active WhatsApp Provider') }}</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20' => $whatsappProvider === 'meta_cloud_api', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $whatsappProvider !== 'meta_cloud_api'])>
                        <input type="radio" name="whatsapp_provider" wire:model.live="whatsappProvider" value="meta_cloud_api" class="text-emerald-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Meta WhatsApp Cloud API (Official)') }}</div>
                            <div class="text-[11px] text-slate-500">{{ __('Direct official Graph API from Meta Business Suite') }}</div>
                        </div>
                    </label>

                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20' => $whatsappProvider === 'twilio', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $whatsappProvider !== 'twilio'])>
                        <input type="radio" name="whatsapp_provider" wire:model.live="whatsappProvider" value="twilio" class="text-emerald-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Twilio WhatsApp API') }}</div>
                            <div class="text-[11px] text-slate-500">{{ __('Programmable Messaging WhatsApp sandbox or approved number') }}</div>
                        </div>
                    </label>

                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20' => $whatsappProvider === 'unofficial_http', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $whatsappProvider !== 'unofficial_http'])>
                        <input type="radio" name="whatsapp_provider" wire:model.live="whatsappProvider" value="unofficial_http" class="text-emerald-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Unofficial / Self-hosted API') }}</div>
                            <div class="text-[11px] text-amber-600">{{ __('For an approved private bridge or WAPI provider') }}</div>
                        </div>
                    </label>
                </div>
            </div>

            @if($whatsappProvider === 'unofficial_http')
                <div class="p-5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Unofficial WhatsApp API Credentials') }}</div>
                    <p class="text-[11px] text-amber-700 dark:text-amber-300">{{ __('Use only a provider and account permitted by applicable WhatsApp policies.') }}</p>
                    <input type="url" wire:model="unofficialWhatsappUrl" placeholder="https://gateway.example/messages" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono text-slate-900 dark:text-white">
                    <input type="password" wire:model="unofficialWhatsappToken" placeholder="{{ ($company->is_demo ?? false) || str_ends_with(strtolower((string) $company->email), '@zoomnearby.com') ? __('Configured (hidden)') : __('Bearer token') }}" autocomplete="new-password" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono text-slate-900 dark:text-white">
                </div>
            @endif

            <!-- Meta Cloud API Credentials Form -->
            @if($whatsappProvider === 'meta_cloud_api')
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>{{ __('Meta Cloud API Credentials (Encrypted AES-256 Storage)') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone Number ID') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="metaPhoneNumberId" placeholder="e.g. 104523456789012"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                            <p class="text-[10px] text-slate-400 mt-1">{{ __('From WhatsApp > API Setup in Meta Developer Dashboard.') }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('WhatsApp Business Account ID (WABA ID)') }}</label>
                            <input type="text" wire:model="metaWabaId" placeholder="e.g. 108765432109876"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Permanent System User Access Token') }} <span class="text-rose-500">*</span></label>
                        <input type="password" wire:model="metaAccessToken" placeholder="EAAG..." autocomplete="new-password"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        <p class="text-[10px] text-slate-400 mt-1">{{ __('Generated from Meta Business Manager > System Users with whatsapp_business_messaging permissions.') }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Template Namespace / Name (Optional)') }}</label>
                        <input type="text" wire:model="metaTemplateNamespace" placeholder="e.g. store_receipt_v1"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
            @endif

            <!-- Twilio WhatsApp Credentials Form -->
            @if($whatsappProvider === 'twilio')
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>{{ __('Twilio WhatsApp Credentials') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Twilio Account SID') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="twilioWhatsappSid" placeholder="AC..."
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Twilio Auth Token') }} <span class="text-rose-500">*</span></label>
                            <input type="password" wire:model="twilioWhatsappToken" placeholder="Auth Token" autocomplete="new-password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Sender WhatsApp Number') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="twilioWhatsappFrom" placeholder="e.g. +14155238886"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>
                    </div>
                </div>
            @endif

            <!-- Save WhatsApp Button -->
            <div class="flex justify-end pt-2">
                <button type="button" wire:click="saveWhatsAppGateway" wire:loading.attr="disabled"
                        class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition cursor-pointer flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveWhatsAppGateway">💾 {{ __('Save WhatsApp Gateway') }}</span>
                    <span wire:loading wire:target="saveWhatsAppGateway">⏳ {{ __('Saving Credentials...') }}</span>
                </button>
            </div>

            <!-- Test WhatsApp Dispatch Section -->
            <div class="p-5 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/60 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-black text-emerald-950 dark:text-emerald-300 flex items-center gap-1.5">
                            <span>🚀</span>
                            <span>{{ __('Test WhatsApp Dispatch') }}</span>
                        </h4>
                        <p class="text-[11px] text-emerald-800/80 dark:text-emerald-400 mt-0.5">
                            {{ __('Send an instant verification test ping to verify your credentials before sending to customers.') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-1">
                    <input type="text" wire:model="whatsappTestPhone" placeholder="{{ __('e.g. 14155552671 (with country code)') }}"
                           class="flex-1 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-emerald-300 dark:border-emerald-700 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    <button type="button" wire:click="testWhatsApp" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition active:scale-95 cursor-pointer whitespace-nowrap">
                        <span wire:loading.remove wire:target="testWhatsApp">📲 {{ __('Send Test Ping') }}</span>
                        <span wire:loading wire:target="testWhatsApp">⏳ {{ __('Dispatching...') }}</span>
                    </button>
                </div>

                @if($whatsappTestResult)
                    <div @class(['p-3 rounded-xl text-xs font-mono font-semibold flex items-center gap-2', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 border border-emerald-300' => $whatsappTestStatus === 'success', 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300 border border-rose-300' => $whatsappTestStatus === 'error'])>
                        <span>{{ $whatsappTestStatus === 'success' ? '✅' : '❌' }}</span>
                        <span>{{ $whatsappTestResult }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =========================================================================
         CARD 2: SMS GATEWAYS
         ========================================================================= -->
    <div x-show="integrationsTab === 'sms'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-7 shadow-sm space-y-6">
            <!-- Header & Toggle -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl font-bold">
                        📱
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ __('SMS Notification Gateways') }}</span>
                            @if($smsEnabled)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">{{ __('ACTIVE') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-500">{{ __('DISABLED') }}</span>
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Send transactional SMS for receipts, balance due reminders, and OTP alerts. Toggling this on immediately makes SMS checkboxes selectable in checkout and document share sheets.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="smsEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                        <span class="ml-2.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable SMS') }}</span>
                    </label>
                </div>
            </div>

            <!-- Provider Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">{{ __('Active SMS Provider') }}</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-blue-500 bg-blue-50/30 dark:bg-blue-950/20 ring-2 ring-blue-500/20' => $smsProvider === 'twilio', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $smsProvider !== 'twilio'])>
                        <input type="radio" name="sms_provider" wire:model.live="smsProvider" value="twilio" class="text-blue-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Twilio SMS') }}</div>
                            <div class="text-[11px] text-slate-500">{{ __('Global carrier delivery') }}</div>
                        </div>
                    </label>

                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-blue-500 bg-blue-50/30 dark:bg-blue-950/20 ring-2 ring-blue-500/20' => $smsProvider === 'msg91', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $smsProvider !== 'msg91'])>
                        <input type="radio" name="sms_provider" wire:model.live="smsProvider" value="msg91" class="text-blue-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('MSG91 (India DLT)') }}</div>
                            <div class="text-[11px] text-slate-500">{{ __('DLT compliance & flow routing') }}</div>
                        </div>
                    </label>

                    <label @class(['p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3', 'border-blue-500 bg-blue-50/30 dark:bg-blue-950/20 ring-2 ring-blue-500/20' => $smsProvider === 'generic_http', 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' => $smsProvider !== 'generic_http'])>
                        <input type="radio" name="sms_provider" wire:model.live="smsProvider" value="generic_http" class="text-blue-600 focus:ring-0">
                        <div>
                            <div class="text-xs font-black text-slate-900 dark:text-white">{{ __('Generic HTTP Gateway') }}</div>
                            <div class="text-[11px] text-slate-500">{{ __('Custom REST SMS endpoint') }}</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Twilio SMS Form -->
            @if($smsProvider === 'twilio')
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>{{ __('Twilio SMS Credentials') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account SID') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="smsTwilioSid" placeholder="AC..."
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Auth Token') }} <span class="text-rose-500">*</span></label>
                            <input type="password" wire:model="smsTwilioToken" placeholder="Auth Token" autocomplete="new-password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Sender ID / From Number') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="smsTwilioFrom" placeholder="e.g. +12025550192"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>
                    </div>
                </div>
            @endif

            <!-- MSG91 Form -->
            @if($smsProvider === 'msg91')
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>{{ __('MSG91 India DLT Credentials') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Auth Key') }} <span class="text-rose-500">*</span></label>
                            <input type="password" wire:model="msg91AuthKey" placeholder="Auth Key" autocomplete="new-password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Approved Sender ID') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="msg91SenderId" placeholder="e.g. ZOOMNB (6 characters)" maxlength="10"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('DLT Template / Flow ID') }}</label>
                            <input type="text" wire:model="msg91DltTemplateId" placeholder="e.g. 64b3..."
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                        </div>
                    </div>
                </div>
            @endif

            <!-- Generic HTTP REST SMS Form -->
            @if($smsProvider === 'generic_http')
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                    <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span>🛡️</span>
                        <span>{{ __('Generic HTTP REST SMS Gateway') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Gateway Endpoint URL') }} <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="genericSmsUrl" placeholder="https://api.sms.com/send?to={phone}&msg={message}"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono text-slate-900 dark:text-white">
                            <p class="text-[10px] text-slate-400 mt-1">{{ __('Use {phone} and {message} placeholders in URL or payload.') }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('HTTP Method') }}</label>
                            <select wire:model="genericSmsMethod" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-900 dark:text-white">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('API Key / Bearer Token (Optional)') }}</label>
                        <input type="password" wire:model="genericSmsApiKey" placeholder="API Key / Token" autocomplete="new-password"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>
                </div>
            @endif

            <!-- Save SMS Button -->
            <div class="flex justify-end pt-2">
                <button type="button" wire:click="saveSmsGateway" wire:loading.attr="disabled"
                        class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-600/20 active:scale-95 transition cursor-pointer flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveSmsGateway">💾 {{ __('Save SMS Gateway') }}</span>
                    <span wire:loading wire:target="saveSmsGateway">⏳ {{ __('Saving Gateway...') }}</span>
                </button>
            </div>

            <!-- Test SMS Dispatch Section -->
            <div class="p-5 rounded-2xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200/80 dark:border-blue-800/60 space-y-3">
                <div>
                    <h4 class="text-xs font-black text-blue-950 dark:text-blue-300 flex items-center gap-1.5">
                        <span>🚀</span>
                        <span>{{ __('Test SMS Dispatch') }}</span>
                    </h4>
                    <p class="text-[11px] text-blue-800/80 dark:text-blue-400 mt-0.5">
                        {{ __('Dispatch a test SMS to confirm provider credentials and route deliverability.') }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-1">
                    <input type="text" wire:model="smsTestPhone" placeholder="{{ __('e.g. 12025550192 (with country code)') }}"
                           class="flex-1 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-blue-300 dark:border-blue-700 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    <button type="button" wire:click="testSms" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition active:scale-95 cursor-pointer whitespace-nowrap">
                        <span wire:loading.remove wire:target="testSms">📨 {{ __('Send Test SMS') }}</span>
                        <span wire:loading wire:target="testSms">⏳ {{ __('Sending...') }}</span>
                    </button>
                </div>

                @if($smsTestResult)
                    <div @class(['p-3 rounded-xl text-xs font-mono font-semibold flex items-center gap-2', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 border border-emerald-300' => $smsTestStatus === 'success', 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300 border border-rose-300' => $smsTestStatus === 'error'])>
                        <span>{{ $smsTestStatus === 'success' ? '✅' : '❌' }}</span>
                        <span>{{ $smsTestResult }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =========================================================================
         CARD 3: CUSTOM SMTP / MAILER
         ========================================================================= -->
    <div x-show="integrationsTab === 'smtp'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-7 shadow-sm space-y-6">
            <!-- Header & Toggle -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold">
                        ✉️
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ __('Custom SMTP Mail Server') }}</span>
                            @if($smtpEnabled)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">{{ __('ACTIVE') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-500">{{ __('DISABLED') }}</span>
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Send branded PDF invoices, receipts, and quotations directly from your store email domain via your authenticated mail host.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="smtpEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-600"></div>
                        <span class="ml-2.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable SMTP') }}</span>
                    </label>
                </div>
            </div>

            <!-- SMTP Settings Form -->
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span>🛡️</span>
                    <span>{{ __('SMTP Host Credentials') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('SMTP Host') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="smtpHost" placeholder="smtp.gmail.com or mail.store.com"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Port') }} <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="smtpPort" placeholder="587"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Encryption') }}</label>
                        <select wire:model="smtpEncryption" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-900 dark:text-white">
                            <option value="tls">{{ __('TLS (Port 587 - Recommended)') }}</option>
                            <option value="ssl">{{ __('SSL (Port 465)') }}</option>
                            <option value="none">{{ __('None / Plain (Port 25)') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('SMTP Username') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="smtpUsername" placeholder="billing@yourstore.com"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('SMTP Password') }}
                            @if($hasStoredSmtpPassword)
                                <span class="text-[10px] text-emerald-600 font-semibold">({{ __('Saved') }})</span>
                            @endif
                        </label>
                        <input type="password" wire:model="smtpPassword" placeholder="{{ $hasStoredSmtpPassword ? __('Leave blank to keep saved password') : '••••••••••••' }}" autocomplete="new-password"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('From Email Address') }}</label>
                        <input type="email" wire:model="smtpFromAddress" placeholder="receipts@yourstore.com"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('From Display Name') }}</label>
                        <input type="text" wire:model="smtpFromName" placeholder="{{ $company->name ?? 'Store Name' }}"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-900 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Save SMTP Button -->
            <div class="flex justify-end pt-2">
                <button type="button" wire:click="saveSmtpGateway" wire:loading.attr="disabled"
                        class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md shadow-amber-600/20 active:scale-95 transition cursor-pointer flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveSmtpGateway">💾 {{ __('Save SMTP Server') }}</span>
                    <span wire:loading wire:target="saveSmtpGateway">⏳ {{ __('Saving SMTP...') }}</span>
                </button>
            </div>

            <!-- Test Email Dispatch Section -->
            <div class="p-5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/60 space-y-3">
                <div>
                    <h4 class="text-xs font-black text-amber-950 dark:text-amber-300 flex items-center gap-1.5">
                        <span>🚀</span>
                        <span>{{ __('Send Test Email') }}</span>
                    </h4>
                    <p class="text-[11px] text-amber-800/80 dark:text-amber-400 mt-0.5">
                        {{ __('Send a live verification email to check your server credentials.') }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-1">
                    <input type="email" wire:model="smtpTestEmail" placeholder="{{ __('you@example.com') }}"
                           class="flex-1 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 text-xs font-mono font-bold text-slate-900 dark:text-white">
                    <button type="button" wire:click="testSmtp" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm transition active:scale-95 cursor-pointer whitespace-nowrap">
                        <span wire:loading.remove wire:target="testSmtp">✉️ {{ __('Send Test Email') }}</span>
                        <span wire:loading wire:target="testSmtp">⏳ {{ __('Sending...') }}</span>
                    </button>
                </div>

                @if($smtpTestResult)
                    <div @class(['p-3 rounded-xl text-xs font-mono font-semibold flex items-center gap-2', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 border border-emerald-300' => $smtpTestStatus === 'success', 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300 border border-rose-300' => $smtpTestStatus === 'error'])>
                        <span>{{ $smtpTestStatus === 'success' ? '✅' : '❌' }}</span>
                        <span>{{ $smtpTestResult }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =========================================================================
         CARD 4: CUSTOM WEBHOOK DISPATCHER
         ========================================================================= -->
    <div x-show="integrationsTab === 'webhook'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-7 shadow-sm space-y-6">
            <!-- Header & Toggle -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl font-bold">
                        🪝
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ __('Custom Webhook Dispatcher') }}</span>
                            @if($webhookEnabled)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">{{ __('ACTIVE') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-500">{{ __('DISABLED') }}</span>
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Stream POS transactions and financial events to external endpoints in real-time with HMAC SHA-256 signature verification.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="webhookEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-purple-600"></div>
                        <span class="ml-2.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Enable Webhooks') }}</span>
                    </label>
                </div>
            </div>

            <!-- Webhook Settings Form -->
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-4">
                <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span>🛡️</span>
                    <span>{{ __('Webhook Endpoint Configuration') }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Webhook Destination URL') }} <span class="text-rose-500">*</span></label>
                        <input type="url" wire:model="webhookUrl" placeholder="https://api.yourdomain.com/pos-events"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('HTTP Method') }}</label>
                        <select wire:model="webhookMethod" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-900 dark:text-white">
                            <option value="POST">POST (JSON)</option>
                            <option value="PUT">PUT (JSON)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('HMAC SHA-256 Secret Key (Optional)') }}</label>
                    <input type="password" wire:model="webhookSecret" placeholder="Shared secret key for signature verification (sent in X-Signature header)" autocomplete="new-password"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold text-slate-900 dark:text-white">
                </div>

                <!-- Event Subscriptions -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">{{ __('Event Subscriptions') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach([
                            'receipt_generated' => [__('Receipt Generated'), __('Triggered when a sale is finalized and receipt is generated')],
                            'invoice_created' => [__('Order / Invoice Created'), __('Triggered when an invoice or checkout order is placed')],
                            'quotation_sent' => [__('Quotation Sent'), __('Triggered when a quotation is generated or emailed')],
                            'due_reminder' => [__('Overdue / Due Reminder'), __('Triggered when an account balance due reminder is dispatched')],
                        ] as $eventKey => [$eventLabel, $eventDesc])
                            <label class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-start gap-3 cursor-pointer hover:border-purple-400 transition">
                                <input type="checkbox" wire:model="webhookEvents" value="{{ $eventKey }}" class="mt-0.5 text-purple-600 rounded focus:ring-0">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $eventLabel }}</div>
                                    <div class="text-[11px] text-slate-400 leading-snug mt-0.5">{{ $eventDesc }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Save Webhook Button -->
            <div class="flex justify-end pt-2">
                <button type="button" wire:click="saveWebhookGateway" wire:loading.attr="disabled"
                        class="px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs shadow-md shadow-purple-600/20 active:scale-95 transition cursor-pointer flex items-center gap-2">
                    <span wire:loading.remove wire:target="saveWebhookGateway">💾 {{ __('Save Webhook Dispatcher') }}</span>
                    <span wire:loading wire:target="saveWebhookGateway">⏳ {{ __('Saving Webhook...') }}</span>
                </button>
            </div>

            <!-- Ping Webhook Test Button -->
            <div class="p-5 rounded-2xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200/80 dark:border-purple-800/60 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-black text-purple-950 dark:text-purple-300 flex items-center gap-1.5">
                            <span>🚀</span>
                            <span>{{ __('Ping Webhook Endpoint') }}</span>
                        </h4>
                        <p class="text-[11px] text-purple-800/80 dark:text-purple-400 mt-0.5">
                            {{ __('Send a live test payload to your webhook destination to verify receipt.') }}
                        </p>
                    </div>

                    <button type="button" wire:click="testWebhook" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition active:scale-95 cursor-pointer whitespace-nowrap">
                        <span wire:loading.remove wire:target="testWebhook">🪝 {{ __('Ping Webhook') }}</span>
                        <span wire:loading wire:target="testWebhook">⏳ {{ __('Pinging...') }}</span>
                    </button>
                </div>

                @if($webhookTestResult)
                    <div @class(['p-3 rounded-xl text-xs font-mono font-semibold flex items-center gap-2', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 border border-emerald-300' => $webhookTestStatus === 'success', 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300 border border-rose-300' => $webhookTestStatus === 'error'])>
                        <span>{{ $webhookTestStatus === 'success' ? '✅' : '❌' }}</span>
                        <span>{{ $webhookTestResult }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- =========================================================================
         CARD 5: REST API TOKENS & GENERATIVE AI STUDIO
         ========================================================================= -->
    <div x-show="integrationsTab === 'api_keys'" x-cloak class="space-y-6">
        <!-- AI Vision Studio -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center text-lg">✨</span>
                    <div><h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Generative AI Studio & Vision Engine') }}</h3><p class="text-xs text-slate-400">{{ __('Automate studio-grade commercial product imagery from product titles and categories.') }}</p></div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">{{ __('Active Provider:') }} {{ strtoupper($defaultAiProvider) }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                @foreach([
                    'openai' => ['OpenAI', 'DALL-E / GPT Vision', 'openaiModel', 'openaiApiKey', 'sk-proj-...', $hasOpenaiApiKey],
                    'gemini' => ['Google Gemini', 'Imagen / Gemini', 'geminiModel', 'geminiApiKey', 'AIzaSy...', $hasGeminiApiKey],
                    'claude' => ['Anthropic Claude', 'Vision + Prompt', 'claudeModel', 'claudeApiKey', 'sk-ant-...', $hasClaudeApiKey],
                ] as $provider => [$title, $family, $modelProperty, $keyProperty, $placeholder, $stored])
                    <div @class(['p-4 rounded-2xl border transition-all duration-200 space-y-4', 'border-purple-500 bg-purple-50/20 dark:bg-purple-950/20 ring-2 ring-purple-500/20' => $defaultAiProvider === $provider, 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30' => $defaultAiProvider !== $provider])>
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="provider" wire:model.live="defaultAiProvider" value="{{ $provider }}" class="text-purple-600 focus:ring-0"><span class="text-xs font-black text-slate-800 dark:text-white">{{ $title }}</span></label>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $family }}</span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Model Preset (Legacy to Flagship)') }}</label>
                            <select wire:model="{{ $modelProperty }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200">
                                @foreach($this->modelPresets[$provider] as $model)
                                    <option value="{{ $model['id'] }}">{{ $model['name'] }} — {{ $model['badge'] }}</option>
                                @endforeach
                            </select>
                            @error($modelProperty)<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('API Key') }} @if($stored)<span class="text-emerald-600">· {{ __('Saved') }}</span>@endif</label>
                            <input type="password" wire:model="{{ $keyProperty }}" placeholder="{{ $stored ? __('Leave blank to keep saved key') : $placeholder }}" autocomplete="new-password" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800"><button type="button" wire:click="saveAiConfiguration" class="px-6 py-2.5 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs shadow-sm transition active:scale-95">{{ __('Save AI Configuration') }}</button></div>
        </div>

        <!-- Sanctum API Keys Table -->
        <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>⚡</span>
                    <span>{{ __('Developer API Keys & E-Invoicing Gateway') }}</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    {{ __('Manage bearer API credentials for external ERPs, Shopify/WooCommerce webhooks, and third-party accounting integrations.') }}
                </p>
            </div>

            <button type="button"
                    wire:click="$set('showApiKeyModal', true)"
                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md shadow-blue-500/20">
                <span>+</span>
                <span>{{ __('Generate New API Key') }}</span>
            </button>
        </div>

        <!-- Active API Keys Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                    {{ __('Active API Tokens') }}
                </div>
                <span class="text-xs text-slate-400 font-mono">
                    {{ count($apiKeys) }} {{ __('keys active') }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('Key Name') }}</th>
                            <th class="px-6 py-3.5">{{ __('Token Identifier') }}</th>
                            <th class="px-6 py-3.5">{{ __('Permissions / Scopes') }}</th>
                            <th class="px-6 py-3.5">{{ __('Last Used') }}</th>
                            <th class="px-6 py-3.5">{{ __('Status') }}</th>
                            <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @forelse ($apiKeys as $k)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">{{ $k->name }}</td>
                                <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-400">{{ substr($k->token, 0, 14) }}••••••••••••••••</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($k->permissions ?? ['*'] as $perm)
                                            <span class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-mono font-bold">{{ $perm }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">{{ $k->last_used_at ? $k->last_used_at->diffForHumans() : __('Never used') }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $k->active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ $k->active ? __('Active') : __('Disabled') }}</span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button type="button" wire:click="toggleApiKey('{{ $k->id }}')" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 text-xs font-bold transition cursor-pointer">{{ $k->active ? __('Disable') : __('Enable') }}</button>
                                    <button type="button" wire:click="revokeApiKey('{{ $k->id }}')" wire:confirm="{{ __('Revoke this API Key? Any external systems using it will lose access.') }}" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-bold transition cursor-pointer">{{ __('Revoke') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                    {{ __('No API keys generated yet. Click "Generate New API Key" above to integrate third-party systems.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- API Reference & Quick Testing Documentation -->
        <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
            <h4 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                <span>📖</span>
                <span>{{ __('Developer API Quick Reference') }}</span>
            </h4>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Calculate Tax Endpoint -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-md bg-blue-600 text-white font-mono text-[10px] font-black">POST</span>
                        <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">/api/v1/tax/calculate</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Computes subtotal, customer exemptions, and itemized tax breakdowns.') }}
                    </p>
                    <pre class="p-2.5 rounded-xl bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto"><code>curl -X POST "{{ url('/api/v1/tax/calculate') }}" \
  -H "Authorization: Bearer zk_live_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"name":"Item 1","quantity":2,"price":15.0}]}'</code></pre>
                </div>

                <!-- Issue Invoice Endpoint -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white font-mono text-[10px] font-black">POST</span>
                        <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">/api/v1/tax/invoices</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Directly creates a cleared tax invoice inside the tenant database.') }}
                    </p>
                    <pre class="p-2.5 rounded-xl bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto"><code>curl -X POST "{{ url('/api/v1/tax/invoices') }}" \
  -H "Authorization: Bearer zk_live_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"customer":{"name":"Acme Corp","tax_id":"27AA..."},"items":[{"name":"Service","quantity":1,"price":100}]}'</code></pre>
                </div>
            </div>
        </div>
    </div>

</div>
