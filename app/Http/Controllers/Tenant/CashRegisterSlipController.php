<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Company;
use App\Models\PlatformBranding;
use App\Services\CashRegister\CashRegisterReportService;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CashRegisterSlipController extends Controller
{
    /**
     * Download or stream 80mm/58mm Z-Report PDF.
     */
    public function pdfZReport(Request $request, CashRegister $register)
    {
        $reportService = app(CashRegisterReportService::class);
        $format = $request->query('format') ?: $request->query('paperSize', '80mm');
        $pdfBinary = $reportService->generateZReportPdf($register, $format);

        $filename = "Z-Report-Shift-{$register->id}.pdf";
        $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Printable 80mm/58mm HTML slip view for Z-Report in the browser.
     */
    public function viewZReport(Request $request, CashRegister $register)
    {
        $company = $register->company ?? Company::find($register->company_id);
        $branding = PlatformBranding::current();
        $metrics = $register->computeMetrics();
        $format = $request->query('format', $company?->getReceiptFormat() ?? '80mm');
        $is58mm = ($format === '58mm');

        $deliveryService = app(InvoiceDeliveryService::class);
        $logoBase64 = $deliveryService->resolveLogoBase64($company?->logo);
        $reportService = app(CashRegisterReportService::class);
        $whatsAppUrl = $reportService->generateZReportWhatsAppUrl($register);

        $verifyUrl = route('tenant.financials.cash_register');
        $qrCodeSvg = '';
        try {
            $qrCodeSvg = (string) QrCode::size($is58mm ? 70 : 90)->generate($verifyUrl);
        } catch (\Throwable $e) {
        }

        return view('documents.cash-register-z-report-slip', [
            'register' => $register,
            'company' => $company,
            'branding' => $branding,
            'metrics' => $metrics,
            'is58mm' => $is58mm,
            'paperWidth' => $is58mm ? '58mm' : '80mm',
            'logoBase64' => $logoBase64,
            'qrCodeSvg' => $qrCodeSvg,
            'verifyUrl' => $verifyUrl,
            'whatsAppUrl' => $whatsAppUrl,
        ]);
    }

    /**
     * Download or stream 80mm/58mm Cash Drawer Movement PDF.
     */
    public function pdfMovement(Request $request, CashRegisterTransaction $tx)
    {
        $reportService = app(CashRegisterReportService::class);
        $format = $request->query('format') ?: $request->query('paperSize', '80mm');
        $pdfBinary = $reportService->generateMovementPdf($tx, $format);

        $voucherNum = $tx->voucher_number ?? "TX-{$tx->id}";
        $filename = "Voucher-{$voucherNum}.pdf";
        $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Printable 80mm/58mm HTML slip view for Cash Drawer Movement in the browser.
     */
    public function viewMovement(Request $request, CashRegisterTransaction $tx)
    {
        $register = $tx->cashRegister;
        $company = $tx->company ?? ($register?->company ?? Company::find($tx->company_id));
        $branding = PlatformBranding::current();
        $format = $request->query('format', $company?->getReceiptFormat() ?? '80mm');
        $is58mm = ($format === '58mm');

        $deliveryService = app(InvoiceDeliveryService::class);
        $logoBase64 = $deliveryService->resolveLogoBase64($company?->logo);
        $reportService = app(CashRegisterReportService::class);
        $whatsAppUrl = $reportService->generateMovementWhatsAppUrl($tx);

        $verifyUrl = route('tenant.financials.cash_register');
        $qrCodeSvg = '';
        try {
            $qrCodeSvg = (string) QrCode::size($is58mm ? 70 : 90)->generate($verifyUrl);
        } catch (\Throwable $e) {
        }

        return view('documents.cash-register-movement-slip', [
            'tx' => $tx,
            'register' => $register,
            'company' => $company,
            'branding' => $branding,
            'is58mm' => $is58mm,
            'paperWidth' => $is58mm ? '58mm' : '80mm',
            'logoBase64' => $logoBase64,
            'qrCodeSvg' => $qrCodeSvg,
            'verifyUrl' => $verifyUrl,
            'whatsAppUrl' => $whatsAppUrl,
        ]);
    }
}
