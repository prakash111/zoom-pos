<?php

namespace App\Services\Dispatch;

use App\Models\Company;
use App\Models\CustomNotificationChannel;
use App\Models\Tenant;
use App\Services\DispatchChannelService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Notifications\DeviceMessageService;
use App\Services\Notifications\CustomChannelDispatcherService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Routes delivery through configured gateways or prepares a device composer. */
class DocumentDispatchService
{
    public function __construct(
        protected TenantNotificationDispatcherService $platformDispatcher,
    ) {
    }

    public function dispatchWhatsApp(
        Tenant|Company $tenant,
        string $phoneNumber,
        string $message,
        ?string $pdfUrl = null,
    ): array {
        return $this->platformDispatcher->dispatchWhatsApp($tenant, $phoneNumber, $message, $pdfUrl);
    }

    public function dispatchEmail(
        Tenant|Company $tenant,
        string $recipientEmail,
        string $subject,
        Mailable|string $mailable,
    ): array {
        if (! filter_var(trim($recipientEmail), FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'status' => 'error', 'message' => 'A valid recipient email address is required.'];
        }
        if (! DispatchChannelService::isEmailConfigured($tenant->id)) {
            // Rendering hydrates declarative attachments. Only render a clone
            // for device fallback so a later SMTP send never reuses a mailable
            // whose generated PDF attachment was already hydrated.
            $html = is_string($mailable) ? $mailable : (clone $mailable)->render();

            return DeviceMessageService::prepare('email', $recipientEmail, DeviceMessageService::plainText($html), $subject);
        }
        if (is_string($mailable)) {
            return $this->platformDispatcher->dispatchEmail($tenant, $recipientEmail, $subject, $mailable);
        }

        $smtp = app(InvoiceDeliveryService::class)->getSmtpConfig($tenant);
        Config::set('mail.mailers.tenant_dispatch', array_merge($smtp, ['transport' => 'smtp', 'timeout' => 15]));
        Mail::purge('tenant_dispatch');
        try {
            Mail::mailer('tenant_dispatch')->to($recipientEmail)->send($mailable);
            return ['success' => true, 'status' => 'sent', 'channel' => 'email'];
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function dispatchSms(
        Tenant|Company $tenant,
        string $phoneNumber,
        string $message,
    ): array {
        return $this->platformDispatcher->dispatchSms($tenant, $phoneNumber, $message);
    }

    public function dispatchWebhook(
        Tenant|Company $tenant,
        string $eventType,
        array $payload,
    ): array {
        try {
            $company = $tenant instanceof Company ? $tenant : Company::find($tenant->id);
            if ($company) {
                return $this->platformDispatcher->dispatchWebhook($company, $eventType, $payload, ignoreEventSubscription: true);
            }
        } catch (\Throwable $e) {
            Log::warning('Webhook dispatch failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }

        return [
            'success' => false,
            'status' => 'not_configured',
            'channel' => 'webhook',
            'message' => 'Webhook is not configured.',
        ];
    }

    public function dispatchCustom(
        Tenant|Company $tenant,
        int|string $channelId,
        array $payload,
    ): array {
        try {
            $company = $tenant instanceof Company ? $tenant : Company::find($tenant->id);
            $custom = CustomNotificationChannel::withoutGlobalScope('company')
                ->where('company_id', $company?->id ?? $tenant->id)
                ->where('is_active', true)
                ->find($channelId);

            if ($custom) {
                $customDispatcher = app(CustomChannelDispatcherService::class);
                $result = $customDispatcher->dispatch($custom, $payload);

                return $result + ['status' => $result['success'] ? 'sent' : 'failed', 'channel' => 'custom', 'channel_id' => $custom->id];
            }
        } catch (\Throwable $e) {
            Log::warning('Custom channel dispatch failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }

        return [
            'success' => false,
            'status' => 'not_configured',
            'channel' => 'custom',
            'message' => 'The selected custom channel is unavailable or inactive.',
        ];
    }
}
