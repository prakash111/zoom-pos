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
    $docTemplate = $template ?? \App\Models\TenantDocumentTemplate::getForCompany($company->id, $type === 'quotation' ? 'quotation' : 'invoice');
    $accentColor = $isThermal ? '#0F172A' : ($docTemplate->theme_color ?: ($company->primary_color ?: '#10b981'));
    $documentLabel = $docTemplate->header_title ?: ($type === 'quotation' ? 'QUOTATION' : ($type === 'sale' ? 'SALES RECEIPT' : 'TAX INVOICE'));
    $reference = $document->sale_number ?: $document->id;
    $currency = $company->currency_symbol ?: ($company->currency ?: '₹');
    $money = fn ($amount) => $currency.number_format((float) $amount, 2);
    $isPaid = ($document->payment_status === 'paid') || ((float) ($document->due_amount ?? 0) <= 0);
    $store = ($document instanceof \Illuminate\Database\Eloquent\Model || property_exists($document, 'store')) ? $document->store : null;
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
        .brand { color: {{ $accentColor }}; font-weight: 800; font-size: {{ $isA4 ? '22px' : '1.35em' }}; text-transform: uppercase; }
        .muted { color: #64748b; }
        .header { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid {{ $accentColor }}; }
        .right { text-align: right; }
        .doc-title { font-size: 1.25em; font-weight: 800; margin-top: 5px; color: {{ $accentColor }}; }
        .client { margin: 14px 0; padding: 10px; background: {{ $isThermal ? '#F8FAFC' : '#f8fafc' }}; border: 1px solid #e2e8f0; border-radius: 8px; }
        .badge-paid { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.85em; font-weight: 700; background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .badge-unpaid { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.85em; font-weight: 700; background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 7px 5px; text-align: left; color: #fff; background: {{ $accentColor }}; font-size: .82em; text-transform: uppercase; }
        td { padding: 7px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th:nth-child(n+2), td:nth-child(n+2) { text-align: right; }
        .totals { width: {{ $isA4 ? '42%' : '100%' }}; margin: 14px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .grand { margin-top: 4px; padding-top: 7px !important; border-top: 2px solid {{ $accentColor }}; color: {{ $accentColor }}; font-size: 1.25em; font-weight: 800; }
        .notes { margin-top: 14px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; color: #475569; font-size: 0.88em; }
        .footer { margin-top: 18px; text-align: center; color: #64748b; font-size: .82em; }
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
            <div class="brand">{{ $store?->name ?: $company->display_name }}</div>
            @if ($store?->receipt_header)
                <div style="font-weight: 600; font-size: 0.9em; margin-bottom: 2px;">{{ $store->receipt_header }}</div>
            @endif
            @php
                $headerAddress = $store?->effective_address ?: ($company->address ? $company->address.' '.$company->city : $company->city);
                $headerPhone = $store?->effective_phone ?: $company->phone;
                $headerTaxId = $store?->effective_tax_id ?: ($company->tax_id ?: 'Unregistered');
            @endphp
            @if (! empty($headerAddress))
                <div class="muted">{{ $headerAddress }}</div>
            @endif
            @if (! empty($headerPhone))
                <div class="muted">Tel: {{ $headerPhone }}</div>
            @endif
            <div class="muted">{{ $company->tax_id_label ?: 'GSTIN' }}: {{ $headerTaxId }}</div>
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
        @if ($docTemplate->show_tax_breakup)
            <div><span>Tax</span><strong>{{ $money($document->tax_amount ?? 0) }}</strong></div>
        @endif
        <div class="grand"><span>Total</span><span>{{ $money($document->total) }}</span></div>
    </section>

    @if ($docTemplate->show_qr_code)
        @php
            $qrPayload = $document->einvoice_qr ?? null;
            if (empty($qrPayload)) {
                try {
                    $qrPayload = ($type === 'quotation')
                        ? route('quotes.public', $reference)
                        : route('sales.public', $reference);
                } catch (\Throwable $e) {
                    $qrPayload = url('/i/' . $reference);
                }
            }
            $qrSize = $isThermal ? 72 : 88;
            $qrDataUri = null;
            try {
                if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                    $rawSvg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size($qrSize)
                        ->margin(0)
                        ->errorCorrection('M')
                        ->generate($qrPayload);
                    $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($rawSvg);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('QR generation error in document template: ' . $e->getMessage());
            }
            if (empty($qrDataUri)) {
                $qrDataUri = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qrSize . 'x' . $qrSize . '&data=' . urlencode($qrPayload);
            }
        @endphp
        <div style="margin-top: 14px; text-align: center;">
            <div style="display: inline-block; padding: 6px; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 10px; color: #64748b; background: #fff;">
                <img src="{{ $qrDataUri }}"
                     width="{{ $qrSize }}"
                     height="{{ $qrSize }}"
                     alt="Scan to Verify QR Code"
                     style="display: block; margin: 0 auto; width: {{ $qrSize }}px; height: {{ $qrSize }}px; object-fit: contain;" />
                <span style="display: block; margin-top: 4px; font-weight: 600; font-size: 9px; letter-spacing: 0.02em;">{{ __('Scan to Verify') }}</span>
            </div>
        </div>
    @endif

    @if ($document->notes)
        <section class="notes"><strong>Notes</strong><br>{{ strip_tags($document->notes) }}</section>
    @endif

    @if (! empty($docTemplate->terms_conditions))
        <section class="notes" style="margin-top: 10px; border-left: 3px solid {{ $accentColor }};">
            <strong>Terms & Conditions</strong><br>
            {!! nl2br(e($docTemplate->terms_conditions)) !!}
        </section>
    @endif

    <footer class="footer">
        @if (! empty($store?->receipt_footer))
            {!! nl2br(e($store->receipt_footer)) !!}
        @elseif (! empty($docTemplate->footer_notes))
            {!! nl2br(e($docTemplate->footer_notes)) !!}
        @else
            Thank you for choosing {{ $store?->name ?: $company->display_name }}.
        @endif
    </footer>
</body>
</html>
