@extends('layouts.tenant', ['title' => ucfirst($type) . ' Template Customizer'])

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    themeColor: @js($template->theme_color ?: ($type === 'quotation' ? '#0284c7' : '#10b981')),
    logoPlacement: @js($template->logo_placement ?: 'left'),
    headerTitle: @js($template->header_title ?: ($type === 'quotation' ? 'Commercial Quotation' : 'Tax Invoice')),
    termsConditions: @js($template->terms_conditions ?: ''),
    footerNotes: @js($template->footer_notes ?: ''),
    showQrCode: @js((bool) $template->show_qr_code),
    showTaxBreakup: @js((bool) $template->show_tax_breakup),
    sendAsAttachment: @js((bool) $template->send_as_attachment),
    sendTextWithLink: @js((bool) $template->send_text_with_link),
    messageBody: @js($template->message_body_template ?: ''),
    insertTag(tag) {
        const el = this.$refs.messageBodyTextarea;
        if (!el) {
            this.messageBody += ' ' + tag;
            return;
        }
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const val = this.messageBody;
        this.messageBody = val.substring(0, start) + tag + val.substring(end);
        this.$nextTick(() => {
            el.focus();
            el.setSelectionRange(start + tag.length, start + tag.length);
        });
    },
    refreshPreview() {
        const iframe = this.$refs.previewFrame;
        if (!iframe) return;
        const params = new URLSearchParams({
            theme_color: this.themeColor,
            header_title: this.headerTitle,
            terms_conditions: this.termsConditions,
            footer_notes: this.footerNotes,
            logo_placement: this.logoPlacement,
            show_qr_code: this.showQrCode ? '1' : '0',
            show_tax_breakup: this.showTaxBreakup ? '1' : '0',
            _t: Date.now()
        });
        iframe.src = '{{ route('settings.templates.preview', ['type' => $type]) }}?' + params.toString();
    }
}" x-init="$watch('themeColor', () => refreshPreview()); $watch('headerTitle', () => refreshPreview()); $watch('showQrCode', () => refreshPreview()); $watch('showTaxBreakup', () => refreshPreview());">

    <!-- Top Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/60 dark:border-slate-800">
        <div class="space-y-1">
            <div class="flex items-center gap-2 text-xs sm:text-sm font-bold text-slate-500 dark:text-slate-400">
                <a href="{{ route('tenant.settings.index') }}" class="hover:text-blue-600 transition flex items-center gap-1">
                    <span>⚙️</span>
                    <span>{{ __('Settings') }}</span>
                </a>
                <span>/</span>
                <span class="text-slate-900 dark:text-white font-extrabold">{{ __('Document Templates') }}</span>
                <span>/</span>
                <span class="text-blue-600 dark:text-blue-400 capitalize">{{ $type }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <span>🎨</span>
                <span>{{ ucfirst($type) }} {{ __('Template & Dispatch Designer') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Customize invoice styles, branding accents, dynamic placeholder tags, and dispatch delivery rules.') }}
            </p>
        </div>

        <!-- Template Type Switcher Tabs -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/60 p-1 rounded-2xl border border-slate-200 dark:border-slate-700">
            <a href="{{ route('settings.templates.edit', ['type' => 'invoices']) }}"
               @class([
                   'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5',
                   'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs' => $type === 'invoice',
                   'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' => $type !== 'invoice',
               ])>
                <span>🧾</span>
                <span>{{ __('Invoices') }}</span>
            </a>
            <a href="{{ route('settings.templates.edit', ['type' => 'quotations']) }}"
               @class([
                   'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5',
                   'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs' => $type === 'quotation',
                   'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' => $type !== 'quotation',
               ])>
                <span>📋</span>
                <span>{{ __('Quotations') }}</span>
            </a>
        </div>
    </div>

    <!-- Feedback alerts -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm font-semibold flex items-center gap-2.5">
            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Main Grid: Left Form (7 cols) + Right Live Preview (5 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Form Column -->
        <div class="lg:col-span-7 space-y-6">
            <form action="{{ route('settings.templates.update', ['type' => $type]) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Card 1: Branding & Visual Appearance -->
                <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">1</span>
                            <div>
                                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">{{ __('Branding & Colors') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Primary theme accent and document title') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Theme Color Picker -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Theme Accent Color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="themeColor" name="theme_color" class="w-10 h-10 rounded-xl cursor-pointer border border-slate-300 dark:border-slate-700 bg-transparent p-0.5">
                                <input type="text" x-model="themeColor" name="theme_color" class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono text-slate-900 dark:text-white">
                            </div>
                            <!-- Swatches -->
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" x-on:click="themeColor = '#10b981'" class="w-6 h-6 rounded-full bg-[#10b981] border border-white shadow-xs" title="Emerald"></button>
                                <button type="button" x-on:click="themeColor = '#0284c7'" class="w-6 h-6 rounded-full bg-[#0284c7] border border-white shadow-xs" title="Sky"></button>
                                <button type="button" x-on:click="themeColor = '#6366f1'" class="w-6 h-6 rounded-full bg-[#6366f1] border border-white shadow-xs" title="Indigo"></button>
                                <button type="button" x-on:click="themeColor = '#f59e0b'" class="w-6 h-6 rounded-full bg-[#f59e0b] border border-white shadow-xs" title="Amber"></button>
                                <button type="button" x-on:click="themeColor = '#f43f5e'" class="w-6 h-6 rounded-full bg-[#f43f5e] border border-white shadow-xs" title="Rose"></button>
                                <button type="button" x-on:click="themeColor = '#0f172a'" class="w-6 h-6 rounded-full bg-[#0f172a] border border-white shadow-xs" title="Slate"></button>
                            </div>
                        </div>

                        <!-- Header Title -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Document Header Title') }}</label>
                            <input type="text" x-model="headerTitle" name="header_title" placeholder="{{ $type === 'quotation' ? 'Commercial Quotation' : 'Tax Invoice' }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white">
                        </div>
                    </div>

                    <!-- Logo Placement & Display Toggles -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Store Logo Placement') }}</label>
                            <select x-model="logoPlacement" name="logo_placement" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white">
                                <option value="left">{{ __('Left-aligned (Default)') }}</option>
                                <option value="center">{{ __('Centered') }}</option>
                                <option value="right">{{ __('Right-aligned') }}</option>
                                <option value="hidden">{{ __('Hidden (Text brand only)') }}</option>
                            </select>
                        </div>

                        <div class="space-y-3 pt-1">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" x-model="showQrCode" name="show_qr_code" value="1" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Show Dynamic QR Code') }}</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" x-model="showTaxBreakup" name="show_tax_breakup" value="1" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('Show Tax Breakdown Row') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Terms & Footer Notes -->
                <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">2</span>
                        <div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">{{ __('Terms & Conditions & Footer') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Disclaimers, payment instructions, and thank-you notes') }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Terms & Conditions') }}</label>
                        <textarea x-model="termsConditions" name="terms_conditions" rows="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-sans" placeholder="1. Goods once sold will not be exchanged.&#10;2. Payment due within 15 days."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Footer Note') }}</label>
                        <textarea x-model="footerNotes" name="footer_notes" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white" placeholder="Thank you for your business!"></textarea>
                    </div>
                </div>

                <!-- Card 3: Dispatch Delivery Modes (Attachment vs Verified Link) -->
                <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-5">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">3</span>
                        <div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">{{ __('Outbound Dispatch Modes & Message Template') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Configure WhatsApp, SMS, and Email dispatch behaviors') }}</p>
                        </div>
                    </div>

                    <!-- Dispatch Mode Toggles -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-2xl border transition" :class="sendAsAttachment ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-800'">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="sendAsAttachment" name="send_as_attachment" value="1" class="w-4 h-4 mt-0.5 rounded text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span>📎</span>
                                        <span>{{ __('Attach PDF Document') }}</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        {{ __('Generates and attaches binary PDF to outgoing emails and document share flows.') }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="p-4 rounded-2xl border transition" :class="sendTextWithLink ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-800'">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="sendTextWithLink" name="send_text_with_link" value="1" class="w-4 h-4 mt-0.5 rounded text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span>🔗</span>
                                        <span>{{ __('Send Text with Verified Link') }}</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                        {{ __('Sends lightweight formatted text with digital link without requiring heavy PDF downloads.') }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Message Body Template & Placeholder Pills -->
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Message Body Template') }}</label>
                            <span class="text-[11px] text-slate-500">{{ __('Click a tag to insert into message') }}</span>
                        </div>

                        <!-- Clickable Placeholder Chips -->
                        <div class="flex flex-wrap gap-1.5 p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700">
                            <button type="button" x-on:click="insertTag('{customer_name}')" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-600 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition shadow-2xs">
                                + {customer_name}
                            </button>
                            <button type="button" x-on:click="insertTag('{invoice_number}')" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-600 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition shadow-2xs">
                                + {invoice_number}
                            </button>
                            <button type="button" x-on:click="insertTag('{amount}')" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-600 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition shadow-2xs">
                                + {amount}
                            </button>
                            <button type="button" x-on:click="insertTag('{due_date}')" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-600 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition shadow-2xs">
                                + {due_date}
                            </button>
                            <button type="button" x-on:click="insertTag('{document_link}')" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-600 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition shadow-2xs">
                                + {document_link}
                            </button>
                        </div>

                        <textarea x-ref="messageBodyTextarea" x-model="messageBody" name="message_body_template" rows="4" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-mono" placeholder="Hello {customer_name}, your order #{invoice_number} total is {amount}. View online: {document_link}"></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between gap-4 pt-2">
                    <button type="button" x-on:click="refreshPreview()" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                        🔄 {{ __('Refresh Live Preview') }}
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-extrabold shadow-md shadow-blue-600/20 transition active:scale-95">
                        💾 {{ __('Save Template Settings') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Live Visual Preview Pane -->
        <div class="lg:col-span-5 space-y-4">
            <div class="sticky top-20 p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">{{ __('Live Visual Preview') }}</h4>
                    </div>
                    <button type="button" x-on:click="refreshPreview()" class="text-xs text-blue-600 dark:text-blue-400 font-bold hover:underline">
                        {{ __('Reload') }}
                    </button>
                </div>

                <!-- Iframe Container -->
                <div class="relative w-full rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-950 shadow-inner" style="height: 640px;">
                    <iframe x-ref="previewFrame" src="{{ route('settings.templates.preview', ['type' => $type]) }}" class="w-full h-full border-0 bg-white" title="Template Live Preview"></iframe>
                </div>

                <p class="text-[11px] text-center text-slate-400">
                    {{ __('Preview displays sample order items with your configured theme color, title, and terms.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
