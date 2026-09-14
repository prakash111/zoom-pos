<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\AutomatedReminderDispatch;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Notifications\AutomatedReminderSettingsService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DispatchAutomatedCustomerReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $dispatchId)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        TenantNotificationDispatcherService $dispatcher,
        AutomatedReminderSettingsService $settingsService,
    ): void {
        $dispatch = AutomatedReminderDispatch::withoutGlobalScope('company')->find($this->dispatchId);
        if (! $dispatch || in_array($dispatch->status, [
            AutomatedReminderDispatch::STATUS_SENT,
            AutomatedReminderDispatch::STATUS_SKIPPED,
        ], true)) {
            return;
        }

        $company = Company::withoutGlobalScopes()->find($dispatch->company_id);
        $document = Sale::withoutGlobalScope('company')
            ->where('company_id', $dispatch->company_id)
            ->with('customer')
            ->find($dispatch->sale_id);

        if (! $company || ! $document) {
            $this->skip($dispatch, 'Tenant or document no longer exists.');

            return;
        }

        $settings = $settingsService->get($company);
        if (! $this->stillEligible($company, $document, $dispatch, $settings)) {
            $this->skip($dispatch, 'Reminder is disabled or the document is no longer eligible.');

            return;
        }

        if (! $dispatcher->isChannelActive($company, $dispatch->channel)) {
            $this->skip($dispatch, 'The selected tenant gateway is no longer active and configured.');

            return;
        }

        $dispatch->forceFill([
            'status' => AutomatedReminderDispatch::STATUS_PROCESSING,
            'attempts' => $dispatch->attempts + 1,
            'last_error' => null,
        ])->save();

        [$message, $subject, $html] = $this->messageFor($company, $document, $dispatch->document_type);
        $recipient = trim((string) $dispatch->recipient);

        $result = match ($dispatch->channel) {
            'sms' => $dispatcher->dispatchSms($company, $recipient, $message, [
                'customer_name' => $document->customer?->name ?? $document->customer_name ?? 'Customer',
                'total' => $dispatch->document_type === 'invoice'
                    ? (float) $document->due_amount
                    : (float) $document->total,
            ]),
            'whatsapp' => $dispatcher->dispatchWhatsApp($company, $recipient, $message, null, $document),
            'email' => $dispatcher->dispatchEmail($company, $recipient, $subject, $html),
            default => ['success' => false, 'message' => 'Unsupported automated reminder channel.'],
        };

        if (! ($result['success'] ?? false)) {
            $error = (string) ($result['error'] ?? $result['message'] ?? 'Gateway dispatch failed.');
            $dispatch->forceFill([
                'status' => AutomatedReminderDispatch::STATUS_FAILED,
                'last_error' => $error,
            ])->save();

            throw new RuntimeException($error);
        }

        $dispatch->forceFill([
            'status' => AutomatedReminderDispatch::STATUS_SENT,
            'last_error' => null,
            'dispatched_at' => now(),
        ])->save();

        AuditLog::record('automated_customer_reminder.dispatched', $company->id, null, [
            'sale_id' => $document->id,
            'document_type' => $dispatch->document_type,
            'document_number' => $document->sale_number,
            'channel' => $dispatch->channel,
            'recipient' => $recipient,
            'cycle_key' => $dispatch->cycle_key,
        ]);

        Log::info('Automated customer reminder dispatched.', [
            'company_id' => $company->id,
            'sale_id' => $document->id,
            'document_type' => $dispatch->document_type,
            'channel' => $dispatch->channel,
            'recipient' => $recipient,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        AutomatedReminderDispatch::withoutGlobalScope('company')
            ->whereKey($this->dispatchId)
            ->where('status', '!=', AutomatedReminderDispatch::STATUS_SENT)
            ->update([
                'status' => AutomatedReminderDispatch::STATUS_FAILED,
                'last_error' => $exception?->getMessage() ?: 'Queue job failed.',
            ]);
    }

    private function stillEligible(
        Company $company,
        Sale $document,
        AutomatedReminderDispatch $dispatch,
        array $settings,
    ): bool {
        if (! $settings['auto_reminders_enabled']) {
            return false;
        }

        $preferred = $settings['reminder_preferred_channel'];
        if ($preferred !== 'all_active' && $preferred !== $dispatch->channel) {
            return false;
        }

        $scope = $settings['reminder_target_documents'];
        $localToday = now($company->resolveTimezone())->toDateString();

        if ($dispatch->document_type === 'invoice') {
            return in_array($scope, ['both', 'invoices_only'], true)
                && $document->operation_type !== 'quotation'
                && ! in_array($document->status, ['cancelled', 'refunded'], true)
                && (float) $document->due_amount > 0
                && $document->due_date !== null
                && $document->due_date->toDateString() <= $localToday;
        }

        $localNow = now($company->resolveTimezone());

        return in_array($scope, ['both', 'quotations_only'], true)
            && $document->operation_type === 'quotation'
            && in_array($document->status, ['draft', 'sent', 'pending'], true)
            && ($document->due_date !== null
                ? $document->due_date->toDateString() <= $localNow->copy()->addDays(3)->toDateString()
                : $document->created_at?->lte($localNow->copy()->subDays(2)));
    }

    /** @return array{string, string, string} */
    private function messageFor(Company $company, Sale $document, string $documentType): array
    {
        $storeName = $company->trade_name ?: ($company->name ?: 'Store');
        $customerName = $document->customer?->name ?? $document->customer_name ?? 'Customer';
        $reference = $document->sale_number ?: (string) $document->id;
        $amount = $company->formatMoney(
            $documentType === 'invoice' ? $document->due_amount : $document->total
        );

        if ($documentType === 'invoice') {
            $message = "Hello {$customerName}, gentle reminder from {$storeName}: the outstanding balance of {$amount} for Invoice #{$reference} is due. Please settle your payment. Thank you.";
            $subject = "Payment reminder for Invoice #{$reference}";
        } else {
            $message = "Hello {$customerName}, greetings from {$storeName}: your Quotation #{$reference} valued at {$amount} is pending review. Contact us to proceed. Thank you.";
            $subject = "Quotation #{$reference} is awaiting your review";
        }

        $safeStore = htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8');
        $safeCustomer = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
        $safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
        $safeAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $documentLabel = $documentType === 'invoice' ? 'Invoice' : 'Quotation';
        $body = $documentType === 'invoice'
            ? "The outstanding balance of <strong>{$safeAmount}</strong> is now due. Please settle the payment at your earliest convenience."
            : "Your quotation valued at <strong>{$safeAmount}</strong> is awaiting review. Please contact us when you are ready to proceed.";
        $html = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px">'
            ."<h2>{$safeStore}</h2><p>Hello <strong>{$safeCustomer}</strong>,</p>"
            ."<p>{$body}</p><p>{$documentLabel}: <strong>#{$safeReference}</strong></p>"
            .'<p style="color:#64748b;font-size:12px">This is an automated customer reminder.</p></div>';

        return [$message, $subject, $html];
    }

    private function skip(AutomatedReminderDispatch $dispatch, string $reason): void
    {
        $dispatch->forceFill([
            'status' => AutomatedReminderDispatch::STATUS_SKIPPED,
            'last_error' => $reason,
        ])->save();
    }
}
