<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    /**
     * Show printable/PDF template or download PDF for quotation.
     */
    public function pdf(Request $request, Sale $quote)
    {
        $quote->loadMissing(['customer', 'user']);
        $deliveryService = app(InvoiceDeliveryService::class);
        $company = $quote->company ?? auth('web')->user()?->company;
        $format = $request->query('format');
        $fileName = "Quotation-{$quote->sale_number}.pdf";

        if ($request->query('download') == 1) {
            // Clean active output buffer to prevent corrupted binary header output
            if (ob_get_length()) {
                ob_end_clean();
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfContent = $deliveryService->generateQuotationPdf($quote, $format);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($pdfContent),
                'Cache-Control' => 'private, must-revalidate, post-check=0, pre-check=0, max-age=1',
                'Pragma' => 'public',
            ]);
        }

        if ($request->query('stream') == 1) {
            // Clean active output buffer to prevent corrupted binary header output
            if (ob_get_length()) {
                ob_end_clean();
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $pdfContent = $deliveryService->generateQuotationPdf($quote, $format);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Content-Length' => strlen($pdfContent),
            ]);
        }

        $whatsAppUrl = $deliveryService->generateQuotationWhatsAppUrl($quote);

        if ($format === '80mm' || $format === '58mm') {
            return view('documents.receipt', [
                'sale' => $quote,
                'company' => $company,
                'whatsAppUrl' => $whatsAppUrl,
                'isQuotation' => true,
                'backRoute' => route('tenant.quotes.show', $quote),
            ]);
        }

        return view('documents.template', [
            'sale' => $quote,
            'company' => $company,
            'documentTitle' => 'Quotation Proposal',
            'isQuotation' => true,
            'whatsAppUrl' => $whatsAppUrl,
            'backRoute' => route('tenant.quotes.show', $quote),
        ]);
    }

    /**
     * HTTP API/Web endpoint to dispatch a quotation proposal.
     */
    public function send(Request $request, Sale $quote)
    {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email'],
            'custom_message' => ['nullable', 'string', 'max:2000'],
            'attach_pdf' => ['nullable', 'boolean'],
        ]);

        $attachPdf = $request->boolean('attach_pdf', true);

        try {
            $result = app(MessageQueueService::class)->sendOrQueueEmail(
                $quote,
                $validated['recipient_email'],
                $validated['custom_message'] ?? null,
                $attachPdf
            );

            if ($result['status'] === 'manual_link') {
                if ($request->wantsJson()) return response()->json($result);
                return redirect()->back()->with('device_message_url', $result['url'])->with('status', $result['message']);
            }

            $message = $result['status'] === 'sent'
                ? "Quotation #{$quote->sale_number} sent successfully to {$validated['recipient_email']}."
                : "No connection right now — quotation #{$quote->sale_number} is queued and will send to {$validated['recipient_email']} automatically once you're back online.";

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

            return redirect()->back()->with('error', 'Failed to send quotation: '.$e->getMessage());
        }
    }

    /**
     * Public shareable quotation view for clients clicking WhatsApp/Email links.
     */
    public function publicShow(string $quoteNumber)
    {
        $quote = Sale::withoutGlobalScopes()->where('sale_number', $quoteNumber)->firstOrFail();
        $company = $quote->company ?? Company::find($quote->company_id);
        $deliveryService = app(InvoiceDeliveryService::class);
        $whatsAppUrl = $deliveryService->generateQuotationWhatsAppUrl($quote);

        return view('documents.template', [
            'sale' => $quote,
            'company' => $company,
            'documentTitle' => 'Quotation Proposal',
            'isQuotation' => true,
            'whatsAppUrl' => $whatsAppUrl,
            'backRoute' => null,
        ]);
    }
}
