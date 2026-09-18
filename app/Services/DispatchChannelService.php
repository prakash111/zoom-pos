<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\TenantNotificationGateway;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\WhatsApp\WhatsAppCloudApiClient;
use Illuminate\Support\Facades\Schema;

class DispatchChannelService
{
    /**
     * Determine if SMS Gateway notifications are enabled and configured for a tenant.
     */
    public static function isSmsConfigured(mixed $tenantId): bool
    {
        $companyId = self::resolveCompanyId($tenantId);
        $gateway = self::gateway($companyId, TenantNotificationGateway::CHANNEL_SMS);

        if ($gateway !== null) {
            return $gateway->isConfigured();
        }

        $settings = self::apiSettings($companyId);
        if (array_key_exists('sms_api_enabled', $settings)) {
            return self::isEnabledValue($settings['sms_api_enabled'])
                && filled($settings['sms_api_url'] ?? $settings['generic_sms_url'] ?? null);
        }

        $legacy = self::configuration($companyId, 'sms_gateway');
        $legacy = is_string($legacy) ? (json_decode($legacy, true) ?: []) : $legacy;

        return is_array($legacy)
            && ! empty($legacy['gateway_url'] ?? $legacy['url'] ?? null)
            && self::isEnabledValue($legacy['is_enabled'] ?? $legacy['enabled'] ?? true);
    }

    /**
     * Determine if WhatsApp is available for this tenant/recipient.
     */
    public static function isWhatsAppConfigured(mixed $tenantId): bool
    {
        $companyId = self::resolveCompanyId($tenantId);
        $gateway = self::gateway($companyId, TenantNotificationGateway::CHANNEL_WHATSAPP);
        if ($gateway !== null) {
            return $gateway->isConfigured();
        }

        $company = Company::withoutGlobalScopes()->find($companyId);

        $settings = self::apiSettings($companyId);
        if (array_key_exists('whatsapp_api_enabled', $settings)) {
            if (! self::isEnabledValue($settings['whatsapp_api_enabled'])) {
                return false;
            }
            if (filled($settings['whatsapp_api_url'] ?? null) && filled($settings['whatsapp_api_token'] ?? null)) {
                return true;
            }
        }

        return $company && app(WhatsAppCloudApiClient::class)->isConfigured($company);
    }

    /**
     * Determine if Email dispatch is available for this tenant/recipient.
     */
    public static function isEmailConfigured(mixed $tenantId): bool
    {
        $companyId = self::resolveCompanyId($tenantId);
        $gateway = self::gateway($companyId, TenantNotificationGateway::CHANNEL_EMAIL);
        if ($gateway !== null) {
            return $gateway->isConfigured();
        }

        $smtp = app(InvoiceDeliveryService::class)->getSmtpConfig(
            Company::withoutGlobalScopes()->find($companyId)
        );

        return filled($smtp['host'] ?? null);
    }

    /**
     * Build dynamic channel options based on active tenant configurations.
     */
    public static function getAvailableChannels(mixed $tenantId, array $context = []): array
    {
        $companyId = self::resolveCompanyId($tenantId);

        return OmnichannelRegistryService::resolveChannels($companyId, $context);
    }

    public static function splitChannels(array $channels): array
    {
        return [
            'api' => array_values(array_filter($channels, fn ($channel) => ($channel['delivery_mode'] ?? 'api') !== 'utility' && ($channel['api_enabled'] ?? true)
                && ($channel['delivery_mode'] ?? 'api') !== 'device')),
            'device' => array_values(array_filter($channels, fn ($channel) => ($channel['delivery_mode'] ?? 'api') !== 'utility'
                && (! ($channel['api_enabled'] ?? true) || ($channel['delivery_mode'] ?? 'api') === 'device'))),
        ];
    }

    /** Render every channel together; only configured APIs participate in the form. */
    public static function groupedComponents(array $channels, array $context): array
    {
        $type = $context['type'] ?? $context['document_type'] ?? 'document';
        $id = $context['id'] ?? $context['document_id'] ?? null;
        $phone = $context['phone'] ?? $context['recipient_phone'] ?? '';
        $email = $context['email'] ?? $context['recipient_email'] ?? '';
        $payload = ['document_type' => $type, 'document_id' => $id, 'phone' => $phone, 'email' => $email, 'api_only' => true];
        $utilities = self::documentUtilities($context);
        $components = [$utilities[0]];
        $apiChannels = [];

        $order = ['whatsapp' => 0, 'email' => 1, 'sms' => 2];
        usort($channels, fn ($a, $b) => ($order[$a['channel']] ?? 3) <=> ($order[$b['channel']] ?? 3));
        foreach ($channels as $channel) {
            $key = $channel['channel'] === 'custom' ? 'custom:'.$channel['channel_id'] : $channel['channel'];
            $device = ! ($channel['api_enabled'] ?? true) || ($channel['delivery_mode'] ?? 'api') === 'device';
            if ($device) {
                $components[] = array_merge($channel, [
                    'type' => 'list_tile', 'available' => true, 'visible' => true,
                    'selectable' => false, 'default' => false, 'mode' => 'local_intent', 'launch_label' => 'Open',
                ]);
                continue;
            }

            $apiChannels[] = $key;
            $recipient = $channel['channel'] === 'email' ? $email : $phone;
            $component = array_merge($channel, [
                'type' => 'checkbox', 'name' => 'channels[]', 'label' => $channel['title'],
                'value' => $key, 'option_value' => $key, 'selection_value' => $key,
                'available' => true, 'visible' => true, 'selectable' => true, 'mode' => 'cloud_api',
                'initial_value' => in_array($key, ['whatsapp', 'sms', 'email'], true) && filled($recipient),
            ]);
            unset($component['action'], $component['action_type'], $component['on_tap'], $component['endpoint']);
            $components[] = $component;
        }
        $showPreview = $context['show_preview'] ?? ! in_array($type, ['repair', 'ticket', 'job_sheet'], true);
        if ($showPreview) {
            $components[] = $utilities[1];
        }
        if ($apiChannels && $id !== null) {
            $components[] = [
                'type' => 'button_primary', 'id' => 'dispatch_selected_channels', 'label' => 'Send to Selected Channels',
                'action' => ['type' => 'form_submit', 'endpoint' => '/api/v1/documents/dispatch', 'method' => 'POST',
                    'collect_form' => true, 'data' => $payload, 'payload' => $payload],
            ];
        }

        return $components;
    }

    public static function documentUtilities(array $context): array
    {
        $type = $context['type'] ?? $context['document_type'] ?? 'document';
        $id = $context['id'] ?? $context['document_id'] ?? null;
        $pathType = rawurlencode((string) $type);
        $pathId = rawurlencode((string) $id);
        $endpoint = "/api/v1/tenant/documents/{$pathType}/{$pathId}/preview-modal?format=a4&preview_document=1";
        $document = ['document_type' => $type, 'document_id' => $id];
        $metadata = ['type' => 'list_tile', 'available' => true, 'visible' => true, 'api_enabled' => false,
            'selectable' => false, 'default' => false, 'mode' => 'document_action', 'delivery_mode' => 'utility'];
        $pdfAction = ['type' => 'OPEN_BOTTOM_SHEET', 'endpoint' => $endpoint] + $document;
        if (in_array($type, ['invoice', 'invoice_reminder', 'sale', 'receipt', 'quotation', 'quote'], true)) {
            $pdfAction = ['type' => 'OPEN_RECEIPT_PREVIEW', 'endpoint' => $endpoint,
                'pdf_endpoint' => "/api/tenant/invoices/{$pathId}/pdf-stream"] + $document;
        }

        return [
            $metadata + [
                'id' => 'channel_thermal_print', 'channel' => 'thermal_print', 'title' => 'Thermal Print',
                'subtitle' => 'Print on a Bluetooth or network receipt printer', 'launch_label' => 'Print',
                'leading' => ['type' => 'icon', 'icon' => 'print', 'color' => '#10B981', 'size' => 22],
                'action_type' => 'THERMAL_PRINT', 'action' => ['type' => 'THERMAL_PRINT', 'data' => $document] + $document,
            ],
            $metadata + [
                'id' => 'channel_pdf_preview', 'channel' => 'pdf_preview', 'title' => 'PDF Preview',
                'subtitle' => 'Preview and print the document', 'launch_label' => 'Preview',
                'leading' => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'color' => '#818CF8', 'size' => 22],
                'action_type' => $pdfAction['type'], 'action' => $pdfAction,
            ],
        ];
    }

    public static function visibleChannels(array $components): array
    {
        return array_values(array_filter($components, fn ($component) => isset($component['channel'])));
    }

    private static function resolveCompanyId(mixed $tenantId): mixed
    {
        $companyId = is_object($tenantId) ? ($tenantId->id ?? null) : $tenantId;

        return $companyId
            ?: (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?: auth('tenant_api')->user()?->company_id
            ?: auth('web')->user()?->company_id
            ?: auth()->user()?->company_id
            ?: auth()->user()?->tenant_id;
    }

    public static function apiSettings(mixed $companyId): array
    {
        $settings = (array) (Company::withoutGlobalScopes()->find($companyId)?->api_settings ?? []);
        if ($companyId && Schema::hasTable('configurations')) {
            $settings = array_merge($settings, Configuration::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('key', ['whatsapp_api_enabled', 'whatsapp_api_url', 'whatsapp_api_token', 'sms_api_enabled', 'sms_api_url', 'sms_api_token', 'generic_sms_url', 'generic_sms_api_key'])
                ->pluck('value', 'key')->all());
        }

        return $settings;
    }

    private static function gateway(mixed $companyId, string $channel): ?TenantNotificationGateway
    {
        if (! $companyId || ! Schema::hasTable('tenant_notification_gateways')) {
            return null;
        }

        return TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('channel', $channel)
            ->first();
    }

    private static function configuration(mixed $companyId, string $key): mixed
    {
        if (! $companyId || ! Schema::hasTable('configurations')) {
            return null;
        }

        return Configuration::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->value('value');
    }

    private static function isEnabledValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    /**
     * Build the universal dispatch sheet with cloud selections and device intents.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<string, mixed>  $extraPayload
     * @param  array<string, mixed>  $headerInfo
     * @return array<string, mixed>
     */
    public static function buildBottomSheetSchema(
        string $title,
        array $channels,
        string $dispatchEndpoint,
        array $extraPayload = [],
        array $headerInfo = []
    ): array {
        $context = array_merge($headerInfo, $extraPayload);
        $components = self::groupedComponents($channels, $context);
        $groups = self::splitChannels(self::visibleChannels($components));
        $options = array_map(fn ($channel) => [
            'label' => $channel['title'], 'icon' => $channel['leading']['icon'] ?? 'send',
            'action' => $channel['action'] ?? ['type' => 'OPEN_BOTTOM_SHEET',
                'endpoint' => '/api/v1/tenant/documents/'.rawurlencode((string) ($context['document_type'] ?? $context['type'] ?? 'document')).'/'.rawurlencode((string) ($context['document_id'] ?? $context['id'] ?? '')).'/dispatch-options'],
        ], self::visibleChannels($components));
        $schema = ['type' => 'bottom_sheet', 'title' => $title, 'header' => $headerInfo, 'components' => $components];

        return $schema + ['channels' => self::visibleChannels($components), 'enabled_channels' => $groups['api'],
            'device_channels' => $groups['device'], 'secondary_options' => $groups['device'],
            'multi_select' => true, 'options' => $options, 'schema' => $schema];
    }
}
