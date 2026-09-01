<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Z-Report #{{ $register->id }} — {{ $company->name }}</title>
    @php
        $is58mm = $is58mm ?? false;
        $fontSize = $is58mm ? '9px' : '11px';
        $titleSize = $is58mm ? '13px' : '15px';
        $subSize = $is58mm ? '8px' : '9.5px';
        $totalSize = $is58mm ? '12px' : '13.5px';
        $qrDimension = $is58mm ? '65px' : '85px';
        $platformName = $branding?->platform_name ?? config('app.name');
        $platformUrl = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'saas.zoomnearby.com';
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page {
            size: {{ $paperWidth ?? ($is58mm ? '58mm' : '80mm') }} auto;
            margin: {{ $is58mm ? '0mm 2.5mm' : '0mm 3.5mm' }};
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
            font-size: {{ $fontSize }};
            line-height: 1.2;
            color: #000000;
            background: #ffffff;
            width: 100%;
            max-width: {{ $is58mm ? '52mm' : '72mm' }};
            margin: 0 auto;
            padding: 2mm 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 4px;
        }
        .logo {
            max-width: {{ $is58mm ? '90px' : '120px' }};
            max-height: {{ $is58mm ? '30px' : '40px' }};
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
            line-height: 1.25;
        }
        .badge {
            display: inline-block;
            font-size: {{ $fontSize }};
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 4px;
            border: 1px solid #000000;
            margin: 3px 0;
        }
        
        .dashed {
            border: none;
            border-top: 1px dashed #222222;
            margin: 3px 0;
            width: 100%;
        }
        .solid {
            border: none;
            border-top: 1px solid #000000;
            margin: 3px 0;
            width: 100%;
        }
        
        .meta-table, .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .meta-table td {
            font-size: {{ $subSize }};
            padding: 1px 0;
            vertical-align: top;
        }
        .report-table td {
            font-size: {{ $fontSize }};
            padding: 1.5px 0;
            vertical-align: top;
        }
        .col-left {
            width: 58%;
            text-align: left;
        }
        .col-right {
            width: 42%;
            text-align: right;
        }
        
        .highlight-row td {
            font-size: {{ $totalSize }};
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 2px 0;
        }
        
        .section-header {
            font-size: {{ $subSize }};
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 4px 0 2px 0;
            text-align: left;
        }

        .signatures {
            margin-top: 12px;
            width: 100%;
        }
        .sig-box {
            width: 48%;
            display: inline-block;
            text-align: center;
            font-size: {{ $subSize }};
            border-top: 1px solid #444444;
            padding-top: 3px;
        }

        .footer-block {
            text-align: center;
            margin-top: 8px;
            padding-top: 4px;
        }
        .footer-attribution {
            font-size: {{ $subSize }};
            color: #444444;
            line-height: 1.3;
            margin-top: 3px;
        }
        .qr-wrapper {
            margin: 4px auto;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- 1. Store Header & Logo (Primary Branding) -->
    <div class="receipt-header">
        @if (!empty($logoBase64))
            <img src="{{ $logoBase64 }}" class="logo" alt="{{ $company->name }}">
        @endif
        <div class="store-title">{{ $company->trade_name ?? $company->name }}</div>
        <div class="store-meta">
            @if ($company->address)
                <div>{{ $company->address }}</div>
            @endif
            <div>
                @if ($company->phone) Tel: {{ $company->phone }} @endif
                @if ($company->tax_number) &bull; GST/Tax: {{ $company->tax_number }} @endif
            </div>
        </div>
        
        <div class="badge">
            {{ $register->isOpen() ? 'X-REPORT (LIVE SHIFT)' : 'Z-REPORT & SETTLEMENT' }}
        </div>
    </div>

    <div class="dashed"></div>

    <!-- 2. Shift Metadata -->
    <table class="meta-table">
        <tr>
            <td class="col-left"><strong>Shift Session:</strong> #{{ $register->id }}</td>
            <td class="col-right"><strong>Terminal:</strong> {{ $register->terminal_id ?? 'Main POS' }}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>Opened:</strong> {{ $register->opened_at ? $register->opened_at->format('d/m/Y H:i') : '—' }}</td>
            <td class="col-right"><strong>By:</strong> {{ $metrics['opened_by_name'] }}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>Closed:</strong> {{ $register->closed_at ? $register->closed_at->format('d/m/Y H:i') : 'In Progress' }}</td>
            <td class="col-right"><strong>By:</strong> {{ $metrics['closed_by_name'] }}</td>
        </tr>
    </table>

    <div class="dashed"></div>

    <!-- 3. Financial Summary -->
    <div class="section-header">Financial Summary</div>
    <table class="report-table">
        <tr>
            <td class="col-left">Opening Float:</td>
            <td class="col-right bold">{{ $company->formatMoney($metrics['opening_balance']) }}</td>
        </tr>
        <tr>
            <td class="col-left">Total Gross Sales ({{ $metrics['sale_count'] }}):</td>
            <td class="col-right bold">{{ $company->formatMoney($metrics['total_sales']) }}</td>
        </tr>
        <tr>
            <td class="col-left">&nbsp;&nbsp;• Cash Sales:</td>
            <td class="col-right">{{ $company->formatMoney($metrics['cash_sales']) }}</td>
        </tr>
        <tr>
            <td class="col-left">&nbsp;&nbsp;• Non-Cash Sales:</td>
            <td class="col-right">{{ $company->formatMoney($metrics['non_cash_sales']) }}</td>
        </tr>
        <tr>
            <td class="col-left">Cash In (Suprimento):</td>
            <td class="col-right">+{{ $company->formatMoney($metrics['cash_in']) }}</td>
        </tr>
        <tr>
            <td class="col-left">Cash Out (Sangria):</td>
            <td class="col-right">-{{ $company->formatMoney($metrics['cash_out']) }}</td>
        </tr>
        <tr class="highlight-row">
            <td class="col-left">EXPECTED CASH:</td>
            <td class="col-right">{{ $company->formatMoney($metrics['expected_cash']) }}</td>
        </tr>
        <tr>
            <td class="col-left">Physical Counted:</td>
            <td class="col-right bold">{{ $company->formatMoney($metrics['counted_cash']) }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td class="col-left">Variance (Over/Short):</td>
            <td class="col-right">
                {{ ($metrics['variance'] >= 0 ? '+' : '') . $company->formatMoney($metrics['variance']) }}
                ({{ $metrics['variance'] == 0 ? 'BALANCED' : ($metrics['variance'] < 0 ? 'SHORT' : 'OVER') }})
            </td>
        </tr>
    </table>

    <div class="dashed"></div>

    <!-- 4. Sales by Payment Method -->
    <div class="section-header">Sales by Payment Method</div>
    <table class="report-table">
        @forelse ($metrics['payments_by_method'] as $method => $data)
            <tr>
                <td class="col-left">{{ ucfirst($method) }} ({{ $data['count'] }}):</td>
                <td class="col-right bold">{{ $company->formatMoney($data['total']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="text-center" style="color: #666; font-size: {{ $subSize }};">No sales recorded</td>
            </tr>
        @endforelse
    </table>

    <!-- 5. Cash Drawer Movement Transactions -->
    @if ($register->transactions && $register->transactions->isNotEmpty())
        <div class="dashed"></div>
        <div class="section-header">Drawer Movements</div>
        <table class="report-table">
            @foreach ($register->transactions as $tx)
                <tr>
                    <td class="col-left" style="font-size: {{ $subSize }};">
                        {{ $tx->getCategoryLabel() }}
                        @if ($tx->reason)
                            <br><span style="color: #555;">{{ $tx->reason }}</span>
                        @endif
                    </td>
                    <td class="col-right bold" style="font-size: {{ $subSize }};">
                        {{ $tx->type === 'cash_in' ? '+' : '-' }}{{ $company->formatMoney($tx->amount) }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (!empty($metrics['notes']))
        <div class="dashed"></div>
        <div style="font-size: {{ $subSize }}; text-align: left; padding: 2px 0;">
            <strong>Remarks:</strong> {{ $metrics['notes'] }}
        </div>
    @endif

    <!-- 6. Signatures -->
    <div class="signatures">
        <table style="width: 100%;">
            <tr>
                <td style="width: 48%; text-align: center; vertical-align: bottom;">
                    <div style="border-top: 1px solid #333; padding-top: 3px; font-size: {{ $subSize }};">
                        Cashier Signature
                    </div>
                </td>
                <td style="width: 4%;"></td>
                <td style="width: 48%; text-align: center; vertical-align: bottom;">
                    <div style="border-top: 1px solid #333; padding-top: 3px; font-size: {{ $subSize }};">
                        Manager Signature
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="solid"></div>

    <!-- 7. Footer: Dual Branding Attribution & QR Verification -->
    <div class="footer-block">
        @if (!empty($qrCodeSvg))
            <div class="qr-wrapper">
                <div style="width: {{ $qrDimension }}; height: {{ $qrDimension }}; margin: 0 auto;">
                    {!! $qrCodeSvg !!}
                </div>
                <div style="font-size: {{ $subSize }}; color: #555; margin-top: 2px;">Scan to verify shift audit</div>
            </div>
        @endif

        <div class="footer-attribution">
            <div>{{ $company->trade_name ?? $company->name }} &bull; Internal Shift Audit Record</div>
            @if (setting('show_powered_by', true))
            <div style="margin-top: 2px; font-weight: bold;">
                Powered by {{ $platformName }}
            </div>
            @endif
            <div>Issued via {{ $platformUrl }}</div>
        </div>
    </div>

</body>
</html>
