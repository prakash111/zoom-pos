<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Voucher #{{ $tx->voucher_number ?? $tx->id }} — {{ $company->name }}</title>
    @php
        $is58mm = $is58mm ?? false;
        $fontSize = $is58mm ? '9.5px' : '11px';
        $titleSize = $is58mm ? '13px' : '15px';
        $subSize = $is58mm ? '8px' : '9.5px';
        $amountSize = $is58mm ? '14px' : '17px';
        $qrDimension = $is58mm ? '65px' : '80px';
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
            line-height: 1.25;
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
            padding: 2.5px 5px;
            border: 1.5px solid #000000;
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
        
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .meta-table td {
            font-size: {{ $subSize }};
            padding: 1.5px 0;
            vertical-align: top;
        }
        .col-left {
            width: 55%;
            text-align: left;
        }
        .col-right {
            width: 45%;
            text-align: right;
        }

        .amount-box {
            text-align: center;
            padding: 5px 0;
            margin: 4px 0;
            border: 1.5px solid #000000;
            background: #f9f9f9;
        }
        .amount-val {
            font-size: {{ $amountSize }};
            font-weight: bold;
            letter-spacing: -0.5px;
        }

        .signatures {
            margin-top: 14px;
            width: 100%;
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
            {{ $tx->getCategoryLabel() }}
        </div>
    </div>

    <div class="dashed"></div>

    <!-- 2. Transaction & Session Metadata -->
    <table class="meta-table">
        <tr>
            <td class="col-left"><strong>Voucher #:</strong> {{ $tx->voucher_number ?? "TX-{$tx->id}" }}</td>
            <td class="col-right"><strong>Session:</strong> #{{ $tx->cash_register_id }}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>Date:</strong> {{ $tx->created_at ? $tx->created_at->format('d/m/Y') : now()->format('d/m/Y') }}</td>
            <td class="col-right"><strong>Time:</strong> {{ $tx->created_at ? $tx->created_at->format('H:i:s') : now()->format('H:i:s') }}</td>
        </tr>
        <tr>
            <td class="col-left"><strong>Cashier:</strong> {{ $tx->creator?->name ?? 'Staff' }}</td>
            <td class="col-right"><strong>Terminal:</strong> {{ $register?->terminal_id ?? 'POS' }}</td>
        </tr>
    </table>

    <div class="dashed"></div>

    <!-- 3. Transaction Amount Highlight -->
    <div class="amount-box">
        <div style="font-size: {{ $subSize }}; text-transform: uppercase; font-weight: bold; color: #444;">
            {{ $tx->type === 'cash_in' ? 'CASH INFLOW' : 'CASH OUTFLOW' }}
        </div>
        <div class="amount-val">
            {{ $tx->type === 'cash_in' ? '+' : '-' }}{{ $company->formatMoney($tx->amount) }}
        </div>
    </div>

    <!-- 4. Drawer Balances & Reason -->
    <table class="meta-table" style="margin-top: 4px;">
        @if ($tx->balance_before !== null)
            <tr>
                <td class="col-left">Previous Drawer Cash:</td>
                <td class="col-right bold">{{ $company->formatMoney($tx->balance_before) }}</td>
            </tr>
        @endif
        @if ($tx->balance_after !== null)
            <tr>
                <td class="col-left"><strong>New Drawer Cash:</strong></td>
                <td class="col-right bold"><strong>{{ $company->formatMoney($tx->balance_after) }}</strong></td>
            </tr>
        @endif
    </table>

    @if (!empty($tx->reason))
        <div class="dashed"></div>
        <div style="font-size: {{ $subSize }}; text-align: left; padding: 2px 0;">
            <strong>Reason / Purpose:</strong> {{ $tx->reason }}
        </div>
    @endif

    <div class="dashed"></div>

    <!-- 5. Signatures -->
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

    <!-- 6. Footer: Dual Branding Attribution & QR Verification -->
    <div class="footer-block">
        @if (!empty($qrCodeSvg))
            <div class="qr-wrapper">
                <div style="width: {{ $qrDimension }}; height: {{ $qrDimension }}; margin: 0 auto;">
                    {!! $qrCodeSvg !!}
                </div>
                <div style="font-size: {{ $subSize }}; color: #555; margin-top: 2px;">Scan to verify drawer movement</div>
            </div>
        @endif

        <div class="footer-attribution">
            <div>{{ $company->trade_name ?? $company->name }} &bull; Internal Cash Ledger Voucher</div>
            <div style="margin-top: 2px; font-weight: bold;">
                Powered by {{ $platformName }}
            </div>
            <div>Issued via {{ $platformUrl }}</div>
        </div>
    </div>

</body>
</html>
