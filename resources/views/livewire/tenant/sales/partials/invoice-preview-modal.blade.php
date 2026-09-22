@php
    $effectiveCompany = $company ?? auth('web')->user()?->company ?? \App\Models\Company::first();

    $draftDoc = (object)[
        'id' => $orderNumber,
        'sale_number' => $orderNumber,
        'created_at' => now(),
        'subtotal' => (float)$this->subtotal,
        'discount' => (float)$this->discount,
        'tax_amount' => (float)$this->taxAmount,
        'total' => (float)$this->total,
        'due_amount' => (float)($this->remainingBalance > 0 ? $this->remainingBalance : 0),
        'payment_status' => $paymentMethod === 'credit' ? 'unpaid' : 'paid',
        'payment_method' => $paymentMethod,
        'flattened_tax_components' => $this->flattenedTaxComponents ?? [],
        'notes' => $notes,
        'customer_name' => $this->selectedCustomer?->name ?: __('Walk-in Regular Customer'),
        'customer' => $this->selectedCustomer,
        'tenant' => $effectiveCompany,
        'company' => $effectiveCompany,
        'einvoice_qr' => null,
    ];

    $draftLines = collect($items)
        ->filter(fn ($it) => !empty($it['product_id']) || !empty($it['name']))
        ->map(fn ($it) => [
            'name' => (string)($it['name'] ?: 'Item'),
            'quantity' => (float)($it['quantity'] ?? 1),
            'unit_price' => (float)($it['price'] ?? 0),
            'line_total' => (float)($it['quantity'] ?? 1) * (float)($it['price'] ?? 0),
        ])->values()->all();

    $previewFormats = ['a4', 'thermal_80mm', 'thermal_58mm', 'slip'];
    $previewHtml = [];
    foreach ($previewFormats as $fmt) {
        $previewHtml[$fmt] = view('tenant.documents.templates.document', [
            'paperFormat' => $fmt,
            'document' => $draftDoc,
            'type' => 'invoice',
            'company' => $effectiveCompany,
            'customer' => $this->selectedCustomer,
            'lines' => $draftLines,
        ])->render();
    }

    $gstin = $effectiveCompany?->tax_id ?: ($effectiveCompany?->gstin ?: 'Unregistered');
@endphp

<!-- Real-Time SDUI Bottom Sheet Preview & Dispatch Modal (0ms Alpine Client-Side Mount) -->
<template x-teleport="body">
<div>
    <!-- Backdrop with blur -->
    <div x-show="showInvoicePreview"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100000] bg-slate-950/65 backdrop-blur-sm"
         x-on:click="closePreview()"
         aria-hidden="true"
         style="display: none;"
         x-cloak
         wire:cloak></div>

    <!-- Preview & Dispatch Bottom Sheet matching SDUI Schema -->
    <section
        x-show="showInvoicePreview"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        x-on:keydown.escape.window="closePreview()"
        class="fixed inset-x-0 bottom-0 z-[100001] mx-auto flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-[#131E29]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="preview-dispatch-sheet-title"
        style="display: none;"
        x-cloak
        wire:cloak
        x-data="{
            activeFormat: 'a4',
            printDocument() {
                const iframe = this.$refs.previewIframe;
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                }
            }
        }">

        <!-- Handle bar -->
        <div class="mx-auto mt-2 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 dark:bg-slate-700"></div>

        <!-- Header -->
        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6 dark:border-slate-700">
            <div class="min-w-0 flex-1">
                <h2 id="preview-dispatch-sheet-title" class="truncate text-base font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>{{ __("Preview & Dispatch") }} #{{ $orderNumber }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-500 dark:text-amber-400 border border-amber-500/30">
                        {{ __("DRAFT") }}
                    </span>
                </h2>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                    {{ \App\Services\TaxEngineService::getTaxIdentifierLabel($effectiveCompany?->country) }}: {{ $gstin }}
                </p>
            </div>
            <button
                type="button"
                x-on:click="closePreview()"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer"
                aria-label="{{ __('Close (Esc)') }}"
                title="{{ __('Close (Esc)') }}"
            >&times;</button>
        </header>

        <!-- Scrollable Sheet Canvas -->
        <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 dark:bg-[#0B1120] space-y-4">
            
            <!-- 1. Paper Size Selector Bar (Segmented Tabs) -->
            <div class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 dark:border-slate-700 dark:bg-[#1E293B]">
                <button
                    type="button"
                    x-on:click="activeFormat = 'a4'"
                    class="shrink-0 rounded-xl px-3 py-2 text-xs font-extrabold transition cursor-pointer"
                    :class="activeFormat === 'a4' ? 'bg-emerald-500 text-slate-950 shadow-sm font-extrabold' : 'text-slate-600 hover:bg-slate-100 dark:bg-[#1E293B] dark:text-slate-400 dark:hover:bg-slate-700'">
                    {{ __('Standard A4') }}
                </button>
                <button
                    type="button"
                    x-on:click="activeFormat = 'thermal_80mm'"
                    class="shrink-0 rounded-xl px-3 py-2 text-xs font-extrabold transition cursor-pointer"
                    :class="activeFormat === 'thermal_80mm' ? 'bg-emerald-500 text-slate-950 shadow-sm font-extrabold' : 'text-slate-600 hover:bg-slate-100 dark:bg-[#1E293B] dark:text-slate-400 dark:hover:bg-slate-700'">
                    {{ __('80mm POS') }}
                </button>
                <button
                    type="button"
                    x-on:click="activeFormat = 'thermal_58mm'"
                    class="shrink-0 rounded-xl px-3 py-2 text-xs font-extrabold transition cursor-pointer"
                    :class="activeFormat === 'thermal_58mm' ? 'bg-emerald-500 text-slate-950 shadow-sm font-extrabold' : 'text-slate-600 hover:bg-slate-100 dark:bg-[#1E293B] dark:text-slate-400 dark:hover:bg-slate-700'">
                    {{ __('58mm Receipt') }}
                </button>
                <button
                    type="button"
                    x-on:click="activeFormat = 'slip'"
                    class="shrink-0 rounded-xl px-3 py-2 text-xs font-extrabold transition cursor-pointer"
                    :class="activeFormat === 'slip' ? 'bg-emerald-500 text-slate-950 shadow-sm font-extrabold' : 'text-slate-600 hover:bg-slate-100 dark:bg-[#1E293B] dark:text-slate-400 dark:hover:bg-slate-700'">
                    {{ __('Mobile Slip') }}
                </button>
            </div>

            <!-- 2. Scaled Document Preview Card with Summary -->
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-[#0F172A]">
                <div class="relative min-h-[440px] overflow-auto rounded-xl bg-[#0F172A] p-2 sm:p-4">
                    <iframe
                        x-ref="previewIframe"
                        :srcdoc="activeFormat === 'a4' ? @js($previewHtml['a4']) : (activeFormat === 'thermal_80mm' ? @js($previewHtml['thermal_80mm']) : (activeFormat === 'thermal_58mm' ? @js($previewHtml['thermal_58mm']) : @js($previewHtml['slip'])))"
                        :title="'Document preview: ' + activeFormat"
                        class="mx-auto block h-[52dvh] min-h-[420px] w-full rounded-lg border-0 bg-white shadow-xl transition-all duration-150"
                        :style="activeFormat === 'thermal_58mm' ? 'max-width: 360px' : (activeFormat === 'thermal_80mm' ? 'max-width: 460px' : (activeFormat === 'slip' ? 'max-width: 390px' : 'max-width: 820px'))"
                    ></iframe>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800">
                        <span class="block text-slate-400 font-medium">{{ __('Client') }}</span>
                        <strong class="text-slate-800 dark:text-slate-100 truncate block">{{ $this->selectedCustomer?->name ?: __('Walk-in Regular Customer') }}</strong>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800">
                        <span class="block text-slate-400 font-medium">{{ __('Phone') }}</span>
                        <strong class="text-slate-800 dark:text-slate-100 truncate block">{{ $this->selectedCustomer?->phone ?: 'N/A' }}</strong>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800">
                        <span class="block text-slate-400 font-medium">{{ __('Total') }}</span>
                        <strong class="text-slate-800 dark:text-slate-100 block">{{ $effectiveCompany?->formatMoney($this->total) }}</strong>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800">
                        <span class="block text-slate-400 font-medium">{{ __('Items') }}</span>
                        <strong class="text-slate-800 dark:text-slate-100 block">{{ $this->cartItemCount }}</strong>
                    </div>
                </div>
            </article>

        </div>

        <!-- 3. Bottom Action Bar -->
        <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-700 dark:bg-[#131E29]">
            <button type="button"
                    x-on:click="closePreview()"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer active:scale-95">
                <span>← {{ __("Back to Payment") }}</span>
            </button>

            <div class="flex items-center gap-2">
                <button type="button"
                        x-on:click="printDocument()"
                        class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition cursor-pointer active:scale-95">
                    <span>🖨️ {{ __("Print Preview") }}</span>
                </button>

                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 rounded-xl bg-[#006aff] hover:bg-[#0055d6] px-6 py-2.5 text-xs sm:text-sm font-black text-white shadow-lg shadow-blue-500/25 transition active:scale-95 cursor-pointer disabled:opacity-60 disabled:pointer-events-none">
                    <span wire:loading.remove class="flex items-center gap-1.5">
                        <span>✓ {{ __("Confirm Sale & Complete") }} ({{ $effectiveCompany?->formatMoney($this->total) }})</span>
                    </span>
                    <span wire:loading>{{ __("Processing Transaction...") }}</span>
                </button>
            </div>
        </footer>

    </section>
</div>
</template>
