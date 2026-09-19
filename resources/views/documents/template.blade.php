<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle ?? 'Document' }} #{{ $sale->sale_number }} — {{ $company->name }}</title>
    @if (!empty($company->favicon))
        <link rel="icon" href="{{ $company->favicon }}">
    @endif
    @php
        $accentColor = $company->primary_color ?: '#2d7a58';
        $hex = ltrim($accentColor, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lightBg = sprintf('rgba(%d, %d, %d, 0.08)', $r, $g, $b);
        $borderTint = sprintf('rgba(%d, %d, %d, 0.22)', $r, $g, $b);
        $documentTaxRows = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
        $documentTaxRuleName = $documentTaxRows[0]['name'] ?? $sale->tax_name;
        $documentTaxLabel = \App\Services\TaxEngineService::getTaxIdentifierLabel($company->country, $documentTaxRuleName);
        $customerTaxNumber = $sale->customer?->tax_id ?: ($sale->customer?->gstin ?: $sale->customer?->document);
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --doc-accent: {{ $accentColor }};
            --doc-light-bg: {{ $lightBg }};
            --doc-border-tint: {{ $borderTint }};
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 24px 12px;
            -webkit-font-smoothing: antialiased;
        }

        .no-print-bar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: var(--doc-accent);
            color: #ffffff;
        }
        .btn-primary:hover {
            filter: brightness(0.9);
        }

        .btn-whatsapp {
            background-color: #16a34a;
            color: #ffffff;
        }
        .btn-whatsapp:hover {
            background-color: #15803d;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        /* The Main Document Paper Container matching 1131w-Zy7QIPVSff8.png */
        .document-paper {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 48px 44px;
            border-radius: 20px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        /* Top Header Section */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        .brand-logo {
            max-height: 48px;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 8px;
            display: block;
        }

        .brand-title {
            font-size: 32px;
            font-weight: 900;
            color: var(--doc-accent);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .brand-address {
            margin-top: 10px;
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
        }

        .meta-box {
            text-align: right;
            font-size: 13px;
            color: #334155;
            line-height: 1.6;
        }

        .meta-row {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .meta-label {
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
        }

        .meta-val {
            font-weight: 800;
            color: #1e293b;
        }

        /* Table Design matching 1131w-Zy7QIPVSff8.png */
        .items-header {
            background-color: var(--doc-accent);
            color: #ffffff;
            border-radius: 12px;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-container {
            background-color: #f8fafc;
            border-radius: 14px;
            margin-top: 8px;
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }

        .item-row {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #1e293b;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            border: 1px solid #f1f5f9;
        }

        .col-desc {
            flex: 2.5;
            font-weight: 600;
        }
        .col-qty {
            flex: 0.8;
            text-align: center;
            font-weight: 600;
        }
        .col-price {
            flex: 1;
            text-align: right;
            font-weight: 600;
        }
        .col-total {
            flex: 1.2;
            text-align: right;
            font-weight: 800;
        }

        .item-desc-bold {
            font-weight: 800;
            color: #0f172a;
        }
        .item-desc-sub {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Summary Section matching 1131w-Zy7QIPVSff8.png */
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .summary-box {
            width: 320px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 8px;
            color: #334155;
            font-weight: 600;
        }

        .summary-row.total-row {
            font-size: 18px;
            font-weight: 900;
            color: #1e293b;
            padding-top: 8px;
            border-top: 2px solid #cbd5e1;
        }

        .total-amount {
            color: var(--doc-accent);
        }

        /* Two Bottom Info Cards matching 1131w-Zy7QIPVSff8.png */
        .bottom-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 36px;
        }

        .info-card {
            background-color: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid #e2e8f0;
        }

        .card-heading {
            font-size: 14px;
            font-weight: 800;
            color: var(--doc-accent);
            margin-bottom: 6px;
        }

        .card-text {
            font-size: 12px;
            line-height: 1.5;
            color: #475569;
            margin-bottom: 12px;
        }

        .terms-list {
            padding-left: 16px;
            margin: 0;
            font-size: 11px;
            line-height: 1.6;
            color: #475569;
        }

        /* Full Width Bottom Contact Bar matching 1131w-Zy7QIPVSff8.png */
        .doc-footer-bar {
            margin-top: 36px;
            border-radius: 9999px;
            background-color: var(--doc-accent);
            color: #ffffff;
            padding: 12px 28px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .document-paper {
                box-shadow: none !important;
                border: none !important;
                padding: 20px 10px !important;
                max-width: 100% !important;
            }
            .doc-footer-bar {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items-container {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .summary-section, .bottom-cards-grid, .doc-footer-bar, .info-card, .item-row {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Bar (Screen Only) -->
    @unless(request()->boolean('embed'))
    <div class="no-print-bar">
        <div style="font-weight: 800; font-size: 14px; color: #334155;">
            {{ $documentTitle ?? 'Document' }} #{{ $sale->sale_number }}
        </div>

        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                <span>Print / Download PDF</span>
            </button>

            @include('documents.partials.dispatch-actions')

            @if (auth('web')->check())
                <a href="{{ $backRoute ?? route('tenant.sales.index') }}" class="btn btn-secondary">
                    <span>&larr; Back to App</span>
                </a>
            @endif
        </div>
    </div>
    @endunless

    <!-- Printable Paper Area matching 1131w-Zy7QIPVSff8.png -->
    <div class="document-paper">
        
        <!-- Header -->
        <div class="doc-header">
            <div>
                @if (!empty($company->logo))
                    <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="brand-logo">
                @endif
                <h1 class="brand-title">{{ $company->name ?? 'FAUGET' }}</h1>
                <div class="brand-subtitle">{{ $company->trade_name ?? ($isQuotation ? 'QUOTATION PROPOSAL' : 'DESIGN STUDIO') }}</div>
                <div class="brand-address">
                    {{ $company->address ?? '123 Anywhere St., Any City' }}<br>
                    @if ($company->city || $company->state)
                        {{ $company->city }}{{ $company->state ? ', ' . $company->state : '' }} {{ $company->postal_code }}
                    @endif
                    @if ($company->tax_id)
                        <br>{{ $documentTaxLabel }}: {{ $company->tax_id }}
                    @endif
                </div>
            </div>

            <div class="meta-box">
                <div class="meta-row">
                    <span class="meta-label">Customer ID:</span>
                    <span class="meta-val">{{ $sale->customer?->id ? sprintf('%04d-01', $sale->customer->id) : '3110-01' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">{{ __("Date:") }}</span>
                    <span class="meta-val">{{ $sale->created_at ? $sale->created_at->format('d/m/Y') : now()->format('d/m/Y') }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">{{ $isQuotation ? 'Proposal For:' : 'Client:' }}</span>
                    <span class="meta-val">{{ strtoupper($sale->customer_name ?? 'Valued Customer') }}</span>
                </div>
                @if ($sale->customer?->phone)
                    <div class="meta-row">
                        <span class="meta-label">Phone:</span>
                        <span class="meta-val">{{ $sale->customer->phone }}</span>
                    </div>
                @endif
                @if ($sale->customer?->email)
                    <div class="meta-row">
                        <span class="meta-label">Email:</span>
                        <span class="meta-val">{{ $sale->customer->email }}</span>
                    </div>
                @endif
                @if ($customerTaxNumber)
                    <div class="meta-row">
                        <span class="meta-label">{{ $documentTaxLabel }}:</span>
                        <span class="meta-val">{{ $customerTaxNumber }}</span>
                    </div>
                @endif
                @if (!empty($sale->payment_terms))
                    <div class="meta-row">
                        <span class="meta-label">Payment Terms:</span>
                        <span class="meta-val" style="font-weight: bold; color: var(--doc-accent);">{{ $sale->payment_terms }}</span>
                    </div>
                @endif
                <div class="meta-row" style="margin-top: 4px;">
                    <span class="meta-label">{{ $isQuotation ? 'Quote' : 'Invoice' }} #:</span>
                    <span class="meta-val" style="color: var(--doc-accent);">{{ $sale->sale_number }}</span>
                </div>
            </div>
        </div>

        <!-- Table Header Bar -->
        <div class="items-header">
            <div class="col-desc">Item Description</div>
            <div class="col-qty">Qty</div>
            <div class="col-price">Price</div>
            <div class="col-total">Total</div>
        </div>

        <!-- Items Container with Light Mint Background -->
        <div class="items-container">
            @forelse ($sale->items ?? [] as $item)
                @php
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['price'] ?? 0);
                    $lineTotal = $qty * $price;
                @endphp
                <div class="item-row">
                    <div class="col-desc">
                        <div class="item-desc-bold">{{ $item['name'] ?? 'Custom Item' }}</div>
                        @if (!empty($item['description']))
                            <div class="item-desc-sub">{{ $item['description'] }}</div>
                        @endif
                    </div>
                    <div class="col-qty">{{ $qty }}</div>
                    <div class="col-price">{{ $company->formatMoney($price) }}</div>
                    <div class="col-total">{{ $company->formatMoney($lineTotal) }}</div>
                </div>
            @empty
                <div class="item-row" style="justify-content: center; color: #94a3b8;">
                    No line items recorded for this document.
                </div>
            @endforelse
        </div>

        <!-- Summary Calculation Area -->
        <div class="summary-section">
            <div class="summary-box">
                @php
                    $subtotal = (float) $sale->total + (float) $sale->discount;
                @endphp

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>{{ $company->formatMoney($subtotal) }}</span>
                </div>

                @if ($sale->discount > 0)
                    <div class="summary-row" style="color: #dc2626;">
                        <span>Discount:</span>
                        <span>-{{ $company->formatMoney($sale->discount) }}</span>
                    </div>
                @endif

                @php
                    $isIndia = in_array(strtoupper(trim((string)$company->country)), ['IN', 'IND', 'INDIA'], true) || str_contains(strtoupper(trim((string)$company->country)), 'INDIA') || ($company->currency ?? '') === 'INR' || ($company->currency_symbol ?? '') === '₹';
                    $taxLabel = $isIndia ? 'GST' : ($sale->tax_name ?: 'Tax / VAT');
                    $taxBreakdown = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
                @endphp

                @if ($sale->tax > 0 || !empty($taxBreakdown))
                    @if (!empty($taxBreakdown))
                        @foreach ($taxBreakdown as $taxRow)
                            <div class="summary-row">
                                <span>{{ $taxRow['name'] }} ({{ $taxRow['rate'] }}%):</span>
                                <span>+{{ $company->formatMoney($taxRow['amount']) }}</span>
                            </div>
                            @foreach ($taxRow['sub_components'] as $comp)
                                <div class="summary-row" style="font-size: 11px; color: #64748b; padding-left: 14px;">
                                    <span>&#9492; {{ $comp['name'] }} ({{ $comp['rate'] }}%):</span>
                                    <span>+{{ $company->formatMoney($comp['amount']) }}</span>
                                </div>
                            @endforeach
                        @endforeach
                    @else
                        @if ($isIndia && (float)($sale->tax_rate ?? 0) > 0)
                            @php
                                $halfRate = (float)$sale->tax_rate / 2;
                                $halfTax = (float)$sale->tax / 2;
                            @endphp
                            <div class="summary-row">
                                <span>CGST ({{ number_format($halfRate, 1) }}%):</span>
                                <span>+{{ $company->formatMoney($halfTax) }}</span>
                            </div>
                            <div class="summary-row">
                                <span>SGST ({{ number_format($halfRate, 1) }}%):</span>
                                <span>+{{ $company->formatMoney($halfTax) }}</span>
                            </div>
                        @else
                            <div class="summary-row">
                                <span>{{ $taxLabel }}{{ (float)($sale->tax_rate ?? 0) > 0 ? ' (' . (float)$sale->tax_rate . '%)' : '' }}:</span>
                                <span>+{{ $company->formatMoney($sale->tax) }}</span>
                            </div>
                        @endif
                    @endif
                @endif

                <div class="summary-row total-row">
                    <span>Total Amount:</span>
                    <span class="total-amount">{{ $company->formatMoney($sale->total) }}</span>
                </div>

                @if ($sale->payments && $sale->payments->count() > 1)
                    @foreach ($sale->payments as $payment)
                        <div class="summary-row">
                            <span>Paid via {{ ucfirst($payment->payment_method) }}:</span>
                            <span>{{ $company->formatMoney($payment->amount) }}</span>
                        </div>
                    @endforeach
                @endif

                @if ((float) ($sale->due_amount ?? 0) > 0)
                    <div class="summary-row" style="color: #dc2626;">
                        <span>Balance Due{{ $sale->due_date ? ' (by ' . $sale->due_date->format('d-m-Y') . ')' : '' }}:</span>
                        <span>{{ $company->formatMoney($sale->due_amount) }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if (!empty($sale->notes))
            <div class="info-card" style="margin-top: 24px;">
                <div class="card-heading">Order Notes & Remarks</div>
                <div class="card-text" style="margin-bottom: 0;">{!! clean_html($sale->notes) !!}</div>
            </div>
        @endif

        <!-- Bottom Two Cards: Payable To & Terms -->
        <div class="bottom-cards-grid">

            <!-- Left Card: Payable To / Bank Details -->
            <div class="info-card">
                <div class="card-heading">Payable To</div>
                <div class="card-text">
                    <strong>{{ $company->trade_name ?? $company->name }}</strong><br>
                    {{ $company->address ?? '123 Anywhere St., Any City' }}
                </div>

                <div class="card-heading">Bank Details</div>
                <div class="card-text" style="margin-bottom: 0;">
                    {!! clean_html($company->bank_details ?? ($company->name . '<br>Phone: ' . ($company->phone ?? '+123-456-7890'))) !!}
                </div>
            </div>

            <!-- Right Card: Terms and conditions -->
            <div class="info-card">
                <div class="card-heading">Terms and conditions:</div>
                <div class="card-text" style="margin-bottom: 0;">
                    @if ($isQuotation && !empty($company->quote_terms))
                        {!! clean_html($company->quote_terms) !!}
                    @elseif (!$isQuotation && !empty($company->invoice_terms))
                        {!! clean_html($company->invoice_terms) !!}
                    @else
                        <ul class="terms-list">
                            <li>All rates quoted are valid for 15 days.</li>
                            <li>40% payment should be done in advance.</li>
                            <li>The remaining amount should be paid within 20 days of delivery.</li>
                        </ul>
                    @endif
                </div>
            </div>

        </div>

        <!-- Full-Width Footer Bar -->
        <div class="doc-footer-bar">
            @if (!empty($company->phone))
                <div>📞 {{ $company->phone }}</div>
            @endif
            @if (!empty($company->email))
                <div>✉️ {{ $company->email }}</div>
            @endif
            @if (!empty($company->website))
                <div>🌐 {{ $company->website }}</div>
            @endif
            @php
                $platformBranding = \App\Models\PlatformBranding::current();
                $platformName = $platformBranding?->platform_name ?? config('app.name');
                $platformDomain = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'yourdomain.com';
            @endphp
            @if (setting('show_powered_by', true))
                <div style="margin-top: 4px; font-size: 9px; color: #94a3b8;">
                    {{ __('Powered by') }} {{ $platformName }} &bull; {{ __('Issued via') }} {{ $platformDomain }}
                </div>
            @endif
        </div>

    </div>

    <script>
        // If ?print=1 in query string, auto open print dialog
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
