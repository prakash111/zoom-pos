<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CustomNotificationChannel;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Send sale invoice via WhatsApp, SMS, or Email.
     * POST /api/tenant/sales/{id}/send-invoice
     */
    public function sendInvoice(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'channel' => ['nullable', 'string', 'in:whatsapp,sms,email,custom'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2500'],
            'channel_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->with(['customer', 'company'])
            ->first();

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error' => "Sale transaction #{$id} not found.",
            ], 404);
        }

        $channel = strtolower($request->input('channel', 'whatsapp'));
        $recipient = trim((string) $request->input('recipient'));
        $customMessage = $request->input('message');

        $messageQueue = app(MessageQueueService::class);
        $deliveryService = app(InvoiceDeliveryService::class);

        if ($channel === 'email') {
            $email = $recipient ?: ($sale->customer?->email ?? '');
            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer has no email address configured. Please provide an email recipient.',
                ], 422);
            }

            try {
                $result = $messageQueue->sendOrQueueEmail($sale, $email, $customMessage, true);
                AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'channel' => 'email',
                    'recipient' => $email,
                    'status' => $result['status'],
                ]);

                return response()->json([
                    'success' => true,
                    'channel' => 'email',
                    'status' => $result['status'],
                    'message' => $result['status'] === 'sent'
                        ? "Invoice #{$sale->sale_number} sent to {$email}."
                        : "Invoice #{$sale->sale_number} queued for delivery to {$email}.",
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to deliver email invoice: '.$e->getMessage(),
                ], 500);
            }
        }

        if ($channel === 'sms') {
            $phone = $recipient ?: ($sale->customer?->phone ?? '');
            if (empty($phone)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer has no phone number configured. Please provide a phone recipient.',
                ], 422);
            }

            $smsChannel = CustomNotificationChannel::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->get()
                ->first(fn ($c) => $c->handlesEvent('invoice') && str_contains(strtolower($c->driver ?? $c->name), 'sms'));

            if ($smsChannel) {
                app(WebhookDispatchService::class)->dispatch($smsChannel, [
                    'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? 'Customer',
                    'invoice_no' => $sale->sale_number,
                    'total' => (float) $sale->total,
                    'phone' => $phone,
                    'receipt_link' => route('sales.public', $sale->sale_number),
                ]);
            }

            AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'channel' => 'sms',
                'recipient' => $phone,
            ]);

            return response()->json([
                'success' => true,
                'channel' => 'sms',
                'message' => "SMS receipt notification dispatched to {$phone}.",
            ]);
        }

        // WhatsApp (default)
        $phone = $recipient ?: ($sale->customer?->phone ?? '');
        $whatsappUrl = $deliveryService->generateInvoiceWhatsAppUrl($sale, $phone ?: null, $customMessage);

        try {
            $result = $messageQueue->sendOrQueueWhatsApp($sale, $phone ?: '0000000000', $customMessage);
            AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'channel' => 'whatsapp',
                'recipient' => $phone,
                'status' => $result['status'] ?? 'manual_link',
            ]);

            return response()->json([
                'success' => true,
                'channel' => 'whatsapp',
                'status' => $result['status'] ?? 'manual_link',
                'url' => $result['url'] ?? $whatsappUrl,
                'whatsapp_url' => $result['url'] ?? $whatsappUrl,
                'message' => ($result['status'] ?? '') === 'sent'
                    ? "Invoice #{$sale->sale_number} delivered via WhatsApp."
                    : "Opening WhatsApp with receipt for #{$sale->sale_number}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'channel' => 'whatsapp',
                'status' => 'manual_link',
                'url' => $whatsappUrl,
                'whatsapp_url' => $whatsappUrl,
                'message' => "Invoice #{$sale->sale_number} ready to share via WhatsApp.",
            ]);
        }
    }

    /**
     * Get printable/downloadable PDF URLs for sale invoice.
     * POST /api/tenant/sales/{id}/print
     */
    public function printInvoice(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->first();

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error' => "Sale transaction #{$id} not found.",
            ], 404);
        }

        $printUrl = route('tenant.sales.pdf', ['sale' => $sale->id, 'download' => 0, 'embed' => 1]);
        $downloadUrl = route('tenant.sales.pdf', ['sale' => $sale->id, 'download' => 1]);

        return response()->json([
            'success' => true,
            'message' => "Invoice #{$sale->sale_number} ready for print.",
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'url' => $printUrl,
            'print_url' => $printUrl,
            'download_url' => $downloadUrl,
        ]);
    }
}
