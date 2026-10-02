<?php

/**
 * Central License Manager — Invoice & Receipt Generator
 * Zero-dependency pure PHP PDF and HTML invoice generation.
 */

function lm_pdf_escape(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

/**
 * Generate a standalone, valid PDF-1.4 invoice document in pure PHP.
 */
function lm_generate_invoice_pdf(array $order, array $licenses, string $siteName = ''): string
{
    $companyName = trim((string) setting('invoice_company_name', ''));
    if ($companyName === '') {
        $companyName = $siteName !== '' ? $siteName : (string) setting('site_name', 'ZoomNearby SaaS Platform');
    }
    $taxNumber = trim((string) setting('invoice_tax_number', ''));
    $companyAddress = trim((string) setting('invoice_company_address', ''));
    $footerNote = trim((string) setting('invoice_footer_note', ''));
    if ($footerNote === '') {
        $footerNote = 'Thank you for your purchase! Each license is authentic and bound to your registered domain.';
    }

    $reference = (string) ($order['reference'] ?? 'ORD-0000');
    $domain = (string) ($order['domain'] ?? $order['target_domain'] ?? 'Not specified');
    $email = (string) ($order['email'] ?? 'customer@example.com');
    $amount = number_format((float) ($order['amount'] ?? 0), 2) . ' ' . strtoupper((string) ($order['currency'] ?? 'USD'));
    $gateway = strtoupper((string) ($order['gateway'] ?? 'Online Checkout'));
    $dateStr = date('M d, Y', !empty($order['created_at']) ? strtotime($order['created_at']) : time());
    $issueTime = date('Y-m-d H:i') . ' UTC';
    $publicUrl = rtrim((string) setting('license_public_url', 'https://license.zoomnearby.com'), '/');

    $stream = "q\n";

    // Header Indigo Banner (0 735 595.28 107)
    $stream .= "0.192 0.180 0.506 rg\n0 735 595.28 107 re f\n";

    // Header Title & Subtitle in White
    $stream .= "1 1 1 rg\n";
    $stream .= "BT /F2 17 Tf 40 794 Td (" . lm_pdf_escape($companyName) . ") Tj ET\n";
    $stream .= "0.85 0.88 0.98 rg\n";
    $stream .= "BT /F1 9.5 Tf 40 774 Td (Official Purchase Receipt & Tax Invoice) Tj ET\n";
    if ($taxNumber !== '') {
        $stream .= "BT /F1 8.5 Tf 40 756 Td (Tax ID / GSTIN: " . lm_pdf_escape($taxNumber) . ") Tj ET\n";
    }

    // Header Right: "INVOICE" and Date
    $stream .= "1 1 1 rg\n";
    $stream .= "BT /F2 18 Tf 450 792 Td (INVOICE) Tj ET\n";
    $stream .= "0.85 0.88 0.98 rg\n";
    $stream .= "BT /F1 9.5 Tf 450 768 Td (Date: " . lm_pdf_escape($dateStr) . ") Tj ET\n";

    // Billed To & Order Details Card (y = 620 to 715, height 95)
    $stream .= "0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 0.5 w\n40 620 515.28 95 re B\n";

    // Left Column: Customer details
    $stream .= "0.192 0.180 0.506 rg\n";
    $stream .= "BT /F2 9.5 Tf 55 695 Td (BILLED TO:) Tj ET\n";
    $stream .= "0.20 0.25 0.32 rg\n";
    $stream .= "BT /F1 9 Tf 55 677 Td (Customer: " . lm_pdf_escape(mb_strimwidth($email, 0, 42, '...')) . ") Tj ET\n";
    $stream .= "BT /F1 9 Tf 55 660 Td (Licensed Domain: " . lm_pdf_escape(mb_strimwidth($domain, 0, 42, '...')) . ") Tj ET\n";
    $stream .= "BT /F1 8.5 Tf 55 643 Td (Payment Gateway: " . lm_pdf_escape($gateway) . ") Tj ET\n";

    // Right Column: Order details & Status
    $stream .= "0.192 0.180 0.506 rg\n";
    $stream .= "BT /F2 9.5 Tf 320 695 Td (INVOICE DETAILS:) Tj ET\n";
    $stream .= "0.20 0.25 0.32 rg\n";
    $stream .= "BT /F1 8.5 Tf 320 677 Td (Order Ref: " . lm_pdf_escape(mb_strimwidth($reference, 0, 45, '...')) . ") Tj ET\n";
    $stream .= "BT /F1 8.5 Tf 320 660 Td (Issued: " . lm_pdf_escape($issueTime) . ") Tj ET\n";
    $stream .= "0.06 0.60 0.30 rg\n";
    $stream .= "BT /F2 9.5 Tf 320 643 Td (Status: PAID / COMPLETED) Tj ET\n";

    // Table Header Bar (y = 580, height 24)
    $stream .= "0.192 0.180 0.506 rg\n40 580 515.28 24 re f\n";
    $stream .= "1 1 1 rg\n";
    $stream .= "BT /F2 9 Tf 52 588 Td (Product Description) Tj ET\n";
    $stream .= "BT /F2 9 Tf 248 588 Td (Assigned License Key) Tj ET\n";
    $stream .= "BT /F2 9 Tf 432 588 Td (Validity) Tj ET\n";
    $stream .= "BT /F2 9 Tf 505 588 Td (Amount) Tj ET\n";

    // Table Rows
    $rowY = 580;
    $idx = 0;
    foreach ($licenses as $item) {
        $name = (string) ($item['name'] ?? $item['slug'] ?? 'Software License');
        $key = (string) ($item['license_key'] ?? 'Pending');
        $validity = (string) ($item['valid_until'] ?: 'Unlimited');

        $rowY -= 26;
        $bg = ($idx % 2 === 0) ? '1 1 1' : '0.97 0.98 0.99';
        $stream .= "{$bg} rg 40 {$rowY} 515.28 26 re f\n";
        $stream .= "0.88 0.90 0.93 RG 0.5 w\n40 {$rowY} 515.28 26 re s\n";

        $stream .= "0.15 0.20 0.28 rg\n";
        $stream .= "BT /F2 8.5 Tf 52 " . ($rowY + 9) . " Td (" . lm_pdf_escape(mb_strimwidth($name, 0, 42, '...')) . ") Tj ET\n";
        $stream .= "0.30 0.35 0.45 rg\n";
        $stream .= "BT /F1 8 Tf 248 " . ($rowY + 9) . " Td (" . lm_pdf_escape(mb_strimwidth($key, 0, 32, '...')) . ") Tj ET\n";
        $stream .= "BT /F1 8.5 Tf 432 " . ($rowY + 9) . " Td (" . lm_pdf_escape($validity) . ") Tj ET\n";
        $stream .= "BT /F1 8.5 Tf 505 " . ($rowY + 9) . " Td (Included) Tj ET\n";

        $idx++;
    }

    $lastRowBottom = $rowY;
    $boxHeight = 70;
    $boxBottom = $lastRowBottom - 16 - $boxHeight;

    // Left Box: Payment Confirmation
    $stream .= "0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 0.5 w\n40 {$boxBottom} 260 {$boxHeight} re B\n";
    $stream .= "0.06 0.60 0.30 rg\n";
    $stream .= "BT /F2 9.5 Tf 55 " . ($boxBottom + 48) . " Td (PAID IN FULL - THANK YOU!) Tj ET\n";
    $stream .= "0.30 0.35 0.45 rg\n";
    $stream .= "BT /F1 8.5 Tf 55 " . ($boxBottom + 30) . " Td (Payment Method: " . lm_pdf_escape($gateway) . ") Tj ET\n";
    $stream .= "BT /F1 8 Tf 55 " . ($boxBottom + 14) . " Td (Each license is authentic and bound to your domain.) Tj ET\n";

    // Right Box: Totals Breakdown (aligned to table right edge 555.28)
    $stream .= "0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 0.5 w\n315 {$boxBottom} 240.28 {$boxHeight} re B\n";
    $stream .= "0.30 0.35 0.45 rg\n";
    $stream .= "BT /F1 9 Tf 330 " . ($boxBottom + 48) . " Td (Subtotal:) Tj ET\n";
    $stream .= "BT /F1 9 Tf 465 " . ($boxBottom + 48) . " Td (" . lm_pdf_escape($amount) . ") Tj ET\n";
    $stream .= "BT /F1 8.5 Tf 330 " . ($boxBottom + 32) . " Td (Tax / VAT (0%):) Tj ET\n";
    $stream .= "BT /F1 8.5 Tf 465 " . ($boxBottom + 32) . " Td (0.00 " . lm_pdf_escape((string) ($order['currency'] ?? 'USD')) . ") Tj ET\n";

    // Divider line inside totals box
    $stream .= "0.85 0.88 0.92 RG 0.5 w\n325 " . ($boxBottom + 24) . " 220 0 re s\n";
    $stream .= "0.192 0.180 0.506 rg\n";
    $stream .= "BT /F2 10.5 Tf 330 " . ($boxBottom + 8) . " Td (Total Paid:) Tj ET\n";
    $stream .= "BT /F2 10.5 Tf 465 " . ($boxBottom + 8) . " Td (" . lm_pdf_escape($amount) . ") Tj ET\n";

    // Footer divider and notes
    $stream .= "0.85 0.88 0.92 RG 0.5 w\n40 70 515.28 0 re s\n";
    $stream .= "0.45 0.50 0.58 rg\n";
    $stream .= "BT /F1 8 Tf 40 54 Td (" . lm_pdf_escape(mb_strimwidth($footerNote, 0, 95, '...')) . ") Tj ET\n";
    $footerMeta = $companyName . ($companyAddress !== '' ? ' | ' . $companyAddress : '') . ' | ' . $publicUrl;
    $stream .= "BT /F1 8 Tf 40 42 Td (" . lm_pdf_escape(mb_strimwidth($footerMeta, 0, 95, '...')) . ") Tj ET\n";
    $stream .= "Q\n";

    // Assemble PDF-1.4 file
    $out = "%PDF-1.4\n";
    $offsets = [];

    $offsets[1] = strlen($out);
    $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    $offsets[2] = strlen($out);
    $out .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

    $offsets[3] = strlen($out);
    $out .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";

    $offsets[4] = strlen($out);
    $out .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

    $offsets[5] = strlen($out);
    $out .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

    $offsets[6] = strlen($out);
    $out .= "6 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";

    $xref = strlen($out);
    $out .= "xref\n0 7\n0000000000 65535 f \n";
    for ($i = 1; $i <= 6; $i++) {
        $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $out .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

    return $out;
}

/**
 * Generate a responsive, printable HTML invoice for web viewing & printing.
 */
function lm_invoice_html(array $order, array $licenses, string $siteName = ''): string
{
    $companyName = trim((string) setting('invoice_company_name', ''));
    if ($companyName === '') {
        $companyName = $siteName !== '' ? $siteName : (string) setting('site_name', 'ZoomNearby SaaS Platform');
    }
    $taxNumber = trim((string) setting('invoice_tax_number', ''));
    $companyAddress = trim((string) setting('invoice_company_address', ''));
    $footerNote = trim((string) setting('invoice_footer_note', ''));
    if ($footerNote === '') {
        $footerNote = 'Thank you for your purchase! Each license is authentic and bound to your registered domain.';
    }

    $reference = (string) ($order['reference'] ?? 'ORD-0000');
    $domain = (string) ($order['domain'] ?? $order['target_domain'] ?? 'Not specified');
    $email = (string) ($order['email'] ?? 'customer@example.com');
    $amount = number_format((float) ($order['amount'] ?? 0), 2) . ' ' . strtoupper((string) ($order['currency'] ?? 'USD'));
    $gateway = strtoupper((string) ($order['gateway'] ?? 'Online Checkout'));
    $dateStr = date('M d, Y', !empty($order['created_at']) ? strtotime($order['created_at']) : time());
    $publicUrl = rtrim((string) setting('license_public_url', 'https://license.zoomnearby.com'), '/');

    $rows = '';
    foreach ($licenses as $item) {
        $name = (string) ($item['name'] ?? $item['slug'] ?? 'Software License');
        $key = (string) ($item['license_key'] ?? 'Pending');
        $validity = (string) ($item['valid_until'] ?: 'Unlimited');

        $rows .= '<tr style="border-bottom:1px solid #e2e8f0">'
            . '<td style="padding:12px 16px;font-weight:600;color:#1e293b">' . e($name) . '</td>'
            . '<td style="padding:12px 16px;font-family:monospace;color:#475569">' . e($key) . '</td>'
            . '<td style="padding:12px 16px;color:#64748b">' . e($validity) . '</td>'
            . '<td style="padding:12px 16px;text-align:right;color:#0f172a">Included</td>'
            . '</tr>';
    }

    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Invoice - ' . e($reference) . '</title>'
        . '<style>'
        . 'body{margin:0;padding:24px 16px;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#334155;line-height:1.5}'
        . '.invoice-box{max-width:760px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.04)}'
        . '.header{background:#312e81;color:#fff;padding:28px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}'
        . '.content{padding:32px}'
        . '.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;background:#f8fafc;padding:20px;border-radius:10px;border:1px solid #e2e8f0}'
        . 'table{width:100%;border-collapse:collapse;margin-bottom:28px}'
        . 'th{background:#312e81;color:#fff;text-align:left;padding:12px 16px;font-size:13px;font-weight:600}'
        . '.badge-paid{display:inline-block;padding:4px 10px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;border-radius:6px}'
        . '.totals{margin-left:auto;width:300px;background:#f8fafc;padding:18px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:28px}'
        . '.actions{display:flex;gap:12px;justify-content:flex-end;margin-bottom:20px;max-width:760px;margin:0 auto 16px}'
        . '@media print{.actions{display:none}body{background:#fff;padding:0}.invoice-box{border:none;box-shadow:none}}'
        . '</style></head><body>'
        . '<div class="actions">'
        . '<a href="?order=' . urlencode((string) ($order['id'] ?? '')) . '&format=pdf" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600">📥 Download PDF</a>'
        . '<button onclick="window.print()" style="cursor:pointer;padding:8px 16px;background:#fff;border:1px solid #cbd5e1;color:#334155;border-radius:6px;font-size:13px;font-weight:600">🖨️ Print Invoice</button>'
        . '</div>'
        . '<div class="invoice-box">'
        . '<div class="header">'
        . '<div><h1 style="margin:0;font-size:22px">' . e($companyName) . '</h1>'
        . '<p style="margin:4px 0 0;font-size:13px;opacity:0.9">Official Purchase Receipt &amp; Tax Invoice</p>'
        . ($taxNumber !== '' ? '<p style="margin:3px 0 0;font-size:12px;opacity:0.85">Tax ID / GSTIN / VAT: ' . e($taxNumber) . '</p>' : '')
        . ($companyAddress !== '' ? '<p style="margin:3px 0 0;font-size:12px;opacity:0.85">' . e($companyAddress) . '</p>' : '')
        . '</div>'
        . '<div style="text-align:right"><div style="font-size:20px;font-weight:700">INVOICE</div><div style="font-size:13px;opacity:0.9">Date: ' . e($dateStr) . '</div></div>'
        . '</div>'
        . '<div class="content">'
        . '<div class="grid">'
        . '<div><strong style="color:#0f172a;font-size:12px;text-transform:uppercase;letter-spacing:0.5px">Billed To</strong>'
        . '<p style="margin:6px 0 4px;font-size:14px"><strong>Email:</strong> ' . e($email) . '</p>'
        . '<p style="margin:0 0 4px;font-size:14px"><strong>Domain:</strong> ' . e($domain) . '</p>'
        . '<p style="margin:0;font-size:13px;color:#64748b">Gateway: ' . e($gateway) . '</p>'
        . '</div>'
        . '<div><strong style="color:#0f172a;font-size:12px;text-transform:uppercase;letter-spacing:0.5px">Invoice Details</strong>'
        . '<p style="margin:6px 0 4px;font-size:13px"><strong>Reference:</strong> <span style="font-family:monospace;overflow-wrap:anywhere">' . e($reference) . '</span></p>'
        . '<p style="margin:0 0 8px;font-size:13px;color:#64748b">Status: <span class="badge-paid">PAID / COMPLETED</span></p>'
        . '</div>'
        . '</div>'
        . '<table>'
        . '<thead><tr><th>Description</th><th>License Key</th><th>Validity</th><th style="text-align:right">Price</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table>'
        . '<div style="display:flex;justify-content:space-between;align-items:stretch;gap:20px;margin-bottom:28px;flex-wrap:wrap">'
        . '<div style="flex:1 1 280px;background:#f8fafc;padding:18px 20px;border-radius:8px;border:1px solid #e2e8f0;display:flex;flex-direction:column;justify-content:center">'
        . '<div style="color:#166534;font-weight:700;font-size:13px;margin-bottom:6px">✓ PAID IN FULL — THANK YOU!</div>'
        . '<div style="font-size:13px;color:#475569"><strong>Payment Method:</strong> ' . e($gateway) . '</div>'
        . '<div style="font-size:12px;color:#64748b;margin-top:4px">All licenses are authentic and bound to your domain.</div>'
        . '</div>'
        . '<div class="totals" style="margin:0;flex:1 1 260px;max-width:320px;background:#f8fafc;padding:18px 20px;border-radius:8px;border:1px solid #e2e8f0">'
        . '<div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px"><span>Subtotal:</span><span>' . e($amount) . '</span></div>'
        . '<div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px;color:#64748b"><span>Tax / VAT:</span><span>0.00 ' . e((string) ($order['currency'] ?? 'USD')) . '</span></div>'
        . '<div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #e2e8f0;font-size:16px;font-weight:700;color:#1e293b"><span>Total Paid:</span><span>' . e($amount) . '</span></div>'
        . '</div>'
        . '</div>'
        . '<div style="border-top:1px solid #e2e8f0;padding-top:20px;font-size:12px;color:#64748b;text-align:center">'
        . '<p style="margin:0 0 4px">' . e($footerNote) . '</p>'
        . '<p style="margin:0"><a href="' . e($publicUrl) . '" target="_blank" style="color:#4f46e5;text-decoration:none">' . e($companyName) . ' — ' . e($publicUrl) . '</a></p>'
        . '</div>'
        . '</div>'
        . '</div></body></html>';
}
