@php
    $isA4 = $paperFormat === 'a4';
    $isSlip = $paperFormat === 'slip';
    $isThermal = in_array($paperFormat, ['thermal_58mm', 'thermal_80mm'], true);
    $paperWidth = match ($paperFormat) {
        'thermal_58mm' => '58mm',
        'thermal_80mm' => '80mm',
        'slip' => '360px',
        default => '210mm',
    };
    $documentLabel = $type === 'quotation' ? 'QUOTATION' : ($type === 'sale' ? 'SALES RECEIPT' : 'TAX INVOICE');
    $reference = $document->sale_number ?: $document->id;
    $currency = $company->currency_symbol ?: ($company->currency ?: '₹');
    $money = fn ($amount) => $currency.number_format((float) $amount, 2);
    $isPaid = ($document->payment_status === 'paid') || ((float) ($document->due_amount ?? 0) <= 0);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentLabel }} #{{ $reference }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        @page { size: {{ $isA4 ? 'A4 portrait' : $paperWidth.' auto' }}; margin: {{ $isA4 ? '12mm' : '2mm' }}; }
        html, body { margin: 0; padding: 0; background: #fff; color: #0F172A; font-family: {{ $isThermal ? 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace' : 'Arial, Helvetica, sans-serif' }}; }
        body { width: {{ $paperWidth }}; min-height: {{ $isA4 ? '297mm' : ($isSlip ? '640px' : 'auto') }}; padding: {{ $isA4 ? '14mm' : ($isSlip ? '20px' : '3mm') }}; font-size: {{ $isA4 ? '12px' : ($isSlip ? '13px' : ($paperFormat === 'thermal_58mm' ? '9px' : '10px')) }}; }
        .brand { color: {{ $isThermal ? '#0F172A' : ($company->primary_color ?: '#65a30d') }}; font-weight: 800; font-size: {{ $isA4 ? '22px' : '1.35em' }}; text-transform: uppercase; }
        .muted { color: #64748b; }
        .header { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid {{ $isThermal ? '#0F172A' : ($company->primary_color ?: '#65a30d') }}; }
        .right { text-align: right; }
        .doc-title { font-size: 1.25em; font-weight: 800; margin-top: 5px; }
        .client { margin: 14px 0; padding: 10px; background: {{ $isThermal ? '#F8FAFC' : '#f7fee7' }}; border: 1px solid {{ $isThermal ? '#E2E8F0' : '#d9f99d' }}; border-radius: 8px; }
        .badge-paid { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.85em; font-weight: 700; background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .badge-unpaid { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.85em; font-weight: 700; background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 7px 5px; text-align: left; color: #fff; background: {{ $isThermal ? '#0F172A' : ($company->primary_color ?: '#65a30d') }}; font-size: .82em; text-transform: uppercase; }
        td { padding: 7px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th:nth-child(n+2), td:nth-child(n+2) { text-align: right; }
        .totals { width: {{ $isA4 ? '42%' : '100%' }}; margin: 14px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .grand { margin-top: 4px; padding-top: 7px !important; border-top: 2px solid {{ $isThermal ? '#0F172A' : ($company->primary_color ?: '#65a30d') }}; color: {{ $isThermal ? '#0F172A' : ($company->primary_color ?: '#65a30d') }}; font-size: 1.25em; font-weight: 800; }
        .notes { margin-top: 16px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; color: #475569; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: .82em; }
        @media (max-width: 100mm) {
            .header { display: block; text-align: center; }
            .right { margin-top: 8px; text-align: center; }
            .client { text-align: center; }
            th:nth-child(3), td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div>
            <div class="brand">{{ $company->display_name }}</div>
            <div class="muted">{{ $company->address }} {{ $company->city }}</div>
            <div class="muted">{{ $company->tax_id_label ?: 'GSTIN' }}: {{ $company->tax_id ?: 'Unregistered' }}</div>
        </div>
        <div class="right">
            <div class="doc-title">{{ $documentLabel }}</div>
            <strong>#{{ $reference }}</strong><br>
            <span class="muted">{{ optional($document->created_at)->format('d M Y') }}</span>
            <div style="margin-top: 4px;">
                @if ($isPaid)
                    <span class="badge-paid">PAID</span>
                @else
                    <span class="badge-unpaid">UNPAID ({{ $money($document->due_amount ?? $document->total) }})</span>
                @endif
            </div>
        </div>
    </header>

    <section class="client">
        <strong>{{ $type === 'quotation' ? 'Prepared for' : 'Customer' }}:</strong>
        {{ $customer?->name ?: ($document->customer_name ?: 'Walk-in Client') }}
        @if ($customer?->phone)<br><span class="muted">{{ $customer->phone }}</span>@endif
        @if ($customer?->email)<br><span class="muted">{{ $customer->email }}</span>@endif
    </section>

    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead>
        <tbody>
        @forelse ($lines as $line)
            <tr>
                <td>{{ $line['name'] }}</td>
                <td>{{ rtrim(rtrim(number_format($line['quantity'], 3), '0'), '.') }}</td>
                <td>{{ $money($line['unit_price']) }}</td>
                <td>{{ $money($line['line_total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No line items</td></tr>
        @endforelse
        </tbody>
    </table>

    <section class="totals">
        <div><span>Subtotal</span><strong>{{ $money($document->subtotal) }}</strong></div>
        @if ((float) $document->discount > 0)<div><span>Discount</span><strong>-{{ $money($document->discount) }}</strong></div>@endif
        <div><span>Tax</span><strong>{{ $money($document->tax_amount ?? 0) }}</strong></div>
        <div class="grand"><span>Total</span><span>{{ $money($document->total) }}</span></div>
    </section>

    @if ($document->notes)
        <section class="notes"><strong>Notes</strong><br>{{ strip_tags($document->notes) }}</section>
    @endif

    <footer class="footer">Thank you for choosing {{ $company->display_name }}.</footer>
</body>
</html>
