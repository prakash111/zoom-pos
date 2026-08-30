@php
    $company = $quote->company;
    $primaryColor = $company?->primary_color ?: '#2d7a58';
    $hex = ltrim($primaryColor, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $lightBg = sprintf('rgba(%d, %d, %d, 0.08)', $r, $g, $b);
    $mediumBg = sprintf('rgba(%d, %d, %d, 0.16)', $r, $g, $b);
    $borderTint = sprintf('rgba(%d, %d, %d, 0.22)', $r, $g, $b);
    $quoteTaxRows = \App\Services\TaxEngineService::normalizeTaxBreakdown($quote->tax_breakdown);
    $appliedTaxRuleName = $quoteTaxRows[0]['name'] ?? $quote->tax_name;
    $taxIdentifierLabel = \App\Services\TaxEngineService::getTaxIdentifierLabel($company?->country, $appliedTaxRuleName);
    $customerTaxNumber = $quote->customer?->tax_id ?: ($quote->customer?->gstin ?: $quote->customer?->document);
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data>
    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-emerald-100 dark:border-emerald-900/50">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3.5 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-rose-100 dark:border-rose-900/50">
            <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Top Action Bar -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-lg shadow-sm"
                 style="background-color: {{ $lightBg }}; color: {{ $primaryColor }}; border: 1px solid {{ $borderTint }};">
                📋
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $quote->sale_number }}</h2>
                    <span @class([
                        'px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider',
                        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $quote->status === 'draft',
                        'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' => $quote->status === 'sent',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $quote->status === 'accepted' || $quote->status === 'converted',
                        'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $quote->status === 'rejected',
                    ])>{{ $quote->status }}</span>
                </div>
                <div class="text-xs text-slate-400 mt-0.5">
                    {{ __("Client:") }} <strong class="text-slate-700 dark:text-slate-300 font-bold">{{ $quote->customer_name }}</strong> &bull;
                    {{ __("Created") }} {{ $quote->created_at ? $quote->created_at->format('M d, Y') : now()->format('M d, Y') }}
                    @if ($quote->user)
                        &bull; {{ __("Rep:") }} <span class="text-slate-600 dark:text-slate-400 font-semibold">{{ $quote->user->name }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Edit Button -->
            @if ($quote->status !== 'converted')
                <a wire:navigate.hover href="{{ route('tenant.quotes.edit', $quote) }}"
                   class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    <span>{{ __("Edit") }}</span>
                </a>
            @endif

            @if ($this->desktopPrintReady)
                <button type="button"
                        wire:click="printNow"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" /></svg>
                    <span>{{ __("Print") }}</span>
                </button>
            @endif

            <!-- Preview PDF in Browser -->
            <button type="button"
               x-on:click="$dispatch('open-print-preview', { url: @js(route('tenant.quotes.pdf', ['quote' => $quote->id, 'download' => 0, 'embed' => 1])), title: @js(__('Quotation Preview')) })"
               class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <span>{{ __("Preview") }}</span>
            </button>

            <!-- Download Official PDF -->
            <a href="{{ route('tenant.quotes.pdf', ['quote' => $quote->id, 'download' => 1]) }}"
               download="Quotation_{{ $quote->sale_number }}.pdf"
               data-turbo="false"
               class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                <span>{{ __("Download PDF") }}</span>
            </a>

            <!-- 80mm Thermal Receipt Print -->
            <button type="button"
               x-on:click="$dispatch('open-print-preview', { url: @js(route('tenant.quotes.pdf', ['quote' => $quote->id, 'format' => '80mm', 'embed' => 1])), title: @js(__('80mm Thermal Preview')) })"
               class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                <span>🖨️ {{ __("80mm Thermal") }}</span>
            </button>

            <!-- WhatsApp Modal Button -->
            <button type="button"
                    wire:click="openSendModal('whatsapp')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center gap-1.5 transition active:scale-95 cursor-pointer">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                <span>{{ __("WhatsApp") }}</span>
            </button>

            <!-- Send Email Modal Button -->
            <button type="button"
                    wire:click="openSendModal('email')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-1.5 transition active:scale-95 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                <span>{{ __("Send Email") }}</span>
            </button>

            <!-- High-Contrast Convert to Sale Action Button -->
            @if ($quote->status !== 'converted' && auth('web')->user()?->hasPermission('quotes', 'convert_to_sale'))
                <button type="button"
                        wire:click="openInPos"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-500 text-white shadow-md shadow-blue-600/30 flex items-center gap-1.5 transition active:scale-95 cursor-pointer">
                    <span>🛒 {{ __("Open in POS") }}</span>
                </button>

                <button type="button"
                        wire:click="convertToSale"
                        wire:confirm="{{ __("Convert this quotation to an active Sale invoice and deduct inventory stock?") }}"
                        class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/30 flex items-center gap-1.5 transition active:scale-95 cursor-pointer border border-emerald-500/40">
                    <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="text-white font-black tracking-wide">{{ __("Direct Convert to Sale") }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Flexible Send Quotation Modal -->
    @if ($showSendModal)
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border-2 shadow-2xl space-y-5 animate-in fade-in duration-200"
             style="border-color: {{ $borderTint }};">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl text-lg font-black" style="background-color: {{ $lightBg }}; color: {{ $primaryColor }};">📋</span>
                    <div>
                        <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ __("Send Quotation Proposal #") }}{{ $quote->sale_number }}</h3>
                        <p class="text-xs text-slate-400">{{ __("Choose dispatch channel, configure message body, and toggle PDF attachment") }}</p>
                    </div>
                </div>
                <button type="button" wire:click="$set('showSendModal', false)" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <!-- Channel Tabs -->
            <div class="flex gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl">
                <button type="button"
                        wire:click="$set('activeChannel', 'email')"
                        @class([
                            'flex-1 py-2 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 cursor-pointer',
                            'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-300 shadow-sm' => $activeChannel === 'email',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900' => $activeChannel !== 'email',
                        ])>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <span>{{ __("Email Dispatch (SMTP)") }}</span>
                </button>

                <button type="button"
                        wire:click="$set('activeChannel', 'whatsapp')"
                        @class([
                            'flex-1 py-2 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 cursor-pointer',
                            'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-300 shadow-sm' => $activeChannel === 'whatsapp',
                            'text-slate-600 dark:text-slate-400 hover:text-slate-900' => $activeChannel !== 'whatsapp',
                        ])>
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                    <span>{{ __("WhatsApp Web / Mobile") }}</span>
                </button>
            </div>

            <!-- Recipient Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if ($activeChannel === 'email')
                    <div class="space-y-1.5 col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Recipient Email Address") }} <span class="text-rose-500">*</span></label>
                        <input type="email" wire:model="recipientEmail" placeholder="client@example.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                        @error('recipientEmail') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="space-y-1.5 col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Recipient WhatsApp Phone Number (with Country Code)") }}</label>
                        <input type="text" wire:model.live="recipientPhone" placeholder="+1234567890" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-emerald-500">
                    </div>
                @endif
            </div>

            <!-- Custom Toggle Switch without Input Clipping Artefacts -->
            @if ($activeChannel === 'email')
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between gap-4">
                    <div class="space-y-0.5">
                        <div class="text-xs font-extrabold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>{{ __("Attach Official PDF Document") }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono"
                                  style="background-color: {{ $lightBg }}; color: {{ $primaryColor }};">
                                Quotation-{{ $quote->sale_number }}.pdf
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $attachPdf ? __('Generates and attaches clean stylized proposal PDF.') : __('Disabled — sends clean structured text email only.') }}
                        </p>
                    </div>

                    <button type="button"
                            role="switch"
                            wire:click="$toggle('attachPdf')"
                            @class([
                                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden',
                                'bg-emerald-600' => $attachPdf,
                                'bg-slate-200 dark:bg-slate-700' => !$attachPdf,
                            ])>
                        <span aria-hidden="true"
                              @class([
                                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                                  'translate-x-5' => $attachPdf,
                                  'translate-x-0' => !$attachPdf,
                              ])></span>
                    </button>
                </div>
            @endif

            <!-- Customizable Text Message Body -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Customizable Text Message Body") }}</label>
                    <button type="button" wire:click="resetMessage" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                        ↺ {{ __("Reset Template") }}
                    </button>
                </div>

                <textarea wire:model.live.debounce.300ms="customMessage" rows="5" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm leading-relaxed font-sans focus:ring-emerald-500"></textarea>

                <!-- Smart Placeholders Helper Chips -->
                <div class="space-y-1">
                    <div class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">{{ __("Click to Insert Smart Placeholders:") }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['{customer_name}', '{document_number}', '{total_amount}', '{expiry_date}', '{download_link}', '{company_name}'] as $tag)
                            <button type="button"
                                    wire:click="appendPlaceholder('{{ $tag }}')"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                                {{ $tag }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button"
                        wire:click="$set('showSendModal', false)"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                    Cancel
                </button>

                @if ($activeChannel === 'email')
                    <button type="button"
                            wire:click="sendEmail"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                        <svg wire:loading class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>{{ __("Send Quotation Email") }}</span>
                    </button>
                @elseif ($this->whatsAppApiConfigured)
                    <button type="button"
                            wire:click="sendWhatsApp"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                        <svg wire:loading class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>{{ __("Send via WhatsApp") }}</span>
                    </button>
                @else
                    <a href="{{ $this->whatsAppUrl }}"
                       target="_blank"
                       wire:click="trackWhatsAppSent"
                       class="px-6 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                        <span>{{ __("Open WhatsApp & Send") }}</span>
                    </a>
                @endif
            </div>
        </div>
    @endif

    <!-- Live Interactive Document Preview with Real-Time Theme Color Sync -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-8 sm:p-12 shadow-[0_4px_30px_rgb(0,0,0,0.04)] border border-slate-100 dark:border-slate-800 space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight leading-tight"
                    style="color: {{ $primaryColor }};">
                    {{ $company?->name }}
                </h1>
                <div class="text-xs font-extrabold tracking-widest uppercase mt-1"
                     style="color: {{ $primaryColor }}; opacity: 0.85;">
                    {{ $company?->trade_name ?? 'QUOTATION PROPOSAL' }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                    @if ($company?->address)
                        {{ $company->address }}<br>
                    @endif
                    @if ($company?->city || $company?->state || $company?->postal_code)
                        {{ $company->city }}{{ $company->state ? ', ' . $company->state : '' }} {{ $company->postal_code }}<br>
                    @endif
                    @if ($company?->tax_id)
                        <span class="font-semibold">{{ $taxIdentifierLabel }}: {{ $company->tax_id }}</span>
                    @endif
                </div>
            </div>

            <div class="text-right text-xs space-y-1.5 bg-slate-50 dark:bg-slate-800/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800/80 min-w-[220px]">
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-400 uppercase">{{ __("Quote No:") }}</span>
                    <strong class="font-mono font-black" style="color: {{ $primaryColor }};">{{ $quote->sale_number }}</strong>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-400 uppercase">{{ __("Date:") }}</span>
                    <strong class="text-slate-800 dark:text-white">{{ $quote->created_at ? $quote->created_at->format('d/m/Y') : now()->format('d/m/Y') }}</strong>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-400 uppercase">{{ __("Valid Until:") }}</span>
                    <strong class="text-slate-800 dark:text-white">{{ $quote->due_date ? $quote->due_date->format('d/m/Y') : ($quote->created_at ? $quote->created_at->addDays(15)->format('d/m/Y') : now()->addDays(15)->format('d/m/Y')) }}</strong>
                </div>
                @if ($quote->agreed_payment_method || $quote->payment_method)
                    <div class="flex justify-between gap-4">
                        <span class="font-bold text-slate-400 uppercase">{{ __("Payment:") }}</span>
                        <strong class="text-blue-600 dark:text-blue-400 uppercase font-black">{{ $quote->agreed_payment_method ?: $quote->payment_method }}</strong>
                    </div>
                @endif
                @if ($quote->payment_terms)
                    <div class="flex justify-between gap-4">
                        <span class="font-bold text-slate-400 uppercase">{{ __("Terms:") }}</span>
                        <strong class="text-slate-800 dark:text-white">{{ $quote->payment_terms }}</strong>
                    </div>
                @endif
                @if ($quote->user)
                    <div class="flex justify-between gap-4 pt-1 border-t border-slate-200 dark:border-slate-700">
                        <span class="font-bold text-slate-400 uppercase">{{ __("Prepared By:") }}</span>
                        <strong class="text-slate-800 dark:text-white">{{ $quote->user->name }}</strong>
                    </div>
                @endif
            </div>
        </div>

        <!-- Grouped Client / Recipient Contact Information Card -->
        <div class="p-5 rounded-2xl flex flex-col sm:flex-row justify-between items-start gap-4"
             style="background-color: {{ $lightBg }}; border: 1px solid {{ $borderTint }};">
            <div class="space-y-1">
                <div class="text-[10px] font-extrabold uppercase tracking-wider" style="color: {{ $primaryColor }};">
                    {{ __("PROPOSAL PREPARED FOR:") }}
                </div>
                <div class="text-base font-black text-slate-900 dark:text-white">
                    {{ $quote->customer_name }}
                </div>
                @if ($quote->customer?->phone)
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-bold">{{ __("Phone:") }}</span> {{ $quote->customer->phone }}
                    </div>
                @endif
                @if ($quote->customer?->email)
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-bold">{{ __("Email:") }}</span> {{ $quote->customer->email }}
                    </div>
                @endif
                @if ($quote->customer?->address || $quote->customer?->city)
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-bold">{{ __("Address:") }}</span> {{ $quote->customer->address ? $quote->customer->address . ', ' : '' }}{{ $quote->customer->city }}{{ $quote->customer->state ? ' ' . $quote->customer->state : '' }}
                    </div>
                @endif
                @if ($customerTaxNumber)
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-bold">{{ $taxIdentifierLabel }}:</span> {{ $customerTaxNumber }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <div>
            <div class="rounded-xl px-6 py-3.5 flex justify-between font-extrabold text-xs uppercase tracking-wider shadow-xs"
                 style="background-color: {{ $primaryColor }}; color: #ffffff;">
                <div class="flex-1">{{ __("Item & Scope Description") }}</div>
                <div class="w-16 text-center">{{ __("Qty") }}</div>
                <div class="w-24 text-right">{{ __("Price") }}</div>
                <div class="w-24 text-right">{{ __("Total") }}</div>
            </div>

            <div class="rounded-2xl p-6 mt-2 space-y-3"
                 style="background-color: {{ $lightBg }}; border: 1px solid {{ $borderTint }};">
                @php $subtotal = 0; @endphp
                @foreach ($quote->items ?? [] as $item)
                    @php
                        $qty = (float)($item['quantity'] ?? 1);
                        $price = (float)($item['price'] ?? 0);
                        $rTotal = $qty * $price;
                        $subtotal += $rTotal;
                    @endphp
                    <div class="flex justify-between items-start text-xs sm:text-sm text-slate-800 dark:text-slate-200 py-1">
                        <div class="flex-1 pr-4">
                            <div class="font-bold">{{ $item['name'] }}</div>
                            @if (!empty($item['description']))
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">{{ $item['description'] }}</div>
                            @endif
                        </div>
                        <div class="w-16 text-center font-bold">{{ $qty }}</div>
                        <div class="w-24 text-right font-semibold">${{ number_format($price, 2) }}</div>
                        <div class="w-24 text-right font-black">${{ number_format($rTotal, 2) }}</div>
                    </div>
                @endforeach

                <div class="h-px my-4" style="background-color: {{ $borderTint }};"></div>

                <div class="flex flex-col items-end gap-1.5 text-xs sm:text-sm">
                    <div class="flex justify-between w-64 text-slate-600 dark:text-slate-300 font-bold">
                        <span>{{ __("SUB TOTAL") }}</span>
                        <span>${{ number_format($subtotal, 2) }}</span>
                    </div>
                    @if ($quote->discount > 0)
                        <div class="flex justify-between w-64 text-rose-600 font-bold">
                            <span>{{ __("DISCOUNT") }}</span>
                            <span>-${{ number_format($quote->discount, 2) }}</span>
                        </div>
                    @endif
                    @php
                        $flattenedTaxes = $quote->flattened_tax_components;
                        $taxCalculated = (float)($quote->tax_amount ?? max(0, $quote->total - $subtotal + $quote->discount));
                        if ($taxCalculated <= 0 && !empty($flattenedTaxes)) {
                            $taxCalculated = array_sum(array_column($flattenedTaxes, 'amount'));
                        }
                    @endphp
                    @if ($taxCalculated > 0 || !empty($flattenedTaxes))
                        @if (!empty($flattenedTaxes))
                            @foreach ($flattenedTaxes as $tComp)
                                <div class="flex justify-between w-64 text-slate-600 dark:text-slate-300 font-bold text-xs">
                                    <span>{{ $tComp['name'] }} ({{ $tComp['rate'] }}%)</span>
                                    <span>+${{ number_format($tComp['amount'], 2) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="flex justify-between w-64 text-slate-600 dark:text-slate-300 font-bold text-xs">
                                <span>{{ $quote->tax_name ?: __('TAX ESTIMATE') }} ({{ (float)($quote->tax_rate ?? 0) }}%)</span>
                                <span>+${{ number_format($taxCalculated, 2) }}</span>
                            </div>
                        @endif
                    @endif
                    <div class="flex justify-between w-64 text-base font-black pt-2 border-t"
                         style="border-color: {{ $borderTint }};">
                        <span>{{ __("GRAND TOTAL") }}</span>
                        <span class="text-xl" style="color: {{ $primaryColor }};">${{ number_format($quote->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Notes & Remarks (Rich-Text HTML Rendered Safely) -->
        @if (!empty($quote->notes))
            <div class="rounded-2xl p-6 text-xs space-y-1"
                 style="background-color: {{ $lightBg }}; border: 1px solid {{ $borderTint }};">
                <div class="font-black text-sm mb-1" style="color: {{ $primaryColor }};">{{ __("Quote Terms & Notes") }}</div>
                <div class="text-slate-600 dark:text-slate-300 leading-relaxed prose prose-sm dark:prose-invert max-w-none">{!! clean_html($quote->notes) !!}</div>
            </div>
        @endif

        <!-- Bottom Two Cards: Payable To & Terms -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="rounded-2xl p-6 text-xs space-y-3"
                 style="background-color: {{ $lightBg }}; border: 1px solid {{ $borderTint }};">
                <div>
                    <div class="font-black text-sm mb-1" style="color: {{ $primaryColor }};">{{ __("Payable To") }}</div>
                    <div class="text-slate-600 dark:text-slate-300">
                        <strong>{{ $company?->trade_name ?? $company?->name }}</strong><br>
                        {{ $company?->address ?? '123 Anywhere St., Any City' }}
                    </div>
                </div>
                <div>
                    <div class="font-black text-sm mb-1" style="color: {{ $primaryColor }};">{{ __("Bank & Payment Instructions") }}</div>
                    <div class="text-slate-600 dark:text-slate-300 leading-relaxed">
                        {!! clean_html($company?->bank_details ?? ($company?->name . '<br>Phone: ' . ($company?->phone ?? '+123-456-7890'))) !!}
                    </div>
                </div>
            </div>

            <div class="rounded-2xl p-6 text-xs space-y-2"
                 style="background-color: {{ $lightBg }}; border: 1px solid {{ $borderTint }};">
                <div class="font-black text-sm mb-1" style="color: {{ $primaryColor }};">{{ __("Terms and Conditions:") }}</div>
                <div class="text-slate-600 dark:text-slate-300 leading-relaxed prose prose-sm dark:prose-invert max-w-none">
                    @if (!empty($company?->quote_terms))
                        {!! clean_html($company->quote_terms) !!}
                    @else
                        <ul class="list-disc pl-4 space-y-1.5">
                            <li>{{ __("All rates quoted are valid for 15 days from issue date.") }}</li>
                            <li>{{ __("40% advance milestone required upon quotation acceptance.") }}</li>
                            <li>{{ __("The remaining balance is payable upon delivery/project completion.") }}</li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer Contact Bar -->
        <div class="rounded-xl px-6 py-3.5 flex flex-col sm:flex-row justify-between items-center text-xs font-bold gap-2"
             style="background-color: {{ $lightBg }}; color: {{ $primaryColor }}; border: 1px solid {{ $borderTint }};">
            @if ($company?->phone)
                <div>📞 {{ $company->phone }}</div>
            @endif
            @if ($company?->email)
                <div>✉️ {{ $company->email }}</div>
            @endif
            @if ($company?->website)
                <div>🌐 {{ $company->website }}</div>
            @endif
        </div>

    </div>

</div>
