<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation #{{ $sale->sale_number }} — {{ $company->name }}</title>
    @php
        $accentColor = $company->primary_color ?: '#2d7a58';
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
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
                    <div class="brand-subtitle">Quotation & Price Proposal</div>
                    <div class="brand-address">
                        {{ $company->address ?? 'Business Address' }}<br>
                        @if ($company->city || $company->state)
                            {{ $company->city }}{{ $company->state ? ', ' . $company->state : '' }} {{ $company->postal_code }}<br>
                        @endif
                        @if ($company->tax_id)
                            <span>{{ $company->tax_id_label ?: 'Tax ID' }}: {{ $company->tax_id }}</span><br>
                        @endif
                        @if ($company->phone) Tel: {{ $company->phone }} @endif
                        @if ($company->email) | {{ $company->email }} @endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Quote Number:</td>
                            <td class="meta-val"><span class="doc-badge">{{ $sale->sale_number }}</span></td>
                        </tr>
                        <tr>
                            <td class="meta-label">Date Issued:</td>
                            <td class="meta-val">{{ $sale->created_at ? $sale->created_at->format('d M Y') : now()->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Valid Until:</td>
                            <td class="meta-val">{{ $sale->due_date ? $sale->due_date->format('d M Y') : ($sale->created_at ? $sale->created_at->addDays(15)->format('d M Y') : now()->addDays(15)->format('d M Y')) }}</td>
                        </tr>
                        @if ($sale->user)
                            <tr>
                                <td class="meta-label">Sales Rep:</td>
                                <td class="meta-val">{{ $sale->user->name }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <!-- Grouped Recipient / Customer Contact Info Box -->
        <div class="client-box">
            <div class="client-title">PROPOSAL PREPARED FOR:</div>
            <div style="font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 3px;">
                {{ $sale->customer_name ?: 'Valued Client' }}
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
                    <th style="width: 50%;">Item & Scope Description</th>
                    <th class="text-center" style="width: 12%;">Qty</th>
                    <th class="text-right" style="width: 18%;">Unit Price</th>
                    <th class="text-right" style="width: 20%;">Total Amount</th>
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
                        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 15px;">No items included in this quotation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Summary Totals -->
        <table class="summary-table">
            <tr>
                <td style="color: #64748b; font-weight: bold;">Subtotal:</td>
                <td style="text-align: right; font-weight: bold;">{{ $company->formatMoney($subtotal) }}</td>
            </tr>
            @if ($sale->discount > 0)
                <tr>
                    <td style="color: #dc2626; font-weight: bold;">Discount:</td>
                    <td style="text-align: right; color: #dc2626; font-weight: bold;">-{{ $company->formatMoney($sale->discount) }}</td>
                </tr>
            @endif
            @php
                $taxAmount = ($sale->total - $subtotal + $sale->discount) > 0 ? ($sale->total - $subtotal + $sale->discount) : 0;
            @endphp
            @if ($taxAmount > 0)
                <tr>
                    <td style="color: #64748b; font-weight: bold;">Estimated Tax / VAT:</td>
                    <td style="text-align: right; font-weight: bold;">+{{ $company->formatMoney($taxAmount) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Total Estimate:</td>
                <td style="text-align: right;">{{ $company->formatMoney($sale->total) }}</td>
            </tr>
        </table>

        <!-- Document Notes & Remarks -->
        @if (!empty($sale->notes))
            <div class="notes-section">
                <div class="card-heading">Notes & Proposal Remarks</div>
                <div class="card-content">{!! $sale->notes !!}</div>
            </div>
        @endif

        <!-- Bottom Cards: Payment Instructions & Terms -->
        <table class="cards-table">
            <tr>
                <td>
                    <div class="card-heading">Payable To & Banking</div>
                    <div class="card-content">
                        <strong>{{ $company->trade_name ?? $company->name }}</strong><br>
                        {!! $company->bank_details ?? ($company->name . '<br>Phone: ' . ($company->phone ?? '+123-456-7890')) !!}
                    </div>
                </td>
                <td>
                    <div class="card-heading">Terms & Conditions</div>
                    <div class="card-content">
                        @if (!empty($company->quote_terms))
                            {!! $company->quote_terms !!}
                        @else
                            <ul class="terms-list">
                                <li>All rates quoted are valid for 15 days from date of issuance.</li>
                                <li>40% payment deposit required upon acceptance to initiate work.</li>
                                <li>Balance due within 20 days upon delivery/completion.</li>
                            </ul>
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
                        Client Acceptance & Date
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer-bar">
            Thank you for considering {{ $company->name }}!
            @if ($company->phone) &bull; Tel: {{ $company->phone }} @endif
            @if ($company->email) &bull; Email: {{ $company->email }} @endif
            @if ($company->website) &bull; {{ $company->website }} @endif
        </div>
    </div>
</body>
</html>
