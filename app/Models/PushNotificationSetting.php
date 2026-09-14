<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationSetting extends Model
{
    protected $attributes = [
        'enabled' => false,
        'order_channel_id' => 'delayed_orders_alarm',
        'order_channel_name' => 'Delayed order alarms',
        'order_sound' => 'alarm',
        'invoice_channel_id' => 'due_invoice_reminders',
        'invoice_channel_name' => 'Due invoice reminders',
        'invoice_sound' => 'alarm',
        'alarm_repeat_seconds' => 60,
    ];

    protected $fillable = [
        'enabled', 'fcm_project_id', 'fcm_service_account_json', 'fcm_server_key',
        'android_api_key', 'android_app_id', 'messaging_sender_id',
        'order_channel_id', 'order_channel_name', 'order_sound',
        'invoice_channel_id', 'invoice_channel_name', 'invoice_sound',
        'alarm_repeat_seconds',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'fcm_service_account_json' => \App\Casts\SafeEncryptedString::class,
            'fcm_server_key' => \App\Casts\SafeEncryptedString::class,
            'android_api_key' => \App\Casts\SafeEncryptedString::class,
            'alarm_repeat_seconds' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Values which are safe and necessary for client-side Firebase setup.
     */
    public function publicConfig(?string $companyId = null): array
    {
        $orderSound = $this->order_sound;
        $orderChannelId = $this->order_channel_id;
        $invoiceSound = $this->invoice_sound;
        $invoiceChannelId = $this->invoice_channel_id;

        if ($companyId) {
            $configs = \App\Models\Configuration::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('key', ['order_sound_preset', 'delayed_order_sound', 'tenant_audio_notifications'])
                ->pluck('value', 'key')
                ->all();

            $delayed = $configs['delayed_order_sound'] ?? null;
            if (! empty($configs['tenant_audio_notifications'])) {
                $granular = json_decode($configs['tenant_audio_notifications'], true);
                if (is_array($granular)) {
                    if (! empty($granular['delayed_orders_alarm']['sound_preset'])) {
                        $delayed = $granular['delayed_orders_alarm']['sound_preset'];
                    }
                    if (! empty($granular['new_online_order']['sound_preset'])) {
                        $invoiceSound = $granular['new_online_order']['sound_preset'];
                    }
                }
            }

            if ($delayed) {
                $orderSound = \App\Services\Push\FirebasePushService::mapToSystemSound($delayed);
                $orderChannelId = $this->order_channel_id . '_' . $orderSound;
            }
            if ($invoiceSound) {
                $invoiceSound = \App\Services\Push\FirebasePushService::mapToSystemSound($invoiceSound);
                $invoiceChannelId = $this->invoice_channel_id . '_' . $invoiceSound;
            }
        }

        $androidApiKey = null;
        try {
            $androidApiKey = $this->android_api_key;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to decrypt android_api_key in PushNotificationSetting: {$e->getMessage()}");
        }

        return [
            'enabled' => (bool) $this->enabled,
            'project_id' => $this->fcm_project_id,
            'android_api_key' => $androidApiKey,
            'android_app_id' => $this->android_app_id,
            'messaging_sender_id' => $this->messaging_sender_id,
            'order_channel' => [
                'id' => $orderChannelId,
                'name' => $this->order_channel_name,
                'sound' => $orderSound,
            ],
            'invoice_channel' => [
                'id' => $invoiceChannelId,
                'name' => $this->invoice_channel_name,
                'sound' => $invoiceSound,
            ],
            'alarm_repeat_seconds' => $this->alarm_repeat_seconds,
        ];
    }
}
