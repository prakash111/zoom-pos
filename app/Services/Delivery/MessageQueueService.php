<?php

namespace App\Services\Delivery;

use App\Models\Company;
use App\Models\MessageQueue;
use App\Models\Sale;
use App\Services\DispatchChannelService;
use App\Services\Notifications\DeviceMessageService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\WhatsApp\WhatsAppCloudApiClient;
use Illuminate\Support\Facades\Log;

/**
 * "Generate -> Save locally -> Add to queue -> Show 'Queued'" for invoice/
 * quotation email and WhatsApp delivery. A send is always attempted
 * immediately first (when online, this is strictly better than always
 * queuing — no needless round trip); only a failure lands a row in
 * `message_queue`, and only a confirmed provider success ever flips a
 * message to "Sent" — never assumed on enqueue.
 */
class MessageQueueService
{
    public function __construct(
        protected InvoiceDeliveryService $delivery,
        protected WhatsAppCloudApiClient $whatsapp,
    ) {
    }

    /**
     * @return array{status: 'sent'|'queued'|'manual_link', id?: int, error?: string, url?: string}
     */
    public function sendOrQueueEmail(Sale $sale, string $recipient, ?string $customMessage = null, bool $attachPdf = true): array
    {
        if (! filter_var(trim($recipient), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid recipient email address is required.');
        }
        if (! DispatchChannelService::isEmailConfigured($sale->company_id)) {
            $quote = $sale->operation_type === 'quotation';
            $message = $quote
                ? $this->delivery->buildQuotationWhatsAppMessage($sale, $customMessage)
                : $this->delivery->buildInvoiceWhatsAppMessage($sale, $customMessage);
            $link = route($quote ? 'quotes.public' : 'sales.public', $sale->sale_number);
            if (! str_contains($message, $link)) {
                $message .= "\nView online: {$link}";
            }
            return DeviceMessageService::prepare('email', $recipient, $message, ($quote ? 'Quotation #' : 'Invoice #').$sale->sale_number);
        }

        try {
            $this->deliverEmail($sale, $recipient, $customMessage, $attachPdf);

            return ['status' => 'sent'];
        } catch (\InvalidArgumentException $e) {
            // Bad input (e.g. malformed email) — nothing a retry fixes, surface it now.
            throw $e;
        } catch (\Throwable $e) {
            $queued = $this->enqueue($sale, 'email', $recipient, $customMessage, $attachPdf, $e->getMessage());

            return ['status' => 'queued', 'id' => $queued->id, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: 'sent'|'queued'|'manual_link', id?: int, error?: string, url?: string}
     */
    public function sendOrQueueWhatsApp(Sale $sale, string $recipientPhone, ?string $customMessage = null): array
    {
        $company = $sale->company ?? Company::find($sale->company_id);

        if (! DispatchChannelService::isWhatsAppConfigured($company->id)) {
            $quote = $sale->operation_type === 'quotation';
            $message = $quote
                ? $this->delivery->buildQuotationWhatsAppMessage($sale, $customMessage)
                : $this->delivery->buildInvoiceWhatsAppMessage($sale, $customMessage);
            $link = route($quote ? 'quotes.public' : 'sales.public', $sale->sale_number);
            if (! str_contains($message, $link)) {
                $message .= "\nView online: {$link}";
            }

            return DeviceMessageService::prepare('whatsapp', $recipientPhone, $message);
        }

        try {
            $this->deliverWhatsApp($sale, $company, $recipientPhone, $customMessage);

            return ['status' => 'sent'];
        } catch (\Throwable $e) {
            $queued = $this->enqueue($sale, 'whatsapp', $recipientPhone, $customMessage, false, $e->getMessage());

            return ['status' => 'queued', 'id' => $queued->id, 'error' => $e->getMessage()];
        }
    }

    /**
     * Drain everything retryable for a company — called by DesktopSyncClient
     * right after it confirms connectivity is back, and safe to call from
     * anywhere else that just wants "try to flush the queue now".
     *
     * @return array{sent: int, failed: int}
     */
    public function processDue(Company $company, int $limit = 25): array
    {
        $rows = MessageQueue::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('status', [MessageQueue::STATUS_QUEUED, MessageQueue::STATUS_FAILED])
            ->where('attempts', '<', MessageQueue::MAX_ATTEMPTS)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if ($this->attemptSend($row)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    protected function attemptSend(MessageQueue $row): bool
    {
        $row->forceFill(['status' => MessageQueue::STATUS_SENDING])->save();

        $sale = $row->sale;
        if (! $sale) {
            $row->forceFill([
                'status' => MessageQueue::STATUS_FAILED,
                'attempts' => $row->attempts + 1,
                'last_error' => 'The linked sale/quotation no longer exists.',
            ])->save();

            return false;
        }

        try {
            $customMessage = $row->payload['custom_message'] ?? null;

            if ($row->type === 'email') {
                $this->deliverEmail($sale, $row->recipient, $customMessage, (bool) ($row->payload['attach_pdf'] ?? true));
            } else {
                $this->deliverWhatsApp($sale, $sale->company ?? Company::find($sale->company_id), $row->recipient, $customMessage);
            }

            $row->forceFill([
                'status' => MessageQueue::STATUS_SENT,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Queued message delivery retry failed.', ['message_queue_id' => $row->id, 'exception' => $e]);

            $row->forceFill([
                'status' => MessageQueue::STATUS_FAILED,
                'attempts' => $row->attempts + 1,
                'last_error' => $e->getMessage(),
            ])->save();

            return false;
        }
    }

    protected function deliverEmail(Sale $sale, string $recipient, ?string $customMessage, bool $attachPdf): void
    {
        if ($sale->operation_type === 'quotation') {
            $this->delivery->sendQuotationEmail($sale, $recipient, $customMessage, $attachPdf);
        } else {
            $this->delivery->sendInvoiceEmail($sale, $recipient, $customMessage, $attachPdf);
        }
    }

    protected function deliverWhatsApp(Sale $sale, Company $company, string $recipientPhone, ?string $customMessage): void
    {
        $isQuotation = $sale->operation_type === 'quotation';

        $text = $isQuotation
            ? $this->delivery->buildQuotationWhatsAppMessage($sale, $customMessage)
            : $this->delivery->buildInvoiceWhatsAppMessage($sale, $customMessage);

        if (! $this->whatsapp->isConfigured($company)) {
            $result = app(TenantNotificationDispatcherService::class)->dispatchWhatsApp($company, $recipientPhone, $text);
            if (($result['status'] ?? '') !== 'sent') {
                throw new \RuntimeException($result['error'] ?? $result['message'] ?? 'WhatsApp delivery failed.');
            }
            return;
        }

        // Deliver the real receipt/quotation PDF as a WhatsApp document with the
        // formatted summary as its caption. If PDF generation fails for any
        // reason, still get the text summary out rather than nothing.
        try {
            $pdf = $isQuotation
                ? $this->delivery->generateQuotationPdf($sale)
                : $this->delivery->generateInvoicePdf($sale);

            $label = $isQuotation ? 'Quotation' : 'Receipt';
            $filename = ($isQuotation ? 'Quotation-' : 'Receipt-').preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $sale->sale_number).'.pdf';

            $this->whatsapp->sendDocument($company, $recipientPhone, $pdf, $filename, $text);

            return;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp PDF document send failed, falling back to text.', [
                'sale_id' => $sale->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $this->whatsapp->sendText($company, $recipientPhone, $text);
    }

    protected function enqueue(Sale $sale, string $type, string $recipient, ?string $customMessage, bool $attachPdf, string $error): MessageQueue
    {
        $company = $sale->company ?? Company::find($sale->company_id);

        Log::info('Delivery failed, queued for retry.', ['sale_id' => $sale->id, 'type' => $type, 'error' => $error]);

        return MessageQueue::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'type' => $type,
            'recipient' => $recipient,
            'payload' => ['custom_message' => $customMessage, 'attach_pdf' => $attachPdf],
            'status' => MessageQueue::STATUS_QUEUED,
            'last_error' => $error,
        ]);
    }
}
