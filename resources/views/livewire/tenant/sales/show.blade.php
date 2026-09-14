<div class="max-w-3xl mx-auto space-y-6" x-data>
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-3 py-1 rounded-full">
                    🧾 {{ $sale->sale_number }}
                </span>
                <h2 class="text-xl font-black text-slate-900 dark:text-white mt-2">{{ $sale->customer_name ?? __('Walk-in Customer') }}</h2>
                <div class="text-xs text-slate-400 mt-0.5">{{ $sale->created_at ? $sale->created_at->format('l, d M Y, h:i A') : now()->format('l, d M Y, h:i A') }}</div>
            </div>

            <div class="flex items-center gap-2">
                <span @class([
                    'px-3 py-1 rounded-full text-xs font-extrabold',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $sale->status === 'completed',
                    'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' => $sale->status === 'pending',
                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $sale->status === 'cancelled',
                ])>{{ ucfirst($sale->status) }}</span>
            </div>
        </div>

        <!-- 1-Click WhatsApp, Email & Print Actions Bar -->
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <span>{{ __("Dispatch & Share Invoice:") }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Preview Document Button -->
                <button type="button"
                   x-on:click="$dispatch('open-sdui-sheet', { endpoint: @js(route('tenant.documents.preview-modal', ['type' => 'invoice', 'id' => $sale->id])) })"
                   class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <span>{{ __("Preview") }}</span>
                </button>

                @if ($this->desktopPrintReady)
                    <button type="button"
                            wire:click="printNow"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" /></svg>
                        <span>{{ __("Print") }}</span>
                    </button>
                @endif

                <!-- Download Official PDF -->
                <a href="{{ route('tenant.sales.pdf', ['sale' => $sale->id, 'download' => 1]) }}"
                   download="Invoice_{{ $sale->sale_number }}.pdf"
                   data-turbo="false"
                   class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span>{{ __("Download PDF") }}</span>
                </a>

                <!-- WhatsApp Modal Button -->
                <button type="button"
                        wire:click="openSendModal('whatsapp')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                    <span>{{ __("WhatsApp") }}</span>
                </button>

                <!-- Email Invoice Button -->
                <button type="button"
                        wire:click="openSendModal('email')"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <span>{{ __("Email Invoice") }}</span>
                </button>
            </div>
        </div>

        <!-- Flexible Send Invoice Modal -->
        @if ($showSendModal)
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border-2 border-blue-500/30 dark:border-blue-500/20 shadow-xl space-y-5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 font-black">🧾</span>
                        <div>
                            <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ __("Send Tax Invoice #") }}{{ $sale->sale_number }}</h3>
                            <p class="text-xs text-slate-400">{{ __("Choose dispatch channel, configure message body, and toggle PDF attachment") }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showSendModal', false)" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>

                <!-- Channel Tabs -->
                <div class="flex gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl">
                    <button type="button"
                            wire:click="$set('activeChannel', 'email')"
                            @class([
                                'flex-1 py-2 rounded-xl text-xs font-black transition flex items-center justify-center gap-2',
                                'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-300 shadow-sm' => $activeChannel === 'email',
                                'text-slate-600 dark:text-slate-400 hover:text-slate-900' => $activeChannel !== 'email',
                            ])>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span>{{ __("Email Dispatch (SMTP)") }}</span>
                    </button>

                    <button type="button"
                            wire:click="$set('activeChannel', 'whatsapp')"
                            @class([
                                'flex-1 py-2 rounded-xl text-xs font-black transition flex items-center justify-center gap-2',
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
                            <input type="email" wire:model="recipientEmail" placeholder="customer@example.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                            @error('recipientEmail') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="space-y-1.5 col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Recipient WhatsApp Phone Number (with Country Code)") }}</label>
                            <input type="text" wire:model.live="recipientPhone" placeholder="+1234567890" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-emerald-500">
                        </div>
                    @endif
                </div>

                <!-- Attachment Option (Toggle PDF) -->
                @if ($activeChannel === 'email')
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between gap-4">
                        <div class="space-y-0.5">
                            <div class="text-xs font-extrabold text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>{{ __("Attach Official PDF Document") }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">Invoice-{{ $sale->sale_number }}.pdf</span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $attachPdf ? __('Generates and attaches clean stylized invoice PDF.') : __('Disabled — sends clean structured text email only.') }}
                            </p>
                        </div>

                        <button type="button"
                                role="switch"
                                wire:click="$toggle('attachPdf')"
                                @class([
                                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden',
                                    'bg-blue-600' => $attachPdf,
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
                        <button type="button" wire:click="resetMessage" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline">
                            ↺ {{ __("Reset Template") }}
                        </button>
                    </div>

                    <textarea wire:model.live.debounce.300ms="customMessage" rows="5" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm leading-relaxed font-sans focus:ring-blue-500"></textarea>

                    <!-- Smart Placeholders Helper Chips -->
                    <div class="space-y-1">
                        <div class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">{{ __("Click to Insert Smart Placeholders:") }}</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (['{customer_name}', '{document_number}', '{total_amount}', '{due_date}', '{download_link}', '{company_name}'] as $tag)
                                <button type="button"
                                        wire:click="appendPlaceholder('{{ $tag }}')"
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
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
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Cancel
                    </button>

                    @if ($activeChannel === 'email')
                        <button type="button"
                                wire:click="sendEmail"
                                wire:loading.attr="disabled"
                                class="px-6 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                            <svg wire:loading class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ __("Send Invoice Email") }}</span>
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
                           @click="$wire.set('showSendModal', false)"
                           class="px-6 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                            <span>{{ __("Open WhatsApp & Send") }}</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <!-- Items Table -->
        <table class="w-full text-xs sm:text-sm">
            <thead class="text-left text-slate-400 dark:text-slate-500 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="py-2.5">{{ __("Item") }}</th>
                    <th class="py-2.5 text-center">{{ __("Qty") }}</th>
                    <th class="py-2.5 text-right">{{ __("Price") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach ($sale->items ?? [] as $item)
                    <tr>
                        <td class="py-3.5 flex items-center gap-3">
                            <x-pos-product-icon :name="$item['name'] ?? ''" size="xs" />
                            <div>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $item['name'] ?? '—' }}</span>
                                @if (!empty($item['description']))
                                    <div class="text-[11px] text-slate-400">{{ $item['description'] }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 text-center text-slate-600 dark:text-slate-400 font-bold">{{ $item['quantity'] }}</td>
                        <td class="py-3.5 text-right font-bold text-slate-900 dark:text-white">${{ number_format($item['price'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Document Notes & Remarks -->
        @if (!empty($sale->notes))
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs space-y-1">
                <div class="font-bold text-slate-700 dark:text-slate-300">{{ __("Order Notes & Remarks:") }}</div>
                <div class="text-slate-500 dark:text-slate-400 leading-relaxed">{{ $sale->notes }}</div>
            </div>
        @endif

        <!-- Totals breakdown -->
        <div class="flex justify-end pt-2">
            <div class="w-56 space-y-2 text-xs">
                @if ($sale->discount > 0)
                    <div class="flex justify-between text-rose-500 font-medium">
                        <span>{{ __("Discount") }}</span>
                        <span>-${{ number_format($sale->discount, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between items-baseline font-black text-slate-900 dark:text-white pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-sm">{{ __("Total") }}</span>
                    <span class="text-2xl">${{ number_format($sale->total, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex justify-between items-center pt-4 border-t border-slate-100 dark:border-slate-800 text-xs font-bold">
            <a wire:navigate.hover href="{{ route('tenant.sales.index') }}" class="text-slate-500 hover:text-blue-600 flex items-center gap-1 transition">
                &larr; {{ __("Back to Sales") }}
            </a>

            @if ($sale->status !== 'cancelled')
                <button wire:click="cancel"
                        wire:confirm="{{ __("Cancel this sale and restore stock?") }}"
                        type="button"
                        class="px-4 py-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                    {{ __("Cancel Sale & Restock") }}
                </button>
            @endif
        </div>
    </div>
</div>
