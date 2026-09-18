<?php

namespace App\Services\Notifications;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MessageQueue;
use App\Models\Sale;
use App\Models\TenantNotificationGateway;
use App\Services\DispatchChannelService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\WhatsApp\WhatsAppCloudApiClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantNotificationDispatcherService
{
    public function __construct(
        protected InvoiceDeliveryService $invoiceDeliveryService,
        protected WhatsAppCloudApiClient $whatsAppCloudApiClient,
    ) {
    }

    /**
     * Resolve company instance from model or ID.
     */
    protected function resolveCompany(Company|string $company): ?Company
    {
        if ($company instanceof Company) {
            return $company;
        }

        return Company::find($company);
    }

    /**
     * Get a map of enabled notification channels for the tenant.
     * e.g. ['whatsapp' => true, 'sms' => true, 'email' => false, 'custom_webhook' => false]
     */
    public function getEnabledChannels(Company|string $company): array
    {
        $resolved = $this->resolveCompany($company);
        if (! $resolved) {
            return [
                'whatsapp' => false,
                'sms' => false,
                'email' => false,
                'custom_webhook' => false,
            ];
        }

        $channels = [
            'whatsapp' => DispatchChannelService::isWhatsAppConfigured($resolved->id),
            'sms' => DispatchChannelService::isSmsConfigured($resolved->id),
            'email' => DispatchChannelService::isEmailConfigured($resolved->id),
            'custom_webhook' => $this->getGateway($resolved, 'custom_webhook')?->isConfigured() ?? false,
        ];

        return $channels;
    }

    /**
     * Check if a specific channel is enabled and configured for the tenant.
     */
    public function isChannelActive(Company|string $company, string $channel): bool
    {
        $channels = $this->getEnabledChannels($company);

        return ! empty($channels[$channel]);
    }

    /**
     * Retrieve gateway model for a specific channel.
     */
    public function getGateway(Company|string $company, string $channel): ?TenantNotificationGateway
    {
        $resolved = $this->resolveCompany($company);
        if (! $resolved) {
            return null;
        }

        return TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $resolved->id)
            ->where('channel', $channel)
            ->first();
    }

    /**
     * Send WhatsApp notification through tenant-configured provider.
     */
    public function dispatchWhatsApp(
        Company $company,
        string $toPhone,
        string $message,
        ?string $documentUrl = null,
        ?Sale $sale = null
    ): array {
        $sanitizedPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($sanitizedPhone)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Invalid phone number.'];
        }

        if (! DispatchChannelService::isWhatsAppConfigured($company->id)) {
            return DeviceMessageService::prepare('whatsapp', $toPhone, $message);
        }

        $gateway = $this->getGateway($company, TenantNotificationGateway::CHANNEL_WHATSAPP);
        if (! $gateway) {
            $settings = DispatchChannelService::apiSettings($company->id);
            if (! empty($settings['whatsapp_api_url']) && ! empty($settings['whatsapp_api_token'])) {
                return $this->dispatchHttpMessage($company, 'whatsapp', $toPhone, $message, $settings['whatsapp_api_url'], $settings['whatsapp_api_token'], $documentUrl);
            }
        }

        // Unofficial/self-hosted WhatsApp HTTP providers (for example WAPI,
        // Baileys or a tenant's private bridge). Keep this opt-in and fall
        // through to the existing official/platform routes on failure.
        if ($gateway && $gateway->is_enabled && $gateway->provider === TenantNotificationGateway::PROVIDER_UNOFFICIAL_HTTP) {
            $creds = (array) $gateway->credentials;
            $url = trim((string) ($creds['url'] ?? ''));
            $token = trim((string) ($creds['api_token'] ?? ($creds['api_key'] ?? '')));
            if ($url !== '' && $token !== '') {
                try {
                    $endpoint = str_replace(['{phone}', '{to}', '{message}'], [$sanitizedPhone, $sanitizedPhone, rawurlencode($message)], $url);
                    $response = Http::withToken($token)->acceptJson()->timeout(15)->post($endpoint, [
                        'phone' => $sanitizedPhone,
                        'to' => $sanitizedPhone,
                        'message' => $message,
                    ]);
                    if ($response->successful()) {
                        $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'sent');
                        return ['success' => true, 'status' => 'sent', 'provider' => 'unofficial_http', 'message' => 'WhatsApp delivered via private gateway.'];
                    }
                    Log::warning('Unofficial WhatsApp gateway failed; using fallback.', ['company_id' => $company->id, 'status' => $response->status()]);
                } catch (\Throwable $e) {
                    Log::warning('Unofficial WhatsApp gateway exception; using fallback.', ['company_id' => $company->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // Twilio WhatsApp Provider
        if ($gateway && $gateway->is_enabled && $gateway->provider === TenantNotificationGateway::PROVIDER_TWILIO) {
            $creds = (array) $gateway->credentials;
            $sid = trim((string) ($creds['account_sid'] ?? ''));
            $token = trim((string) ($creds['auth_token'] ?? ''));
            $from = trim((string) ($creds['from_number'] ?? ''));

            if (! str_starts_with($from, 'whatsapp:')) {
                $from = 'whatsapp:'.$from;
            }
            $to = 'whatsapp:+'.$sanitizedPhone;

            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->timeout(15)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from,
                        'To' => $to,
                        'Body' => $message,
                    ]);

                if ($response->successful()) {
                    $messageId = $response->json('sid');
                    $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'sent', null, $messageId);

                    return [
                        'success' => true,
                        'status' => 'sent',
                        'provider' => 'twilio',
                        'message_id' => $messageId,
                        'message' => "WhatsApp delivered via Twilio to +{$sanitizedPhone}.",
                    ];
                }

                $errMsg = $response->json('message') ?: $response->body();
                Log::error("Twilio WhatsApp delivery error: {$errMsg}");
                $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'failed', $errMsg);

                return ['success' => false, 'status' => 'failed', 'error' => $errMsg];
            } catch (\Throwable $e) {
                Log::error("Twilio WhatsApp Exception: ".$e->getMessage());
                $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'failed', $e->getMessage());

                return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        // Meta Cloud API Provider (Gateway or Legacy)
        $phoneNumberId = null;
        $accessToken = null;

        if ($gateway && $gateway->is_enabled && $gateway->provider === TenantNotificationGateway::PROVIDER_META_CLOUD) {
            $creds = (array) $gateway->credentials;
            $phoneNumberId = trim((string) ($creds['phone_number_id'] ?? ''));
            $accessToken = trim((string) ($creds['access_token'] ?? ''));
        }

        if (empty($phoneNumberId) || empty($accessToken)) {
            // Check legacy configuration
            if ($this->whatsAppCloudApiClient->isConfigured($company)) {
                try {
                    $msgId = $this->whatsAppCloudApiClient->sendText($company, $sanitizedPhone, $message);
                    $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'sent', null, $msgId);

                    return [
                        'success' => true,
                        'status' => 'sent',
                        'provider' => 'meta_cloud_api',
                        'message_id' => $msgId,
                        'message' => "WhatsApp delivered via Meta Cloud API to +{$sanitizedPhone}.",
                    ];
                } catch (\Throwable $e) {
                    Log::warning("Meta Cloud API failed: ".$e->getMessage());
                }
            }

            return DeviceMessageService::prepare('whatsapp', $toPhone, $message);
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $sanitizedPhone,
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $message],
                ]);

            if ($response->successful()) {
                $msgId = (string) ($response->json('messages.0.id') ?: '');
                $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'sent', null, $msgId);

                return [
                    'success' => true,
                    'status' => 'sent',
                    'provider' => 'meta_cloud_api',
                    'message_id' => $msgId,
                    'message' => "WhatsApp delivered via Meta Cloud API to +{$sanitizedPhone}.",
                ];
            }

            $errMsg = $response->json('error.message') ?: $response->body();
            Log::error("Meta Cloud API error: {$errMsg}");
            $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'failed', $errMsg);

            return ['success' => false, 'status' => 'failed', 'error' => $errMsg];
        } catch (\Throwable $e) {
            Log::error("Meta Cloud API Exception: ".$e->getMessage());
            $this->logMessageQueue($company->id, 'whatsapp', $sanitizedPhone, $message, 'failed', $e->getMessage());

            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS through tenant-configured provider (Twilio, MSG91, or Generic HTTP Gateway).
     */
    public function dispatchSms(Company $company, string $toPhone, string $message, array $extra = []): array
    {
        $sanitizedPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($sanitizedPhone)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Invalid phone number.'];
        }

        $gateway = $this->getGateway($company, TenantNotificationGateway::CHANNEL_SMS);
        if (! DispatchChannelService::isSmsConfigured($company->id)) {
            return DeviceMessageService::prepare('sms', $toPhone, $message);
        }
        if (! $gateway) {
            $settings = DispatchChannelService::apiSettings($company->id);
            if (! empty($settings['sms_api_url'] ?? $settings['generic_sms_url'] ?? null)) {
                return $this->dispatchHttpMessage($company, 'sms', $toPhone, $message,
                    $settings['sms_api_url'] ?? $settings['generic_sms_url'],
                    $settings['sms_api_token'] ?? $settings['generic_sms_api_key'] ?? '');
            }
            $result = \App\Services\SmsGatewayService::send($sanitizedPhone, $message, $company->id);
            return ['success' => $result['success'], 'status' => $result['success'] ? 'sent' : 'failed', 'message' => $result['success'] ? 'SMS sent.' : 'SMS dispatch failed.', 'details' => $result];
        }

        $creds = (array) $gateway->credentials;
        $provider = $gateway->provider;

        // Twilio SMS
        if ($provider === TenantNotificationGateway::PROVIDER_TWILIO) {
            $sid = trim((string) ($creds['account_sid'] ?? ''));
            $token = trim((string) ($creds['auth_token'] ?? ''));
            $from = trim((string) ($creds['from_number'] ?? $creds['sender_id'] ?? ''));

            if (empty($sid) || empty($token) || empty($from)) {
                return ['success' => false, 'status' => 'invalid_config', 'message' => 'Twilio SMS credentials incomplete.'];
            }

            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->timeout(15)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from,
                        'To' => '+'.$sanitizedPhone,
                        'Body' => $message,
                    ]);

                if ($response->successful()) {
                    $msgId = $response->json('sid');
                    $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'sent', null, $msgId);

                    return [
                        'success' => true,
                        'status' => 'sent',
                        'provider' => 'twilio',
                        'message_id' => $msgId,
                        'message' => "SMS delivered via Twilio to +{$sanitizedPhone}.",
                    ];
                }

                $errMsg = $response->json('message') ?: $response->body();
                Log::error("Twilio SMS error: {$errMsg}");
                $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'failed', $errMsg);

                return ['success' => false, 'status' => 'failed', 'error' => $errMsg];
            } catch (\Throwable $e) {
                Log::error("Twilio SMS Exception: ".$e->getMessage());
                $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'failed', $e->getMessage());

                return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        // MSG91 SMS (India DLT Flow / Transactional)
        if ($provider === TenantNotificationGateway::PROVIDER_MSG91) {
            $authKey = trim((string) ($creds['auth_key'] ?? ''));
            $senderId = trim((string) ($creds['sender_id'] ?? ''));
            $dltTemplateId = trim((string) ($creds['dlt_template_id'] ?? $creds['template_id'] ?? ''));

            if (empty($authKey)) {
                return ['success' => false, 'status' => 'invalid_config', 'message' => 'MSG91 Auth Key is required.'];
            }

            try {
                // If template ID is set, use Flow API
                if (! empty($dltTemplateId)) {
                    $flowPayload = [
                        'template_id' => $dltTemplateId,
                        'short_url' => '1',
                        'recipients' => [
                            [
                                'mobiles' => $sanitizedPhone,
                                'message' => $message,
                                'customer_name' => $extra['customer_name'] ?? 'Customer',
                                'total' => $extra['total'] ?? '',
                                'link' => $extra['link'] ?? '',
                            ],
                        ],
                    ];
                    if (! empty($senderId)) {
                        $flowPayload['sender'] = $senderId;
                    }

                    $response = Http::withHeaders(['authkey' => $authKey, 'Content-Type' => 'application/json'])
                        ->timeout(15)
                        ->post('https://control.msg91.com/api/v5/flow/', $flowPayload);
                } else {
                    // Fallback to standard SMS API
                    $response = Http::withHeaders(['authkey' => $authKey, 'Content-Type' => 'application/json'])
                        ->timeout(15)
                        ->post('https://api.msg91.com/api/v2/sendsms', [
                            'sender' => $senderId ?: 'ZOOMNB',
                            'route' => '4',
                            'country' => '91',
                            'sms' => [
                                [
                                    'message' => $message,
                                    'to' => [$sanitizedPhone],
                                ],
                            ],
                        ]);
                }

                if ($response->successful()) {
                    $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'sent');

                    return [
                        'success' => true,
                        'status' => 'sent',
                        'provider' => 'msg91',
                        'message' => "SMS dispatched via MSG91 to {$sanitizedPhone}.",
                    ];
                }

                $errMsg = $response->json('message') ?: $response->body();
                Log::error("MSG91 SMS error: {$errMsg}");
                $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'failed', $errMsg);

                return ['success' => false, 'status' => 'failed', 'error' => $errMsg];
            } catch (\Throwable $e) {
                Log::error("MSG91 SMS Exception: ".$e->getMessage());
                $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'failed', $e->getMessage());

                return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        // Generic HTTP Gateway
        if ($provider === TenantNotificationGateway::PROVIDER_GENERIC_HTTP) {
            $result = \App\Services\SmsGatewayService::send($sanitizedPhone, $message, $company->id);

            if ($result['success']) {
                $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'sent');

                return [
                    'success' => true,
                    'status' => 'sent',
                    'provider' => 'generic_http',
                    'message' => "SMS sent via HTTP Gateway to {$sanitizedPhone}.",
                    'response' => $result['body'] ?? null,
                ];
            }

            $errMsg = $result['error'] ?? (is_array($result['body']) ? json_encode($result['body']) : (string) ($result['body'] ?? 'HTTP request failed.'));
            $this->logMessageQueue($company->id, 'sms', $sanitizedPhone, $message, 'failed', $errMsg);

            return ['success' => false, 'status' => 'failed', 'error' => $errMsg];
        }

        return ['success' => false, 'status' => 'unknown_provider', 'message' => "Unsupported SMS provider '{$provider}'."];
    }

    /**
     * Send email notification using tenant SMTP credentials or platform fallback.
     */
    public function dispatchEmail(
        Company $company,
        string $recipientEmail,
        string $subject,
        string $htmlContent,
        ?string $attachmentPdf = null,
        ?string $attachmentName = 'document.pdf'
    ): array {
        $cleanEmail = trim($recipientEmail);
        if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Invalid email address.'];
        }

        if (! DispatchChannelService::isEmailConfigured($company->id)) {
            return DeviceMessageService::prepare('email', $recipientEmail, DeviceMessageService::plainText($htmlContent), $subject);
        }
        $smtp = $this->invoiceDeliveryService->getSmtpConfig($company);

        Config::set('mail.mailers.tenant_dynamic', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => 15,
        ]);
        Mail::purge('tenant_dynamic');

        try {
            Mail::mailer('tenant_dynamic')->send([], [], function ($msg) use ($cleanEmail, $smtp, $subject, $htmlContent, $attachmentPdf, $attachmentName) {
                $msg->to($cleanEmail)
                    ->from($smtp['from_address'], $smtp['from_name'])
                    ->subject($subject)
                    ->html($htmlContent);

                if ($attachmentPdf) {
                    $msg->attachData($attachmentPdf, $attachmentName ?: 'document.pdf', ['mime' => 'application/pdf']);
                }
            });

            $this->logMessageQueue($company->id, 'email', $cleanEmail, $subject, 'sent');

            return [
                'success' => true,
                'status' => 'sent',
                'message' => "Email sent successfully to {$cleanEmail}.",
            ];
        } catch (\Throwable $e) {
            Log::error("SMTP Dispatch Error to {$cleanEmail}: ".$e->getMessage());
            $this->logMessageQueue($company->id, 'email', $cleanEmail, $subject, 'failed', $e->getMessage());

            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Dispatch webhook with HMAC signature.
     */
    public function dispatchWebhook(Company $company, string $eventType, array $payload, bool $ignoreEventSubscription = false): array
    {
        $gateway = $this->getGateway($company, TenantNotificationGateway::CHANNEL_WEBHOOK);
        if (! $gateway || ! $gateway->is_enabled) {
            return ['success' => false, 'status' => 'not_configured', 'message' => 'Webhook is not enabled.'];
        }

        $creds = (array) $gateway->credentials;
        $url = trim((string) ($creds['url'] ?? ''));
        $secret = trim((string) ($creds['secret'] ?? ''));
        $method = strtoupper(trim((string) ($creds['method'] ?? 'POST')));
        $headers = (array) ($creds['headers'] ?? []);
        $triggers = (array) ($creds['event_types'] ?? $creds['triggers'] ?? []);

        if (empty($url)) {
            return ['success' => false, 'status' => 'invalid_config', 'message' => 'Webhook URL is required.'];
        }

        // Check if event type is subscribed
        if (! $ignoreEventSubscription && ! empty($triggers) && ! in_array($eventType, $triggers, true) && ! in_array('*', $triggers, true)) {
            return ['success' => false, 'status' => 'skipped', 'message' => "Event '{$eventType}' not subscribed."];
        }

        $timestamp = time();
        $payloadEnriched = [
            'event' => $eventType,
            'timestamp' => $timestamp,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'data' => $payload,
        ];

        $jsonPayload = json_encode($payloadEnriched);

        $headers['Content-Type'] = 'application/json';
        $headers['X-Event'] = $eventType;
        $headers['X-Timestamp'] = (string) $timestamp;

        if (! empty($secret)) {
            $signature = hash_hmac('sha256', $jsonPayload, $secret);
            $headers['X-Webhook-Signature'] = $signature;
            $headers['X-Signature'] = $signature;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->send($method, $url, ['body' => $jsonPayload]);

            $isSuccess = $response->successful();
            $this->logMessageQueue(
                $company->id,
                'webhook',
                $url,
                $jsonPayload,
                $isSuccess ? 'sent' : 'failed',
                $isSuccess ? null : "HTTP {$response->status()}: ".$response->body()
            );

            return [
                'success' => $isSuccess,
                'status' => $isSuccess ? 'sent' : 'failed',
                'status_code' => $response->status(),
                'message' => $isSuccess ? "Webhook dispatched to {$url}." : "Webhook returned HTTP {$response->status()}.",
            ];
        } catch (\Throwable $e) {
            Log::error("Webhook Dispatch Exception: ".$e->getMessage());
            $this->logMessageQueue($company->id, 'webhook', $url, $jsonPayload, 'failed', $e->getMessage());

            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Dispatch Receipt / Sale Document across all requested channels.
     */
    public function dispatchReceipt(
        Company $company,
        Sale $sale,
        array $channels,
        ?string $recipientPhone = null,
        ?string $recipientEmail = null
    ): array {
        $phone = $recipientPhone ?: ($sale->customer?->phone ?? $sale->customer_phone ?? '');
        $email = $recipientEmail ?: ($sale->customer?->email ?? $sale->customer_email ?? '');
        $currency = $company->currency_symbol ?: '$';
        $totalFormatted = $currency.number_format((float) $sale->total, 2);
        $publicLink = route('sales.public', $sale->sale_number);
        $storeName = $company->trade_name ?: $company->name;
        $customerName = $sale->customer?->name ?? $sale->customer_name ?? 'Valued Customer';

        $textMessage = "Hello {$customerName},\nThank you for shopping at {$storeName}! Your receipt #{$sale->sale_number} for {$totalFormatted} is ready.\nView online: {$publicLink}\nHave a wonderful day!";

        $results = [];

        if (in_array('whatsapp', $channels, true) && ! empty($phone)) {
            $results['whatsapp'] = $this->dispatchWhatsApp($company, $phone, $textMessage, $publicLink, $sale);
        }

        if (in_array('sms', $channels, true) && ! empty($phone)) {
            $results['sms'] = $this->dispatchSms($company, $phone, $textMessage, [
                'customer_name' => $customerName,
                'total' => $totalFormatted,
                'link' => $publicLink,
            ]);
        }

        if (in_array('email', $channels, true) && ! empty($email)) {
            $pdfData = null;
            try {
                $pdfData = DispatchChannelService::isEmailConfigured($company->id) ? $this->invoiceDeliveryService->generateInvoicePdf($sale) : null;
            } catch (\Throwable $e) {
                Log::warning("Could not generate PDF for email: ".$e->getMessage());
            }

            $subject = "Your Receipt #{$sale->sale_number} from {$storeName}";
            $html = "<div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;'>"
                ."<h2 style='color:#166534;'>{$storeName}</h2>"
                ."<p>Hello <strong>{$customerName}</strong>,</p>"
                ."<p>Thank you for your purchase. Please find your official receipt #<strong>{$sale->sale_number}</strong> for <strong>{$totalFormatted}</strong>.</p>"
                ."<p><a href='{$publicLink}' style='display:inline-block;padding:10px 20px;background:#166534;color:#ffffff;text-decoration:none;border-radius:6px;'>View Digital Receipt</a></p>"
                ."<hr style='border:0;border-top:1px solid #e2e8f0;margin:20px 0;'>"
                ."<p style='color:#64748b;font-size:12px;'>Payment Method: ".ucfirst($sale->payment_method ?? 'Cash')."</p>"
                ."</div>";

            $results['email'] = $this->dispatchEmail($company, $email, $subject, $html, $pdfData, "Receipt-{$sale->sale_number}.pdf");
        }

        if (in_array('custom_webhook', $channels, true) || in_array('webhook', $channels, true)) {
            $results['webhook'] = $this->dispatchWebhook($company, 'receipt_generated', [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'due_amount' => (float) ($sale->due_amount ?? 0),
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'payment_method' => $sale->payment_method,
                'public_link' => $publicLink,
            ]);
        }

        AuditLog::record('receipt.dispatched', $company->id, auth('web')->id() ?? auth('sanctum')->id(), [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'channels' => $channels,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Dispatch Invoice Document across requested channels.
     */
    public function dispatchInvoice(
        Company $company,
        Sale $sale,
        array $channels,
        ?string $recipientPhone = null,
        ?string $recipientEmail = null
    ): array {
        return $this->dispatchReceipt($company, $sale, $channels, $recipientPhone, $recipientEmail);
    }

    /**
     * Dispatch Quotation Document across requested channels.
     */
    public function dispatchQuotation(
        Company $company,
        Sale $quote,
        array $channels,
        ?string $recipientPhone = null,
        ?string $recipientEmail = null
    ): array {
        $phone = $recipientPhone ?: ($quote->customer?->phone ?? $quote->customer_phone ?? '');
        $email = $recipientEmail ?: ($quote->customer?->email ?? $quote->customer_email ?? '');
        $currency = $company->currency_symbol ?: '$';
        $totalFormatted = $currency.number_format((float) $quote->total, 2);
        $publicLink = route('quotes.public', $quote->sale_number);
        $storeName = $company->trade_name ?: $company->name;
        $customerName = $quote->customer?->name ?? $quote->customer_name ?? 'Valued Client';
        $expiryDate = $quote->due_date ? $quote->due_date->format('d M Y') : now()->addDays(15)->format('d M Y');

        $textMessage = "Hello {$customerName},\nPlease find quotation proposal #{$quote->sale_number} for {$totalFormatted} from {$storeName}. Valid until {$expiryDate}.\nReview online: {$publicLink}\nThank you!";

        $results = [];

        if (in_array('whatsapp', $channels, true) && ! empty($phone)) {
            $results['whatsapp'] = $this->dispatchWhatsApp($company, $phone, $textMessage, $publicLink, $quote);
        }

        if (in_array('sms', $channels, true) && ! empty($phone)) {
            $results['sms'] = $this->dispatchSms($company, $phone, $textMessage, [
                'customer_name' => $customerName,
                'total' => $totalFormatted,
                'link' => $publicLink,
            ]);
        }

        if (in_array('email', $channels, true) && ! empty($email)) {
            $pdfData = null;
            try {
                $pdfData = DispatchChannelService::isEmailConfigured($company->id) ? $this->invoiceDeliveryService->generateQuotationPdf($quote) : null;
            } catch (\Throwable $e) {
                Log::warning("Could not generate Quotation PDF: ".$e->getMessage());
            }

            $subject = "Quotation Proposal #{$quote->sale_number} from {$storeName}";
            $html = "<div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;'>"
                ."<h2 style='color:#0284c7;'>{$storeName}</h2>"
                ."<p>Hello <strong>{$customerName}</strong>,</p>"
                ."<p>Please find attached quotation proposal #<strong>{$quote->sale_number}</strong> for <strong>{$totalFormatted}</strong>. This estimate is valid until <strong>{$expiryDate}</strong>.</p>"
                ."<p><a href='{$publicLink}' style='display:inline-block;padding:10px 20px;background:#0284c7;color:#ffffff;text-decoration:none;border-radius:6px;'>Review Proposal Online</a></p>"
                ."</div>";

            $results['email'] = $this->dispatchEmail($company, $email, $subject, $html, $pdfData, "Quotation-{$quote->sale_number}.pdf");
        }

        if (in_array('custom_webhook', $channels, true) || in_array('webhook', $channels, true)) {
            $results['webhook'] = $this->dispatchWebhook($company, 'quotation_sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->sale_number,
                'total' => (float) $quote->total,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'expiry_date' => $expiryDate,
                'public_link' => $publicLink,
            ]);
        }

        AuditLog::record('quotation.dispatched', $company->id, auth('web')->id() ?? auth('sanctum')->id(), [
            'quote_id' => $quote->id,
            'quote_number' => $quote->sale_number,
            'channels' => $channels,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Dispatch Customer Due Balance Reminder.
     */
    public function dispatchDueReminder(
        Company $company,
        Customer $customer,
        array $channels,
        ?string $recipientPhone = null,
        ?string $recipientEmail = null,
        ?float $dueAmount = null
    ): array {
        $phone = $recipientPhone ?: $customer->phone;
        $email = $recipientEmail ?: $customer->email;
        $currency = $company->currency_symbol ?: '$';
        $due = $dueAmount ?? (float) ($customer->due_balance ?? 0);
        $dueFormatted = $currency.number_format($due, 2);
        $storeName = $company->trade_name ?: $company->name;
        $customerName = $customer->name ?: 'Customer';

        $textMessage = "Dear {$customerName},\nThis is a gentle payment reminder from {$storeName}. Your current outstanding balance is {$dueFormatted}. Please clear the balance at your earliest convenience. Thank you!";

        $results = [];

        if (in_array('whatsapp', $channels, true) && ! empty($phone)) {
            $results['whatsapp'] = $this->dispatchWhatsApp($company, $phone, $textMessage);
        }

        if (in_array('sms', $channels, true) && ! empty($phone)) {
            $results['sms'] = $this->dispatchSms($company, $phone, $textMessage, [
                'customer_name' => $customerName,
                'total' => $dueFormatted,
            ]);
        }

        if (in_array('email', $channels, true) && ! empty($email)) {
            $subject = "Payment Due Reminder from {$storeName}";
            $html = "<div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;'>"
                ."<h2 style='color:#dc2626;'>{$storeName} - Account Statement</h2>"
                ."<p>Dear <strong>{$customerName}</strong>,</p>"
                ."<p>We would like to remind you that your account has an outstanding balance of <strong style='color:#dc2626;font-size:18px;'>{$dueFormatted}</strong>.</p>"
                ."<p>Kindly arrange payment via cash, card, or UPI at our counter or contact us if you have any questions.</p>"
                ."<p style='color:#64748b;font-size:12px;'>Thank you for your valued business!</p>"
                ."</div>";

            $results['email'] = $this->dispatchEmail($company, $email, $subject, $html);
        }

        if (in_array('custom_webhook', $channels, true) || in_array('webhook', $channels, true)) {
            $results['webhook'] = $this->dispatchWebhook($company, 'due_reminder', [
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'due_balance' => $due,
            ]);
        }

        AuditLog::record('reminder.dispatched', $company->id, auth('web')->id() ?? auth('sanctum')->id(), [
            'customer_id' => $customer->id,
            'channels' => $channels,
            'due_balance' => $due,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Test gateway connectivity and send test ping.
     */
    public function testGateway(
        Company $company,
        TenantNotificationGateway $gateway,
        ?string $testRecipient = null
    ): array {
        $channel = $gateway->channel;
        $storeName = $company->trade_name ?: $company->name;

        switch ($channel) {
            case TenantNotificationGateway::CHANNEL_WHATSAPP:
                $phone = $testRecipient ?: ($company->phone ?: '1234567890');
                $message = "Test message from {$storeName}! Your WhatsApp Business notification gateway is active and configured properly.";

                return $this->dispatchWhatsApp($company, $phone, $message);

            case TenantNotificationGateway::CHANNEL_SMS:
                $phone = $testRecipient ?: ($company->phone ?: '1234567890');
                $message = "Test SMS from {$storeName}: SMS Gateway configured successfully!";

                return $this->dispatchSms($company, $phone, $message);

            case TenantNotificationGateway::CHANNEL_EMAIL:
                $email = $testRecipient ?: ($company->email ?: 'admin@example.com');
                $subject = "Test Email from {$storeName}";
                $html = "<p>Congratulations! Your Custom SMTP gateway is active and sending emails successfully for <strong>{$storeName}</strong>.</p>";

                return $this->dispatchEmail($company, $email, $subject, $html);

            case TenantNotificationGateway::CHANNEL_WEBHOOK:
                return $this->dispatchWebhook($company, 'test_ping', [
                    'test' => true,
                    'message' => "Webhook integration test from {$storeName}",
                    'sent_at' => now()->toIso8601String(),
                ]);

            default:
                return ['success' => false, 'message' => "Unknown channel '{$channel}'."];
        }
    }

    protected function dispatchHttpMessage(Company $company, string $channel, string $recipient, string $message, string $url, string $token, ?string $documentUrl = null): array
    {
        try {
            $endpoint = str_replace(['{phone}', '{to}', '{message}'], [rawurlencode($recipient), rawurlencode($recipient), rawurlencode($message)], $url);
            $client = Http::timeout(15)->acceptJson();
            if ($token !== '') {
                $client = $client->withToken($token);
            }
            $response = $channel === 'sms'
                ? $client->get($endpoint)
                : $client->post($endpoint, ['recipient' => $recipient, 'message' => $message, 'media_url' => $documentUrl]);
            $success = $response->successful();
            $this->logMessageQueue($company->id, $channel, $recipient, $message, $success ? 'sent' : 'failed', $success ? null : $response->body());

            return ['success' => $success, 'status' => $success ? 'sent' : 'failed', 'message' => $success ? 'Message sent via configured gateway.' : 'Configured gateway rejected the message.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function logMessageQueue(
        string $companyId,
        string $type,
        string $recipient,
        string $body,
        string $status,
        ?string $error = null,
        ?string $providerId = null
    ): void {
        try {
            MessageQueue::create([
                'company_id' => $companyId,
                'type' => $type,
                'recipient' => $recipient,
                'payload' => ['body' => $body, 'provider_id' => $providerId],
                'status' => $status,
                'last_error' => $error,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to write to message_queue: ".$e->getMessage());
        }
    }
}
