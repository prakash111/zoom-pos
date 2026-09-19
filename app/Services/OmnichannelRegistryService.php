<?php

namespace App\Services;

use App\Models\CustomNotificationChannel;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\TenantNotificationGateway;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Notifications\DeviceMessageService;
use App\Services\Repair\RepairNotificationService;
use App\Services\Restaurant\KotDeliveryService;
use Illuminate\Support\Facades\Schema;

class OmnichannelRegistryService
{
    /**
     * Get gateway and device dispatch options formatted for SDUI bottom sheets.
     */
    public static function resolveChannels(mixed $tenantId, array $context): array
    {
        $phone       = $context['phone'] ?? $context['recipient_phone'] ?? $context['customer_phone'] ?? null;
        $email       = $context['email'] ?? $context['recipient_email'] ?? $context['customer_email'] ?? null;
        $documentId  = $context['id'] ?? $context['document_id'] ?? null;
        $docType     = $context['type'] ?? $context['document_type'] ?? 'document';
        $reference   = $context['reference'] ?? $context['document_number'] ?? "DOC-{$documentId}";
        $cleanPhone  = preg_replace('/[^0-9+]/', '', (string) ($phone ?? ''));

        $channelItems = [];
        $deviceMessage = null;

        foreach ([
            'whatsapp' => ['WhatsApp', 'chat', '#25D366', $cleanPhone],
            'sms' => ['SMS', 'textsms', '#38BDF8', $cleanPhone],
            'email' => ['Email', 'mark_email_read', '#818CF8', $email],
        ] as $channel => [$label, $icon, $color, $recipient]) {
            $configured = match ($channel) {
                'whatsapp' => DispatchChannelService::isWhatsAppConfigured($tenantId),
                'sms' => DispatchChannelService::isSmsConfigured($tenantId),
                'email' => DispatchChannelService::isEmailConfigured($tenantId),
            };
            if ($configured) {
                $action = [
                    'type' => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method' => 'POST',
                    'data' => ['channel' => $channel, 'type' => $docType, 'id' => $documentId, 'recipient' => $recipient ?: ''],
                ];
            } else {
                $deviceMessage ??= self::deviceMessage($tenantId, $docType, $documentId, $reference, $context);
                $subject = $context['subject'] ?? ucfirst($docType).' #'.ltrim((string) $reference, '#');
                $action = [
                    'type' => 'OPEN_URL',
                    'url' => DeviceMessageService::appUrl($channel, (string) $recipient, $deviceMessage, $subject),
                    'fallback_url' => DeviceMessageService::url($channel, (string) $recipient, $deviceMessage, $subject),
                ];
            }
            $channelItems[] = [
                'type' => 'list_tile',
                'id' => 'channel_'.$channel,
                'channel' => $channel,
                'title' => $configured ? 'Send via '.$label.' [Cloud API]' : match ($channel) {
                    'whatsapp' => 'Open WhatsApp App', 'email' => 'Open Mail App', 'sms' => 'Open Messages / SMS',
                },
                'subtitle' => ($recipient ? 'to '.$recipient.' — ' : '').($configured ? 'Configured gateway' : 'Complete sending in your device app'),
                'api_enabled' => $configured,
                'selectable' => $configured, 'default' => $configured && filled($recipient),
                'available' => true, 'visible' => true, 'mode' => $configured ? 'cloud_api' : 'local_intent',
                ...($configured ? [] : ['launch_label' => 'Open']),
                'delivery_mode' => $configured ? 'api' : 'device',
                'leading' => ['type' => 'icon', 'icon' => $icon, 'color' => $color, 'size' => 22],
                'trailing' => ['type' => 'icon', 'icon' => 'send', 'size' => 18, 'color' => 'theme.textSecondary'],
                'action_type' => $action['type'],
                'action' => $action,
                'background_color' => 'theme.surface',
                'divider_color' => 'theme.divider',
                'text_color' => 'theme.textPrimary',
            ];
        }

        // -------------------------------------------------------------
        // 4. WEBHOOKS (If Outbound Webhooks are Enabled)
        // -------------------------------------------------------------
        $webhookSettings = function_exists('tenant_setting') ? tenant_setting($tenantId, 'webhook_dispatcher', []) : [];
        if (is_string($webhookSettings)) {
            $webhookSettings = json_decode($webhookSettings, true) ?: [];
        }
        if (class_exists(TenantNotificationGateway::class)) {
            $gw = TenantNotificationGateway::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                })
                ->where('channel', TenantNotificationGateway::CHANNEL_WEBHOOK)
                ->first();
            if ($gw) {
                $webhookSettings['enabled'] = (bool) $gw->is_enabled;
                $webhookSettings['is_enabled'] = (bool) $gw->is_enabled;
                $webhookSettings['destination_url'] = $gw->credentials['url'] ?? ($webhookSettings['destination_url'] ?? null);
            }
        }
        $webhookExplicitlyDisabled = (isset($webhookSettings['is_enabled']) && ! $webhookSettings['is_enabled'])
            || (isset($webhookSettings['enabled']) && ! $webhookSettings['enabled']);
        $webhookEnabled = ! $webhookExplicitlyDisabled
            && (! empty($webhookSettings['enabled']) || ! empty($webhookSettings['is_enabled']) || (function_exists('tenant_setting') && tenant_setting($tenantId, 'enable_webhooks', false)));

        if ($webhookEnabled && (! empty($webhookSettings['destination_url']) || ! empty($webhookSettings['url']))) {
            $channelItems[] = [
                'type'        => 'list_tile',
                'id'          => 'channel_webhook',
                'channel'     => 'webhook',
                'title'       => 'Trigger External Webhook',
                'subtitle'    => 'POST payload to configured destination URL',
                'leading'     => ['type' => 'icon', 'icon' => 'hub', 'color' => '#A855F7', 'size' => 22],
                'trailing'    => ['type' => 'icon', 'icon' => 'send', 'size' => 18, 'color' => 'theme.textSecondary'],
                'action_type' => 'SUBMIT_FORM',
                'action'      => [
                    'type'     => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method'   => 'POST',
                    'data'     => ['channel' => 'webhook', 'type' => $docType, 'id' => $documentId],
                    'feedback' => 'Webhook dispatched successfully.',
                ],
                'background_color' => 'theme.surface',
                'divider_color'    => 'theme.divider',
                'text_color'       => 'theme.textPrimary',
            ];
        }

        // -------------------------------------------------------------
        // 5. TENANT-DEFINED CUSTOM CHANNELS
        // -------------------------------------------------------------
        // These rows deliberately use the same list_tile/action contract as
        // built-in channels. Flutter therefore renders newly-created custom
        // integrations without a client release or another hardcoded tile.
        if (Schema::hasTable('custom_notification_channels')) {
            $eventType = $docType === 'quotation' ? 'quotation' : 'invoice';
            $customChannels = CustomNotificationChannel::withoutGlobalScope('company')
                ->where('company_id', $tenantId)
                ->where('is_active', true)
                ->get()
                ->filter(fn (CustomNotificationChannel $channel) => $docType === 'document' || $channel->handlesEvent($eventType)
                    || ($docType === 'kot' && ($channel->handlesEvent('kot') || $channel->handlesEvent('kot_created'))));

            foreach ($customChannels as $customChannel) {
                $channelItems[] = [
                    'type' => 'list_tile',
                    'id' => 'channel_custom_'.$customChannel->id,
                    'channel' => 'custom',
                    'channel_id' => $customChannel->id,
                    'title' => 'Send via '.$customChannel->name,
                    'subtitle' => parse_url((string) $customChannel->url, PHP_URL_HOST) ?: 'Custom integration',
                    'leading' => [
                        'type' => 'icon',
                        'icon' => $customChannel->icon ?: 'webhook',
                        'color' => '#A855F7',
                        'size' => 22,
                    ],
                    'trailing' => [
                        'type' => 'icon',
                        'icon' => 'send',
                        'size' => 18,
                        'color' => 'theme.textSecondary',
                    ],
                    'action_type' => 'SUBMIT_FORM',
                    'action' => [
                        'type' => 'SUBMIT_FORM',
                        'endpoint' => "/api/v1/tenant/dispatch/{$docType}/{$documentId}",
                        'method' => 'POST',
                        'data' => [
                            'channel' => 'custom',
                            'channel_id' => $customChannel->id,
                        ],
                        'feedback' => "Dispatched via {$customChannel->name}.",
                    ],
                    'background_color' => 'theme.surface',
                    'divider_color' => 'theme.divider',
                    'text_color' => 'theme.textPrimary',
                ];
            }
        }

        // -------------------------------------------------------------
        // 6. EXTENSIBILITY HOOK FOR FUTURE CHANNELS
        // -------------------------------------------------------------
        return function_exists('apply_filters') ? apply_filters('tenant_dispatch_channels', $channelItems, $tenantId, $context) : $channelItems;
    }

    private static function deviceMessage(mixed $tenantId, string $type, mixed $id, string $reference, array $context): string
    {
        if (isset($context['message'])) {
            return (string) $context['message'];
        }

        if ($id && $type === 'kot') {
            $delivery = app(KotDeliveryService::class);

            return $delivery->message($delivery->find($tenantId, $id));
        }

        if ($id && $type === 'repair') {
            $ticket = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $tenantId)
                ->where(fn ($query) => $query->where('id', $id)->orWhere('ticket_number', $id))
                ->with(['company', 'customer'])
                ->first();
            if ($ticket) {
                return app(RepairNotificationService::class)->buildCustomerMessage($ticket);
            }
        }

        if ($id && in_array($type, ['invoice', 'sale', 'receipt', 'quotation', 'quote', 'due_invoice', 'due_reminder'], true)) {
            $sale = Sale::withoutGlobalScope('company')
                ->where('company_id', $tenantId)
                ->where(fn ($query) => $query->where('id', $id)->orWhere('sale_number', $id))
                ->with(['company', 'customer'])
                ->first();
            if ($sale) {
                $delivery = app(InvoiceDeliveryService::class);

                return match (true) {
                    in_array($type, ['due_invoice', 'due_reminder'], true) => $delivery->buildDueReminderMessage($sale),
                    $sale->operation_type === 'quotation' => $delivery->buildQuotationWhatsAppMessage($sale),
                    default => $delivery->buildInvoiceWhatsAppMessage($sale),
                };
            }
        }

        return (string) ($context['default_message'] ?? 'Please review your '.str_replace('_', ' ', $type).' #'.$reference.'.');
    }
}
