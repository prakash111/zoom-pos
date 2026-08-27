<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Show printable/PDF template or download PDF for sale invoice.
     */
    public function pdf(Request $request, Sale $sale)
    {
        $sale->loadMissing('payments');
        $deliveryService = app(InvoiceDeliveryService::class);

        $format = $request->query('format') ?: $request->query('paperSize');
        $fileName = "Invoice-{$sale->sale_number}.pdf";

        if ($request->query('download') == 1) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfContent = $deliveryService->generateInvoicePdf($sale, $format);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($pdfContent),
                'Cache-Control' => 'private, must-revalidate, post-check=0, pre-check=0, max-age=1',
                'Pragma' => 'public',
            ]);
        }

        if ($request->query('stream') == 1) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfContent = $deliveryService->generateInvoicePdf($sale, $format);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Content-Length' => strlen($pdfContent),
            ]);
        }

        $company = $sale->company ?? auth('web')->user()?->company;
        $whatsAppUrl = $deliveryService->generateInvoiceWhatsAppUrl($sale);
        $is58mm = ($format === '58mm' || ($company?->getReceiptFormat() === '58mm' && $format !== '80mm'));
        $qrCodeData = $deliveryService->generateReceiptQrCode($sale, $is58mm);

        $view = ($format === 'standard' || $format === 'a4') ? 'documents.template' : 'documents.receipt';

        return view($view, [
            'sale' => $sale,
            'company' => $company,
            'documentTitle' => 'Cash Receipt / Invoice',
            'isQuotation' => false,
            'whatsAppUrl' => $whatsAppUrl,
            'backRoute' => route('tenant.sales.show', $sale),
            'qrCodeSvg' => $qrCodeData['svg'],
            'qrCodeDataUri' => $qrCodeData['data_uri'],
            'verificationUrl' => $qrCodeData['url'],
        ]);
    }

    /**
     * HTTP API/Web endpoint to dispatch a tax invoice.
     */
    public function send(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email'],
            'custom_message' => ['nullable', 'string', 'max:2000'],
            'attach_pdf' => ['nullable', 'boolean'],
        ]);

        $attachPdf = $request->boolean('attach_pdf', true);

        try {
            $result = app(MessageQueueService::class)->sendOrQueueEmail(
                $sale,
                $validated['recipient_email'],
                $validated['custom_message'] ?? null,
                $attachPdf
            );

            $message = $result['status'] === 'sent'
                ? "Invoice #{$sale->sale_number} sent successfully to {$validated['recipient_email']}."
                : "No connection right now — invoice #{$sale->sale_number} is queued and will send to {$validated['recipient_email']} automatically once you're back online.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'status' => $result['status'],
                    'message' => $message,
                ]);
            }

            return redirect()->back()->with('status', $message);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Failed to send invoice: '.$e->getMessage());
        }
    }

    /**
     * Public shareable invoice view for customers clicking WhatsApp/Email links.
     */
    public function publicShow(Request $request, string $saleNumber)
    {
        $sale = Sale::withoutGlobalScopes()
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope('company')])
            ->where('sale_number', $saleNumber)
            ->firstOrFail();
        $company = $sale->company ?? Company::find($sale->company_id);
        $deliveryService = app(InvoiceDeliveryService::class);
        $whatsAppUrl = $deliveryService->generateInvoiceWhatsAppUrl($sale);
        $format = $request->query('format') ?: $request->query('paperSize');
        $is58mm = ($format === '58mm' || ($company?->getReceiptFormat() === '58mm' && $format !== '80mm'));
        $qrCodeData = $deliveryService->generateReceiptQrCode($sale, $is58mm);

        $view = ($format === 'standard' || $format === 'a4') ? 'documents.template' : 'documents.receipt';

        return view($view, [
            'sale' => $sale,
            'company' => $company,
            'documentTitle' => 'Cash Receipt / Invoice',
            'isQuotation' => false,
            'whatsAppUrl' => $whatsAppUrl,
            'backRoute' => null,
            'qrCodeSvg' => $qrCodeData['svg'],
            'qrCodeDataUri' => $qrCodeData['data_uri'],
            'verificationUrl' => $qrCodeData['url'],
        ]);
    }
}
