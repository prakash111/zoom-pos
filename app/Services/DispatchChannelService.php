<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\CustomNotificationChannel;
use App\Models\TenantNotificationGateway;
use App\Services\Invoice\InvoiceDeliveryService;
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

        $legacy = self::configuration($companyId, 'sms_gateway');
        $legacy = is_string($legacy) ? (json_decode($legacy, true) ?: []) : $legacy;

        return is_array($legacy)
            && ! empty($legacy['gateway_url'] ?? $legacy['url'] ?? null)
            && self::isEnabledValue($legacy['is_enabled'] ?? true);
    }

    /**
     * Determine if WhatsApp is available for this tenant/recipient.
     */
    public static function isWhatsAppConfigured(mixed $tenantId): bool
    {
        $gateway = self::activeGateway(
            self::resolveCompanyId($tenantId),
            TenantNotificationGateway::CHANNEL_WHATSAPP
        );

        return $gateway?->isConfigured() ?? false;
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

        if (filled(self::configuration($companyId, 'smtp_host'))) {
            return true;
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

    private static function activeGateway(mixed $companyId, string $channel): ?TenantNotificationGateway
    {
        $gateway = self::gateway($companyId, $channel);

        return $gateway?->is_enabled ? $gateway : null;
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
     * Build standard SDUI Bottom Sheet Schema containing dynamic action cards and options.
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
        $components = [];

        // 1. Optional Header Component
        if (! empty($headerInfo['subtitle'])) {
            $components[] = [
                'type' => 'text',
                'text' => $headerInfo['subtitle'],
                'style' => [
                    'fontSize' => 14,
                    'color' => '#94A3B8',
                    'marginBottom' => 12,
                ],
            ];
        }

        // 2. Action Options for action_sheet_trigger
        $actionSheetOptions = [];

        // 3. Render Card components for each channel (compatible with SDUI modal sheets)
        foreach ($channels as $chan) {
            $channelKey = $chan['channel'] ?? $chan['id'] ?? 'sms';
            $channelTitle = $chan['title'] ?? $chan['name'] ?? ucfirst($channelKey);
            $channelSubtitle = $chan['subtitle'] ?? '';
            $channelIcon = $chan['leading']['icon'] ?? $chan['icon'] ?? 'message';
            $channelColor = $chan['leading']['color'] ?? $chan['color'] ?? '#38BDF8';

            $payload = array_merge(['channel' => $channelKey], $extraPayload);
            if (! empty($chan['channel_id'])) {
                $payload['channel_id'] = $chan['channel_id'];
            }
            if (! empty($chan['recipient'])) {
                $payload['phone'] = $chan['recipient'];
                $payload['email'] = $chan['recipient'];
                $payload['recipient'] = $chan['recipient'];
            }

            $successMsg = "Dispatched via {$channelTitle} successfully.";

            $actionDescriptor = $chan['action'] ?? [
                'type' => 'SUBMIT_FORM',
                'action_type' => 'SUBMIT_FORM',
                'action' => 'SUBMIT_FORM',
                'action_name' => 'SUBMIT_FORM',
                'endpoint' => $dispatchEndpoint,
                'method' => 'POST',
                'data' => $payload,
                'payload' => $payload,
                'feedback' => $successMsg,
                'success_toast' => $successMsg,
                'navigate_back' => true,
            ];

            $actionSheetOptions[] = [
                'label' => $channelTitle.($channelSubtitle ? " ({$channelSubtitle})" : ''),
                'icon' => $channelIcon,
                'action' => $actionDescriptor,
            ];

            $components[] = [
                'type' => 'card',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor' => 'theme.divider',
                    'padding' => 14,
                    'marginBottom' => 10,
                    'borderRadius' => 12,
                ],
                'action_type' => $chan['action_type'] ?? 'SUBMIT_FORM',
                'action_name' => $chan['action_type'] ?? 'SUBMIT_FORM',
                'endpoint' => $dispatchEndpoint,
                'method' => 'POST',
                'action' => $actionDescriptor,
                'on_tap' => $actionDescriptor,
                'components' => [
                    [
                        'type' => 'row',
                        'components' => [
                            [
                                'type' => 'icon',
                                'icon' => $channelIcon,
                                'color' => $channelColor,
                                'size' => 24,
                            ],
                            [
                                'type' => 'column',
                                'style' => ['marginLeft' => 14],
                                'components' => [
                                    [
                                        'type' => 'text',
                                        'text' => $channelTitle,
                                        'style' => [
                                            'fontWeight' => 'bold',
                                            'fontSize' => 15,
                                            'color' => '#F8FAFC',
                                        ],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $channelSubtitle ?: 'Click to send',
                                        'style' => [
                                            'fontSize' => 12,
                                            'color' => '#94A3B8',
                                            'marginTop' => 2,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'type' => 'bottom_sheet',
            'title' => $title,
            'header' => $headerInfo,
            'channels' => $channels,
            'components' => $components,
            'options' => $actionSheetOptions,
            'schema' => [
                'type' => 'bottom_sheet',
                'title' => $title,
                'components' => $components,
                'options' => $actionSheetOptions,
            ],
        ];
    }
}
