<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Z-Report #{{ $register->id }} — {{ $company->name }}</title>
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
        .section-title {
            font-size: {{ $subSize }};
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 6px 0 3px 0;
        }
        .highlight-row td {
            font-size: 13px;
            font-weight: 800;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 3px 0;
        }
        .signatures {
            margin-top: 16px;
            width: 100%;
        }
        .sig-col {
            width: 48%;
            display: inline-block;
            text-align: center;
            font-size: {{ $subSize }};
            border-top: 1px solid #000000;
            padding-top: 4px;
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
        <a href="{{ route('tenant.cash_register.z_report.pdf', ['register' => $register->id, 'paperSize' => $paperWidth, 'download' => 1]) }}" class="btn btn-secondary">
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
                {{ $register->isOpen() ? __('X-REPORT (LIVE SHIFT)') : __('Z-REPORT & SETTLEMENT') }}
            </div>
        </div>

        <div class="dashed"></div>

        <!-- Shift Metadata -->
        <table class="data-table" style="font-size: {{ $subSize }};">
            <tr>
                <td><strong>{{ __("Shift #:") }}</strong> #{{ $register->id }}</td>
                <td class="col-right"><strong>{{ __("Terminal:") }}</strong> {{ $register->terminal_id ?? 'Main POS' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __("Opened:") }}</strong> {{ $register->opened_at ? $register->opened_at->format('d/m/Y H:i') : '—' }}</td>
                <td class="col-right"><strong>{{ __("By:") }}</strong> {{ $metrics['opened_by_name'] }}</td>
            </tr>
            <tr>
                <td><strong>{{ __("Closed:") }}</strong> {{ $register->closed_at ? $register->closed_at->format('d/m/Y H:i') : __('In Progress') }}</td>
                <td class="col-right"><strong>{{ __("By:") }}</strong> {{ $metrics['closed_by_name'] }}</td>
            </tr>
        </table>

        <div class="dashed"></div>

        <!-- Financial Summary -->
        <div class="section-title">{{ __("Financial Summary") }}</div>
        <table class="data-table">
            <tr>
                <td>{{ __("Opening Float:") }}</td>
                <td class="col-right bold">{{ $company->formatMoney($metrics['opening_balance']) }}</td>
            </tr>
            <tr>
                <td>{{ __("Gross Sales") }} ({{ $metrics['sale_count'] }}):</td>
                <td class="col-right bold">{{ $company->formatMoney($metrics['total_sales']) }}</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;• {{ __("Cash Sales:") }}</td>
                <td class="col-right">{{ $company->formatMoney($metrics['cash_sales']) }}</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;• {{ __("Non-Cash Sales:") }}</td>
                <td class="col-right">{{ $company->formatMoney($metrics['non_cash_sales']) }}</td>
            </tr>
            <tr>
                <td>{{ __("Cash In (Suprimento):") }}</td>
                <td class="col-right">+{{ $company->formatMoney($metrics['cash_in']) }}</td>
            </tr>
            <tr>
                <td>{{ __("Cash Out (Sangria):") }}</td>
                <td class="col-right">-{{ $company->formatMoney($metrics['cash_out']) }}</td>
            </tr>
            <tr class="highlight-row">
                <td>{{ __("EXPECTED CASH:") }}</td>
                <td class="col-right">{{ $company->formatMoney($metrics['expected_cash']) }}</td>
            </tr>
            <tr>
                <td>{{ __("Physical Counted:") }}</td>
                <td class="col-right bold">{{ $company->formatMoney($metrics['counted_cash']) }}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td>{{ __("Variance:") }}</td>
                <td class="col-right">
                    {{ ($metrics['variance'] >= 0 ? '+' : '') . $company->formatMoney($metrics['variance']) }}
                    ({{ $metrics['variance'] == 0 ? __('BALANCED') : ($metrics['variance'] < 0 ? __('SHORT') : __('OVER')) }})
                </td>
            </tr>
        </table>

        <div class="dashed"></div>

        <!-- Payment Breakdown -->
        <div class="section-title">{{ __("Sales by Payment Method") }}</div>
        <table class="data-table">
            @forelse ($metrics['payments_by_method'] as $method => $data)
                <tr>
                    <td>{{ ucfirst($method) }} ({{ $data['count'] }}):</td>
                    <td class="col-right bold">{{ $company->formatMoney($data['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center" style="color: #64748b; font-size: {{ $subSize }};">{{ __("No sales recorded") }}</td></tr>
            @endforelse
        </table>

        @if ($register->transactions && $register->transactions->isNotEmpty())
            <div class="dashed"></div>
            <div class="section-title">{{ __("Drawer Movements") }}</div>
            <table class="data-table" style="font-size: {{ $subSize }};">
                @foreach ($register->transactions as $tx)
                    <tr>
                        <td>
                            {{ $tx->getCategoryLabel() }}
                            @if ($tx->reason)
                                <br><span style="color: #64748b;">{{ $tx->reason }}</span>
                            @endif
                        </td>
                        <td class="col-right bold">
                            {{ $tx->type === 'cash_in' ? '+' : '-' }}{{ $company->formatMoney($tx->amount) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if (!empty($metrics['notes']))
            <div class="dashed"></div>
            <div style="font-size: {{ $subSize }}; padding: 3px 0;">
                <strong>{{ __("Remarks:") }}</strong> {{ $metrics['notes'] }}
            </div>
        @endif

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
            <div>{{ $company->trade_name ?? $company->name }} &bull; {{ __("Shift Audit Ledger") }}</div>
            <div style="font-weight: 700; margin-top: 3px;">
                {{ __("Powered by") }} {{ $platformName }}
            </div>
            <div>{{ __("Issued via") }} {{ $platformUrl }}</div>
        </div>

    </div>

</body>
</html>
