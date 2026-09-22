<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CustomNotificationChannel;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Raw invoice PDF stream for the native mobile Post-Sale Action Sheet's
     * "Preview & Print" row. Authenticated by the tenant API bearer token
     * (route middleware), scoped to the caller's company, and always
     * `application/pdf` — never the HTML receipt template — so the Flutter
     * client can hand the bytes straight to `Printing.layoutPdf()`.
     */
    public function pdfStream(Request $request, string $sale)
    {
        $company = $this->resolveCompany($request);

        $model = Sale::withoutGlobalScope('company')
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope('company')])
            ->where('company_id', $company->id)
            ->where(function ($q) use ($sale) {
                $q->where('id', $sale)->orWhere('sale_number', $sale)->orWhere('external_id', $sale);
            })
            ->first();

        abort_if($model === null, 404, 'Invoice not found.');

        $pdf = app(InvoiceDeliveryService::class)->generateInvoicePdf($model, $request->query('format'));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Invoice-'.$model->sale_number.'.pdf"',
            'Content-Length' => strlen($pdf),
            'Cache-Control' => 'no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
    /**
     * Login-free invoice PDF, authorized purely by a valid URL signature
     * (see route `receipt.signed.pdf`). Used by the mobile Post-Sale Action
     * Sheet so "Preview & Print" opens the PDF in the device browser without
     * bouncing through the web /login screen.
     */
    public function signedPdf(Request $request, string $sale)
    {
        $model = Sale::withoutGlobalScope('company')
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope('company')])
            ->where(function ($q) use ($sale) {
                $q->where('id', $sale)->orWhere('sale_number', $sale)->orWhere('external_id', $sale);
            })
            ->first();

        abort_if($model === null, 404, 'Invoice not found.');

        $pdf = app(InvoiceDeliveryService::class)->generateInvoicePdf($model, $request->query('format'));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Invoice-'.$model->sale_number.'.pdf"',
            'Content-Length' => strlen($pdf),
            'Cache-Control' => 'no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

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
                'Cache-Control' => 'no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }

        $company = $sale->company ?? auth('web')->user()?->company;
        $whatsAppUrl = $deliveryService->generateInvoiceWhatsAppUrl($sale);
        $is58mm = ($format === '58mm' || ($company?->getReceiptFormat() === '58mm' && $format !== '80mm'));
        $qrCodeData = $deliveryService->generateReceiptQrCode($sale, $is58mm);

        $view = ($format === 'standard' || $format === 'a4') ? 'documents.template' : 'documents.receipt';

        $sale->loadMissing('customer');
        $dispatchChannels = [];
        if (auth('web')->check()) {
            $dispatchChannels = CustomNotificationChannel::where('company_id', $sale->company_id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (CustomNotificationChannel $c) => $c->handlesEvent('invoice'))
                ->values();
        }

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
            'customerEmail' => $sale->customer?->email,
            'customerPhone' => $sale->customer?->phone,
            'dispatchChannels' => $dispatchChannels,
        ]);
    }

    /**
     * Dispatch an invoice/receipt to one of the tenant's custom notification
     * channels (Slack/Telegram/generic webhook, etc.) — the "Custom
     * Notification Channel" option in the post-settlement dispatch sheet.
     */
    public function sendCustom(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'integer'],
        ]);

        $channel = CustomNotificationChannel::where('company_id', $sale->company_id)
            ->where('is_active', true)
            ->find($validated['channel_id']);

        if (! $channel || ! $channel->handlesEvent('invoice')) {
            $message = 'That notification channel is unavailable.';

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : redirect()->back()->with('error', $message);
        }

        app(WebhookDispatchService::class)->dispatch($channel, [
            'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? '',
            'invoice_no' => $sale->sale_number,
            'total' => (float) $sale->total,
            'due_amount' => (float) $sale->due_amount,
            'receipt_link' => route('sales.public', $sale->sale_number),
        ]);

        $message = "Invoice #{$sale->sale_number} dispatched to {$channel->name}.";

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->back()->with('status', $message);
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

            if ($result['status'] === 'manual_link') {
                if ($request->wantsJson()) return response()->json($result);
                return redirect()->back()->with('device_message_url', $result['url'])->with('status', $result['message']);
            }

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
        $sale = Sale::withoutGlobalScope('company')
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
