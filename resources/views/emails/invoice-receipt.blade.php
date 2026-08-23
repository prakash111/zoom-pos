<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $sale->sale_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 32px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
        .header { border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 24px; }
        .brand { font-size: 22px; font-weight: 800; color: #2563eb; margin: 0 0 4px 0; }
        .meta { font-size: 13px; color: #64748b; margin: 2px 0; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 800; background: #dcfce7; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; }
        .custom-message { background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; line-height: 1.6; color: #1e40af; white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th { text-align: left; padding: 10px 8px; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 11px; }
        td { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; }
        .totals { margin-top: 16px; border-top: 2px solid #f1f5f9; padding-top: 16px; font-size: 14px; }
        .row { display: flex; justify-content: space-between; margin: 6px 0; }
        .total-row { font-size: 18px; font-weight: 900; color: #0f172a; margin-top: 10px; }
        .notes-box { background: #f8fafc; border-radius: 10px; padding: 12px 16px; margin: 20px 0; font-size: 12px; color: #475569; border: 1px solid #e2e8f0; }
        .action-button { display: inline-block; background-color: #2563eb; color: #ffffff !important; font-weight: 700; font-size: 14px; text-decoration: none; padding: 12px 24px; border-radius: 12px; margin: 20px 0; text-align: center; }
        .attachment-note { font-size: 12px; color: #2563eb; font-weight: 600; margin-top: 10px; display: flex; align-items: center; gap: 6px; }
        .footer { margin-top: 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 class="brand">{{ $company->trade_name ?? $company->name ?? 'Store Receipt' }}</h1>
            <div class="meta">Invoice No: <strong>#{{ $sale->sale_number }}</strong></div>
            <div class="meta">Date: {{ $sale->created_at ? $sale->created_at->format('M d, Y - h:i A') : now()->format('M d, Y - h:i A') }}</div>
            <div class="meta">Customer: <strong>{{ $sale->customer_name ?? 'Valued Customer' }}</strong></div>
            <div style="margin-top: 10px;">
                <span class="badge">🧾 {{ ucfirst($sale->status ?? 'Completed') }}</span>
            </div>
        </div>

        @if (!empty($customMessage))
            <div class="custom-message">
                {{ $customMessage }}
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items ?? [] as $item)
                    <tr>
                        <td>
                            <strong>{{ $item['name'] ?? 'Product' }}</strong>
                            @if (!empty($item['description']))
                                <div style="font-size: 11px; color: #64748b;">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item['quantity'] ?? 1 }}</td>
                        <td style="text-align: right;">{{ $company->formatMoney($item['price'] ?? 0) }}</td>
                        <td style="text-align: right;"><strong>{{ $company->formatMoney((float) ($item['quantity'] ?? 1) * (float) ($item['price'] ?? 0)) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            @php
                $subtotal = collect($sale->items ?? [])->sum(fn($i) => (float)($i['quantity'] ?? 1) * (float)($i['price'] ?? 0));
            @endphp
            <div class="row">
                <span style="color: #64748b;">Subtotal:</span>
                <span>{{ $company->formatMoney($subtotal) }}</span>
            </div>
            @if ($sale->discount > 0)
                <div class="row" style="color: #e11d48;">
                    <span>Discount:</span>
                    <span>-{{ $company->formatMoney($sale->discount) }}</span>
                </div>
            @endif
            <div class="row total-row">
                <span>Total Amount:</span>
                <span style="color: #2563eb;">{{ $company->formatMoney($sale->total) }}</span>
            </div>
            <div class="row" style="font-size: 12px; color: #64748b; margin-top: 8px;">
                <span>Payment Method:</span>
                <span>{{ ucfirst($sale->payment_method ?? 'Cash') }}</span>
            </div>
            @if ((float) ($sale->due_amount ?? 0) > 0)
                <div class="row" style="font-size: 13px; color: #e11d48; font-weight: 700; margin-top: 4px;">
                    <span>Balance Due:</span>
                    <span>{{ $company->formatMoney($sale->due_amount) }}</span>
                </div>
            @endif
        </div>

        @if (!empty($sale->notes))
            <div class="notes-box">
                <strong style="color: #0f172a; display: block; margin-bottom: 4px;">Order Notes:</strong>
                {{ $sale->notes }}
            </div>
        @endif

        <div style="text-align: center; margin: 24px 0 16px 0;">
            <a href="{{ route('sales.public', $sale->sale_number) }}" target="_blank" class="action-button">
                View & Download Official Invoice &rarr;
            </a>
        </div>

        @if (!empty($hasAttachment))
            <div class="attachment-note">
                📎 An official PDF invoice (<strong>Invoice-{{ $sale->sale_number }}.pdf</strong>) is attached to this email for your records.
            </div>
        @endif

        <div class="footer">
            <p>Thank you for your business with {{ $company->name ?? 'our store' }}!</p>
            @if ($company->phone || $company->email)
                <p>Contact: {{ $company->phone }} {{ $company->email ? '• ' . $company->email : '' }}</p>
            @endif
        </div>
    </div>
</body>
</html>
