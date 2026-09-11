<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $sale->sale_number }} — {{ $company->name }}</title>
    @if ($company->getFaviconUrl())
        <link rel="icon" href="{{ $company->getFaviconUrl() }}">
    @endif
    @php
        $requestedFormat = request('format', $company->getReceiptFormat());
        $is58mm = ($requestedFormat === '58mm');
        $paperWidth = $is58mm ? '58mm' : '80mm';
        $containerWidth = $is58mm ? '280px' : '380px';
        $qrDimension = $is58mm ? '70px' : '90px';
        $fontSize = $is58mm ? '11px' : '12.5px';
        $subSize = $is58mm ? '10px' : '11px';
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #7b838f;
            color: #1e293b;
            padding: 24px 12px;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Top Action Bar (Screen Only) */
        .no-print-bar {
            max-width: {{ $is58mm ? '360px' : '440px' }};
            width: 100%;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 14px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
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

        .format-badge {
            display: inline-flex;
            gap: 2px;
            background: #f1f5f9;
            padding: 2px;
            border-radius: 8px;
        }
        .format-badge a {
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            border-radius: 6px;
            text-decoration: none;
            color: #64748b;
        }
        .format-badge a.active {
            background: #2563eb;
            color: #ffffff;
        }

        /* Receipt Paper Wrapper */
        .receipt-container {
            max-width: {{ $containerWidth }};
            width: 100%;
            background: #ffffff;
            padding: {{ $is58mm ? '18px 12px 22px 12px' : '28px 20px 32px 20px' }};
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border-radius: 4px;
            color: #111827;
            font-size: {{ $fontSize }};
            line-height: 1.35;
        }

        /* Centered Header Section */
        .receipt-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .store-logo {
            max-height: {{ $is58mm ? '32px' : '44px' }};
            max-width: {{ $is58mm ? '110px' : '150px' }};
            object-fit: contain;
            margin: 0 auto 5px auto;
            display: block;
        }

        .receipt-title {
            font-size: {{ $is58mm ? '14px' : '17px' }};
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
            color: #0f172a;
        }

        .store-info {
            font-size: {{ $subSize }};
            line-height: 1.3;
            color: #475569;
        }

        .receipt-type-pill {
            font-size: {{ $subSize }};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            margin: 4px 0 2px 0;
        }

        /* Dashed Separator Line */
        .dashed-line {
            border: none;
            border-top: 1px dashed #64748b;
            margin: 6px 0;
            width: 100%;
        }

        /* Tables & Alignment (Fixed Layout to prevent text clipping) */
        .meta-table, .items-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .meta-table td {
            font-size: {{ $subSize }};
            padding: 1.5px 0;
            vertical-align: top;
        }
        .meta-table td.col-left {
            width: 60%;
            text-align: left;
            padding-right: 1mm;
            vertical-align: top;
            white-space: nowrap;
        }
        .meta-table td.col-right {
            width: 40%;
            text-align: right;
            padding-right: 1mm;
            vertical-align: top;
            white-space: nowrap;
        }

        /* Itemized Product List Table */
        .items-table th {
            font-size: {{ $subSize }};
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 0;
            border-bottom: 1px dashed #64748b;
            color: #1e293b;
        }
        .items-table td {
            font-size: {{ $fontSize }};
            padding: 3px 0;
            vertical-align: top;
        }
        .items-table .col-item {
            width: 50%;
            text-align: left;
            padding-right: 1mm;
            word-break: break-word;
            font-weight: 600;
        }
        .items-table .col-qty {
            width: 15%;
            text-align: center;
            font-weight: 600;
        }
        .items-table .col-total {
            width: 35%;
            text-align: right;
            padding-right: 1mm;
            white-space: nowrap;
            font-weight: 700;
        }

        /* Totals Breakdown */
        .totals-table td {
            font-size: {{ $fontSize }};
            padding: 2px 0;
        }
        .totals-table td.col-label {
            width: 55%;
            text-align: left;
            padding-right: 1mm;
        }
        .totals-table td.col-val {
            width: 45%;
            text-align: right;
            padding-right: 1mm;
            white-space: nowrap;
        }
        .total-row {
            font-size: {{ $is58mm ? '13px' : '15px' }};
            font-weight: 800;
            border-top: 1px dashed #0f172a;
            border-bottom: 1px dashed #0f172a;
            padding: 4px 0 !important;
            color: #0f172a;
        }

        /* Footer Section */
        .footer-section {
            text-align: center;
            margin-top: 6px;
            padding-top: 2px;
        }
        .qr-container {
            margin: 4px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .qr-image {
            width: {{ $qrDimension }};
            height: {{ $qrDimension }};
            margin: 0 auto;
            display: block;
        }
        .qr-caption {
            font-size: {{ $subSize }};
            color: #475569;
            margin-top: 3px;
            font-weight: 600;
        }
        .ref-code {
            font-size: {{ $subSize }};
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 4px;
            color: #0f172a;
        }
        .footer-text {
            font-size: {{ $subSize }};
            color: #475569;
            margin-top: 3px;
            line-height: 1.3;
        }

        @media print {
            @page {
                size: {{ $is58mm ? '58mm auto' : '80mm auto' }};
                margin: 0;
            }
            html, body {
                width: {{ $is58mm ? '58mm' : '80mm' }} !important;
                min-width: {{ $is58mm ? '58mm' : '80mm' }} !important;
                max-width: {{ $is58mm ? '58mm' : '80mm' }} !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: {{ $is58mm ? '2mm 3mm' : '2mm 4mm' }} !important;
                background: #ffffff !important;
                display: block !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print, .no-print-bar, nav, header, button, .btn, .format-badge {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                background: #ffffff !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
                border: none !important;
                page-break-after: avoid !important;
            }

            /* Prevent flex/min-height rounding from emitting a trailing blank
               sheet in Chromium's thermal print pipeline. */
            body::before,
            body::after {
                display: none !important;
                content: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (Screen Only) -->
    @unless(request()->boolean('embed'))
    <div class="no-print-bar">
        <div style="font-weight: 800; font-size: 11px; color: #1e293b;">
            Receipt #{{ $sale->sale_number }}
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <!-- Format switcher -->
            <div class="format-badge">
                <a href="{{ request()->fullUrlWithQuery(['format' => '80mm']) }}" class="{{ !$is58mm ? 'active' : '' }}">80mm</a>
                <a href="{{ request()->fullUrlWithQuery(['format' => '58mm']) }}" class="{{ $is58mm ? 'active' : '' }}">58mm</a>
            </div>

            <button onclick="window.print()" class="btn btn-primary">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                <span>{{ __("Print") }}</span>
            </button>

            @if (!empty($whatsAppUrl))
                <a href="{{ $whatsAppUrl }}" target="_blank" class="btn btn-whatsapp">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                </a>
            @endif

            @if (auth('web')->check() && !$isQuotation)
                <button type="button" onclick="posDispatchEmail()" class="btn btn-secondary" title="{{ __('Email') }}">✉️</button>

                @foreach ($dispatchChannels ?? [] as $dc)
                    <button type="button" onclick="posDispatchCustom({{ $dc->id }}, {{ Js::from($dc->name) }})" class="btn btn-secondary" title="{{ $dc->name }}">
                        @if ($dc->isIconUrl())
                            <img src="{{ $dc->iconDisplay() }}" alt="" style="width:13px;height:13px;border-radius:3px;object-fit:cover;">
                        @else
                            <span style="line-height:1;">{{ $dc->iconDisplay() }}</span>
                        @endif
                    </button>
                @endforeach
            @endif

            @if (auth('web')->check())
                <a href="{{ $backRoute ?? route('tenant.sales.index') }}" class="btn btn-secondary">
                    <span>{{ __("Back") }}</span>
                </a>
            @endif
        </div>
    </div>
    @endunless

    @if (auth('web')->check() && !$isQuotation)
    <script>
        function posCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '{{ csrf_token() }}';
        }

        function posDispatchEmail() {
            const email = prompt('{{ __('Send invoice to which email address?') }}', {{ Js::from($customerEmail ?? '') }});
            if (!email) return;
            fetch({{ Js::from(route('tenant.sales.send', $sale)) }}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': posCsrfToken() },
                body: JSON.stringify({ recipient_email: email }),
            }).then(r => r.json()).then(data => alert(data.message || (data.success ? 'Sent.' : 'Failed to send.')))
              .catch(() => alert('Failed to send invoice.'));
        }

        function posDispatchCustom(channelId, channelName) {
            if (!confirm('{{ __('Send this invoice to') }} ' + channelName + '?')) return;
            fetch({{ Js::from(route('tenant.sales.send-custom', $sale)) }}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': posCsrfToken() },
                body: JSON.stringify({ channel_id: channelId }),
            }).then(r => r.json()).then(data => alert(data.message || (data.success ? 'Dispatched.' : 'Failed to dispatch.')))
              .catch(() => alert('Failed to dispatch invoice.'));
        }
    </script>
    @endif

    <!-- Thermal Cash Receipt Paper -->
    <div class="receipt-container">
        
        <!-- Header -->
        <div class="receipt-header">
            @if ($company->getLogoUrl())
                <img src="{{ $company->getLogoUrl() }}" alt="{{ $company->name }}" class="store-logo">
            @endif
            
            <div class="receipt-title">{{ $company->trade_name ?: $company->name }}</div>
            <div class="receipt-type-pill">*** {{ __("TAX INVOICE / RECEIPT") }} ***</div>
            <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">{{ __("Invoice / Receipt") }}</div>
            
            <div class="store-info" style="margin-top: 4px;">
                @if (!empty($company->address))
                    <div>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
                @endif
                @if (!empty($company->phone))
                    <div>Tel: {{ $company->phone }}</div>
                @endif
                @if (!empty($company->tax_id))
                    @php
                        $receiptTaxRows = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
                        $appliedTaxRuleName = $receiptTaxRows[0]['name'] ?? $sale->tax_name;
                    @endphp
                    <div>{{ \App\Services\TaxEngineService::getTaxIdentifierLabel($company->country, $appliedTaxRuleName) }}: {{ $company->tax_id }}</div>
                @endif
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Metadata Block (2-Column Key-Value Layout) -->
        <table class="meta-table">
            <tr>
                <td class="col-left"><strong>{{ __("Receipt #:") }}</strong> {{ $sale->sale_number }}</td>
                <td class="col-right">{{ $sale->created_at ? $sale->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="col-left"><strong>{{ __("Customer:") }}</strong> {{ Str::limit($sale->customer_name ?: __('Walk-in'), $is58mm ? 14 : 20) }}</td>
                <td class="col-right"><strong>{{ __("Staff:") }}</strong> {{ Str::limit($sale->user?->name ?: __('Admin'), $is58mm ? 10 : 15) }}</td>
            </tr>
            <tr>
                <td class="col-left"><strong>{{ __("Payment:") }}</strong> {{ ucfirst($sale->payment_method ?: 'Cash') }}</td>
                <td class="col-right"><strong>{{ __("Status:") }}</strong> <span style="font-weight: 700;">{{ strtoupper($sale->payment_status ?: ($sale->status === 'completed' ? 'PAID' : $sale->status)) }}</span></td>
            </tr>
            @if ($sale->table_name)
                <tr>
                    <td class="col-left"><strong>{{ __("Table:") }}</strong> {{ $sale->table_name }}</td>
                    <td class="col-right"></td>
                </tr>
            @endif
        </table>

        <div class="dashed-line"></div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-item">{{ __("ITEM") }}</th>
                    <th class="col-qty">{{ __("QTY") }}</th>
                    <th class="col-total">{{ __("TOTAL") }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sale->items ?? [] as $item)
                    @php
                        $qty = (float) ($item['quantity'] ?? 1);
                        $price = (float) ($item['price'] ?? 0);
                        $lineTotal = $qty * $price;
                    @endphp
                    <tr>
                        <td class="col-item">
                            <div>{{ $item['name'] ?? 'Item' }}</div>
                            @if ($qty > 1)
                                <div style="font-size: {{ $subSize }}; color: #64748b; font-weight: normal;">{{ $qty }} x {{ $company->formatMoney($price) }}</div>
                            @endif
                            @if (!empty($item['is_overridden']))
                                <div style="font-size: {{ $subSize }}; color: #64748b; font-weight: normal;">Std: {{ $company->formatMoney((float) ($item['base_price'] ?? 0)) }}</div>
                            @endif
                        </td>
                        <td class="col-qty">{{ $qty }}</td>
                        <td class="col-total">{{ $company->formatMoney($lineTotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: #94a3b8; padding: 6px 0;">
                            {{ __("No items in receipt.") }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <!-- Calculation Summary & Totals -->
        <table class="totals-table">
            @php
                $subtotal = $sale->subtotal;
                if ($subtotal <= 0) {
                    $subtotal = (float) $sale->total + (float) $sale->discount - (float) ($sale->tax_amount ?? $sale->tax ?? 0);
                }
                $paidAmt = (float) ($sale->paid_amount ?: $sale->total);
                $totalAmt = (float) $sale->total;
                $changeDue = max(0, $paidAmt - $totalAmt);
                $taxBreakdown = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
                $saleTax = (float) ($sale->tax_amount ?? $sale->tax ?? 0);
                if ($saleTax <= 0 && !empty($taxBreakdown)) {
                    $saleTax = array_sum(array_column($taxBreakdown, 'amount'));
                }
            @endphp

            <tr>
                <td class="col-label">{{ __("Subtotal:") }}</td>
                <td class="col-val">{{ $company->formatMoney($subtotal > 0 ? $subtotal : $sale->total) }}</td>
            </tr>

            @if ((float) $sale->discount > 0)
                <tr>
                    <td class="col-label" style="color: #dc2626;">{{ __("Discount:") }}</td>
                    <td class="col-val" style="color: #dc2626;">-{{ $company->formatMoney($sale->discount) }}</td>
                </tr>
            @endif

            @if ($saleTax > 0 || !empty($taxBreakdown))
                @forelse ($taxBreakdown as $taxRow)
                    <tr>
                        <td class="col-label" style="font-weight: 700;">{{ $taxRow['name'] }} ({{ $taxRow['rate'] }}%):</td>
                        <td class="col-val" style="font-weight: 700;">+{{ $company->formatMoney($taxRow['amount']) }}</td>
                    </tr>
                    @foreach ($taxRow['sub_components'] as $component)
                        <tr>
                            <td class="col-label" style="color: #64748b; padding-left: 8px;">&#9492; {{ $component['name'] }} ({{ $component['rate'] }}%):</td>
                            <td class="col-val" style="color: #64748b;">{{ $company->formatMoney($component['amount']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td class="col-label">{{ $sale->tax_name ?: __('Tax') }} ({{ (float) ($sale->tax_rate ?? 0) }}%):</td>
                        <td class="col-val">+{{ $company->formatMoney($saleTax) }}</td>
                    </tr>
                @endforelse
            @endif

            <tr class="total-row">
                <td class="col-label"><strong>{{ __("TOTAL AMOUNT:") }}</strong></td>
                <td class="col-val"><strong>{{ $company->formatMoney($sale->total) }}</strong></td>
            </tr>

            @if ($paidAmt > 0)
                <tr>
                    <td class="col-label">{{ __("Amount Paid:") }}</td>
                    <td class="col-val">{{ $company->formatMoney($paidAmt) }}</td>
                </tr>
            @endif

            @if ($changeDue > 0)
                <tr>
                    <td class="col-label">{{ __("Change Due:") }}</td>
                    <td class="col-val">{{ $company->formatMoney($changeDue) }}</td>
                </tr>
            @endif

            @if ((float) ($sale->due_amount ?? 0) > 0)
                <tr>
                    <td class="col-label" style="color: #dc2626; font-weight: 700;">{{ __("Remaining Balance:") }}</td>
                    <td class="col-val" style="color: #dc2626; font-weight: 700;">{{ $company->formatMoney($sale->due_amount) }}</td>
                </tr>
            @endif
        </table>

        @if ($sale->payments && $sale->payments->count() > 1)
            <div class="dashed-line"></div>
            <table class="meta-table">
                @foreach ($sale->payments as $pmt)
                    <tr>
                        <td class="col-left">{{ ucfirst($pmt->payment_method) }}{{ $pmt->reference_number ? ' (' . $pmt->reference_number . ')' : '' }}:</td>
                        <td class="col-right"><strong>{{ $company->formatMoney($pmt->amount) }}</strong></td>
                    </tr>
                @endforeach
            </table>
        @endif

        @php
            $hasPix = strtolower($sale->payment_method ?? '') === 'pix' || ($sale->payments && $sale->payments->contains(fn($p) => strtolower($p->payment_method) === 'pix'));
        @endphp
        @if ($hasPix)
            <div class="dashed-line"></div>
            <!-- Thermal Receipt PIX Block -->
            <div class="text-center my-2" style="text-align: center; margin: 6px 0;">
                <div class="font-bold text-xs uppercase" style="font-weight: 800; font-size: {{ $fontSize }}; text-transform: uppercase;">{{ __('Payment via PIX') }}</div>
                <div class="text-xs" style="font-size: {{ $fontSize }}; font-weight: 600;">{{ tenant_setting('pix_holder_name', $company->trade_name ?: $company->name) }}</div>
                <div class="text-[10px] text-slate-500" style="font-size: {{ $subSize }}; color: #64748b;">{{ tenant_setting('pix_city', $company->city ?: 'Springfield') }}</div>
            </div>
        @endif

        @if (!empty($sale->notes))
            <div class="dashed-line"></div>
            <div class="store-info" style="text-align: left; word-break: break-word;">
                <strong>{{ __("Note:") }}</strong> {!! nl2br(e(strip_tags($sale->notes))) !== $sale->notes ? $sale->notes : nl2br(e($sale->notes)) !!}
            </div>
        @endif

        @php
            $termsContent = $sale->terms ?? ($company->invoice_terms ?? null);
        @endphp
        @if (!empty($termsContent))
            <div class="dashed-line"></div>
            <div class="store-info" style="text-align: left; word-break: break-word;">
                <strong>{{ __("Terms:") }}</strong>
                <div class="terms-content" style="margin-top: 2px;">
                    {!! clean_html($termsContent) !!}
                </div>
            </div>
        @endif

        <div class="dashed-line"></div>

        <!-- Centered Footer Block with QR Code -->
        <div class="footer-section">
            @if (!empty($qrCodeSvg))
                <div class="qr-container">
                    <div style="width: {{ $qrDimension }}; height: {{ $qrDimension }}; margin: 0 auto;">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="qr-caption">{{ __("Scan for digital e-receipt & verify") }}</div>
                </div>
            @elseif (!empty($qrCodeDataUri))
                <div class="qr-container">
                    <img src="{{ $qrCodeDataUri }}" class="qr-image" alt="QR Code">
                    <div class="qr-caption">{{ __("Scan for digital e-receipt & verify") }}</div>
                </div>
            @endif

            <div class="ref-code">#{{ $sale->sale_number }}</div>

            <div class="footer-text">
                <div>{{ __("Thank you for your visit & business!") }}</div>
                <div>{{ $company->website ?: (config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'yourdomain.com') }}</div>
                @php
                    $platformBranding = \App\Models\PlatformBranding::current();
                    $platformName = $platformBranding?->platform_name ?? config('app.name');
                    $platformDomain = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'yourdomain.com';
                @endphp
                @if (setting('show_powered_by', true))
                <div style="font-size: {{ $subSize }}; color: #64748b; margin-top: 4px; border-top: 1px dashed #cbd5e1; padding-top: 4px;">
                    {{ __("Powered by") }} {{ $platformName }} &bull; {{ __("Issued via") }} {{ $platformDomain }}
                </div>
                @endif
            </div>
        </div>

    </div>

</body>
</html>
