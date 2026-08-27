<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voucher #{{ $tx->voucher_number ?? $tx->id }} — {{ $company->name }}</title>
    @if ($company->getFaviconUrl())
        <link rel="icon" href="{{ $company->getFaviconUrl() }}">
    @endif
    @php
        $requestedFormat = request('format', $company->getReceiptFormat());
        $is58mm = ($requestedFormat === '58mm');
        $paperWidth = $is58mm ? '58mm' : '80mm';
        $containerWidth = $is58mm ? '280px' : '380px';
        $fontSize = $is58mm ? '11px' : '12.5px';
        $subSize = $is58mm ? '10px' : '11px';
        $platformName = $branding?->platform_name ?? config('app.name');
        $platformUrl = config('app.url') ? parse_url(config('app.url'), PHP_URL_HOST) : 'saas.zoomnearby.com';
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #64748b;
            color: #0f172a;
            padding: 24px 12px;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .no-print-bar {
            max-width: {{ $is58mm ? '360px' : '440px' }};
            width: 100%;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 14px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
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
        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-whatsapp { background-color: #16a34a; color: #ffffff; }
        .btn-whatsapp:hover { background-color: #15803d; }
        .btn-secondary { background-color: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background-color: #e2e8f0; }

        .receipt-card {
            width: {{ $containerWidth }};
            background: #ffffff;
            padding: 16px 14px;
            border-radius: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            font-size: {{ $fontSize }};
            line-height: 1.25;
            color: #000000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: 700; }

        .receipt-logo {
            max-width: {{ $is58mm ? '100px' : '130px' }};
            max-height: 42px;
            margin: 0 auto 6px auto;
            display: block;
            object-contain: contain;
        }
        .store-name {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .store-meta {
            font-size: {{ $subSize }};
            color: #334155;
            line-height: 1.3;
        }
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 6px;
            border: 1.5px solid #000000;
            margin: 6px 0;
        }
        .dashed {
            border: none;
            border-top: 1px dashed #475569;
            margin: 6px 0;
            width: 100%;
        }
        .solid {
            border: none;
            border-top: 1.5px solid #000000;
            margin: 6px 0;
            width: 100%;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            font-size: {{ $fontSize }};
            padding: 2px 0;
            vertical-align: top;
        }
        .data-table td.col-right {
            text-align: right;
        }

        .amount-box {
            text-align: center;
            padding: 8px 4px;
            margin: 6px 0;
            border: 1.5px solid #000000;
            background: #f8fafc;
        }
        .amount-val {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-top: 2px;
        }

        .signatures {
            margin-top: 16px;
            width: 100%;
        }
        .footer-text {
            font-size: {{ $subSize }};
            color: #475569;
            text-align: center;
            margin-top: 10px;
            line-height: 1.35;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print-bar { display: none !important; }
            .receipt-card {
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
            }
            @page {
                size: {{ $paperWidth }} auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Navigation Bar -->
    <div class="no-print-bar">
        <button type="button" onclick="window.print()" class="btn btn-primary">
            🖨️ {{ __("Print Slip") }}
        </button>
        <a href="{{ route('tenant.cash_register.movement.pdf', ['tx' => $tx->id, 'paperSize' => $paperWidth, 'download' => 1]) }}" class="btn btn-secondary">
            📥 {{ __("PDF") }}
        </a>
        @if (!empty($whatsAppUrl))
            <a href="{{ $whatsAppUrl }}" target="_blank" class="btn btn-whatsapp">
                💬 {{ __("WhatsApp") }}
            </a>
        @endif
        <a href="{{ route('tenant.financials.cash_register') }}" class="btn btn-secondary">
            &larr; {{ __("Back") }}
        </a>
    </div>

    <!-- Thermal Paper Container -->
    <div class="receipt-card">
        
        <!-- Header & Logo -->
        <div class="text-center">
            @if ($company->logo)
                <img src="{{ $company->logo }}" class="receipt-logo" alt="{{ $company->name }}">
            @endif
            <div class="store-name">{{ $company->trade_name ?? $company->name }}</div>
            <div class="store-meta">
                @if ($company->address) <div>{{ $company->address }}</div> @endif
                <div>
                    @if ($company->phone) Tel: {{ $company->phone }} @endif
                    @if ($company->tax_number) &bull; Tax ID: {{ $company->tax_number }} @endif
                </div>
            </div>
            
            <div class="badge">
                {{ $tx->getCategoryLabel() }}
            </div>
        </div>

        <div class="dashed"></div>

        <!-- Voucher Details -->
        <table class="data-table" style="font-size: {{ $subSize }};">
            <tr>
                <td><strong>{{ __("Voucher #:") }}</strong> {{ $tx->voucher_number ?? "TX-{$tx->id}" }}</td>
                <td class="col-right"><strong>{{ __("Shift Session:") }}</strong> #{{ $tx->cash_register_id }}</td>
            </tr>
            <tr>
                <td><strong>{{ __("Date/Time:") }}</strong> {{ $tx->created_at ? $tx->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td>
                <td class="col-right"><strong>{{ __("Cashier:") }}</strong> {{ $tx->creator?->name ?? 'Staff' }}</td>
            </tr>
        </table>

        <div class="dashed"></div>

        <!-- Amount Box -->
        <div class="amount-box">
            <div style="font-size: {{ $subSize }}; text-transform: uppercase; font-weight: 700; color: #475569;">
                {{ $tx->type === 'cash_in' ? __('CASH INFLOW') : __('CASH OUTFLOW') }}
            </div>
            <div class="amount-val">
                {{ $tx->type === 'cash_in' ? '+' : '-' }}{{ $company->formatMoney($tx->amount) }}
            </div>
        </div>

        <!-- Balances -->
        <table class="data-table">
            @if ($tx->balance_before !== null)
                <tr>
                    <td>{{ __("Previous Drawer Balance:") }}</td>
                    <td class="col-right bold">{{ $company->formatMoney($tx->balance_before) }}</td>
                </tr>
            @endif
            @if ($tx->balance_after !== null)
                <tr>
                    <td><strong>{{ __("New Drawer Balance:") }}</strong></td>
                    <td class="col-right bold"><strong>{{ $company->formatMoney($tx->balance_after) }}</strong></td>
                </tr>
            @endif
        </table>

        @if (!empty($tx->reason))
            <div class="dashed"></div>
            <div style="font-size: {{ $subSize }}; padding: 3px 0;">
                <strong>{{ __("Reason / Notes:") }}</strong> {{ $tx->reason }}
            </div>
        @endif

        <div class="dashed"></div>

        <!-- Signatures -->
        <div class="signatures">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 48%; text-align: center;">
                        <div style="border-top: 1px solid #000; padding-top: 4px; font-size: {{ $subSize }};">
                            {{ __("Cashier Signature") }}
                        </div>
                    </td>
                    <td style="width: 4%;"></td>
                    <td style="width: 48%; text-align: center;">
                        <div style="border-top: 1px solid #000; padding-top: 4px; font-size: {{ $subSize }};">
                            {{ __("Manager Signature") }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="solid"></div>

        <!-- Footer Dual Branding Attribution -->
        <div class="footer-text">
            @if (!empty($qrCodeSvg))
                <div style="width: 70px; margin: 4px auto;">
                    {!! $qrCodeSvg !!}
                </div>
            @endif
            <div>{{ $company->trade_name ?? $company->name }} &bull; {{ __("Cash Drawer Voucher") }}</div>
            <div style="font-weight: 700; margin-top: 3px;">
                {{ __("Powered by") }} {{ $platformName }}
            </div>
            <div>{{ __("Issued via") }} {{ $platformUrl }}</div>
        </div>

    </div>

</body>
</html>
