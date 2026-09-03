<?php

namespace App\Services\Invoice;

use App\Mail\DueReminderMailable;
use App\Mail\InvoiceMailable;
use App\Mail\QuotationMailable;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\PlatformBranding;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoiceDeliveryService
{
    /**
     * Bundled Devanagari-script font (resources/fonts/), base64-encoded once
     * per process for @font-face embedding in pdf.receipt/pdf.invoice — see
     * devanagariFontData(). DejaVu Sans (dompdf's default PDF font) has no
     * Devanagari glyphs at all, so a Hindi-locale document's translated
     * labels rendered under it come out as tofu boxes; dompdf also doesn't
     * do per-glyph font fallback across a CSS font-family list (confirmed
     * empirically — it picks one font per styled element regardless of
     * glyph coverage), so the fix is to wrap just the translated-label text
     * in a class backed by this font, rather than swapping the document's
     * base font.
     */
    private static ?string $devanagariRegularBase64 = null;

    private static ?string $devanagariBoldBase64 = null;

    /**
     * @return array{notoDevanagariRegularBase64: string, notoDevanagariBoldBase64: string}
     */
    private function devanagariFontData(): array
    {
        self::$devanagariRegularBase64 ??= base64_encode(
            file_get_contents(resource_path('fonts/NotoSansDevanagari-Regular.ttf'))
        );
        self::$devanagariBoldBase64 ??= base64_encode(
            file_get_contents(resource_path('fonts/NotoSansDevanagari-Bold.ttf'))
        );

        return [
            'notoDevanagariRegularBase64' => self::$devanagariRegularBase64,
            'notoDevanagariBoldBase64' => self::$devanagariBoldBase64,
        ];
    }

    /**
     * Resolves SMTP configuration for a tenant with fallback to platform settings.
     */
    public function getSmtpConfig(?Company $company = null): array
    {
        $companyId = $company?->id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : auth('web')->user()?->company_id);

        $tenantConfigs = [];
        if ($companyId) {
            $tenantConfigs = Configuration::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('key', 'like', 'smtp_%')
                ->pluck('value', 'key')
                ->all();
        }

        $host = $tenantConfigs['smtp_host'] ?? null;
        $port = ! empty($tenantConfigs['smtp_port']) ? (int) $tenantConfigs['smtp_port'] : null;
        $username = $tenantConfigs['smtp_username'] ?? null;
        $password = $tenantConfigs['smtp_password'] ?? null;
        $encryption = $tenantConfigs['smtp_encryption'] ?? 'tls';
        $fromAddress = $tenantConfigs['smtp_from_address'] ?? ($company?->email ?? null);
        $fromName = $tenantConfigs['smtp_from_name'] ?? ($company?->name ?? 'Store');

        // Fallback to platform settings if tenant has no host configured
        if (empty($host)) {
            $branding = PlatformBranding::current();
            $host = $branding?->smtp_host;
            $port = $branding?->smtp_port ?? 587;
            $username = $branding?->smtp_username;
            $password = $branding?->smtp_password;
            $encryption = $branding?->smtp_encryption ?? 'tls';
            $fromAddress = $fromAddress ?: ($branding?->smtp_from_address ?: ($branding?->support_email ?: config('mail.from.address')));
            $fromName = $fromName ?: ($branding?->smtp_from_name ?: ($branding?->platform_name ?: config('mail.from.name')));
        }

        // Fallback to default config
        if (empty($host)) {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port') ?: 587;
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');
            $encryption = config('mail.mailers.smtp.encryption') ?: 'tls';
            $fromAddress = $fromAddress ?: config('mail.from.address');
            $fromName = $fromName ?: config('mail.from.name');
        }

        return [
            'host' => $host,
            'port' => $port ?: 587,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => $fromAddress ?: 'no-reply@saas.zoomnearby.com',
            'from_name' => $fromName ?: 'Store',
        ];
    }

    /**
     * Resolve company logo to base64 Data URI for reliable offline/DomPDF rendering.
     */
    public function resolveLogoBase64(?string $logo): ?string
    {
        if (empty($logo)) {
            return null;
        }

        if (str_starts_with($logo, 'data:image/')) {
            return $logo;
        }

        $candidates = [
            public_path($logo),
            public_path(ltrim($logo, '/')),
            storage_path('app/public/'.ltrim($logo, '/')),
            storage_path('app/'.ltrim($logo, '/')),
        ];

        if (str_starts_with($logo, '/storage/') || str_starts_with($logo, 'storage/')) {
            $relativePath = preg_replace('#^/?storage/#', '', $logo);
            $candidates[] = storage_path('app/public/'.$relativePath);
            $candidates[] = public_path('storage/'.$relativePath);
        }

        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $mime = @mime_content_type($filePath) ?: 'image/png';
                $data = base64_encode(file_get_contents($filePath));

                return "data:{$mime};base64,{$data}";
            }
        }

        if (filter_var($logo, FILTER_VALIDATE_URL) && (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://'))) {
            try {
                $response = Http::timeout(3)->get($logo);
                if ($response->successful()) {
                    $mime = $response->header('Content-Type') ?: 'image/png';
                    $mime = explode(';', $mime)[0];
                    $data = base64_encode($response->body());

                    return "data:{$mime};base64,{$data}";
                }
            } catch (\Throwable $e) {
                Log::warning("Could not fetch remote logo {$logo} for PDF: ".$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Generate raw binary PDF content for a quotation.
     */
    public function generateQuotationPdf(Sale $quote, ?string $format = null): string
    {
        if ($format === '80mm' || $format === '58mm') {
            return $this->generateInvoicePdf($quote, $format);
        }

        $company = $quote->company ?? Company::find($quote->company_id);
        $logoBase64 = $this->resolveLogoBase64($company?->logo);

        $previousTenantBound = app()->bound('tenant.company_id');
        $previousTenantId = $previousTenantBound ? app('tenant.company_id') : null;
        $previousLocale = app()->getLocale();

        try {
            if ($company) {
                app()->instance('tenant.company_id', $company->id);
                $targetLocale = $company->default_locale ?: ($company->language ?: $previousLocale);
                if ($targetLocale) {
                    app()->setLocale($targetLocale);
                }
            }

            $pdf = Pdf::loadView('pdf.quotation', [
                'sale' => $quote,
                'company' => $company,
                'logoBase64' => $logoBase64,
            ])->setPaper('a4', 'portrait')
              ->setOptions([
                  'isHtml5ParserEnabled' => true,
                  'isRemoteEnabled' => true,
                  'defaultFont' => 'sans-serif',
              ]);

            return $pdf->output();
        } finally {
            if ($previousTenantBound) {
                app()->instance('tenant.company_id', $previousTenantId);
            }
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Generate raw binary PDF content for a sales invoice or thermal receipt.
     */
    public function generateInvoicePdf(Sale $sale, ?string $format = null): string
    {
        $sale->loadMissing(['payments', 'user']);
        $company = $sale->company ?? Company::find($sale->company_id);
        $logoBase64 = $this->resolveLogoBase64($company?->logo);

        $selectedFormat = $format ?: ($company?->getReceiptFormat() ?: '80mm');

        $previousTenantBound = app()->bound('tenant.company_id');
        $previousTenantId = $previousTenantBound ? app('tenant.company_id') : null;
        $previousLocale = app()->getLocale();

        try {
            if ($company) {
                app()->instance('tenant.company_id', $company->id);
                $targetLocale = $company->default_locale ?: ($company->language ?: $previousLocale);
                if ($targetLocale) {
                    app()->setLocale($targetLocale);
                }
            }

            if ($selectedFormat === 'standard' || $selectedFormat === 'a4') {
                $pdf = Pdf::loadView('pdf.invoice', [
                    'sale' => $sale,
                    'company' => $company,
                    'logoBase64' => $logoBase64,
                    ...$this->devanagariFontData(),
                ])->setPaper('a4', 'portrait')
                  ->setOptions([
                      'isHtml5ParserEnabled' => true,
                      'isRemoteEnabled' => true,
                      'defaultFont' => 'sans-serif',
                  ]);

                return $pdf->output();
            }

            // Thermal receipt mode (58mm or 80mm)
            $is58mm = ($selectedFormat === '58mm');
            $paperSize = $is58mm ? '58mm' : '80mm';
            $itemCount = count($sale->items ?? []);
            $paymentCount = count($sale->payments ?? []);
            $qrCodeData = $this->generateReceiptQrCode($sale, $is58mm);

            $hasNotes = ! empty($sale->notes);
            $hasTerms = ! empty($sale->terms ?? ($company?->invoice_terms ?? null));
            $hasLogo = ! empty($logoBase64);
            $hasQr = ! empty($qrCodeData['data_uri']) || ! empty($qrCodeData['svg']);

            // Tightly calculated heights in mm
            $headerHeightMm = ($hasLogo ? 14 : 0) + 26; // Logo, store name, address, tel, tax ID, receipt type
            $metaHeightMm = 17 + ($sale->table_name ? 3.5 : 0); // Receipt#, date, customer, staff, payment, status (+ table)
            $tableHeadMm = 6; // ITEM / QTY / TOTAL headers + dashed line

            // Item rows height: account for long name line-wrapping and sublines (e.g. qty x unit price or standard price)
            $itemsHeightMm = 0;
            foreach ($sale->items ?? [] as $it) {
                $name = (string) ($it['name'] ?? 'Item');
                $nameLines = max(1, (int) ceil(mb_strlen($name) / ($is58mm ? 16 : 24)));
                $qty = (float) ($it['quantity'] ?? 1);
                $hasSubline = ($qty > 1 || ! empty($it['is_overridden']));
                $rowHeight = ($nameLines * 3.8) + ($hasSubline ? 3.2 : 0) + 2.0;
                $itemsHeightMm += max(5.5, $rowHeight);
            }

            // Totals breakdown: Subtotal, Total, Paid, Change (+ discount/tax/multiple payments if present)
            $totalsHeightMm = 16;
            if ((float) $sale->discount > 0) {
                $totalsHeightMm += 3.5;
            }
            if ((float) $sale->tax > 0) {
                $totalsHeightMm += 3.5;
            }
            if ((float) $sale->due_amount > 0) {
                $totalsHeightMm += 3.5;
            }
            if ($paymentCount > 1) {
                $totalsHeightMm += ($paymentCount * 3.5);
            }

            $notesHeightMm = $hasNotes ? max(8, (int) ceil(mb_strlen((string) $sale->notes) / ($is58mm ? 25 : 38)) * 4.0) : 0;
            $termsHeightMm = $hasTerms ? max(8, (int) ceil(mb_strlen((string) ($sale->terms ?? ($company?->invoice_terms ?? ''))) / ($is58mm ? 25 : 38)) * 4.0) : 0;
            $qrFooterHeightMm = ($hasQr ? ($is58mm ? 30 : 38) : 0) + ($is58mm ? 18 : 20); // QR code + caption + ref code + thank you + website

            $totalHeightMm = $headerHeightMm + $metaHeightMm + $tableHeadMm + $itemsHeightMm + $totalsHeightMm + $notesHeightMm + $termsHeightMm + $qrFooterHeightMm;

            // 1mm = 2.83465 pt; add a tiny 4mm safety margin to guarantee zero page-break splits
            $widthPt = ($paperSize === '58mm') ? 164.41 : 226.77;
            $heightPt = ($totalHeightMm + 4) * 2.83465;
            $customPaper = [0, 0, $widthPt, $heightPt];

            $pdf = Pdf::loadView('pdf.receipt', [
                'sale' => $sale,
                'company' => $company,
                'logoBase64' => $logoBase64,
                'is58mm' => $is58mm,
                'paperWidth' => $paperSize,
                'paperSize' => $paperSize,
                'calculatedHeight' => $heightPt,
                'qrCodeDataUri' => $qrCodeData['data_uri'],
                'qrCodeSvg' => $qrCodeData['svg'],
                'verificationUrl' => $qrCodeData['url'],
                ...$this->devanagariFontData(),
            ])->setPaper($customPaper, 'portrait')
                ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif']);

            return $pdf->output();
        } finally {
            if ($previousTenantBound) {
                app()->instance('tenant.company_id', $previousTenantId);
            }
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Generate QR Code as inline SVG and Base64 Data URI for digital receipt verification.
     */
    public function generateReceiptQrCode(Sale $sale, bool $is58mm = false): array
    {
        $verificationUrl = $sale->operation_type === 'quotation'
            ? route('quotes.public', $sale->sale_number)
            : route('sales.public', $sale->sale_number);

        $size = $is58mm ? 70 : 90;

        try {
            if (class_exists(QrCode::class)) {
                $svg = (string) QrCode::format('svg')
                    ->size($size)
                    ->margin(0)
                    ->errorCorrection('M')
                    ->generate($verificationUrl);

                $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);

                return [
                    'svg' => $svg,
                    'data_uri' => $dataUri,
                    'url' => $verificationUrl,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('QR Code generation error: '.$e->getMessage());
        }

        return [
            'svg' => '',
            'data_uri' => '',
            'url' => $verificationUrl,
        ];
    }

    /**
     * Format a custom message string with smart placeholder replacements.
     */
    public function formatCustomMessage(string $template, Sale $document, ?Company $company = null): string
    {
        $company ??= $document->company ?? Company::find($document->company_id);
        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $customerName = $document->customer_name ?: 'Valued Client';

        $itemsSummary = '';
        $subtotal = 0;
        foreach ($document->items ?? [] as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
            $priceFormatted = $company ? $company->formatMoney($price) : '$'.number_format($price, 2);
            $name = $item['name'] ?? 'Item';
            $itemsSummary .= "• {$name} (x{$qty}) - {$priceFormatted}\n";
        }

        $totalFormatted = $company ? $company->formatMoney($document->total) : '$'.number_format((float) $document->total, 2);
        $subtotalFormatted = $company ? $company->formatMoney($subtotal) : '$'.number_format((float) $subtotal, 2);
        $discountFormatted = $company ? $company->formatMoney($document->discount) : '$'.number_format((float) $document->discount, 2);

        $dueDate = $document->due_date
            ? $document->due_date->format('d M Y')
            : ($document->created_at ? $document->created_at->addDays(15)->format('d M Y') : now()->addDays(15)->format('d M Y'));

        $downloadLink = $document->operation_type === 'quotation'
            ? route('quotes.public', $document->sale_number)
            : route('sales.public', $document->sale_number);

        $placeholders = [
            '{customer_name}' => $customerName,
            '{client_name}' => $customerName,
            '{document_number}' => $document->sale_number,
            '{quote_number}' => $document->sale_number,
            '{invoice_number}' => $document->sale_number,
            '{company_name}' => $companyName,
            '{total_amount}' => $totalFormatted,
            '{subtotal}' => $subtotalFormatted,
            '{discount}' => $discountFormatted,
            '{expiry_date}' => $dueDate,
            '{due_date}' => $dueDate,
            '{download_link}' => $downloadLink,
            '{date}' => $document->created_at ? $document->created_at->format('d M Y') : now()->format('d M Y'),
            '{payment_method}' => ucfirst($document->payment_method ?? 'Cash'),
            '{status}' => ucfirst($document->status ?? 'Active'),
            '{notes}' => $document->notes ?? '',
            '{items_summary}' => trim($itemsSummary),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Returns default text template for Quotations.
     */
    public function getDefaultQuotationMessage(Sale $quote, ?Company $company = null): string
    {
        return "Hello {customer_name},\n\nPlease find attached quotation proposal #{document_number} for {total_amount} from {company_name}. This estimate is valid until {expiry_date}.\n\nYou can review your proposal online here: {download_link}\n\nThank you for considering {company_name}!";
    }

    /**
     * Returns default text template for Invoices.
     */
    public function getDefaultInvoiceMessage(Sale $sale, ?Company $company = null): string
    {
        return "Hello {customer_name},\n\nThank you for your business! Please find your official Tax Invoice & Receipt #{document_number} for {total_amount} from {company_name}.\n\nYou can review or download your invoice here: {download_link}\n\nHave a great day!";
    }

    /**
     * Send Quotation Proposal email to client using dedicated Quotation Mailable.
     */
    public function sendQuotationEmail(Sale $quote, string $recipientEmail, ?string $customMessage = null, bool $attachPdf = true): void
    {
        $cleanEmail = trim($recipientEmail);
        if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email address: '{$recipientEmail}'. Please enter a valid email.");
        }

        $company = $quote->company ?? Company::find($quote->company_id);
        $smtp = $this->getSmtpConfig($company);

        $pdfBinary = null;
        if ($attachPdf) {
            try {
                $pdfBinary = $this->generateQuotationPdf($quote);
            } catch (\Throwable $pdfEx) {
                Log::error("Quotation PDF generation error for #{$quote->sale_number}: ".$pdfEx->getMessage(), [
                    'quote_id' => $quote->id,
                    'trace' => $pdfEx->getTraceAsString(),
                ]);
                throw new \RuntimeException('Failed to generate PDF attachment: '.$pdfEx->getMessage(), 0, $pdfEx);
            }
        }

        $formattedMessage = $customMessage ? $this->formatCustomMessage($customMessage, $quote, $company) : null;
        $mailable = new QuotationMailable($quote, $company, $formattedMessage, $attachPdf, $pdfBinary);

        if (app()->environment('testing') || config('mail.default') === 'array') {
            Mail::to($cleanEmail)->send($mailable);

            if ($quote->status === 'draft') {
                $quote->update(['status' => 'sent']);
            }

            AuditLog::record('quotation.emailed', $quote->company_id, auth('web')->id(), [
                'quote_id' => $quote->id,
                'recipient' => $cleanEmail,
                'attached_pdf' => $attachPdf,
            ]);

            return;
        }

        if (empty($smtp['host'])) {
            throw new \RuntimeException('SMTP host is not configured for your store or platform. Please configure SMTP in Settings.');
        }

        Config::set('mail.mailers.tenant_dynamic', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 15,
        ]);

        try {
            Mail::mailer('tenant_dynamic')->to($cleanEmail)->send($mailable);
        } catch (\Throwable $mailEx) {
            Log::error("SMTP Delivery Exception for quote #{$quote->sale_number} to {$cleanEmail}: ".$mailEx->getMessage(), [
                'quote_id' => $quote->id,
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'from_address' => $smtp['from_address'],
                'exception' => $mailEx->getMessage(),
            ]);
            throw new \RuntimeException('Email delivery failed: '.$mailEx->getMessage(), 0, $mailEx);
        }

        if ($quote->status === 'draft') {
            $quote->update(['status' => 'sent']);
        }

        AuditLog::record('quotation.emailed', $quote->company_id, auth('web')->id(), [
            'quote_id' => $quote->id,
            'recipient' => $cleanEmail,
            'attached_pdf' => $attachPdf,
        ]);
    }

    /**
     * Send Invoice & Receipt email to customer using dedicated Invoice Mailable.
     */
    public function sendInvoiceEmail(Sale $sale, string $recipientEmail, ?string $customMessage = null, bool $attachPdf = true): void
    {
        $cleanEmail = trim($recipientEmail);
        if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email address: '{$recipientEmail}'. Please enter a valid email.");
        }

        $company = $sale->company ?? Company::find($sale->company_id);
        $smtp = $this->getSmtpConfig($company);

        $pdfBinary = null;
        if ($attachPdf) {
            try {
                $pdfBinary = $this->generateInvoicePdf($sale);
            } catch (\Throwable $pdfEx) {
                Log::error("Invoice PDF generation error for #{$sale->sale_number}: ".$pdfEx->getMessage(), [
                    'sale_id' => $sale->id,
                    'trace' => $pdfEx->getTraceAsString(),
                ]);
                throw new \RuntimeException('Failed to generate PDF attachment: '.$pdfEx->getMessage(), 0, $pdfEx);
            }
        }

        $formattedMessage = $customMessage ? $this->formatCustomMessage($customMessage, $sale, $company) : null;
        $mailable = new InvoiceMailable($sale, $company, $formattedMessage, $attachPdf, $pdfBinary);

        if (app()->environment('testing') || config('mail.default') === 'array') {
            Mail::to($cleanEmail)->send($mailable);

            AuditLog::record('invoice.emailed', $sale->company_id, auth('web')->id(), [
                'sale_id' => $sale->id,
                'recipient' => $cleanEmail,
                'attached_pdf' => $attachPdf,
            ]);

            return;
        }

        if (empty($smtp['host'])) {
            throw new \RuntimeException('SMTP host is not configured for your store or platform. Please configure SMTP in Settings.');
        }

        Config::set('mail.mailers.tenant_dynamic', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 15,
        ]);

        try {
            Mail::mailer('tenant_dynamic')->to($cleanEmail)->send($mailable);
        } catch (\Throwable $mailEx) {
            Log::error("SMTP Delivery Exception for invoice #{$sale->sale_number} to {$cleanEmail}: ".$mailEx->getMessage(), [
                'sale_id' => $sale->id,
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'from_address' => $smtp['from_address'],
                'exception' => $mailEx->getMessage(),
            ]);
            throw new \RuntimeException('Email delivery failed: '.$mailEx->getMessage(), 0, $mailEx);
        }

        AuditLog::record('invoice.emailed', $sale->company_id, auth('web')->id(), [
            'sale_id' => $sale->id,
            'recipient' => $cleanEmail,
            'attached_pdf' => $attachPdf,
        ]);
    }

    /**
     * Plain-text WhatsApp message body for a quotation — shared by the wa.me
     * link generator below and by MessageQueueService's real API send, so
     * the wording never drifts between the two delivery paths.
     */
    public function buildQuotationWhatsAppMessage(Sale $quote, ?string $customMessage = null): string
    {
        $company = $quote->company ?? Company::find($quote->company_id);

        if (! empty($customMessage)) {
            return $this->formatCustomMessage($customMessage, $quote, $company);
        }

        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $customerName = $quote->customer_name ?: 'Valued Client';
        $currency = $company?->currency ?? 'USD';

        $itemsText = '';
        foreach ($quote->items ?? [] as $item) {
            $qty = $item['quantity'] ?? 1;
            $price = number_format((float) ($item['price'] ?? 0), 2);
            $name = $item['name'] ?? 'Item';
            $itemsText .= "• {$name} (x{$qty}) - \${$price}\n";
        }

        $subtotal = number_format((float) $quote->total + (float) $quote->discount, 2);
        $discountText = $quote->discount > 0 ? "\n*Discount:* -\$".number_format((float) $quote->discount, 2) : '';
        $total = number_format((float) $quote->total, 2);
        $date = $quote->created_at ? $quote->created_at->format('d M Y') : now()->format('d M Y');
        $validUntil = $quote->due_date ? $quote->due_date->format('d M Y') : ($quote->created_at ? $quote->created_at->addDays(15)->format('d M Y') : now()->addDays(15)->format('d M Y'));
        $publicLink = route('quotes.public', $quote->sale_number);

        return "📋 *QUOTATION PROPOSAL / PRICE ESTIMATE*\n"
            ."*Store:* {$companyName}\n"
            ."*Quote No:* #{$quote->sale_number}\n"
            ."*Date:* {$date}\n"
            ."*Valid Until:* {$validUntil}\n"
            ."*Client:* {$customerName}\n\n"
            ."*Items Estimate:*\n"
            ."{$itemsText}\n"
            ."----------------------------\n"
            ."*Subtotal:* \${$subtotal}"
            ."{$discountText}\n"
            ."*Total Estimate:* \${$total} {$currency}\n"
            ."----------------------------\n"
            ."🔗 *View & Accept Proposal Online:*\n{$publicLink}\n\n"
            ."Thank you for considering {$companyName}! ✨";
    }

    /**
     * Generate WhatsApp web / app link with formatted quotation estimate text.
     */
    public function generateQuotationWhatsAppUrl(Sale $quote, ?string $phone = null, ?string $customMessage = null): string
    {
        $message = $this->buildQuotationWhatsAppMessage($quote, $customMessage);

        $sanitizedPhone = '';
        if ($phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $phone);
        } elseif ($quote->customer?->phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $quote->customer->phone);
        }

        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }

    /**
     * Plain-text WhatsApp message body for an invoice/receipt — shared by the
     * wa.me link generator below and by MessageQueueService's real API send.
     */
    public function buildInvoiceWhatsAppMessage(Sale $sale, ?string $customMessage = null): string
    {
        $company = $sale->company ?? Company::find($sale->company_id);

        if (! empty($customMessage)) {
            return $this->formatCustomMessage($customMessage, $sale, $company);
        }

        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $customerName = $sale->customer_name ?: 'Valued Customer';
        $currency = $company?->currency ?? 'USD';
        $sym = $company?->currency_symbol ?: ($currency === 'INR' ? '₹' : '$');
        $isIndia = in_array(strtoupper(trim((string)($company?->country ?? ''))), ['IN', 'IND', 'INDIA'], true) || $currency === 'INR' || $sym === '₹';
        $taxLabel = $isIndia ? 'GSTIN' : 'Tax ID';

        $itemsText = '';
        foreach ($sale->items ?? [] as $item) {
            $qty = $item['quantity'] ?? 1;
            $price = number_format((float) ($item['price'] ?? 0), 2);
            $name = $item['name'] ?? 'Item';
            $itemsText .= "• {$name} (x{$qty}) - {$sym}{$price}\n";
        }

        $subtotal = number_format((float) ($sale->total - ($sale->tax_amount ?? 0) + $sale->discount), 2);
        $discountText = $sale->discount > 0 ? "\n*Discount:* -{$sym}".number_format((float) $sale->discount, 2) : '';
        $taxText = (float)($sale->tax_amount ?? 0) > 0 ? "\n*".($isIndia ? 'GST' : 'Tax').":* +{$sym}".number_format((float) $sale->tax_amount, 2) : '';
        $total = number_format((float) $sale->total, 2);
        $date = $sale->created_at ? $sale->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $publicLink = route('sales.public', $sale->sale_number);
        $taxIdLine = !empty($company?->tax_id) ? "*{$taxLabel}:* {$company->tax_id}\n" : '';

        return "🧾 *" . ($isIndia ? 'TAX INVOICE / GST RECEIPT' : 'TAX INVOICE RECEIPT') . "*\n"
            ."*Store:* {$companyName}\n"
            . $taxIdLine
            ."*Invoice:* #{$sale->sale_number}\n"
            ."*Date:* {$date}\n"
            ."*Customer:* {$customerName}\n\n"
            ."*Items:*\n"
            ."{$itemsText}\n"
            ."----------------------------\n"
            ."*Subtotal:* {$sym}{$subtotal}"
            ."{$discountText}"
            ."{$taxText}\n"
            ."*Total Paid:* {$sym}{$total} {$currency}\n"
            .'*Payment:* '.ucfirst($sale->payment_method ?? 'Cash')."\n"
            .'*Status:* '.ucfirst($sale->status ?? 'Completed')."\n"
            ."----------------------------\n"
            ."🔗 *View Official Receipt Online:*\n{$publicLink}\n\n"
            ."Thank you for shopping with {$companyName}! Have a great day! ✨";
    }

    /**
     * Generate WhatsApp web / app link with formatted invoice receipt text.
     */
    public function generateInvoiceWhatsAppUrl(Sale $sale, ?string $phone = null, ?string $customMessage = null): string
    {
        $message = $this->buildInvoiceWhatsAppMessage($sale, $customMessage);

        $sanitizedPhone = '';
        if ($phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $phone);
        } elseif ($sale->customer?->phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $sale->customer->phone);
        }

        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }

    /**
     * Unified WhatsApp URL generator with automatic document type detection.
     */
    public function generateWhatsAppUrl(Sale $sale, ?string $phone = null, ?string $customMessage = null): string
    {
        if ($sale->operation_type === 'quotation') {
            return $this->generateQuotationWhatsAppUrl($sale, $phone, $customMessage);
        }

        return $this->generateInvoiceWhatsAppUrl($sale, $phone, $customMessage);
    }

    /**
     * Plain-text WhatsApp body for a due-payment reminder against a
     * partially-paid or unpaid sale.
     */
    public function buildDueReminderMessage(Sale $sale): string
    {
        $company = $sale->company ?? Company::find($sale->company_id);
        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $customerName = $sale->customer_name ?: 'Valued Customer';
        $sym = $company?->currency_symbol ?: '$';
        $due = number_format((float) $sale->due_amount, 2);
        $total = number_format((float) $sale->total, 2);
        $paid = number_format((float) $sale->paid_amount, 2);
        $dueDate = $sale->due_date ? $sale->due_date->format('d M Y') : 'as soon as possible';
        $publicLink = route('sales.public', $sale->sale_number);

        return "💳 *Payment Reminder*\n"
            ."*Store:* {$companyName}\n"
            ."*Invoice:* #{$sale->sale_number}\n"
            ."Hi {$customerName}, this is a friendly reminder of an outstanding balance:\n\n"
            ."*Total:* {$sym}{$total}\n"
            ."*Paid:* {$sym}{$paid}\n"
            ."*Due:* {$sym}{$due}\n"
            ."*Due Date:* {$dueDate}\n\n"
            ."🔗 *View Invoice:*\n{$publicLink}\n\n"
            ."Thank you for your business — {$companyName}";
    }

    public function generateDueReminderWhatsAppUrl(Sale $sale, ?string $phone = null): string
    {
        $message = $this->buildDueReminderMessage($sale);

        $sanitizedPhone = '';
        if ($phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $phone);
        } elseif ($sale->customer?->phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $sale->customer->phone);
        }

        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }

    /**
     * Send a due-payment reminder email. Throws if no SMTP host resolves
     * (tenant, then platform, then .env) — callers should build a mailto:
     * fallback instead of calling this when that's the case.
     */
    public function sendDueReminderEmail(Sale $sale, string $recipientEmail): void
    {
        $cleanEmail = trim($recipientEmail);
        if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email address: '{$recipientEmail}'. Please enter a valid email.");
        }

        $company = $sale->company ?? Company::find($sale->company_id);
        $smtp = $this->getSmtpConfig($company);

        if (empty($smtp['host'])) {
            throw new \RuntimeException('SMTP host is not configured for your store or platform.');
        }

        $mailable = new DueReminderMailable($sale, $company);

        if (app()->environment('testing') || config('mail.default') === 'array') {
            Mail::to($cleanEmail)->send($mailable);

            return;
        }

        Config::set('mail.mailers.tenant_dynamic', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 15,
        ]);

        Mail::mailer('tenant_dynamic')->to($cleanEmail)->send($mailable);
    }

    /**
     * Send test email using provided or saved SMTP settings.
     */
    public function sendTestEmail(Company $company, string $recipientEmail, array $config = []): void
    {
        $smtp = array_merge($this->getSmtpConfig($company), array_filter($config));

        if (empty($smtp['host'])) {
            throw new \RuntimeException('SMTP host is required.');
        }

        Config::set('mail.mailers.tenant_test', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 10,
        ]);

        Mail::mailer('tenant_test')->raw(
            "Hello!\n\nThis is a test email verifying that SMTP email settings for {$company->name} are configured and working properly.",
            function ($message) use ($recipientEmail, $company, $smtp) {
                $message->to($recipientEmail)
                    ->from($smtp['from_address'], $smtp['from_name'])
                    ->subject("SMTP Test Email - {$company->name}");
            }
        );
    }

    /**
     * Send email invitation to newly added/invited team member.
     */
    public function sendInvitationEmail(User $user, string $plainCode, ?string $recipientEmail = null): void
    {
        $company = $user->company ?? Company::find($user->company_id);
        $smtp = $this->getSmtpConfig($company);

        if (empty($smtp['host'])) {
            throw new \RuntimeException('SMTP host is not configured. Please configure SMTP in Settings or use WhatsApp/Copy Link.');
        }

        Config::set('mail.mailers.tenant_invite', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 15,
        ]);

        $companyName = $company?->name ?? 'Store';
        $email = $recipientEmail ?: $user->email;
        $inviteUrl = route('accept-invite', ['email' => $user->email, 'code' => $plainCode]);

        Mail::mailer('tenant_invite')->send('emails.user-invitation', [
            'user' => $user,
            'company' => $company,
            'plainCode' => $plainCode,
            'inviteUrl' => $inviteUrl,
        ], function ($message) use ($email, $companyName, $smtp) {
            $message->to($email)
                ->from($smtp['from_address'], $smtp['from_name'])
                ->subject("Team Invitation: Join {$companyName} on ".config('app.name'));
        });

        AuditLog::record('user.invitation_emailed', $user->company_id, auth('web')->id(), [
            'user_id' => $user->id,
            'recipient' => $email,
        ]);
    }

    /**
     * Generate WhatsApp link to share team invitation with formatted message and one-click code.
     */
    public function generateInvitationWhatsAppUrl(User $user, string $plainCode, ?string $phone = null): string
    {
        $company = $user->company ?? Company::find($user->company_id);
        $companyName = $company?->name ?? 'Store';
        $roleName = User::ROLES[$user->role] ?? ucfirst($user->role);
        $inviteUrl = route('accept-invite', ['email' => $user->email, 'code' => $plainCode]);

        $message = "👋 *Hello {$user->name}!* \n\n"
            ."You have been invited to join *{$companyName}* on *".config('app.name')."* as *{$roleName}*.\n\n"
            ."🔑 *Your One-Time Invitation Code:* `{$plainCode}`\n"
            ."🔗 *Accept Invitation Link:*\n{$inviteUrl}\n\n"
            .'Click the link above to set your password and access your dashboard. Welcome to the team! ✨';

        $sanitizedPhone = '';
        if ($phone) {
            $sanitizedPhone = preg_replace('/[^0-9]/', '', $phone);
        }

        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }
}
