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
            'fcm_service_account_json' => 'encrypted',
            'fcm_server_key' => 'encrypted',
            'android_api_key' => 'encrypted',
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
    public function publicConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'project_id' => $this->fcm_project_id,
            'android_api_key' => $this->android_api_key,
            'android_app_id' => $this->android_app_id,
            'messaging_sender_id' => $this->messaging_sender_id,
            'order_channel' => [
                'id' => $this->order_channel_id,
                'name' => $this->order_channel_name,
                'sound' => $this->order_sound,
            ],
            'invoice_channel' => [
                'id' => $this->invoice_channel_id,
                'name' => $this->invoice_channel_name,
                'sound' => $this->invoice_sound,
            ],
            'alarm_repeat_seconds' => $this->alarm_repeat_seconds,
        ];
    }
}
