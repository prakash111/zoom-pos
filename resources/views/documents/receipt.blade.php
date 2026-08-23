<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $sale->sale_number }} — {{ $company->name }}</title>
    @if (!empty($company->favicon))
        <link rel="icon" href="{{ $company->favicon }}">
    @endif
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Courier New", Courier, monospace, -apple-system, sans-serif;
            background-color: #7b838f;
            color: #27272a;
            padding: 24px 12px;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Top Action Bar (Screen Only) */
        .no-print-bar {
            max-width: 440px;
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
            padding: 8px 14px;
            font-size: 12px;
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

        /* Receipt Paper Wrapper matching read/invoice-idea.jpg */
        .receipt-container {
            max-width: 380px;
            width: 100%;
            background: #eef1f4;
            padding: 32px 28px 36px 28px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-radius: 2px;
            color: #262626;
        }

        /* Top and Bottom Jagged Serrated Tear Effect */
        .receipt-container::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(135deg, #7b838f 4px, transparent 0), linear-gradient(-135deg, #7b838f 4px, #eef1f4 0);
            background-size: 8px 8px;
        }

        .receipt-container::after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(45deg, #7b838f 4px, transparent 0), linear-gradient(-45deg, #7b838f 4px, #eef1f4 0);
            background-size: 8px 8px;
        }

        /* Centered Header Section */
        .receipt-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .store-logo {
            max-height: 40px;
            max-width: 150px;
            object-fit: contain;
            margin: 0 auto 6px auto;
            display: block;
        }

        .receipt-title {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #18181b;
        }

        .store-name {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #27272a;
        }

        .store-info {
            font-size: 12px;
            line-height: 1.4;
            color: #3f3f46;
        }

        /* Dashed Separator Line */
        .dashed-line {
            border: none;
            border-top: 1px dashed #71717a;
            margin: 12px 0;
            width: 100%;
        }

        /* Meta Information (Date, Time, Invoice No) */
        .meta-section {
            font-size: 12px;
            font-weight: 600;
            color: #27272a;
            line-height: 1.5;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Itemized Product List */
        .items-list {
            margin: 8px 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            line-height: 1.3;
        }

        .item-name {
            font-weight: 700;
            color: #18181b;
            flex: 1;
            padding-right: 8px;
        }

        .item-sub {
            font-size: 11px;
            color: #52525b;
            margin-top: 1px;
        }

        .item-price {
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
            color: #18181b;
        }

        /* Totals Breakdown */
        .summary-section {
            font-size: 13px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            color: #3f3f46;
        }

        .total-row {
            font-size: 18px;
            font-weight: 900;
            color: #09090b;
            padding: 4px 0;
        }

        /* Thank You & Barcode Section */
        .footer-section {
            text-align: center;
            margin-top: 14px;
        }

        .thank-you-text {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #18181b;
            margin-bottom: 12px;
        }

        /* CSS Barcode Rendering matching invoice-idea.jpg */
        .barcode-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            margin-top: 6px;
        }

        .barcode-bars {
            display: flex;
            justify-content: center;
            align-items: stretch;
            height: 48px;
            gap: 2px;
        }

        .bar {
            background-color: #18181b;
        }

        .barcode-digits {
            font-size: 11px;
            letter-spacing: 3px;
            color: #52525b;
            font-weight: bold;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                background: #ffffff !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 10px 5px !important;
            }
            .receipt-container::before, .receipt-container::after {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (Screen Only) -->
    <div class="no-print-bar">
        <div style="font-weight: 800; font-size: 12px; color: #1e293b;">
            Invoice / Receipt #{{ $sale->sale_number }}
        </div>

        <div style="display: flex; gap: 6px;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                <span>Print</span>
            </button>

            @if (!empty($whatsAppUrl))
                <a href="{{ $whatsAppUrl }}" target="_blank" class="btn btn-whatsapp">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                    <span>WhatsApp</span>
                </a>
            @endif

            @if (auth('web')->check())
                <a href="{{ $backRoute ?? route('tenant.sales.index') }}" class="btn btn-secondary">
                    <span>Back</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Thermal Cash Receipt Paper matching read/invoice-idea.jpg -->
    <div class="receipt-container">
        
        <!-- Header -->
        <div class="receipt-header">
            @if (!empty($company->logo))
                <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="store-logo">
            @endif
            
            <div class="receipt-title">{{ $company->name ?? 'CASH RECEIPT' }}</div>
            
            <div class="store-info">
                @if (!empty($company->address))
                    <div>Address: {{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
                @endif
                @if (!empty($company->phone))
                    <div>Tel: {{ $company->phone }}</div>
                @endif
                @if (!empty($company->website))
                    <div>Web: {{ $company->website }}</div>
                @endif
                @if (!empty($company->tax_id))
                    <div>Tax ID: {{ $company->tax_id }}</div>
                @endif
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Date, Time & Reference -->
        <div class="meta-section">
            <div class="meta-row">
                <span>Date: {{ $sale->created_at ? $sale->created_at->format('d-m-Y') : now()->format('d-m-Y') }}</span>
                <span>{{ $sale->created_at ? $sale->created_at->format('H:i') : now()->format('H:i') }}</span>
            </div>
            <div class="meta-row" style="margin-top: 2px;">
                <span>Receipt: #{{ $sale->sale_number }}</span>
                @if ($sale->customer_name)
                    <span>Cust: {{ $sale->customer_name }}</span>
                @endif
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Itemized Products -->
        <div class="items-list">
            @forelse ($sale->items ?? [] as $item)
                @php
                    $qty = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['price'] ?? 0);
                    $lineTotal = $qty * $price;
                @endphp
                <div class="item-row">
                    <div class="item-name">
                        <div>{{ $item['name'] ?? 'Item' }}</div>
                        @if ($qty > 1)
                            <div class="item-sub">{{ $qty }} x {{ $company->formatMoney($price) }}</div>
                        @endif
                        @if (!empty($item['is_overridden']))
                            <div class="item-sub">Std: {{ $company->formatMoney((float) ($item['base_price'] ?? 0)) }}</div>
                        @endif
                    </div>
                    <div class="item-price">
                        {{ $company->formatMoney($lineTotal) }}
                    </div>
                </div>
            @empty
                <div class="item-row" style="justify-content: center; color: #71717a;">
                    No items in receipt.
                </div>
            @endforelse
        </div>

        <div class="dashed-line"></div>

        <!-- Calculation Summary -->
        <div class="summary-section">
            @php
                $subtotal = (float) $sale->total + (float) $sale->discount - (float) $sale->tax;
            @endphp

            <div class="summary-row total-row">
                <span>Total</span>
                <span>{{ $company->formatMoney($sale->total) }}</span>
            </div>

            <div class="summary-row">
                <span>Sub-total</span>
                <span>{{ $company->formatMoney($subtotal > 0 ? $subtotal : $sale->total) }}</span>
            </div>

            @if ($sale->discount > 0)
                <div class="summary-row" style="color: #dc2626;">
                    <span>Discount</span>
                    <span>-{{ $company->formatMoney($sale->discount) }}</span>
                </div>
            @endif

            @if ($sale->tax > 0)
                <div class="summary-row">
                    <span>Sales Tax</span>
                    <span>{{ $company->formatMoney($sale->tax) }}</span>
                </div>
            @endif

            @if ($sale->payments && $sale->payments->count() > 1)
                @foreach ($sale->payments as $payment)
                    <div class="summary-row">
                        <span>Payment ({{ ucfirst($payment->payment_method) }}{{ $payment->reference_number ? ' • ' . $payment->reference_number : '' }})</span>
                        <span>{{ $company->formatMoney($payment->amount) }}</span>
                    </div>
                @endforeach
            @else
                <div class="summary-row">
                    <span>Payment ({{ ucfirst($sale->payment_method ?? 'Cash') }})</span>
                    <span>{{ $company->formatMoney($sale->paid_amount ?? $sale->total) }}</span>
                </div>
            @endif

            @if ((float) ($sale->due_amount ?? 0) > 0)
                <div class="summary-row" style="color: #dc2626;">
                    <span>Remaining Balance:{{ $sale->due_date ? ' (by ' . $sale->due_date->format('d-m-Y') . ')' : '' }}</span>
                    <span>{{ $company->formatMoney($sale->due_amount) }}</span>
                </div>
            @else
                <div class="summary-row">
                    <span>Balance</span>
                    <span>$0.00</span>
                </div>
            @endif
        </div>

        @if (!empty($sale->notes))
            <div class="dashed-line"></div>
            <div class="meta-section">
                <div style="font-weight: 800; margin-bottom: 2px;">Notes:</div>
                <div>{{ $sale->notes }}</div>
            </div>
        @endif

        @if (!empty($company->invoice_terms))
            <div class="dashed-line"></div>
            <div class="meta-section">
                <div style="font-weight: 800; margin-bottom: 2px;">Terms & Conditions:</div>
                @foreach (explode("\n", $company->invoice_terms) as $termLine)
                    @if (trim($termLine))
                        <div>&bull; {{ trim($termLine) }}</div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="dashed-line"></div>

        <!-- Thank You & Barcode -->
        <div class="footer-section">
            <div class="thank-you-text">THANK YOU</div>

            <div class="barcode-container">
                <div class="barcode-bars">
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 4px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 1px;"></div>
                    <div class="bar" style="width: 2px; background: transparent;"></div>
                    <div class="bar" style="width: 4px;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 4px;"></div>
                    <div class="bar" style="width: 1px;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 2px; background: transparent;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 4px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 4px;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 2px;"></div>
                    <div class="bar" style="width: 1px; background: transparent;"></div>
                    <div class="bar" style="width: 3px;"></div>
                    <div class="bar" style="width: 4px;"></div>
                </div>
                <div class="barcode-digits">{{ $sale->sale_number }}</div>
            </div>
        </div>

    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
