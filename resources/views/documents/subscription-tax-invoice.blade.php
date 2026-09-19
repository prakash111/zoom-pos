<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice #{{ $invoice->invoice_number }} - {{ $branding?->platform_name ?? 'Smart SaaS' }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .invoice-box { box-shadow: none !important; border: 1px solid #e2e8f0 !important; margin: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen p-4 sm:p-8 flex flex-col items-center antialiased font-sans">

    @unless(request()->boolean('embed'))
    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print w-full max-w-4xl flex items-center justify-between gap-3 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2">
            <span class="text-xs font-black text-slate-800">TAX INVOICE:</span>
            <span class="text-xs font-mono font-extrabold text-blue-600">#{{ $invoice->invoice_number }}</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 uppercase">
                {{ $invoice->status }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-sm transition active:scale-95 flex items-center gap-1.5">
                <span>🖨️ Print / Save as PDF</span>
            </button>
            <button type="button" onclick="window.close()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                Close
            </button>
        </div>
    </div>
    @endunless

    <!-- Official Tax Invoice Document Box -->
    <div class="invoice-box w-full max-w-4xl bg-white rounded-3xl p-8 sm:p-12 shadow-xl border border-slate-200/80 space-y-8">
        
        <!-- Header Row -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-6 border-b border-slate-200 pb-8">
            <div class="space-y-1.5">
                <div class="text-2xl font-black tracking-tight text-blue-600 flex items-center gap-2">
                    <span>⚡ {{ $branding?->platform_name ?? 'Smart Inventory & POS Platform' }}</span>
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    {{ $invoice->seller_details['legal_name'] ?? 'Smart Inventory SaaS Solutions Inc.' }}
                </div>
                <div class="text-xs text-slate-500 font-mono">
                    GSTIN / Tax ID: <span class="font-bold text-slate-800">{{ $invoice->seller_details['tax_id'] ?? 'GSTIN-PLATFORM-2026-991A' }}</span>
                </div>
                <div class="text-xs text-slate-400">
                    {{ $invoice->seller_details['address'] ?? '100 Innovation Blvd, Suite 400, San Francisco, CA' }}
                </div>
                <div class="text-xs text-slate-400">
                    Email: {{ $invoice->seller_details['support_email'] ?? 'support@example.com' }} | Phone: {{ $invoice->seller_details['support_phone'] ?? '+1 (800) 555-0199' }}
                </div>
            </div>

            <div class="sm:text-right space-y-1">
                <div class="text-xs font-black tracking-widest uppercase text-blue-600">OFFICIAL TAX INVOICE</div>
                <div class="text-2xl font-black font-mono text-slate-900">#{{ $invoice->invoice_number }}</div>
                <div class="text-xs text-slate-500 font-medium">
                    Date: <span class="font-bold text-slate-800">{{ $invoice->invoice_date->format('d M Y') }}</span>
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    Payment Status: <span class="font-bold text-emerald-600 uppercase">{{ $invoice->status }}</span>
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    Payment Mode: <span class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $invoice->payment_method) }}</span>
                </div>
            </div>
        </div>

        <!-- Billed To (Buyer Information) -->
        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/60 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">BILLED TO (BUYER):</div>
                <div class="font-black text-sm text-slate-900">{{ $invoice->buyer_details['store_name'] ?? $company->name }}</div>
                <div class="text-slate-600 font-medium mt-0.5">Attn: {{ $invoice->buyer_details['contact_name'] ?? 'Store Administrator' }}</div>
                <div class="text-slate-500 mt-0.5">Email: {{ $invoice->buyer_details['email'] ?? $company->email }}</div>
                @if (!empty($invoice->buyer_details['phone'] ?? $company->phone))
                    <div class="text-slate-500">Phone: {{ $invoice->buyer_details['phone'] ?? $company->phone }}</div>
                @endif
            </div>

            <div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">TAX IDENTIFICATION & LOCATION:</div>
                <div class="text-slate-700">
                    GSTIN / Tax ID: <span class="font-mono font-bold text-slate-900">{{ $invoice->buyer_details['tax_id'] ?? $company->tax_id ?? 'Unregistered / Consumer' }}</span>
                </div>
                <div class="text-slate-600 mt-0.5">
                    Account Reference: <span class="font-mono font-bold">{{ $company->unique_account_id }}</span>
                </div>
                <div class="text-slate-500 mt-0.5">
                    Operating Mode: {{ $company->isRestaurantMode() ? 'Food & Restaurant' : 'General Retail POS' }}
                </div>
            </div>
        </div>

        <!-- Line Item Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-900 text-slate-900 font-black uppercase tracking-wider text-[11px]">
                        <th class="py-3 pl-2">#</th>
                        <th class="py-3">Subscription Item / Description</th>
                        <th class="py-3">Billing Cycle</th>
                        <th class="py-3 text-right">Taxable Amount</th>
                        <th class="py-3 text-right pr-2">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr>
                        <td class="py-4 pl-2 font-bold text-slate-400">1</td>
                        <td class="py-4">
                            <div class="font-black text-sm text-slate-900">{{ $invoice->plan_name }} Plan Subscription License</div>
                            <div class="text-slate-500 text-[11px] mt-0.5">
                                Cloud POS Software, Database Isolation, Real-Time Inventory & Reporting License.
                            </div>
                            @if ($invoice->notes)
                                <div class="text-blue-600 text-[10px] font-bold mt-1">{{ $invoice->notes }}</div>
                            @endif
                        </td>
                        <td class="py-4 font-semibold text-slate-700 capitalize">{{ $invoice->billing_cycle }}</td>
                        <td class="py-4 text-right font-bold text-slate-900">${{ $invoice->getFormattedSubtotal() }}</td>
                        <td class="py-4 text-right pr-2 font-black text-slate-900">${{ $invoice->getFormattedSubtotal() }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary & Tax Breakdown Box -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-6 pt-4 border-t border-slate-200">
            <div class="text-xs text-slate-500 space-y-1.5 max-w-sm">
                <div class="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Payment & Compliance Notes</div>
                <p class="leading-relaxed">
                    This is a computer-generated official tax invoice for subscription software services provided by {{ $branding?->platform_name ?? 'Smart Inventory SaaS Platform' }}.
                </p>
                @if ($invoice->payment_reference)
                    <div class="font-mono text-[10px] text-slate-400">Ref: {{ $invoice->payment_reference }}</div>
                @endif
            </div>

            <!-- Totals Box -->
            <div class="w-full sm:w-80 space-y-2 text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                <div class="flex justify-between text-slate-600 font-medium">
                    <span>Taxable Base Value</span>
                    <span class="font-bold text-slate-900">${{ $invoice->getFormattedSubtotal() }}</span>
                </div>

                @if (!empty($invoice->tax_breakdown))
                    @if (!empty($invoice->tax_breakdown['cgst_amount']))
                        <div class="flex justify-between text-slate-500 text-[11px]">
                            <span>CGST ({{ $invoice->tax_breakdown['cgst_rate'] }}%)</span>
                            <span class="font-bold text-slate-800">${{ number_format($invoice->tax_breakdown['cgst_amount'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500 text-[11px]">
                            <span>SGST ({{ $invoice->tax_breakdown['sgst_rate'] }}%)</span>
                            <span class="font-bold text-slate-800">${{ number_format($invoice->tax_breakdown['sgst_amount'], 2) }}</span>
                        </div>
                    @else
                        <div class="flex justify-between text-slate-500 text-[11px]">
                            <span>{{ $invoice->tax_type }} ({{ (float)$invoice->tax_rate }}%)</span>
                            <span class="font-bold text-slate-800">${{ $invoice->getFormattedTax() }}</span>
                        </div>
                    @endif
                @else
                    <div class="flex justify-between text-slate-500 text-[11px]">
                        <span>Tax ({{ (float)$invoice->tax_rate }}%)</span>
                        <span class="font-bold text-slate-800">${{ $invoice->getFormattedTax() }}</span>
                    </div>
                @endif

                <div class="flex justify-between items-baseline pt-2 border-t-2 border-slate-900 text-sm font-black text-slate-900">
                    <span>Total Amount Paid</span>
                    <span class="text-lg text-blue-600">${{ $invoice->getFormattedTotal() }} {{ $invoice->currency }}</span>
                </div>
            </div>
        </div>

        <!-- Footer Seal / Signature -->
        <div class="pt-8 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-400 gap-4">
            <div>
                Thank you for choosing {{ $branding?->platform_name ?? 'Smart Inventory & POS SaaS' }}!
            </div>
            <div class="text-center sm:text-right">
                <div class="font-bold text-slate-800 uppercase tracking-widest text-[10px]">Authorized Digital Signature</div>
                <div class="font-mono text-[10px] text-slate-400 mt-0.5">Digitally Verified by Platform Billing Core</div>
            </div>
        </div>

    </div>

</body>
</html>
