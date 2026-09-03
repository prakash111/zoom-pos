<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt #{{ $sale->sale_number }}</title>
    @php
        $is58mm = $is58mm ?? false;
        $fontSize = $is58mm ? '9px' : '11px';
        $titleSize = $is58mm ? '13px' : '16px';
        $subSize = $is58mm ? '8px' : '9.5px';
        $itemSize = $is58mm ? '8.5px' : '10.5px';
        $totalSize = $is58mm ? '12px' : '14px';
        $qrDimension = $is58mm ? '68px' : '88px';

        // dompdf resolves one font per styled element and does not fall
        // through a CSS font-family list by glyph coverage (confirmed
        // empirically) — DejaVu Sans (this document's base font) has no
        // Devanagari glyphs at all, so a Hindi-locale tenant's translated
        // labels rendered under it show as tofu boxes while labels with no
        // Hindi translation (falling back to their literal English key)
        // render fine. $L() wraps a translated label in the .i18n-label
        // class (backed by the bundled Devanagari font) only when the
        // active locale actually needs it and the label was actually
        // translated, leaving plain English/data text on the base font.
        $needsScriptFont = in_array(app()->getLocale(), ['hi'], true);
        $L = function (string $key) use ($needsScriptFont) {
            $text = __($key);
            return ($needsScriptFont && $text !== $key)
                ? '<span class="i18n-label">'.e($text).'</span>'
                : e($text);
        };
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url(data:font/ttf;base64,{{ $notoDevanagariRegularBase64 ?? '' }}) format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url(data:font/ttf;base64,{{ $notoDevanagariBoldBase64 ?? '' }}) format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        .i18n-label {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }
        @page {
            size: {{ $paperWidth ?? ($is58mm ? '58mm' : '80mm') }} auto;
            margin: {{ $is58mm ? '0mm 3mm' : '0mm 4mm' }};
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
            font-size: {{ $fontSize }};
            line-height: 1.15;
            color: #000000;
            background: #ffffff;
            width: 100%;
            max-width: {{ $is58mm ? '52mm' : '72mm' }};
            margin: 0 auto;
            padding: 1mm 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 3px;
        }
        .logo {
            max-width: {{ $is58mm ? '95px' : '130px' }};
            max-height: {{ $is58mm ? '30px' : '42px' }};
            margin: 0 auto 3px auto;
            display: block;
        }
        .store-title {
            font-size: {{ $titleSize }};
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1px;
            letter-spacing: 0.5px;
        }
        .store-meta {
            font-size: {{ $subSize }};
            color: #222222;
            line-height: 1.2;
        }
        .receipt-type {
            font-size: {{ $fontSize }};
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0 1px 0;
        }
        
        .dashed {
            border: none;
            border-top: 1px dashed #222222;
            margin: 2.5px 0;
            width: 100%;
        }
        
        .meta-table, .items-table, .totals-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .meta-table td {
            font-size: {{ $subSize }};
            padding: 1px 0;
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
        
        .items-table th {
            font-size: {{ $subSize }};
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 0;
            border-bottom: 1px dashed #222222;
        }
        .items-table td {
            font-size: {{ $itemSize }};
            padding: 1.5px 0;
            vertical-align: top;
        }
        .items-table .col-item {
            width: 50%;
            text-align: left;
            padding-right: 1mm;
            word-break: break-word;
        }
        .items-table .col-qty {
            width: 15%;
            text-align: center;
        }
        .items-table .col-total {
            width: 35%;
            text-align: right;
            padding-right: 1mm;
            white-space: nowrap;
        }
        
        .totals-table td {
            font-size: {{ $fontSize }};
            padding: 1px 0;
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
        .grand-total {
            font-size: {{ $totalSize }};
            font-weight: bold;
            border-top: 1px dashed #222222;
            border-bottom: 1px dashed #222222;
            padding: 2.5px 0 !important;
        }
        
        .footer-block {
            text-align: center;
            margin-top: 3px;
            padding-top: 1px;
        }
        .qr-wrapper {
            margin: 2px auto;
            text-align: center;
        }
        .qr-image {
            width: {{ $qrDimension }};
            height: {{ $qrDimension }};
            margin: 0 auto;
            display: block;
        }
        .qr-caption {
            font-size: {{ $subSize }};
            color: #444444;
            margin-top: 1px;
            font-weight: 600;
        }
        .ref-code {
            font-size: {{ $subSize }};
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .footer-text {
            font-size: {{ $subSize }};
            color: #333333;
            margin-top: 2px;
            line-height: 1.2;
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
                margin: 0 auto !important;
                padding: {{ $is58mm ? '2mm 3mm' : '2mm 4mm' }} !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print, nav, header, button, .btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Header & Logo -->
    <div class="receipt-header">
        @if (!empty($logoBase64))
            <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
        @endif
        <div class="store-title">{{ $company->trade_name ?: $company->name }}</div>
        <div class="store-meta">
            @if ($company->address)
                <div>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
            @endif
            @if ($company->phone)
                <div>Tel: {{ $company->phone }}</div>
            @endif
            @if ($company->tax_id)
                @php
                    $receiptTaxRows = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
                    $appliedTaxRuleName = $receiptTaxRows[0]['name'] ?? $sale->tax_name;
                @endphp
                <div>{{ \App\Services\TaxEngineService::getTaxIdentifierLabel($company->country, $appliedTaxRuleName) }}: {{ $company->tax_id }}</div>
            @endif
        </div>
        <div class="receipt-type">*** {!! $L("TAX INVOICE / RECEIPT") !!} ***</div>
    </div>

    <div class="dashed"></div>

    <!-- Metadata Block (2-Column Key-Value Layout) -->
    <table class="meta-table">
        <tr>
            <td class="col-left"><strong>{!! $L("Receipt #:") !!}</strong> {{ $sale->sale_number }}</td>
            <td class="col-right">{{ $sale->created_at ? $sale->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>{!! $L("Customer:") !!}</strong> {!! $sale->customer_name ? e(Str::limit($sale->customer_name, $is58mm ? 14 : 20)) : $L('Walk-in') !!}</td>
            <td class="col-right"><strong>{!! $L("Staff:") !!}</strong> {!! $sale->user?->name ? e(Str::limit($sale->user->name, $is58mm ? 10 : 15)) : $L('Admin') !!}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>{!! $L("Payment:") !!}</strong> {{ ucfirst($sale->payment_method ?: 'Cash') }}</td>
            <td class="col-right"><strong>{!! $L("Status:") !!}</strong> <span class="bold">{{ strtoupper($sale->payment_status ?: ($sale->status === 'completed' ? 'PAID' : $sale->status)) }}</span></td>
        </tr>
        @if ($sale->table_name)
            <tr>
                <td class="col-left"><strong>{!! $L("Table:") !!}</strong> {{ $sale->table_name }}</td>
                <td class="col-right"></td>
            </tr>
        @endif
    </table>

    <div class="dashed"></div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-item">{!! $L("ITEM") !!}</th>
                <th class="col-qty">{!! $L("QTY") !!}</th>
                <th class="col-total">{!! $L("TOTAL") !!}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items ?? [] as $item)
                @php
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['price'] ?? 0);
                    $lineTotal = $qty * $price;
                @endphp
                <tr>
                    <td class="col-item">
                        <div class="bold">{{ $item['name'] ?? 'Item' }}</div>
                        @if ($qty > 1)
                            <div style="font-size: {{ $subSize }}; color: #555555;">{{ $qty }} x {{ $company->formatMoney($price) }}</div>
                        @endif
                        @if (!empty($item['is_overridden']))
                            <div style="font-size: {{ $subSize }}; color: #555555;">Std: {{ $company->formatMoney((float) ($item['base_price'] ?? 0)) }}</div>
                        @endif
                    </td>
                    <td class="col-qty">{{ $qty }}</td>
                    <td class="col-total bold">{{ $company->formatMoney($lineTotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="dashed"></div>

    <!-- Financial Totals & Summary Block -->
    <table class="totals-table">
        @php
            $subtotal = $sale->subtotal;
            $paidAmt = (float) ($sale->paid_amount ?: $sale->total);
            $totalAmt = (float) $sale->total;
            $changeDue = max(0, $paidAmt - $totalAmt);
            $taxBreakdown = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
            $saleTax = (float)($sale->tax_amount ?? $sale->tax ?? 0);
            if ($saleTax <= 0 && !empty($taxBreakdown)) {
                $saleTax = array_sum(array_column($taxBreakdown, 'amount'));
            }
        @endphp
        <tr>
            <td class="col-label">{!! $L("Subtotal:") !!}</td>
            <td class="col-val">{{ $company->formatMoney($subtotal > 0 ? $subtotal : $sale->total) }}</td>
        </tr>
        @if ((float) $sale->discount > 0)
            <tr>
                <td class="col-label" style="color: #dc2626;">{!! $L("Discount:") !!}</td>
                <td class="col-val" style="color: #dc2626;">-{{ $company->formatMoney($sale->discount) }}</td>
            </tr>
        @endif

        {{-- Dynamic Tax Line Items / Sub-Components --}}
        @if ($saleTax > 0 || !empty($taxBreakdown))
            @if (!empty($taxBreakdown))
                @foreach ($taxBreakdown as $taxRow)
                    <tr>
                        <td class="col-label bold" style="color: #374151;">{{ $taxRow['name'] }} ({{ $taxRow['rate'] }}%):</td>
                        <td class="col-val bold" style="color: #111827;">+{{ $company->formatMoney($taxRow['amount']) }}</td>
                    </tr>
                    @foreach ($taxRow['sub_components'] as $component)
                        <tr>
                            <td class="col-label" style="color: #6b7280; padding-left: 8px;">&#9492; {{ $component['name'] }} ({{ $component['rate'] }}%):</td>
                            <td class="col-val" style="color: #4b5563;">{{ $company->formatMoney($component['amount']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @else
                <tr>
                    <td class="col-label" style="color: #4b5563;">{!! $sale->tax_name ? e($sale->tax_name) : $L('Tax') !!} ({{ (float)($sale->tax_rate ?? 0) }}%):</td>
                    <td class="col-val" style="color: #111827;">+{{ $company->formatMoney($saleTax) }}</td>
                </tr>
            @endif
        @endif
        <tr class="grand-total">
            <td class="col-label bold">{!! $L("TOTAL AMOUNT:") !!}</td>
            <td class="col-val bold">{{ $company->formatMoney($sale->total) }}</td>
        </tr>
        @if ($paidAmt > 0)
            <tr>
                <td class="col-label">{!! $L("Amount Paid:") !!}</td>
                <td class="col-val">{{ $company->formatMoney($paidAmt) }}</td>
            </tr>
        @endif
        @if ($changeDue > 0)
            <tr>
                <td class="col-label">{!! $L("Change Due:") !!}</td>
                <td class="col-val">{{ $company->formatMoney($changeDue) }}</td>
            </tr>
        @endif
        @if ((float) $sale->due_amount > 0)
            <tr>
                <td class="col-label bold" style="color: #990000;">{!! $L("Remaining Balance:") !!}</td>
                <td class="col-val bold" style="color: #990000;">{{ $company->formatMoney($sale->due_amount) }}</td>
            </tr>
        @endif
    </table>

    @if ($sale->payments && $sale->payments->isNotEmpty())
        <div class="dashed"></div>
        <table class="meta-table">
            @foreach ($sale->payments as $pmt)
                <tr>
                    <td class="col-left">{{ ucfirst($pmt->payment_method) }}{{ $pmt->reference_number ? ' (' . $pmt->reference_number . ')' : '' }}:</td>
                    <td class="col-right bold">{{ $company->formatMoney($pmt->amount) }}</td>
                </tr>
                @if ((float) $pmt->change_returned > 0)
                    <tr>
                        <td class="col-left" style="font-size: {{ $subSize }}; color: #555;">{!! $L("Change Returned:") !!}</td>
                        <td class="col-right" style="font-size: {{ $subSize }};">{{ $company->formatMoney($pmt->change_returned) }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @php
        $hasPixPdf = strtolower($sale->payment_method ?? '') === 'pix' || ($sale->payments && $sale->payments->contains(fn($p) => strtolower($p->payment_method) === 'pix'));
    @endphp
    @if ($hasPixPdf)
        <div class="dashed"></div>
        <!-- Thermal Receipt PIX Block -->
        <div class="text-center" style="text-align: center; margin: 4px 0;">
            <div class="bold" style="font-size: {{ $subSize }}; text-transform: uppercase;">{!! $L('Payment via PIX') !!}</div>
            <div style="font-size: {{ $fontSize }}; font-weight: bold;">{{ tenant_setting('pix_holder_name', $company->trade_name ?: $company->name) }}</div>
            <div style="font-size: {{ $subSize }}; color: #666666;">{{ tenant_setting('pix_city', $company->city ?: 'Springfield') }}</div>
        </div>
    @endif

    @if (!empty($sale->notes))
        <div class="dashed"></div>
        <div style="font-size: {{ $subSize }}; text-align: left; padding: 2px 0; word-break: break-word;">
            <strong>{!! $L("Note:") !!}</strong> {!! nl2br(e(strip_tags($sale->notes))) !== $sale->notes ? $sale->notes : nl2br(e($sale->notes)) !!}
        </div>
    @endif

    @php
        $termsText = $sale->terms ?? ($company->invoice_terms ?? null);
    @endphp
    @if (!empty($termsText))
        <div class="dashed"></div>
        <div style="font-size: {{ $subSize }}; text-align: left; padding: 2px 0; word-break: break-word;">
            <strong>{!! $L("Terms:") !!}</strong>
            <div style="margin-top: 1px;">
                {!! clean_html($termsText) !!}
            </div>
        </div>
    @endif

    <div class="dashed"></div>

    <!-- Centered Footer Block with QR Code -->
    <div class="footer-block">
        @if (!empty($qrCodeDataUri))
            <div class="qr-wrapper">
                <img src="{{ $qrCodeDataUri }}" class="qr-image" alt="QR Code">
                <div class="qr-caption">{!! $L("Scan for digital e-receipt & verify") !!}</div>
            </div>
        @elseif (!empty($qrCodeSvg))
            <div class="qr-wrapper">
                <div style="width: {{ $qrDimension }}; height: {{ $qrDimension }}; margin: 0 auto;">
                    {!! $qrCodeSvg !!}
                </div>
                <div class="qr-caption">{!! $L("Scan for digital e-receipt & verify") !!}</div>
            </div>
        @endif

        <div class="ref-code">#{{ $sale->sale_number }}</div>

        <div class="footer-text">
            <div>{!! $L("Thank you for your visit & business!") !!}</div>
            <div>{{ $company->website ?: (config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'saas.zoomnearby.com') }}</div>
            @php
                $platformBranding = \App\Models\PlatformBranding::current();
                $platformName = $platformBranding?->platform_name ?? config('app.name');
                $platformDomain = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'saas.zoomnearby.com';
            @endphp
            @if (setting('show_powered_by', true))
            <div style="font-size: {{ $subSize }}; color: #555555; margin-top: 3px; border-top: 1px dashed #dddddd; padding-top: 2px;">
                {!! $L("Powered by") !!} {{ $platformName }} &bull; {!! $L("Issued via") !!} {{ $platformDomain }}
            </div>
            @endif
        </div>
    </div>

</body>
</html>
