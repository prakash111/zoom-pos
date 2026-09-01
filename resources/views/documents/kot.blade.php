<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KOT #{{ $kot->kot_number }} — {{ $company->name }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Courier New", Courier, monospace, -apple-system, sans-serif;
            background-color: #52525b;
            color: #18181b;
            padding: 20px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .no-print-bar {
            max-width: 380px;
            width: 100%;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 10px 16px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        }
        .btn-primary { background-color: #84cc16; color: #18181b; }
        .btn-secondary { background-color: #f1f5f9; color: #475569; }

        .kot-ticket {
            max-width: 360px;
            width: 100%;
            background: #ffffff;
            padding: 24px 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            border-radius: 4px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #18181b;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .kot-title {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 2px;
        }

        .kot-meta {
            font-size: 13px;
            font-weight: 800;
            margin-top: 4px;
            line-height: 1.4;
        }

        .table-badge {
            display: inline-block;
            background: #18181b;
            color: #ffffff;
            font-size: 18px;
            font-weight: 900;
            padding: 4px 12px;
            border-radius: 6px;
            margin: 6px 0;
            text-transform: uppercase;
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #71717a;
        }

        .items-table {
            width: 100%;
            font-size: 14px;
            border-collapse: collapse;
        }

        .item-row {
            border-bottom: 1px dashed #e4e4e7;
            padding: 8px 0;
        }

        .item-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-weight: 900;
        }

        .item-qty {
            font-size: 16px;
            margin-right: 8px;
            color: #09090b;
        }

        .item-variant {
            font-size: 12px;
            font-weight: 700;
            color: #27272a;
            margin-left: 24px;
        }

        .item-modifiers {
            font-size: 11px;
            font-weight: 700;
            color: #4f46e5;
            margin-left: 24px;
        }

        .item-spice {
            font-size: 11px;
            font-weight: 800;
            color: #b91c1c;
            margin-left: 24px;
        }

        .item-note {
            font-size: 11px;
            font-weight: 800;
            color: #dc2626;
            margin-left: 24px;
            background: #fee2e2;
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            margin-top: 2px;
        }

        .seat-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            background: #e4e4e7;
            padding: 1px 5px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .kitchen-note-box {
            margin-top: 14px;
            padding: 8px 10px;
            background: #fef3c7;
            border: 1px dashed #d97706;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #92400e;
        }

        .footer {
            margin-top: 16px;
            border-top: 2px dashed #18181b;
            padding-top: 10px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #71717a;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print-bar { display: none !important; }
            .kot-ticket {
                box-shadow: none !important;
                padding: 5px !important;
                max-width: 100% !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <strong>Ticket: {{ $kot->kot_number }}</strong>
        <div style="display: flex; gap: 6px;">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print KOT</button>
            @if (auth('web')->check())
                <a href="{{ route('tenant.restaurant.kds') }}" class="btn btn-secondary">KDS Live</a>
            @endif
        </div>
    </div>

    <div class="kot-ticket">
        <div class="header">
            <div class="kot-title">KITCHEN ORDER TICKET</div>
            <div class="table-badge">
                {{ $kot->table_name ?: ucfirst(str_replace('_', ' ', $kot->service_type)) }}
            </div>
            <div class="kot-meta">
                #{{ $kot->kot_number }} &bull; {{ strtoupper($kot->service_type) }}
            </div>
        </div>

        <div class="info-grid">
            <div>
                <div>Server: {{ $kot->server_name ?: (auth('web')->user()?->name ?? 'POS Register') }}</div>
                <div>Table: {{ $kot->table_name ?: 'Counter' }}</div>
            </div>
            <div style="text-align: right;">
                <div>Date: {{ $kot->created_at ? $kot->created_at->format('d/m/y') : now()->format('d/m/y') }}</div>
                <div>Time: {{ $kot->created_at ? $kot->created_at->format('H:i:s') : now()->format('H:i:s') }}</div>
            </div>
        </div>

        <div class="items-table">
            @foreach ($kot->items ?? [] as $item)
                <div class="item-row">
                    <div class="item-main">
                        <div>
                            <span class="item-qty">{{ $item['quantity'] ?? 1 }}x</span>
                            <span>{{ $item['name'] ?? 'Food Item' }}</span>
                            @if (!empty($item['seat']))
                                <span class="seat-tag">Seat {{ $item['seat'] }}</span>
                            @endif
                        </div>
                    </div>

                    @if (!empty($item['variant']))
                        <div class="item-variant">&bull; Size/Style: {{ $item['variant'] }}</div>
                    @endif

                    @if (!empty($item['modifiers']) && is_array($item['modifiers']))
                        <div class="item-modifiers">
                            + {{ implode(', ', array_column($item['modifiers'], 'name')) }}
                        </div>
                    @endif

                    @if (!empty($item['spice_level']))
                        <div class="item-spice">🌶 {{ $item['spice_level'] }}</div>
                    @endif

                    @if (!empty($item['note']))
                        <div class="item-note">Note: {{ $item['note'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        @if (!empty($kot->kitchen_notes))
            <div class="kitchen-note-box">
                ⚠️ Order Instruction: {{ $kot->kitchen_notes }}
            </div>
        @endif

        <div class="footer">
            Printed at {{ now()->format('H:i:s') }} &bull; {{ $company->name }} Kitchen
        </div>
    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
