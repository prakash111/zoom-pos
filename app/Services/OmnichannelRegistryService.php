<?php

namespace App\Services;

use App\Models\CustomNotificationChannel;
use App\Models\TenantNotificationGateway;
use Illuminate\Support\Facades\Schema;

class OmnichannelRegistryService
{
    /**
     * Get all active, configured dispatch channels formatted for SDUI bottom sheets.
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

        // -------------------------------------------------------------
        // 1. WHATSAPP (Cloud API or Direct Fallback)
        // -------------------------------------------------------------
        $waSettings = function_exists('tenant_setting') ? tenant_setting($tenantId, 'whatsapp_gateway', []) : [];
        if (is_string($waSettings)) {
            $waSettings = json_decode($waSettings, true) ?: [];
        }
        if (class_exists(TenantNotificationGateway::class)) {
            $gw = TenantNotificationGateway::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                })
                ->where('channel', TenantNotificationGateway::CHANNEL_WHATSAPP)
                ->first();
            if ($gw) {
                $waSettings['enabled'] = (bool) $gw->is_enabled;
                $waSettings['is_enabled'] = (bool) $gw->is_enabled;
                $waSettings['provider'] = $gw->provider;
                $waSettings['access_token'] = $gw->credentials['access_token'] ?? ($waSettings['access_token'] ?? null);
            }
        }
        $waExplicitlyDisabled = (isset($waSettings['is_enabled']) && ! $waSettings['is_enabled'])
            || (isset($waSettings['enabled']) && ! $waSettings['enabled']);
        $waEnabled = ! $waExplicitlyDisabled
            && (! empty($waSettings['enabled']) || ! empty($waSettings['is_enabled']) || (function_exists('tenant_setting') && tenant_setting($tenantId, 'enable_whatsapp', true)));

        if ($waEnabled) {
            $isMetaApi = ($waSettings['provider'] ?? '') === 'meta_cloud_api' && ! empty($waSettings['access_token']);
            $channelItems[] = [
                'type'        => 'list_tile',
                'id'          => 'channel_whatsapp',
                'channel'     => 'whatsapp',
                'title'       => $isMetaApi ? 'Send via WhatsApp Business API' : 'Send via WhatsApp',
                'subtitle'    => ! empty($cleanPhone) ? "to {$cleanPhone}" : 'Enter phone number',
                'leading'     => ['type' => 'icon', 'icon' => 'chat', 'color' => '#25D366', 'size' => 22],
                'trailing'    => ['type' => 'icon', 'icon' => 'send', 'size' => 18, 'color' => 'theme.textSecondary'],
                'action_type' => 'SUBMIT_FORM',
                'action'      => [
                    'type'     => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method'   => 'POST',
                    'data'     => ['channel' => 'whatsapp', 'type' => $docType, 'id' => $documentId, 'recipient' => $cleanPhone ?: ''],
                    'feedback' => ! empty($cleanPhone) ? "Dispatched via WhatsApp to {$cleanPhone}" : 'Dispatched via WhatsApp',
                ],
                'background_color' => 'theme.surface',
                'divider_color'    => 'theme.divider',
                'text_color'       => 'theme.textPrimary',
            ];
        }

        // -------------------------------------------------------------
        // 2. SMS GATEWAYS (Generic HTTP, Android Gateway, Twilio, MSG91)
        // -------------------------------------------------------------
        $smsSettings = function_exists('tenant_setting') ? tenant_setting($tenantId, 'sms_gateway', []) : [];
        if (is_string($smsSettings)) {
            $smsSettings = json_decode($smsSettings, true) ?: [];
        }
        if (class_exists(TenantNotificationGateway::class)) {
            $gw = TenantNotificationGateway::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                })
                ->where('channel', TenantNotificationGateway::CHANNEL_SMS)
                ->first();
            if ($gw) {
                $smsSettings['enabled'] = (bool) $gw->is_enabled;
                $smsSettings['is_enabled'] = (bool) $gw->is_enabled;
                $smsSettings['provider'] = $gw->provider;
                $smsSettings['gateway_url'] = $gw->credentials['url'] ?? ($gw->credentials['gateway_url'] ?? ($smsSettings['gateway_url'] ?? null));
            }
        }
        $smsExplicitlyDisabled = (isset($smsSettings['is_enabled']) && ! $smsSettings['is_enabled'])
            || (isset($smsSettings['enabled']) && ! $smsSettings['enabled']);
        $smsGlobalEnabled = ! $smsExplicitlyDisabled
            && ((function_exists('tenant_setting') && tenant_setting($tenantId, 'enable_sms', false)) || ! empty($smsSettings['enabled']) || ! empty($smsSettings['is_enabled']));
        $hasSmsUrl = ! $smsExplicitlyDisabled
            && (! empty($smsSettings['gateway_url']) || ! empty($smsSettings['url']) || (function_exists('tenant_setting') && ! empty(tenant_setting($tenantId, 'generic_sms_gateway'))));

        if ($smsGlobalEnabled || $hasSmsUrl) {
            $providerName = ucwords(str_replace('_', ' ', $smsSettings['provider'] ?? 'Android Gateway'));

            $channelItems[] = [
                'type'        => 'list_tile',
                'id'          => 'channel_sms',
                'channel'     => 'sms',
                'title'       => 'Send via SMS (Text Message)',
                'subtitle'    => ! empty($cleanPhone) ? "via {$providerName} to {$cleanPhone}" : "via {$providerName} — enter phone number",
                'leading'     => ['type' => 'icon', 'icon' => 'textsms', 'color' => '#38BDF8', 'size' => 22],
                'trailing'    => ['type' => 'icon', 'icon' => 'send', 'size' => 18, 'color' => 'theme.textSecondary'],
                'action_type' => 'SUBMIT_FORM',
                'action'      => [
                    'type'     => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method'   => 'POST',
                    'data'     => ['channel' => 'sms', 'type' => $docType, 'id' => $documentId, 'recipient' => $cleanPhone ?: ''],
                    'feedback' => "SMS queued via {$providerName}",
                ],
                'background_color' => 'theme.surface',
                'divider_color'    => 'theme.divider',
                'text_color'       => 'theme.textPrimary',
            ];
        }

        // -------------------------------------------------------------
        // 3. EMAIL (Custom SMTP / System Mailer)
        // -------------------------------------------------------------
        $smtpSettings = function_exists('tenant_setting') ? tenant_setting($tenantId, 'custom_smtp', []) : [];
        if (is_string($smtpSettings)) {
            $smtpSettings = json_decode($smtpSettings, true) ?: [];
        }
        if (class_exists(TenantNotificationGateway::class)) {
            $gw = TenantNotificationGateway::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                })
                ->where('channel', TenantNotificationGateway::CHANNEL_EMAIL)
                ->first();
            if ($gw) {
                $smtpSettings['enabled'] = (bool) $gw->is_enabled;
                $smtpSettings['is_enabled'] = (bool) $gw->is_enabled;
                $smtpSettings['host'] = $gw->credentials['host'] ?? ($smtpSettings['host'] ?? null);
            }
        }
        $smtpExplicitlyDisabled = (isset($smtpSettings['is_enabled']) && ! $smtpSettings['is_enabled'])
            || (isset($smtpSettings['enabled']) && ! $smtpSettings['enabled']);
        $smtpEnabled = ! $smtpExplicitlyDisabled
            && (! empty($smtpSettings['enabled']) || ! empty($smtpSettings['is_enabled']) || (function_exists('tenant_setting') && tenant_setting($tenantId, 'enable_smtp', false)));
        $hasSmtpHost = ! empty($smtpSettings['host']);

        if (! $smtpExplicitlyDisabled && ($smtpEnabled || $hasSmtpHost || config('mail.default'))) {
            $hostLabel = ! empty($smtpSettings['host']) ? " ({$smtpSettings['host']})" : '';

            $channelItems[] = [
                'type'        => 'list_tile',
                'id'          => 'channel_email',
                'channel'     => 'email',
                'title'       => "Send via Email{$hostLabel}",
                'subtitle'    => ! empty($email) ? "to {$email}" : 'Enter email address',
                'leading'     => ['type' => 'icon', 'icon' => 'mark_email_read', 'color' => '#818CF8', 'size' => 22],
                'trailing'    => ['type' => 'icon', 'icon' => 'send', 'size' => 18, 'color' => 'theme.textSecondary'],
                'action_type' => 'SUBMIT_FORM',
                'action'      => [
                    'type'     => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method'   => 'POST',
                    'data'     => ['channel' => 'email', 'type' => $docType, 'id' => $documentId, 'recipient' => $email ?: ''],
                    'feedback' => ! empty($email) ? "Official document emailed to {$email}" : 'Official document emailed',
                ],
                'background_color' => 'theme.surface',
                'divider_color'    => 'theme.divider',
                'text_color'       => 'theme.textPrimary',
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
                ->filter(fn (CustomNotificationChannel $channel) => $channel->handlesEvent($eventType));

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
}
