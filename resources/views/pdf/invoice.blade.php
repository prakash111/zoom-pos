<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice #{{ $sale->sale_number }} — {{ $company->name }}</title>
    @php
        $accentColor = $company->primary_color ?: '#2563eb';
        $hex = ltrim($accentColor, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lr = (int) ($r * 0.08 + 255 * 0.92);
        $lg = (int) ($g * 0.08 + 255 * 0.92);
        $lb = (int) ($b * 0.08 + 255 * 0.92);
        $lightBg = sprintf('#%02x%02x%02x', $lr, $lg, $lb);
        $br = (int) ($r * 0.25 + 255 * 0.75);
        $bg = (int) ($g * 0.25 + 255 * 0.75);
        $bb = (int) ($b * 0.25 + 255 * 0.75);
        $borderTint = sprintf('#%02x%02x%02x', $br, $bg, $bb);

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
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
            margin: 12mm 14mm;
            size: A4 portrait;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.4;
            padding: 10px;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            color: {{ $accentColor }};
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }
        .brand-subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .brand-address {
            font-size: 10px;
            color: #475569;
            line-height: 1.35;
        }
        .meta-table {
            width: 100%;
            text-align: right;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 0;
            font-size: 11px;
        }
        .meta-label {
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding-right: 8px;
        }
        .meta-val {
            font-weight: bold;
            color: #0f172a;
        }
        .doc-badge {
            background-color: {{ $accentColor }};
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 4px;
        }
        .client-box {
            width: 100%;
            background-color: {{ $lightBg }};
            border: 1px solid {{ $borderTint }};
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .client-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: {{ $accentColor }};
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 18px;
        }
        .items-table th {
            background-color: {{ $accentColor }};
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table th.text-center { text-align: center; }
        .items-table th.text-right { text-align: right; }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid {{ $borderTint }};
            font-size: 11px;
        }
        .items-table tr.even {
            background-color: {{ $lightBg }};
        }
        .avoid-break {
            page-break-inside: avoid;
        }
        .summary-table {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .summary-table td {
            padding: 4px 6px;
            font-size: 11px;
        }
        .summary-table .total-row td {
            border-top: 2px solid {{ $accentColor }};
            font-size: 14px;
            font-weight: bold;
            color: {{ $accentColor }};
            padding-top: 8px;
        }
        .cards-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .cards-table td {
            vertical-align: top;
            width: 50%;
            background-color: {{ $lightBg }};
            border: 1px solid {{ $borderTint }};
            border-radius: 8px;
            padding: 12px 14px;
        }
        .card-heading {
            font-size: 10.5px;
            font-weight: bold;
            color: {{ $accentColor }};
            text-transform: uppercase;
            margin-bottom: 6px;
            border-bottom: 1px solid {{ $borderTint }};
            padding-bottom: 3px;
        }
        .card-content {
            font-size: 10px;
            color: #334155;
            line-height: 1.5;
        }
        .terms-list {
            margin: 0;
            padding-left: 14px;
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
        }
        .notes-section {
            background-color: {{ $lightBg }};
            border: 1px solid {{ $borderTint }};
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 14px;
            font-size: 10px;
            color: #334155;
            line-height: 1.5;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            margin-top: 26px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 1px dashed {{ $borderTint }};
            margin-top: 36px;
            padding-top: 4px;
            text-align: center;
            font-size: 9.5px;
            color: #64748b;
            font-weight: bold;
        }
        .footer-bar {
            margin-top: 22px;
            background-color: {{ $accentColor }};
            color: #ffffff;
            border-radius: 16px;
            padding: 6px 14px;
            text-align: center;
            font-size: 9.5px;
            font-weight: bold;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 55%;">
                    @if (!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" style="max-height: 48px; max-width: 180px; margin-bottom: 6px; display: block;">
                    @endif
                    <div class="brand-title">{{ $company->trade_name ?? $company->name }}</div>
                    <div class="brand-subtitle">Official Tax Invoice & Receipt</div>
                    <div class="brand-address">
                        {{ $company->address ?? 'Business Address' }}<br>
                        @if ($company->city || $company->state)
                            {{ $company->city }}{{ $company->state ? ', ' . $company->state : '' }} {{ $company->postal_code }}<br>
                        @endif
                        @if ($company->tax_id)
                            @php
                                $headerTaxRows = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
                                $appliedTaxRuleName = $headerTaxRows[0]['name'] ?? $sale->tax_name;
                            @endphp
                            <span>{{ \App\Services\TaxEngineService::getTaxIdentifierLabel($company->country, $appliedTaxRuleName) }}: {{ $company->tax_id }}</span><br>
                        @endif
                        @if ($company->phone) Tel: {{ $company->phone }} @endif
                        @if ($company->email) | {{ $company->email }} @endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Invoice Number:</td>
                            <td class="meta-val"><span class="doc-badge">{{ $sale->sale_number }}</span></td>
                        </tr>
                        <tr>
                            <td class="meta-label">Date:</td>
                            <td class="meta-val">{{ $sale->created_at ? $sale->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Payment Status:</td>
                            <td class="meta-val"><strong style="color: #16a34a; text-transform: uppercase;">{{ $sale->payment_status ?: 'PAID' }}</strong></td>
                        </tr>
                        @if ($sale->user)
                            <tr>
                                <td class="meta-label">Cashier / Staff:</td>
                                <td class="meta-val">{{ $sale->user->name }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <!-- Grouped Customer Details Box -->
        <div class="client-box">
            <div class="client-title">BILLED TO:</div>
            <div style="font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 3px;">
                {{ $sale->customer_name ?: 'Walk-in Customer' }}
            </div>
            @if ($sale->customer?->phone)
                <div style="color: #334155; margin-top: 1px;"><strong>Phone:</strong> {{ $sale->customer->phone }}</div>
            @endif
            @if ($sale->customer?->email)
                <div style="color: #334155; margin-top: 1px;"><strong>Email:</strong> {{ $sale->customer->email }}</div>
            @endif
            @if ($sale->customer?->address || $sale->customer?->city)
                <div style="color: #334155; margin-top: 1px;"><strong>Address:</strong> {{ $sale->customer->address ? $sale->customer->address . ', ' : '' }}{{ $sale->customer->city }}{{ $sale->customer->state ? ' ' . $sale->customer->state : '' }} {{ $sale->customer->postal_code ?? '' }}</div>
            @endif
            @if ($sale->customer?->document)
                <div style="color: #334155; margin-top: 1px;"><strong>{{ $sale->customer->tax_id_label ?: 'Tax ID / VAT' }}:</strong> {{ $sale->customer->document }}</div>
            @endif
        </div>

        <!-- Line Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">{!! $L("Item & Description") !!}</th>
                    <th class="text-center" style="width: 12%;">{!! $L("Qty") !!}</th>
                    <th class="text-right" style="width: 18%;">{!! $L("Unit Price") !!}</th>
                    <th class="text-right" style="width: 20%;">{!! $L("Total") !!}</th>
                </tr>
            </thead>
            <tbody>
                @php $subtotal = 0; @endphp
                @forelse ($sale->items ?? [] as $index => $item)
                    @php
                        $qty = (float)($item['quantity'] ?? 1);
                        $price = (float)($item['price'] ?? 0);
                        $lineTotal = $qty * $price;
                        $subtotal += $lineTotal;
                    @endphp
                    <tr class="{{ $index % 2 === 1 ? 'even' : '' }} avoid-break">
                        <td>
                            <strong>{{ $item['name'] ?? 'Item' }}</strong>
                            @if (!empty($item['description']))
                                <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $qty }}</td>
                        <td class="text-right">{{ $company->formatMoney($price) }}</td>
                        <td class="text-right"><strong>{{ $company->formatMoney($lineTotal) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 15px;">No items recorded on this invoice.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Structured Fiscal Tax Summary Breakdown -->
        @php
            $subtotal = $sale->subtotal;
            $taxSummary = \App\Services\TaxEngineService::normalizeTaxBreakdown($sale->tax_breakdown);
        @endphp
        @if (!empty($taxSummary))
            <table class="items-table" style="margin-top: 14px; page-break-inside: avoid;">
                <thead>
                    <tr>
                        <th style="font-size: 8.5px;">{!! $L("Tax Category / Rule") !!}</th>
                        <th class="text-right" style="font-size: 8.5px;">{!! $L("Rate (%)") !!}</th>
                        <th class="text-right" style="font-size: 8.5px;">{!! $L("Taxable Amount") !!}</th>
                        <th class="text-right" style="font-size: 8.5px;">{!! $L("Tax Amount") !!}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($taxSummary as $tRow)
                        <tr>
                            <td><strong>{{ $tRow['name'] }}</strong></td>
                            <td class="text-right">{{ $tRow['rate'] }}%</td>
                            <td class="text-right">{{ $company->formatMoney($tRow['taxable']) }}</td>
                            <td class="text-right"><strong>{{ $company->formatMoney($tRow['amount']) }}</strong></td>
                        </tr>
                        @foreach ($tRow['sub_components'] as $component)
                            <tr style="color: #64748b; font-size: 9px;">
                                <td style="padding-left: 18px;">&#9492; {{ $component['name'] }}</td>
                                <td class="text-right">{{ $component['rate'] }}%</td>
                                <td class="text-right">&mdash;</td>
                                <td class="text-right">{{ $company->formatMoney($component['amount']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif

        <!-- Summary Totals -->
        <table class="summary-table">
            <tr>
                <td style="color: #64748b; font-weight: bold;">{!! $L("Subtotal:") !!}</td>
                <td style="text-align: right; font-weight: bold;">{{ $company->formatMoney($subtotal) }}</td>
            </tr>
            @if ($sale->discount > 0)
                <tr>
                    <td style="color: #dc2626; font-weight: bold;">{!! $L("Discount:") !!}</td>
                    <td style="text-align: right; color: #dc2626; font-weight: bold;">-{{ $company->formatMoney($sale->discount) }}</td>
                </tr>
            @endif
            @php
                $flattenedTaxes = $sale->flattened_tax_components;
                $taxAmount = (float)($sale->tax_amount ?? (($sale->total - $subtotal + $sale->discount) > 0 ? ($sale->total - $subtotal + $sale->discount) : 0));
                if ($taxAmount <= 0 && !empty($flattenedTaxes)) {
                    $taxAmount = array_sum(array_column($flattenedTaxes, 'amount'));
                }
            @endphp
            @if ($taxAmount > 0 || !empty($flattenedTaxes))
                @if (!empty($flattenedTaxes))
                    @foreach ($flattenedTaxes as $tComp)
                        <tr>
                            <td style="color: #475569; font-weight: 500;">{{ $tComp['name'] }} ({{ $tComp['rate'] }}%):</td>
                            <td style="text-align: right; font-weight: 600;">+{{ $company->formatMoney($tComp['amount']) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td style="color: #475569; font-weight: 500;">{!! $sale->tax_name ? e($sale->tax_name) : $L('Tax / GST') !!} ({{ (float)($sale->tax_rate ?? 0) }}%):</td>
                        <td style="text-align: right; font-weight: 600;">+{{ $company->formatMoney($taxAmount) }}</td>
                    </tr>
                @endif
            @endif
            <tr class="total-row">
                <td>{!! $L("Grand Total:") !!}</td>
                <td style="text-align: right;">{{ $company->formatMoney($sale->total) }}</td>
            </tr>
            <tr>
                <td style="color: #16a34a; font-weight: bold; padding-top: 4px;">{!! $L("Paid Amount:") !!}</td>
                <td style="text-align: right; color: #16a34a; font-weight: bold; padding-top: 4px;">{{ $company->formatMoney($sale->paid_amount ?: $sale->total) }}</td>
            </tr>
            @if ($sale->due_amount > 0)
                <tr>
                    <td style="color: #dc2626; font-weight: bold;">{!! $L("Balance Due:") !!}</td>
                    <td style="text-align: right; color: #dc2626; font-weight: bold;">{{ $company->formatMoney($sale->due_amount) }}</td>
                </tr>
            @endif
        </table>

        <!-- Document Notes & Remarks -->
        @if (!empty($sale->notes))
            <div class="notes-section">
                <div class="card-heading">{!! $L("Notes & Remarks") !!}</div>
                <div class="card-content">{!! clean_html($sale->notes) !!}</div>
            </div>
        @endif

        <!-- Bottom Cards: Payment Info & Terms -->
        <table class="cards-table">
            <tr>
                <td>
                    <div class="card-heading">{!! $L("Payment Information") !!}</div>
                    <div class="card-content">
                        <strong>{!! $L("Method:") !!}</strong> {{ ucfirst($sale->payment_method ?: 'Cash') }}<br>
                        <strong>{!! $L("Status:") !!}</strong> {{ ucfirst($sale->payment_status ?: 'Paid') }}<br>
                        @if (!empty($company->bank_details))
                            <div style="margin-top: 4px;">{!! clean_html($company->bank_details) !!}</div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="card-heading">{!! $L("Invoice Terms & Policy") !!}</div>
                    <div class="card-content">
                        @if (!empty($company->invoice_terms))
                            {!! clean_html($company->invoice_terms) !!}
                        @else
                            <p>{!! $L("Thank you for your business! All sales are final unless otherwise specified in your service contract.") !!}</p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Signatures Area -->
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line">
                        Authorized Signature ({{ $company->name }})
                    </div>
                </td>
                <td>
                    <div class="signature-line">
                        Customer Signature & Receipt Ack.
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer-bar">
            Thank you for your business with {{ $company->name }}!
            @if ($company->phone) &bull; Tel: {{ $company->phone }} @endif
            @if ($company->email) &bull; Email: {{ $company->email }} @endif
            @if ($company->website) &bull; {{ $company->website }} @endif
            @php
                $platformBranding = \App\Models\PlatformBranding::current();
                $platformName = $platformBranding?->platform_name ?? config('app.name');
                $platformDomain = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'saas.zoomnearby.com';
            @endphp
            @if (setting('show_powered_by', true))
            <div style="margin-top: 4px; font-size: 9px; color: #94a3b8;">
                Powered by {{ $platformName }} &bull; Issued via {{ $platformDomain }}
            </div>
            @endif
        </div>
    </div>
</body>
</html>
