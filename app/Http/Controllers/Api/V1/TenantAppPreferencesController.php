<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\DynamicSetting;
use App\Models\TenantDynamicSetting;
use App\Services\Notifications\AutomatedReminderSettingsService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantAppPreferencesController extends Controller
{
    use ResolvesTenantSyncContext;

    public const CHANNEL_DELAYED_ORDERS = 'delayed_orders_alarm';

    public const CHANNEL_NEW_ONLINE_ORDER = 'new_online_order';

    public const CHANNEL_DUE_INVOICE = 'due_invoice_reminders';

    public const CHANNEL_LOW_STOCK = 'low_stock_alert';

    public static function defaultPresets(): array
    {
        return [
            ['id' => 'kitchen_bell', 'label' => 'Kitchen Bell', 'category' => 'chime', 'uri' => 'asset://assets/sounds/kitchen_bell.mp3'],
            ['id' => 'kitchen_chime', 'label' => 'Chime', 'category' => 'chime', 'uri' => 'asset://assets/sounds/chime.mp3'],
            ['id' => 'double_beep', 'label' => 'Loud Double Beep', 'category' => 'alert', 'uri' => 'asset://assets/sounds/double_beep.mp3'],
            ['id' => 'alarm_siren', 'label' => 'Siren', 'category' => 'alarm', 'uri' => 'asset://assets/sounds/siren.mp3'],
            ['id' => 'bell_ding', 'label' => 'Bell Ding', 'category' => 'bell', 'uri' => 'asset://assets/sounds/bell_ding.mp3'],
            ['id' => 'subtle_pop', 'label' => 'Subtle Pop', 'category' => 'subtle', 'uri' => 'asset://assets/sounds/subtle_pop.mp3'],
        ];
    }

    public static function durationOptions(): array
    {
        return [
            ['value' => 5, 'label' => '5 Seconds'],
            ['value' => 10, 'label' => '10 Seconds'],
            ['value' => 15, 'label' => '15 Seconds'],
            ['value' => 30, 'label' => '30 Seconds'],
            ['value' => 60, 'label' => '60 Seconds'],
            ['value' => 0, 'label' => 'Loop Until Dismissed'],
        ];
    }

    public static function recurringIntervalOptions(): array
    {
        return [
            ['value' => 0, 'label' => 'Disabled (Play Once)'],
            ['value' => 30, 'label' => 'Every 30 Seconds'],
            ['value' => 60, 'label' => 'Every 1 Minute'],
            ['value' => 120, 'label' => 'Every 2 Minutes'],
            ['value' => 300, 'label' => 'Every 5 Minutes'],
        ];
    }

    public static function vibrationPatternOptions(): array
    {
        return [
            ['id' => 'short_pulse', 'label' => 'Short Pulse', 'pattern' => [0, 200, 100, 200]],
            ['id' => 'double_buzz', 'label' => 'Double Buzz', 'pattern' => [0, 300, 150, 300]],
            ['id' => 'persistent', 'label' => 'Persistent / Urgent', 'pattern' => [0, 500, 200, 500, 200, 500]],
        ];
    }

    public static function defaultChannelDefinitions(): array
    {
        return [
            self::CHANNEL_DELAYED_ORDERS => [
                'id' => self::CHANNEL_DELAYED_ORDERS,
                'title' => 'Delayed Order / KOT Alarms',
                'description' => 'Urgent alerts for delayed kitchen tickets or orders exceeding target prep time.',
                'icon' => 'warning_amber_rounded',
                'badge' => 'High Priority',
                'enabled' => true,
                'sound_source' => 'preset',
                'sound_preset' => 'alarm_siren',
                'custom_audio_url' => '',
                'duration_seconds' => 30,
                'recurring_interval_seconds' => 60,
                'vibration_enabled' => true,
                'vibration_pattern' => 'persistent',
            ],
            self::CHANNEL_NEW_ONLINE_ORDER => [
                'id' => self::CHANNEL_NEW_ONLINE_ORDER,
                'title' => 'New Online Orders',
                'description' => 'Instant chime when customers submit online, delivery, or QR table orders.',
                'icon' => 'receipt_long_rounded',
                'badge' => 'Orders',
                'enabled' => true,
                'sound_source' => 'preset',
                'sound_preset' => 'kitchen_bell',
                'custom_audio_url' => '',
                'duration_seconds' => 10,
                'recurring_interval_seconds' => 0,
                'vibration_enabled' => true,
                'vibration_pattern' => 'double_buzz',
            ],
            self::CHANNEL_DUE_INVOICE => [
                'id' => self::CHANNEL_DUE_INVOICE,
                'title' => 'Due Invoice Reminders',
                'description' => 'Scheduled reminders for overdue or pending customer credit receivables.',
                'icon' => 'payments_rounded',
                'badge' => 'Finance',
                'enabled' => true,
                'sound_source' => 'preset',
                'sound_preset' => 'kitchen_chime',
                'custom_audio_url' => '',
                'duration_seconds' => 10,
                'recurring_interval_seconds' => 0,
                'vibration_enabled' => true,
                'vibration_pattern' => 'short_pulse',
            ],
            self::CHANNEL_LOW_STOCK => [
                'id' => self::CHANNEL_LOW_STOCK,
                'title' => 'Low Stock Alerts',
                'description' => 'Audible notifications when item quantities fall below the reorder point.',
                'icon' => 'inventory_2_rounded',
                'badge' => 'Inventory',
                'enabled' => true,
                'sound_source' => 'preset',
                'sound_preset' => 'double_beep',
                'custom_audio_url' => '',
                'duration_seconds' => 5,
                'recurring_interval_seconds' => 0,
                'vibration_enabled' => true,
                'vibration_pattern' => 'short_pulse',
            ],
        ];
    }

    /**
     * Resolve effective channel settings for a company.
     */
    public static function getEffectivePreferences(Company $company): array
    {
        $defaults = self::defaultChannelDefinitions();

        // 1. Check dynamic_settings table
        $savedDynamic = TenantDynamicSetting::withoutGlobalScopes()
            ->where('tenant_id', $company->id)
            ->where('group', 'tenant_audio_notifications')
            ->pluck('value', 'key')
            ->all();

        // 2. Fallback to configurations table if dynamic_settings is empty
        if (empty($savedDynamic)) {
            $rawConfig = Configuration::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('key', 'tenant_audio_notifications')
                ->value('value');
            if ($rawConfig) {
                $decoded = json_decode($rawConfig, true);
                if (is_array($decoded)) {
                    $savedDynamic = $decoded;
                }
            }
        }

        // 3. Fallback to legacy keys if still empty
        $legacyConfigs = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', ['order_sound_preset', 'order_sound_custom_url', 'delayed_order_sound', 'sound_vibration_enabled'])
            ->pluck('value', 'key')
            ->all();

        $channels = [];
        foreach ($defaults as $channelKey => $defaultConfig) {
            $channelData = $savedDynamic[$channelKey] ?? [];

            // Apply legacy fallbacks for order / delayed order channels if nothing was configured yet
            if (empty($channelData)) {
                if ($channelKey === self::CHANNEL_DELAYED_ORDERS && isset($legacyConfigs['delayed_order_sound'])) {
                    $legacySound = $legacyConfigs['delayed_order_sound'];
                    $mappedPreset = match ($legacySound) {
                        'siren' => 'alarm_siren',
                        'beep' => 'double_beep',
                        default => 'alarm_siren',
                    };
                    $defaultConfig['sound_preset'] = $mappedPreset;
                    $defaultConfig['vibration_enabled'] = ($legacyConfigs['sound_vibration_enabled'] ?? '1') === '1';
                } elseif ($channelKey === self::CHANNEL_NEW_ONLINE_ORDER && isset($legacyConfigs['order_sound_preset'])) {
                    $legacyPreset = $legacyConfigs['order_sound_preset'];
                    $defaultConfig['sound_source'] = ($legacyPreset === 'custom') ? 'custom' : 'preset';
                    $defaultConfig['sound_preset'] = match ($legacyPreset) {
                        'bell' => 'kitchen_bell',
                        'chime' => 'kitchen_chime',
                        'alarm' => 'alarm_siren',
                        default => 'kitchen_bell',
                    };
                    $defaultConfig['custom_audio_url'] = $legacyConfigs['order_sound_custom_url'] ?? '';
                    $defaultConfig['vibration_enabled'] = ($legacyConfigs['sound_vibration_enabled'] ?? '1') === '1';
                }
            }

            $channels[$channelKey] = array_merge($defaultConfig, is_array($channelData) ? $channelData : []);
        }

        return $channels;
    }

    /**
     * GET /api/v1/tenant/settings/notifications-audio
     * Return full declarative SDUI screen for tenant notification sound and vibration preferences.
     */
    public function getNotificationAlertsScreen(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id ?? $company->id;
        $preferences = DynamicSetting::getTenantSettings($tenantId, 'tenant_audio_notifications') ?? [];
        $channels = self::getEffectivePreferences($company);
        $autoReminderSettings = app(AutomatedReminderSettingsService::class)->get($company);

        $components = [
            [
                'type' => 'card',
                'title' => 'Delayed Kitchen Orders (KOT)',
                'subtitle' => 'Play loud alert when ticket preparation time exceeds threshold.',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor' => 'theme.divider',
                    'borderRadius' => 12,
                    'padding' => 16,
                ],
                'components' => [
                    [
                        'type' => 'dropdown_select',
                        'name' => 'delayed_orders_sound_type',
                        'label' => 'Alert Sound Source',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.sound_type', data_get($preferences, 'delayed_orders_sound_type', 'preset')),
                        'value' => (string) data_get($preferences, 'delayed_orders.sound_type', data_get($preferences, 'delayed_orders_sound_type', 'preset')),
                        'options' => [
                            ['label' => 'System Sound Preset', 'value' => 'preset'],
                            ['label' => 'Custom Audio URL (MP3)', 'value' => 'custom'],
                        ],
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'delayed_orders_sound_preset',
                        'label' => 'Preset Sound',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.preset', data_get($preferences, 'delayed_orders_sound_preset', 'alarm_siren')),
                        'value' => (string) data_get($preferences, 'delayed_orders.preset', data_get($preferences, 'delayed_orders_sound_preset', 'alarm_siren')),
                        'options' => [
                            ['label' => 'System Notification Tone (Gentle Ping)', 'value' => 'notification'],
                            ['label' => 'System Phone Ringtone (Melodic)', 'value' => 'ringtone'],
                            ['label' => 'System Alarm Siren (Urgent)', 'value' => 'alarm'],
                            ['label' => 'Kitchen Chime (Gentle)', 'value' => 'kitchen_chime'],
                            ['label' => 'Loud Double Beep (POS Standard)', 'value' => 'double_beep'],
                            ['label' => 'Alarm Siren (Urgent)', 'value' => 'alarm_siren'],
                            ['label' => 'Service Bell Ding', 'value' => 'bell_ding'],
                        ],
                    ],
                    [
                        'type' => 'text_input',
                        'name' => 'delayed_orders_custom_audio_url',
                        'label' => 'Direct Audio URL',
                        'placeholder' => 'https://domain.com/audio/alarm.mp3',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.custom_url', data_get($preferences, 'delayed_orders_custom_audio_url', '')),
                        'value' => (string) data_get($preferences, 'delayed_orders.custom_url', data_get($preferences, 'delayed_orders_custom_audio_url', '')),
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'delayed_orders_duration_seconds',
                        'label' => 'Ringing Duration (Seconds)',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.duration_seconds', data_get($preferences, 'delayed_orders_duration_seconds', '15')),
                        'value' => (string) data_get($preferences, 'delayed_orders.duration_seconds', data_get($preferences, 'delayed_orders_duration_seconds', '15')),
                        'options' => [
                            ['label' => '5 Seconds', 'value' => '5'],
                            ['label' => '10 Seconds', 'value' => '10'],
                            ['label' => '15 Seconds', 'value' => '15'],
                            ['label' => '30 Seconds', 'value' => '30'],
                            ['label' => '60 Seconds', 'value' => '60'],
                            ['label' => 'Loop Until Dismissed', 'value' => '0'],
                        ],
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'delayed_orders_recurring_interval',
                        'label' => 'Recurring Alarm Interval (Seconds, 0 = Off)',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.recurring_interval', data_get($preferences, 'delayed_orders_recurring_interval', '60')),
                        'value' => (string) data_get($preferences, 'delayed_orders.recurring_interval', data_get($preferences, 'delayed_orders_recurring_interval', '60')),
                        'options' => [
                            ['label' => 'Disabled (Play Once)', 'value' => '0'],
                            ['label' => 'Every 30 Seconds', 'value' => '30'],
                            ['label' => 'Every 1 Minute', 'value' => '60'],
                            ['label' => 'Every 2 Minutes', 'value' => '120'],
                            ['label' => 'Every 5 Minutes', 'value' => '300'],
                        ],
                    ],
                    [
                        'type' => 'toggle_switch',
                        'name' => 'delayed_orders_enable_vibration',
                        'label' => 'Device Haptic Vibration',
                        'initial_value' => (bool) data_get($preferences, 'delayed_orders.enable_vibration', data_get($preferences, 'delayed_orders_enable_vibration', true)),
                        'value' => (bool) data_get($preferences, 'delayed_orders.enable_vibration', data_get($preferences, 'delayed_orders_enable_vibration', true)),
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'delayed_orders_vibration_pattern',
                        'label' => 'Vibration Pattern',
                        'initial_value' => (string) data_get($preferences, 'delayed_orders.vibration_pattern', data_get($preferences, 'delayed_orders_vibration_pattern', 'persistent')),
                        'value' => (string) data_get($preferences, 'delayed_orders.vibration_pattern', data_get($preferences, 'delayed_orders_vibration_pattern', 'persistent')),
                        'options' => [
                            ['label' => 'Short Pulse', 'value' => 'short_pulse'],
                            ['label' => 'Double Buzz', 'value' => 'double_buzz'],
                            ['label' => 'Persistent Urgent Buzz', 'value' => 'persistent'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'card',
                'title' => 'Due Invoice & Payment Reminders',
                'subtitle' => 'Alert sound when invoices reach maturity or overdue status.',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor' => 'theme.divider',
                    'borderRadius' => 12,
                    'padding' => 16,
                    'marginTop' => 16,
                ],
                'components' => [
                    [
                        'type' => 'dropdown_select',
                        'name' => 'due_invoices_sound_type',
                        'label' => 'Reminder Sound Source',
                        'initial_value' => (string) data_get($preferences, 'due_invoices.sound_type', data_get($preferences, 'due_invoices_sound_type', 'preset')),
                        'value' => (string) data_get($preferences, 'due_invoices.sound_type', data_get($preferences, 'due_invoices_sound_type', 'preset')),
                        'options' => [
                            ['label' => 'System Preset Sound', 'value' => 'preset'],
                            ['label' => 'Custom Audio URL (MP3)', 'value' => 'custom'],
                        ],
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'due_invoices_sound_preset',
                        'label' => 'Reminder Sound Preset',
                        'initial_value' => (string) data_get($preferences, 'due_invoices.preset', data_get($preferences, 'due_invoices_sound_preset', 'kitchen_chime')),
                        'value' => (string) data_get($preferences, 'due_invoices.preset', data_get($preferences, 'due_invoices_sound_preset', 'kitchen_chime')),
                        'options' => [
                            ['label' => 'System Notification Tone (Gentle)', 'value' => 'notification'],
                            ['label' => 'System Phone Ringtone (Melodic)', 'value' => 'ringtone'],
                            ['label' => 'System Alarm Siren (Urgent)', 'value' => 'alarm'],
                            ['label' => 'Kitchen Chime', 'value' => 'kitchen_chime'],
                            ['label' => 'Double Beep', 'value' => 'double_beep'],
                            ['label' => 'Service Bell', 'value' => 'bell_ding'],
                        ],
                    ],
                    [
                        'type' => 'text_input',
                        'name' => 'due_invoices_custom_audio_url',
                        'label' => 'Direct Audio URL',
                        'placeholder' => 'https://domain.com/sounds/ding.mp3',
                        'initial_value' => (string) data_get($preferences, 'due_invoices.custom_url', data_get($preferences, 'due_invoices_custom_audio_url', '')),
                        'value' => (string) data_get($preferences, 'due_invoices.custom_url', data_get($preferences, 'due_invoices_custom_audio_url', '')),
                    ],
                    [
                        'type' => 'dropdown_select',
                        'name' => 'due_invoices_recurring_interval',
                        'label' => 'Recurring Reminder Interval (Seconds)',
                        'initial_value' => (string) data_get($preferences, 'due_invoices.recurring_interval', data_get($preferences, 'due_invoices_recurring_interval', '0')),
                        'value' => (string) data_get($preferences, 'due_invoices.recurring_interval', data_get($preferences, 'due_invoices_recurring_interval', '0')),
                        'options' => [
                            ['label' => 'Disabled (Play Once)', 'value' => '0'],
                            ['label' => 'Every 30 Seconds', 'value' => '30'],
                            ['label' => 'Every 1 Minute', 'value' => '60'],
                            ['label' => 'Every 2 Minutes', 'value' => '120'],
                            ['label' => 'Every 5 Minutes', 'value' => '300'],
                        ],
                    ],
                    [
                        'type' => 'toggle_switch',
                        'name' => 'due_invoices_enable_vibration',
                        'label' => 'Vibration on Due Alert',
                        'initial_value' => (bool) data_get($preferences, 'due_invoices.enable_vibration', data_get($preferences, 'due_invoices_enable_vibration', false)),
                        'value' => (bool) data_get($preferences, 'due_invoices.enable_vibration', data_get($preferences, 'due_invoices_enable_vibration', false)),
                    ],
                ],
            ],
            self::automatedReminderCard($autoReminderSettings),
            [
                'type' => 'button_primary',
                'label' => 'Save Notification Preferences',
                'variant' => 'primary',
                'action' => [
                    'type' => 'form_submit',
                    'endpoint' => '/api/v1/tenant/settings/notifications-audio',
                    'method' => 'POST',
                    'success_toast' => 'Notification preferences saved successfully',
                ],
            ],
        ];

        $screenPayload = [
            'type' => 'screen',
            'schema_version' => 1,
            'screen' => 'NotificationsAudioAlertsScreen',
            'title' => 'Notifications & Audio Alerts',
            'layout' => 'scroll_view',
            'app_bar' => [
                'title' => 'Notifications & Audio Alerts',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
        ];

        return response()->json([
            'success' => true,
            'screen' => 'NotificationsAudioAlertsScreen',
            'title' => 'Notifications & Audio Alerts',
            'app_bar' => [
                'title' => 'Notifications & Audio Alerts',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'layout' => 'scroll_view',
            'schema_version' => 1,
            'components' => $components,
            'schema' => $screenPayload,
            'channels' => $channels,
            'presets' => self::defaultPresets(),
            'durations' => self::durationOptions(),
            'recurring_intervals' => self::recurringIntervalOptions(),
            'vibration_patterns' => self::vibrationPatternOptions(),
            'auto_reminders' => $autoReminderSettings,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * GET /api/v1/tenant/settings/app-preferences/notifications
     * Return declarative SDUI schema matching tenant notification preferences.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        return $this->getNotificationAlertsScreen($request);
    }

    /**
     * Alias for getNotificationPreferences / getNotificationAlertsScreen.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->getNotificationAlertsScreen($request);
    }

    /**
     * POST /api/v1/tenant/settings/notifications-audio
     * Save notification sound and vibration preferences.
     */
    public function saveNotificationPreferences(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id ?? $company->id;

        // If client submitted full channels hierarchy
        if ($request->has('channels') && is_array($request->input('channels'))) {
            return $this->update($request);
        }

        $validated = $request->validate([
            'delayed_orders_sound_type' => 'nullable|string',
            'delayed_orders_sound_preset' => 'nullable|string',
            'delayed_orders_custom_audio_url' => 'nullable|string',
            'delayed_orders_duration_seconds' => 'nullable',
            'delayed_orders_recurring_interval' => 'nullable',
            'delayed_orders_enable_vibration' => 'nullable',
            'delayed_orders_vibration_pattern' => 'nullable|string',
            'due_invoices_sound_type' => 'nullable|string',
            'due_invoices_sound_preset' => 'nullable|string',
            'due_invoices_custom_audio_url' => 'nullable|string',
            'due_invoices_recurring_interval' => 'nullable',
            'due_invoices_enable_vibration' => 'nullable',
        ]);

        DynamicSetting::putTenantSettings($tenantId, 'tenant_audio_notifications', $validated);

        // Keep Configuration and TenantDynamicSetting synchronized for push notification delivery
        $defaults = self::defaultChannelDefinitions();
        $current = self::getEffectivePreferences($company);

        if (isset($validated['delayed_orders_sound_preset']) || isset($validated['delayed_orders_sound_type'])) {
            $current[self::CHANNEL_DELAYED_ORDERS] = array_merge($current[self::CHANNEL_DELAYED_ORDERS] ?? $defaults[self::CHANNEL_DELAYED_ORDERS], [
                'sound_source' => ($validated['delayed_orders_sound_type'] ?? '') === 'custom' ? 'custom' : 'preset',
                'sound_preset' => (string) ($validated['delayed_orders_sound_preset'] ?? ($current[self::CHANNEL_DELAYED_ORDERS]['sound_preset'] ?? 'alarm_siren')),
                'custom_audio_url' => trim((string) ($validated['delayed_orders_custom_audio_url'] ?? '')),
                'duration_seconds' => (int) ($validated['delayed_orders_duration_seconds'] ?? 15),
                'recurring_interval_seconds' => (int) ($validated['delayed_orders_recurring_interval'] ?? 60),
                'vibration_enabled' => filter_var($validated['delayed_orders_enable_vibration'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'vibration_pattern' => (string) ($validated['delayed_orders_vibration_pattern'] ?? 'persistent'),
            ]);
        }

        if (isset($validated['due_invoices_sound_preset']) || isset($validated['due_invoices_sound_type'])) {
            $current[self::CHANNEL_DUE_INVOICE] = array_merge($current[self::CHANNEL_DUE_INVOICE] ?? $defaults[self::CHANNEL_DUE_INVOICE], [
                'sound_source' => ($validated['due_invoices_sound_type'] ?? '') === 'custom' ? 'custom' : 'preset',
                'sound_preset' => (string) ($validated['due_invoices_sound_preset'] ?? ($current[self::CHANNEL_DUE_INVOICE]['sound_preset'] ?? 'kitchen_chime')),
                'custom_audio_url' => trim((string) ($validated['due_invoices_custom_audio_url'] ?? '')),
                'recurring_interval_seconds' => (int) ($validated['due_invoices_recurring_interval'] ?? 0),
                'vibration_enabled' => filter_var($validated['due_invoices_enable_vibration'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        foreach ($current as $channelId => $channelSettings) {
            TenantDynamicSetting::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $company->id,
                    'group' => 'tenant_audio_notifications',
                    'key' => $channelId,
                ],
                [
                    'company_id' => $company->id,
                    'value' => $channelSettings,
                ]
            );
        }

        Configuration::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'tenant_audio_notifications'],
            ['value' => json_encode($current)]
        );

        if (isset($current[self::CHANNEL_DELAYED_ORDERS])) {
            $delayedCh = $current[self::CHANNEL_DELAYED_ORDERS];
            $legacyDelayedPreset = match ($delayedCh['sound_preset']) {
                'double_beep' => 'beep',
                'alarm_siren' => 'siren',
                default => 'alarm',
            };
            Configuration::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'key' => 'delayed_order_sound'],
                ['value' => $legacyDelayedPreset]
            );
            Configuration::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'key' => 'sound_vibration_enabled'],
                ['value' => ! empty($delayedCh['vibration_enabled']) ? '1' : '0']
            );
        }

        Cache::forget("tenant_{$tenantId}_bootstrap");
        Cache::forget("tenant_{$tenantId}_settings");
        Cache::forget("tenant_{$tenantId}_dyn_setting_tenant_audio_notifications");
        Cache::forget("tenant_{$company->id}_dyn_setting_tenant_audio_notifications");
        Cache::forget("tenant_audio_notifications_{$company->id}");
        Cache::forget("tenant_audio_notifications_{$tenantId}");

        AuditLog::record('company.settings_updated', $company->id, $user?->id, [
            'section' => 'tenant_audio_notifications',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences saved successfully.',
        ]);
    }

    /**
     * POST /api/v1/tenant/settings/auto-reminders
     */
    public function saveAutoReminders(
        Request $request,
        AutomatedReminderSettingsService $settingsService,
    ): JsonResponse {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $validated = $request->validate([
            'auto_reminders_enabled' => ['required', 'boolean'],
            'reminder_preferred_channel' => ['required', 'string', Rule::in(AutomatedReminderSettingsService::CHANNELS)],
            'reminder_schedule_frequency' => ['required', 'string', Rule::in(AutomatedReminderSettingsService::FREQUENCIES)],
            'reminder_target_documents' => ['required', 'string', Rule::in(AutomatedReminderSettingsService::DOCUMENT_SCOPES)],
        ]);

        $settings = $settingsService->save($company, $validated);

        Cache::forget("tenant_{$company->id}_bootstrap");
        Cache::forget("tenant_{$company->id}_settings");
        Cache::forget("tenant_{$company->id}_dyn_setting_auto_reminders");

        AuditLog::record('company.settings_updated', $company->id, $user?->id, [
            'section' => 'auto_reminders',
            'settings' => $settings,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Automated reminder preferences saved successfully.',
            'auto_reminders' => $settings,
        ]);
    }

    /**
     * POST /api/v1/tenant/settings/app-preferences/notifications
     * Update notification channels with backward compatibility.
     */
    public function update(Request $request): JsonResponse
    {
        if ($request->has('delayed_orders_sound_type')) {
            return $this->saveNotificationPreferences($request);
        }

        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $channelsInput = $request->input('channels');

        // Handle both full payload and single-channel payload
        if (! is_array($channelsInput)) {
            // Check if flattened parameters were sent (e.g. channel_id + properties)
            $channelId = $request->input('channel_id');
            if ($channelId && array_key_exists($channelId, self::defaultChannelDefinitions())) {
                $channelsInput = [
                    $channelId => $request->only([
                        'enabled',
                        'sound_source',
                        'sound_preset',
                        'custom_audio_url',
                        'duration_seconds',
                        'recurring_interval_seconds',
                        'vibration_enabled',
                        'vibration_pattern',
                    ]),
                ];
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid channels configuration payload.',
                ], 422);
            }
        }

        $defaults = self::defaultChannelDefinitions();
        $current = self::getEffectivePreferences($company);

        foreach ($channelsInput as $channelId => $channelSettings) {
            if (! is_array($channelSettings) || ! array_key_exists($channelId, $defaults)) {
                continue;
            }

            $merged = array_merge($current[$channelId] ?? $defaults[$channelId], [
                'enabled' => filter_var($channelSettings['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sound_source' => in_array($channelSettings['sound_source'] ?? '', ['preset', 'custom'], true) ? $channelSettings['sound_source'] : 'preset',
                'sound_preset' => (string) ($channelSettings['sound_preset'] ?? $defaults[$channelId]['sound_preset']),
                'custom_audio_url' => trim((string) ($channelSettings['custom_audio_url'] ?? '')),
                'duration_seconds' => (int) ($channelSettings['duration_seconds'] ?? $defaults[$channelId]['duration_seconds']),
                'recurring_interval_seconds' => (int) ($channelSettings['recurring_interval_seconds'] ?? $defaults[$channelId]['recurring_interval_seconds']),
                'vibration_enabled' => filter_var($channelSettings['vibration_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'vibration_pattern' => in_array($channelSettings['vibration_pattern'] ?? '', ['short_pulse', 'double_buzz', 'persistent'], true)
                    ? $channelSettings['vibration_pattern']
                    : $defaults[$channelId]['vibration_pattern'],
            ]);

            $current[$channelId] = $merged;

            // Persist individual row in dynamic_settings
            TenantDynamicSetting::updateOrCreate(
                [
                    'tenant_id' => $company->id,
                    'group' => 'tenant_audio_notifications',
                    'key' => $channelId,
                ],
                [
                    'company_id' => $company->id,
                    'value' => $merged,
                ]
            );
        }

        // Persist consolidated configuration for high-speed retrieval
        Configuration::updateOrCreate(
            [
                'company_id' => $company->id,
                'key' => 'tenant_audio_notifications',
            ],
            [
                'value' => json_encode($current),
            ]
        );

        // Keep legacy backward compatibility with FirebasePushService
        if (isset($current[self::CHANNEL_NEW_ONLINE_ORDER])) {
            $orderCh = $current[self::CHANNEL_NEW_ONLINE_ORDER];
            $legacyOrderPreset = match ($orderCh['sound_preset']) {
                'kitchen_bell' => 'bell',
                'kitchen_chime' => 'chime',
                'alarm_siren' => 'alarm',
                default => 'ringtone',
            };
            if ($orderCh['sound_source'] === 'custom' && ! empty($orderCh['custom_audio_url'])) {
                $legacyOrderPreset = 'custom';
            }
            Configuration::updateOrCreate(
                ['company_id' => $company->id, 'key' => 'order_sound_preset'],
                ['value' => $legacyOrderPreset]
            );
            Configuration::updateOrCreate(
                ['company_id' => $company->id, 'key' => 'order_sound_custom_url'],
                ['value' => $orderCh['custom_audio_url'] ?? '']
            );
        }

        if (isset($current[self::CHANNEL_DELAYED_ORDERS])) {
            $delayedCh = $current[self::CHANNEL_DELAYED_ORDERS];
            $legacyDelayedPreset = match ($delayedCh['sound_preset']) {
                'double_beep' => 'beep',
                'alarm_siren' => 'siren',
                default => 'alarm',
            };
            Configuration::updateOrCreate(
                ['company_id' => $company->id, 'key' => 'delayed_order_sound'],
                ['value' => $legacyDelayedPreset]
            );
            Configuration::updateOrCreate(
                ['company_id' => $company->id, 'key' => 'sound_vibration_enabled'],
                ['value' => $delayedCh['vibration_enabled'] ? '1' : '0']
            );
        }

        Cache::forget("tenant_audio_notifications_{$company->id}");

        AuditLog::record('company.settings_updated', $company->id, $user?->id, [
            'section' => 'tenant_audio_notifications',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification & sound preferences saved successfully.',
            'channels' => $current,
        ]);
    }

    /**
     * POST /api/v1/tenant/settings/app-preferences/notifications/upload-audio
     */
    public function uploadAudio(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,aac,m4a,audio/mpeg,audio/wav,audio/ogg', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid audio file. Please upload an MP3, WAV, or OGG file up to 10MB.',
                'details' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('audio');
        $extension = $file->getClientOriginalExtension() ?: 'mp3';
        $filename = 'sound_'.time().'_'.uniqid().'.'.$extension;
        $path = $file->storeAs("tenant-sounds/{$company->id}", $filename, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'message' => 'Audio file uploaded successfully.',
            'url' => url($url),
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * SDUI Schema Builder for Server-Driven UI fallback
     */
    public static function buildSduiSchema(Company $company, array $channels): array
    {
        $components = [
            SchemaResponse::text('Notifications & Audio Alerts', 'title_large', ['bold' => true]),
            SchemaResponse::text(
                'Personalize sound alerts, alert loop duration, recurring alarms, and vibration for each event type across POS and KDS stations.',
                'body_small',
                ['color' => '#94a3b8']
            ),
            SchemaResponse::divider(),
        ];

        $presets = array_map(fn ($p) => ['label' => $p['label'], 'value' => $p['id']], self::defaultPresets());
        $durations = array_map(fn ($d) => ['label' => $d['label'], 'value' => (string) $d['value']], self::durationOptions());
        $recurring = array_map(fn ($r) => ['label' => $r['label'], 'value' => (string) $r['value']], self::recurringIntervalOptions());
        $vibrations = array_map(fn ($v) => ['label' => $v['label'], 'value' => $v['id']], self::vibrationPatternOptions());

        foreach ($channels as $key => $ch) {
            $components[] = SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::text($ch['title'], 'title_medium', ['bold' => true]),
                    SchemaResponse::badge($ch['badge'] ?? 'Channel', 'primary'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::text($ch['description'], 'body_small', ['color' => '#94a3b8']),
                SchemaResponse::divider(),
                SchemaResponse::toggleSwitch("channels[{$key}][enabled]", 'Enable Notification Channel', (bool) ($ch['enabled'] ?? true)),
                SchemaResponse::dropdownSelect(
                    "channels[{$key}][sound_preset]",
                    'Sound Preset',
                    $presets,
                    $ch['sound_preset'] ?? 'kitchen_bell'
                ),
                SchemaResponse::textInput(
                    "channels[{$key}][custom_audio_url]",
                    'Custom Audio URL (Optional MP3 / WAV)',
                    $ch['custom_audio_url'] ?? '',
                    ['placeholder' => 'https://example.com/audio/custom-alert.mp3']
                ),
                SchemaResponse::dropdownSelect(
                    "channels[{$key}][duration_seconds]",
                    'Alert Ring Duration',
                    $durations,
                    (string) ($ch['duration_seconds'] ?? 10)
                ),
                SchemaResponse::dropdownSelect(
                    "channels[{$key}][recurring_interval_seconds]",
                    'Recurring Alarm Repeat',
                    $recurring,
                    (string) ($ch['recurring_interval_seconds'] ?? 0)
                ),
                SchemaResponse::toggleSwitch(
                    "channels[{$key}][vibration_enabled]",
                    'Enable Vibration / Haptic Feedback',
                    (bool) ($ch['vibration_enabled'] ?? true)
                ),
                SchemaResponse::dropdownSelect(
                    "channels[{$key}][vibration_pattern]",
                    'Vibration Pattern',
                    $vibrations,
                    $ch['vibration_pattern'] ?? 'short_pulse'
                ),
            ], [
                'surface' => '#1E293B',
                'border_color' => '#334155',
            ]);
        }

        $components[] = self::automatedReminderCard(
            app(AutomatedReminderSettingsService::class)->get($company)
        );

        $components[] = SchemaResponse::buttonPrimary(
            'Save Notification Preferences',
            SchemaResponse::formSubmitAction(
                '/api/v1/tenant/settings/app-preferences/notifications',
                'POST',
                'Notification and audio preferences updated successfully'
            ),
            'volume_up'
        );

        return SchemaResponse::screen('Notification Preferences', $components);
    }

    private static function automatedReminderCard(array $settings): array
    {
        return [
            'type' => 'card',
            'style' => [
                'backgroundColor' => 'theme.surface',
                'borderColor' => 'theme.divider',
                'borderRadius' => 14,
                'padding' => 16,
                'marginTop' => 16,
            ],
            'components' => [
                [
                    'type' => 'text',
                    'text' => 'Automated Customer Reminders',
                    'style' => [
                        'fontSize' => 16,
                        'fontWeight' => 'bold',
                        'color' => 'theme.textPrimary',
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'Auto-dispatch overdue balance and pending quote notices using your active channels.',
                    'style' => [
                        'fontSize' => 12,
                        'color' => 'theme.textSecondary',
                        'marginBottom' => 12,
                    ],
                ],
                [
                    'type' => 'toggle_switch',
                    'name' => 'auto_reminders_enabled',
                    'key' => 'auto_reminders_enabled',
                    'label' => 'Enable Scheduled Auto-Dispatch',
                    'title' => 'Enable Scheduled Auto-Dispatch',
                    'subtitle' => 'Runs automatically at the selected tenant-local schedule.',
                    'initial_value' => (bool) $settings['auto_reminders_enabled'],
                    'value' => (bool) $settings['auto_reminders_enabled'],
                ],
                [
                    'type' => 'dropdown_select',
                    'name' => 'reminder_preferred_channel',
                    'key' => 'reminder_preferred_channel',
                    'label' => 'Primary Dispatch Channel',
                    'initial_value' => $settings['reminder_preferred_channel'],
                    'value' => $settings['reminder_preferred_channel'],
                    'options' => [
                        ['label' => 'All Active & Configured Channels', 'value' => 'all_active'],
                        ['label' => 'SMS Gateway Only', 'value' => 'sms'],
                        ['label' => 'WhatsApp Cloud API Only', 'value' => 'whatsapp'],
                        ['label' => 'Email (Custom SMTP) Only', 'value' => 'email'],
                    ],
                    'style' => ['marginTop' => 12],
                ],
                [
                    'type' => 'dropdown_select',
                    'name' => 'reminder_schedule_frequency',
                    'key' => 'reminder_schedule_frequency',
                    'label' => 'Automated Reminder Frequency',
                    'initial_value' => $settings['reminder_schedule_frequency'],
                    'value' => $settings['reminder_schedule_frequency'],
                    'options' => [
                        ['label' => 'Daily Morning (10:00 AM)', 'value' => 'daily_morning'],
                        ['label' => 'Daily Evening (06:00 PM)', 'value' => 'daily_evening'],
                        ['label' => 'Every 3 Days', 'value' => 'every_3_days'],
                        ['label' => 'Weekly on Mondays', 'value' => 'weekly_monday'],
                    ],
                    'style' => ['marginTop' => 12],
                ],
                [
                    'type' => 'dropdown_select',
                    'name' => 'reminder_target_documents',
                    'key' => 'reminder_target_documents',
                    'label' => 'Apply Reminders To',
                    'initial_value' => $settings['reminder_target_documents'],
                    'value' => $settings['reminder_target_documents'],
                    'options' => [
                        ['label' => 'Overdue Invoices & Pending Quotations', 'value' => 'both'],
                        ['label' => 'Overdue Invoices Only', 'value' => 'invoices_only'],
                        ['label' => 'Expiring Quotations Only', 'value' => 'quotations_only'],
                    ],
                    'style' => ['marginTop' => 12],
                ],
                [
                    'type' => 'button_primary',
                    'label' => 'Save Auto-Reminder Settings',
                    'variant' => 'primary',
                    'style' => ['marginTop' => 16],
                    'action' => [
                        'type' => 'form_submit',
                        'endpoint' => '/api/v1/tenant/settings/auto-reminders',
                        'method' => 'POST',
                        'success_toast' => 'Automated reminder preferences saved successfully.',
                    ],
                ],
            ],
        ];
    }
}
