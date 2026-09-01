<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Reminder #{{ $sale->sale_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 32px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
        .header { border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 24px; }
        .brand { font-size: 22px; font-weight: 800; color: #b45309; margin: 0 0 4px 0; }
        .meta { font-size: 13px; color: #64748b; margin: 2px 0; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 800; background: #fef3c7; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px; }
        .totals { margin-top: 16px; border-top: 2px solid #f1f5f9; padding-top: 16px; font-size: 14px; }
        .row { display: flex; justify-content: space-between; margin: 6px 0; }
        .due-row { font-size: 20px; font-weight: 900; color: #b45309; margin-top: 10px; }
        .action-button { display: inline-block; background-color: #b45309; color: #ffffff !important; font-weight: 700; font-size: 14px; text-decoration: none; padding: 12px 24px; border-radius: 12px; margin: 20px 0; text-align: center; }
        .footer { margin-top: 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 class="brand">{{ $company->trade_name ?? $company->name ?? 'Store' }}</h1>
            <div class="meta">Invoice No: <strong>#{{ $sale->sale_number }}</strong></div>
            <div class="meta">Customer: <strong>{{ $sale->customer_name ?? 'Valued Customer' }}</strong></div>
            <div style="margin-top: 10px;">
                <span class="badge">Payment Reminder</span>
            </div>
        </div>

        <p>Hi {{ $sale->customer_name ?: 'there' }}, this is a friendly reminder of an outstanding balance on your account.</p>

        <div class="totals">
            <div class="row"><span>Total</span><strong>{{ $company->currency_symbol ?? '$' }}{{ number_format((float) $sale->total, 2) }}</strong></div>
            <div class="row"><span>Paid</span><strong>{{ $company->currency_symbol ?? '$' }}{{ number_format((float) $sale->paid_amount, 2) }}</strong></div>
            <div class="row due-row"><span>Due</span><span>{{ $company->currency_symbol ?? '$' }}{{ number_format((float) $sale->due_amount, 2) }}</span></div>
            @if ($sale->due_date)
                <div class="row"><span>Due Date</span><strong>{{ $sale->due_date->format('d M Y') }}</strong></div>
            @endif
        </div>

        <div style="text-align: center; margin: 24px 0 16px 0;">
            <a href="{{ route('sales.public', $sale->sale_number) }}" target="_blank" class="action-button">
                View Invoice &rarr;
            </a>
        </div>

        <div class="footer">
            <p>Thank you for your business with {{ $company->name ?? 'our store' }}!</p>
            @if ($company->phone || $company->email)
                <p>Contact: {{ $company->phone }} {{ $company->email ? '• ' . $company->email : '' }}</p>
            @endif
        </div>
    </div>
</body>
</html>
