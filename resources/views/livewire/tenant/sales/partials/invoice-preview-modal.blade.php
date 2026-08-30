<!-- Real-Time Thermal Receipt & Invoice Preview Modal (0ms Alpine Client-Side Mount) -->
<style>
    /* Keep the pre-confirmation review visually consistent with the final
       invoice print preview, regardless of the application's dark mode. */
    .draft-invoice-preview { background: #fff !important; color: #0f172a !important; }
    .draft-invoice-preview .preview-canvas { background: #eef2f7 !important; }
    .draft-invoice-preview .preview-paper { background: #fff !important; color: #0f172a !important; border-color: #cbd5e1 !important; }
    .draft-invoice-preview .text-white { color: #0f172a !important; }
    .draft-invoice-preview .text-slate-100,
    .draft-invoice-preview .text-slate-200 { color: #1e293b !important; }
    .draft-invoice-preview .text-slate-300 { color: #334155 !important; }
    .draft-invoice-preview .text-slate-400 { color: #64748b !important; }
    .draft-invoice-preview .text-slate-500 { color: #94a3b8 !important; }
    .draft-invoice-preview .text-blue-400 { color: #2563eb !important; }
    .draft-invoice-preview .text-emerald-400 { color: #059669 !important; }
    .draft-invoice-preview .text-rose-400 { color: #e11d48 !important; }
    .draft-invoice-preview .text-amber-400 { color: #b45309 !important; }
    .draft-invoice-preview .border-slate-700,
    .draft-invoice-preview .border-slate-800 { border-color: #cbd5e1 !important; }
    .draft-invoice-preview .border-slate-700\/80,
    .draft-invoice-preview .border-slate-700\/60,
    .draft-invoice-preview .border-white\/10 { border-color: #cbd5e1 !important; }
    .draft-invoice-preview .divide-slate-800 > :not(:last-child) { border-color: #e2e8f0 !important; }
    .draft-invoice-preview .bg-slate-800\/90,
    .draft-invoice-preview .bg-slate-800 { background: #f1f5f9 !important; }
    .draft-invoice-preview .bg-slate-700 { background: #e2e8f0 !important; }
    .draft-invoice-preview .bg-amber-500\/10,
    .draft-invoice-preview .bg-amber-500\/15 { background: #fffbeb !important; }
</style>
<template x-teleport="body">
<div x-show="showInvoicePreview"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-on:keydown.escape.window="closePreview()"
     class="fixed inset-0 z-[70] overflow-y-auto"
     style="display: none;"
     x-cloak
     wire:cloak>
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-md" aria-hidden="true" @click="closePreview()"></div>
    
    <div class="min-h-full flex items-center justify-center p-3 sm:p-6 text-center">
        <!-- Modal Card Canvas with Spring Hardware Easing -->
        <div x-show="showInvoicePreview"
             x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2"
             @click.outside="closePreview()"
             class="draft-invoice-preview relative z-10 w-full max-w-3xl my-4 bg-white text-slate-900 border border-slate-200 rounded-3xl shadow-2xl overflow-hidden text-left flex flex-col max-h-[calc(100dvh-2rem)]">
        
        <!-- Preview Modal Header Bar -->
        <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-slate-200 shrink-0 bg-white">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-500/15 border border-blue-500/30 text-blue-400 flex items-center justify-center text-base font-black shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12C3.8 7.9 7.5 5 12 5s8.2 2.9 9.5 7c-1.3 4.1-5 7-9.5 7s-8.2-2.9-9.5-7z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black text-white flex items-center gap-2">
                        <span>{{ __("Live Receipt / Invoice Preview") }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-400 border border-amber-500/30">
                            {{ __("DRAFT") }}
                        </span>
                    </h3>
                    <p class="text-[11px] text-slate-400">{{ __("Review order details with customer before finalizing.") }}</p>
                </div>
            </div>

            <button type="button"
                    @click="closePreview()"
                    class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 hover:text-slate-900 hover:bg-slate-200 flex items-center justify-center font-bold text-base transition active:scale-95 cursor-pointer"
                    title="{{ __('Close (Esc)') }}">
                &times;
            </button>
        </div>

        <!-- Scrollable Receipt Thermal Slip Body -->
        <div class="preview-canvas flex-1 min-h-0 overflow-y-auto bg-slate-100 p-3 sm:p-5">
            <div class="preview-paper max-w-xl mx-auto bg-white border border-slate-300 rounded-xl p-4 sm:p-6 font-mono text-xs space-y-3.5 shadow-sm text-slate-800" id="printable-draft-receipt">
                
                <!-- Watermark / Draft Banner -->
                <div class="text-center py-1.5 px-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] font-black uppercase tracking-widest">
                    *** {{ __("DRAFT INVOICE PREVIEW - NOT A FINAL FISCAL RECEIPT") }} ***
                </div>

                <!-- Store Header Information -->
                <div class="text-center space-y-1 border-b-2 border-dashed border-slate-700 pb-3">
                    <div class="text-base font-black text-white uppercase tracking-tight">
                        {{ $company->name ?: config('app.name') }}
                    </div>
                    @if ($company->address || $company->city)
                        <div class="text-[11px] text-slate-400">
                            {{ $company->address }} {{ $company->city ? ', ' . $company->city : '' }}
                        </div>
                    @endif
                    @if ($company->phone || $company->email)
                        <div class="text-[10px] text-slate-400">
                            {{ $company->phone ? __('Tel: ') . $company->phone : '' }} {{ ($company->phone && $company->email) ? '|' : '' }} {{ $company->email ? __('Email: ') . $company->email : '' }}
                        </div>
                    @endif
                    @if ($company->tax_id)
                        <div class="text-[10px] font-bold text-slate-300">
                            {{ \App\Services\TaxEngineService::getTaxIdentifierLabel($company->country) }}: {{ $company->tax_id }}
                        </div>
                    @endif
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 gap-2 text-[11px] border-b border-dashed border-slate-700 pb-2.5 text-slate-300">
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">{{ __("Order / Draft No.") }}</span>
                        <span class="font-black text-white font-sans">#{{ $orderNumber }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">{{ __("Date & Time") }}</span>
                        <span>{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">{{ __("Customer") }}</span>
                        <span class="font-bold text-white">
                            {{ $this->selectedCustomer?->name ?: __('Walk-in Regular Customer') }}
                        </span>
                        @if ($this->selectedCustomer?->phone)
                            <span class="text-[10px] text-slate-400 block">{{ $this->selectedCustomer->phone }}</span>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">{{ __("Salesperson / Staff") }}</span>
                        <span class="font-bold text-slate-200">
                            {{ $this->assignedSalesperson?->name ?: auth('web')->user()?->name }}
                        </span>
                    </div>
                </div>

                <!-- Itemized Cart Products Table -->
                <div class="space-y-1.5">
                    <div class="grid grid-cols-12 gap-1 text-[10px] font-black uppercase text-slate-400 border-b border-slate-700 pb-1">
                        <span class="col-span-6">{{ __("Item") }}</span>
                        <span class="col-span-2 text-center">{{ __("Qty") }}</span>
                        <span class="col-span-2 text-right">{{ __("Price") }}</span>
                        <span class="col-span-2 text-right">{{ __("Total") }}</span>
                    </div>

                    <div class="space-y-1.5 text-xs divide-y divide-dashed divide-slate-800">
                        @forelse ($items as $idx => $it)
                            @if (!empty($it['product_id']) || !empty($it['name']))
                                <div class="grid grid-cols-12 gap-1 pt-1 items-start text-slate-200">
                                    <div class="col-span-6">
                                        <div class="font-bold text-white truncate">
                                            {{ $it['name'] ?: 'Item' }}
                                        </div>
                                        @if (!empty($it['is_overridden']))
                                            <span class="text-[9px] font-sans text-amber-400">
                                                ({{ __('Price Overridden') }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="col-span-2 text-center font-bold">
                                        {{ (int)$it['quantity'] }}
                                    </div>
                                    <div class="col-span-2 text-right text-slate-400">
                                        {{ $company->formatMoney($it['price']) }}
                                    </div>
                                    <div class="col-span-2 text-right font-black text-white">
                                        {{ $company->formatMoney((float)$it['quantity'] * (float)$it['price']) }}
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="py-4 text-center text-slate-400 text-xs font-sans">
                                {{ __("No items in cart.") }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Financial Calculations & Totals -->
                <div class="border-t-2 border-dashed border-slate-700 pt-2 space-y-1.5 text-xs text-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-400">{{ __("Subtotal") }}</span>
                        <span>{{ $company->formatMoney($this->subtotal) }}</span>
                    </div>
                    @if ($this->taxAmount > 0)
                        <div class="flex justify-between">
                            <span class="text-slate-400">{{ __("Total Tax") }}</span>
                            <span class="font-bold text-white">{{ $company->formatMoney($this->taxAmount) }}</span>
                        </div>

                        @if (!empty($this->taxSummaryTable))
                            <!-- Structured Fiscal Tax Summary Table -->
                            <div class="pt-1.5 pb-1 border-y border-dashed border-slate-700/80 space-y-1 my-1">
                                <div class="text-[9px] font-black uppercase text-slate-400">{{ __('Tax Jurisdiction Breakdown') }}</div>
                                <table class="w-full text-[10px] text-left text-slate-300">
                                    <thead class="text-[9px] uppercase font-bold text-slate-400 border-b border-dashed border-slate-700">
                                        <tr>
                                            <th class="py-0.5">{{ __('Tax') }}</th>
                                            <th class="py-0.5 text-right">{{ __('Rate') }}</th>
                                            <th class="py-0.5 text-right">{{ __('Taxable') }}</th>
                                            <th class="py-0.5 text-right">{{ __('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-dashed divide-slate-800">
                                        @foreach ($this->taxSummaryTable as $taxRow)
                                            <tr>
                                                <td class="py-0.5 font-bold">{{ $taxRow['tax_name'] }}</td>
                                                <td class="py-0.5 text-right font-mono">{{ $taxRow['rate'] }}%</td>
                                                <td class="py-0.5 text-right font-mono">{{ $company->formatMoney($taxRow['taxable_amount']) }}</td>
                                                <td class="py-0.5 text-right font-mono font-bold text-white">{{ $company->formatMoney($taxRow['tax_amount']) }}</td>
                                            </tr>
                                            @if (!empty($taxRow['components']) && count($taxRow['components']) > 1)
                                                @foreach ($taxRow['components'] as $comp)
                                                    <tr class="text-slate-400 text-[9px]">
                                                        <td class="py-0.5 pl-2 font-mono">└ {{ $comp['name'] }}</td>
                                                        <td class="py-0.5 text-right font-mono">{{ $comp['rate'] }}%</td>
                                                        <td class="py-0.5 text-right font-mono">—</td>
                                                        <td class="py-0.5 text-right font-mono">{{ $company->formatMoney($comp['amount']) }}</td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                    @if ($this->discount > 0)
                        <div class="flex justify-between text-rose-400 font-bold">
                            <span>{{ __("Discount Applied") }}</span>
                            <span>-{{ $company->formatMoney($this->discount) }}</span>
                        </div>
                    @endif
                    
                    <div class="flex justify-between text-sm font-black text-white pt-1.5 border-t border-dashed border-slate-700">
                        <span>{{ __("TOTAL PAYABLE") }}</span>
                        <span class="text-base text-blue-400">{{ $company->formatMoney($this->total) }}</span>
                    </div>

                    <!-- Payment Method Breakdown -->
                    <div class="pt-2 border-t border-dashed border-slate-700 space-y-1 text-[11px]">
                        @if (! $isSplitPayment)
                            <div class="flex justify-between">
                                <span class="text-slate-400">{{ __("Payment Method:") }}</span>
                                <span class="font-bold uppercase text-white">{{ $paymentMethod }}</span>
                            </div>

                            @if ($paymentMethod === 'cash')
                                <div class="flex justify-between text-slate-300">
                                    <span>{{ __("Cash Tendered:") }}</span>
                                    <span class="font-bold">{{ $company->formatMoney($this->cashTendered ?: $this->total) }}</span>
                                </div>
                                <div class="flex justify-between text-emerald-400 font-bold">
                                    <span>{{ __("Change Due:") }}</span>
                                    <span>{{ $company->formatMoney($this->changeDue) }}</span>
                                </div>
                            @elseif ($paymentMethod === 'credit')
                                <div class="flex justify-between text-amber-400 font-bold">
                                    <span>{{ __("Deferred Due Date:") }}</span>
                                    <span>{{ $this->dueDate ? \Carbon\Carbon::parse($this->dueDate)->format('d/m/Y') : __('30 Days') }}</span>
                                </div>
                            @endif
                        @else
                            <div class="font-bold text-slate-400 mb-0.5">{{ __("Split Payment Breakdown:") }}</div>
                            @foreach ($splitPayments as $sp)
                                <div class="flex justify-between text-slate-300 pl-2">
                                    <span>&bull; {{ ucfirst($sp['payment_method']) }} {{ !empty($sp['reference_number']) ? '(#'.$sp['reference_number'].')' : '' }}</span>
                                    <span class="font-bold">{{ $company->formatMoney((float)$sp['amount']) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between pt-0.5 font-bold">
                                <span>{{ __("Total Allocated:") }}</span>
                                <span>{{ $company->formatMoney($this->splitTotalPaid) }}</span>
                            </div>
                            @if ($this->remainingBalance != 0)
                                <div class="flex justify-between font-bold {{ $this->remainingBalance > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                <span>{{ $this->remainingBalance > 0 ? __('Receivable Balance:') : __('Change Due:') }}</span>
                                <span>{{ $company->formatMoney(abs($this->remainingBalance)) }}</span>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Custom Order Notes & Remarks -->
                @if (trim($notes))
                    <div class="pt-2 border-t border-dashed border-slate-700 text-[11px]">
                        <span class="text-slate-400 block text-[9px] uppercase font-bold mb-0.5">{{ __("Order Notes / Remarks:") }}</span>
                        <p class="text-slate-200 bg-slate-900/90 p-2 rounded-lg border border-slate-800 italic">
                            "{{ $notes }}"
                        </p>
                    </div>
                @endif

                <!-- Thermal Slip Footer Standard Notes & Barcode Mockup -->
                <div class="text-center pt-2 border-t-2 border-dashed border-slate-700 space-y-2 text-[10px] text-slate-400">
                    <p>{{ __("Thank you for your business! Please retain this draft/invoice receipt for your records.") }}</p>
                    
                    <!-- Simulated Barcode -->
                    <div class="flex flex-col items-center justify-center pt-1 space-y-0.5">
                        <div class="flex items-center gap-[2px] h-8 text-slate-300">
                            <div class="w-1 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                            <div class="w-1.5 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                            <div class="w-2 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                            <div class="w-1 h-full bg-current"></div>
                            <div class="w-2 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                            <div class="w-1.5 h-full bg-current"></div>
                            <div class="w-1 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                            <div class="w-2 h-full bg-current"></div>
                            <div class="w-1 h-full bg-current"></div>
                            <div class="w-0.5 h-full bg-current"></div>
                        </div>
                        <span class="font-mono text-[9px] tracking-widest text-slate-500">
                            *{{ $orderNumber }}-{{ now()->format('Ymd') }}*
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Preview Modal Footer Actions -->
        <div class="px-4 sm:px-6 py-4 border-t border-slate-200 bg-white flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 shrink-0">
            <button type="button"
                    @click="closePreview()"
                    class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-600 font-bold text-xs transition cursor-pointer flex items-center justify-center gap-1.5 border border-slate-300 active:scale-95">
                <span>← {{ __("Back to Payment") }}</span>
            </button>

            <div class="flex items-center w-full sm:flex-1 justify-end">
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-5 py-3 rounded-xl bg-[#006aff] hover:bg-[#0055d6] text-white font-black text-xs sm:text-sm shadow-lg shadow-blue-500/25 inline-flex items-center justify-center transition active:scale-[0.98] disabled:opacity-60 disabled:pointer-events-none cursor-pointer">
                    <span wire:loading.remove class="flex items-center gap-1.5">
                        <span>✓ {{ __("Confirm Sale & Complete") }}</span>
                    </span>
                    <span wire:loading>{{ __("Processing Transaction...") }}</span>
                </button>
            </div>
        </div>

    </div>
    </div>
</div>
</template>
