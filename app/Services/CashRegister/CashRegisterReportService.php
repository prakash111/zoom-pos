<?php

namespace App\Services\CashRegister;

use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Company;
use App\Models\PlatformBranding;
use App\Services\Invoice\InvoiceDeliveryService;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CashRegisterReportService
{
    /**
     * Generate unique consecutive voucher number for cash drawer movements.
     */
    public function generateVoucherNumber(string $companyId, string $prefix = 'CRV'): string
    {
        $dateStr = now()->format('Ymd');
        $countToday = CashRegisterTransaction::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $dateStr, $countToday);
    }

    /**
     * Generate 80mm/58mm thermal DomPDF binary for a shift Z-Report.
     */
    public function generateZReportPdf(CashRegister $register, string $paperSize = '80mm'): string
    {
        $company = $register->company ?? Company::find($register->company_id);
        $branding = PlatformBranding::current();
        $metrics = $register->computeMetrics();
        $is58mm = ($paperSize === '58mm' || ($company?->getReceiptFormat() === '58mm' && $paperSize !== '80mm'));

        $deliveryService = app(InvoiceDeliveryService::class);
        $logoBase64 = $deliveryService->resolveLogoBase64($company?->logo);

        $verifyUrl = route('tenant.financials.cash_register');
        $qrCodeSvg = '';
        try {
            $qrCodeSvg = (string) QrCode::size($is58mm ? 70 : 90)->generate($verifyUrl);
        } catch (\Throwable $e) {
        }

        $pdf = Pdf::loadView('pdf.cash-register-z-report', [
            'register' => $register,
            'company' => $company,
            'branding' => $branding,
            'metrics' => $metrics,
            'is58mm' => $is58mm,
            'paperWidth' => $is58mm ? '58mm' : '80mm',
            'logoBase64' => $logoBase64,
            'qrCodeSvg' => $qrCodeSvg,
            'verifyUrl' => $verifyUrl,
        ]);

        $pdf->setPaper([0, 0, $is58mm ? 164.41 : 226.77, 1200], 'portrait');

        return $pdf->output();
    }

    /**
     * Generate 80mm/58mm thermal DomPDF binary for a cash drawer movement (Sangria/Suprimento).
     */
    public function generateMovementPdf(CashRegisterTransaction $tx, string $paperSize = '80mm'): string
    {
        $register = $tx->cashRegister;
        $company = $tx->company ?? ($register?->company ?? Company::find($tx->company_id));
        $branding = PlatformBranding::current();
        $is58mm = ($paperSize === '58mm' || ($company?->getReceiptFormat() === '58mm' && $paperSize !== '80mm'));

        $deliveryService = app(InvoiceDeliveryService::class);
        $logoBase64 = $deliveryService->resolveLogoBase64($company?->logo);

        $verifyUrl = route('tenant.financials.cash_register');
        $qrCodeSvg = '';
        try {
            $qrCodeSvg = (string) QrCode::size($is58mm ? 70 : 90)->generate($verifyUrl);
        } catch (\Throwable $e) {
        }

        $pdf = Pdf::loadView('pdf.cash-register-movement', [
            'tx' => $tx,
            'register' => $register,
            'company' => $company,
            'branding' => $branding,
            'is58mm' => $is58mm,
            'paperWidth' => $is58mm ? '58mm' : '80mm',
            'logoBase64' => $logoBase64,
            'qrCodeSvg' => $qrCodeSvg,
            'verifyUrl' => $verifyUrl,
        ]);

        $pdf->setPaper([0, 0, $is58mm ? 164.41 : 226.77, 850], 'portrait');

        return $pdf->output();
    }

    /**
     * Generate structured WhatsApp message URL for Cash Drawer Movement (Sangria/Suprimento).
     */
    public function generateMovementWhatsAppUrl(CashRegisterTransaction $tx, ?string $phone = null): string
    {
        $company = $tx->company ?? Company::find($tx->company_id);
        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $branding = PlatformBranding::current();
        $platformName = $branding?->platform_name ?? config('app.name');
        $currency = $company?->currency ?? 'USD';

        $opName = $tx->getCategoryLabel();
        $sign = $tx->type === 'cash_in' ? '+' : '-';
        $amount = number_format((float) $tx->amount, 2);
        $prevBal = $tx->balance_before !== null ? number_format((float) $tx->balance_before, 2) : '—';
        $newBal = $tx->balance_after !== null ? number_format((float) $tx->balance_after, 2) : '—';
        $cashier = $tx->creator?->name ?? 'Cashier';
        $timeStr = $tx->created_at ? $tx->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $voucherNum = $tx->voucher_number ?? "TX-{$tx->id}";

        $message = "💰 *CASH DRAWER VOUCHER*\n"
            ."*Store:* {$companyName}\n"
            ."*Operation:* {$opName}\n"
            ."*Voucher #:* {$voucherNum}\n"
            ."*Date/Time:* {$timeStr}\n"
            ."*Cashier:* {$cashier}\n"
            ."----------------------------\n"
            ."*Transaction Amount:* {$sign}\${$amount} {$currency}\n"
            ."*Previous Drawer Balance:* \${$prevBal}\n"
            ."*New Drawer Balance:* \${$newBal}\n";

        if (! empty($tx->reason)) {
            $message .= "*Reason / Notes:* {$tx->reason}\n";
        }

        $message .= "----------------------------\n"
            ."Issued via *{$companyName}*\n"
            ."⚡ Powered by {$platformName}";

        $sanitizedPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : '';
        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }

    /**
     * Generate structured WhatsApp message URL for End-of-Day Shift Z-Report Settlement.
     */
    public function generateZReportWhatsAppUrl(CashRegister $register, ?string $phone = null): string
    {
        $company = $register->company ?? Company::find($register->company_id);
        $companyName = $company?->trade_name ?? $company?->name ?? 'Store';
        $branding = PlatformBranding::current();
        $platformName = $branding?->platform_name ?? config('app.name');
        $currency = $company?->currency ?? 'USD';
        $metrics = $register->computeMetrics();

        $opened = $register->opened_at ? $register->opened_at->format('d M Y, h:i A') : '—';
        $closed = $register->closed_at ? $register->closed_at->format('d M Y, h:i A') : 'Still Open (X-Report)';
        $opener = $register->opener?->name ?? 'Cashier';
        $closer = $register->closer?->name ?? ($register->isOpen() ? 'In Progress' : '—');

        $openingFloat = number_format((float) $metrics['opening_balance'], 2);
        $cashSales = number_format((float) $metrics['cash_sales'], 2);
        $nonCashSales = number_format((float) $metrics['non_cash_sales'], 2);
        $totalSales = number_format((float) $metrics['total_sales'], 2);
        $saleCount = $metrics['sale_count'];
        $cashIn = number_format((float) $metrics['cash_in'], 2);
        $cashOut = number_format((float) $metrics['cash_out'], 2);
        $expectedCash = number_format((float) $metrics['expected_cash'], 2);
        $countedCash = number_format((float) $metrics['counted_cash'], 2);
        $variance = (float) $metrics['variance'];
        $varText = ($variance >= 0 ? '+' : '').number_format($variance, 2);
        $statusBadge = $variance == 0 ? '✓ Balanced' : ($variance < 0 ? '⚠️ Short (-'.abs($variance).')' : '⚠️ Over (+'.$variance.')');

        $methodsText = '';
        foreach ($metrics['payments_by_method'] as $method => $data) {
            $mTotal = number_format((float) $data['total'], 2);
            $mCount = $data['count'];
            $methodsText .= '• '.ucfirst($method)." ({$mCount}): \${$mTotal}\n";
        }

        $message = "📊 *END-OF-DAY REGISTER Z-REPORT*\n"
            ."*Store:* {$companyName}\n"
            ."*Session #:* #{$register->id} (".($register->isOpen() ? 'OPEN' : 'CLOSED').")\n"
            ."*Shift Opened:* {$opened} by {$opener}\n"
            ."*Shift Closed:* {$closed} by {$closer}\n"
            ."----------------------------\n"
            ."*FINANCIAL SUMMARY:*\n"
            ."• *Opening Float:* \${$openingFloat}\n"
            ."• *Total Gross Sales:* \${$totalSales} ({$saleCount} sales)\n"
            ."  - Cash Sales: \${$cashSales}\n"
            ."  - Non-Cash Sales: \${$nonCashSales}\n"
            ."• *Cash In (Suprimento):* +\${$cashIn}\n"
            ."• *Cash Out (Sangria):* -\${$cashOut}\n"
            ."----------------------------\n"
            ."*CASH RECONCILIATION:*\n"
            ."• *Expected Drawer Balance:* \${$expectedCash} {$currency}\n"
            ."• *Physical Counted Cash:* \${$countedCash} {$currency}\n"
            ."• *Cash Difference / Variance:* \${$varText} [{$statusBadge}]\n"
            ."----------------------------\n"
            ."*SALES BY PAYMENT METHOD:*\n"
            .($methodsText ?: "• No transactions recorded\n")
            ."----------------------------\n";

        if (! empty($register->notes)) {
            $message .= "*Closing Remarks:* {$register->notes}\n----------------------------\n";
        }

        $message .= "Issued via *{$companyName}*\n"
            ."⚡ Powered by {$platformName}";

        $sanitizedPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : '';
        if (! empty($sanitizedPhone)) {
            return "https://wa.me/{$sanitizedPhone}?text=".rawurlencode($message);
        }

        return 'https://api.whatsapp.com/send?text='.rawurlencode($message);
    }
}
