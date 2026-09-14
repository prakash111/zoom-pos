<?php

namespace App\Services\Push;

use App\Models\Configuration;
use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class FirebasePushService
{
    /**
     * Send a data-only, high-priority FCM message to all active tenant devices.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToCompany(string $companyId, array $data): int
    {
        $settings = PushNotificationSetting::current();

        if (! $settings->enabled) {
            return 0;
        }

        $devices = PushDevice::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNull('revoked_at')
            ->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        $tenantSounds = $this->tenantSoundPayload($companyId);

        $rawDelayedSound = $data['sound']
            ?? $data['order_sound']
            ?? $tenantSounds['tenant_delayed_orders_alarm_sound']
            ?? $tenantSounds['tenant_delayed_order_sound']
            ?? $settings->order_sound;

        $rawOrderSound = $data['sound']
            ?? $data['invoice_sound']
            ?? $tenantSounds['tenant_new_online_order_sound']
            ?? $tenantSounds['tenant_order_sound']
            ?? $settings->order_sound;

        $type = (string) ($data['type'] ?? '');
        $isDelayedOrder = $type === 'delayed_order_alarm';

        $delayedSystemSound = self::mapToSystemSound($rawDelayedSound);
        $orderSystemSound = self::mapToSystemSound($rawOrderSound);
        $activeSystemSound = $isDelayedOrder ? $delayedSystemSound : $orderSystemSound;

        $orderChannelId = $settings->order_channel_id . '_' . $delayedSystemSound;
        $invoiceChannelId = $settings->invoice_channel_id . '_' . $activeSystemSound;

        $defaults = [
            'order_channel_id' => $orderChannelId,
            'order_channel_name' => $settings->order_channel_name . ' (' . ucfirst($delayedSystemSound) . ')',
            'order_sound' => $delayedSystemSound,
            'invoice_channel_id' => $invoiceChannelId,
            'invoice_channel_name' => $settings->invoice_channel_name . ' (' . ucfirst($activeSystemSound) . ')',
            'invoice_sound' => $activeSystemSound,
            'sound' => $activeSystemSound,
            'alarm_repeat_seconds' => (string) $settings->alarm_repeat_seconds,
        ];

        $payload = collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->stringValue($value)])
            ->all();

        $payload = array_merge($defaults, $tenantSounds, $payload);
        $payload['order_sound'] = $delayedSystemSound;
        $payload['invoice_sound'] = $activeSystemSound;
        $payload['sound'] = $activeSystemSound;
        $payload['order_channel_id'] = $orderChannelId;
        $payload['invoice_channel_id'] = $invoiceChannelId;

        if ($settings->fcm_service_account_json && $settings->fcm_project_id) {
            return $this->sendHttpV1($settings, $devices, $payload);
        }

        if ($settings->fcm_server_key) {
            return $this->sendLegacy($settings, $devices, $payload);
        }

        return 0;
    }

    /**
     * Send a high-priority FCM message to all active devices of a specific user.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(string $companyId, string $userId, array $data): int
    {
        $settings = PushNotificationSetting::current();

        if (! $settings->enabled) {
            return 0;
        }

        $devices = PushDevice::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        $tenantSounds = $this->tenantSoundPayload($companyId);

        $rawDelayedSound = $data['sound']
            ?? $data['order_sound']
            ?? $tenantSounds['tenant_delayed_orders_alarm_sound']
            ?? $tenantSounds['tenant_delayed_order_sound']
            ?? $settings->order_sound;

        $rawOrderSound = $data['sound']
            ?? $data['invoice_sound']
            ?? $tenantSounds['tenant_new_online_order_sound']
            ?? $tenantSounds['tenant_order_sound']
            ?? $settings->order_sound;

        $type = (string) ($data['type'] ?? '');
        $isDelayedOrder = $type === 'delayed_order_alarm';

        $delayedSystemSound = self::mapToSystemSound($rawDelayedSound);
        $orderSystemSound = self::mapToSystemSound($rawOrderSound);
        $activeSystemSound = $isDelayedOrder ? $delayedSystemSound : $orderSystemSound;

        $orderChannelId = $settings->order_channel_id . '_' . $delayedSystemSound;
        $invoiceChannelId = $settings->invoice_channel_id . '_' . $activeSystemSound;

        $defaults = [
            'order_channel_id' => $orderChannelId,
            'order_channel_name' => $settings->order_channel_name . ' (' . ucfirst($delayedSystemSound) . ')',
            'order_sound' => $delayedSystemSound,
            'invoice_channel_id' => $invoiceChannelId,
            'invoice_channel_name' => $settings->invoice_channel_name . ' (' . ucfirst($activeSystemSound) . ')',
            'invoice_sound' => $activeSystemSound,
            'sound' => $activeSystemSound,
            'alarm_repeat_seconds' => (string) $settings->alarm_repeat_seconds,
        ];

        $payload = collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->stringValue($value)])
            ->all();

        $payload = array_merge($defaults, $tenantSounds, $payload);
        $payload['order_sound'] = $delayedSystemSound;
        $payload['invoice_sound'] = $activeSystemSound;
        $payload['sound'] = $activeSystemSound;
        $payload['order_channel_id'] = $orderChannelId;
        $payload['invoice_channel_id'] = $invoiceChannelId;

        if ($settings->fcm_service_account_json && $settings->fcm_project_id) {
            return $this->sendHttpV1($settings, $devices, $payload);
        }

        if ($settings->fcm_server_key) {
            return $this->sendLegacy($settings, $devices, $payload);
        }

        return 0;
    }

    private function sendHttpV1(PushNotificationSetting $settings, $devices, array $data): int
    {
        $accessToken = $this->accessToken($settings);
        $delivered = 0;

        foreach ($devices as $device) {
            $response = Http::asJson()
                ->withToken($accessToken)
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$settings->fcm_project_id}/messages:send", [
                    'message' => [
                        'token' => $device->token,
                        'data' => $data,
                        'android' => [
                            'priority' => 'HIGH',
                            'ttl' => '0s',
                            'direct_boot_ok' => true,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $delivered++;
                $device->update(['last_seen_at' => now()]);

                continue;
            }

            $body = $response->body();
            if ($response->status() === 404 || Str::contains($body, ['UNREGISTERED', 'registration-token-not-registered'])) {
                Log::info("FCM device token revoked [id: {$device->id}, device: {$device->device_name}] due to UNREGISTERED response.");
                $device->update(['revoked_at' => now()]);
            } else {
                Log::warning("FCM v1 message send failed [status: {$response->status()}]: {$body}", [
                    'device_id'  => $device->id,
                    'company_id' => $device->company_id,
                ]);
            }
        }

        return $delivered;
    }

    private function sendLegacy(PushNotificationSetting $settings, $devices, array $data): int
    {
        $response = Http::asJson()
            ->withHeaders(['Authorization' => 'key='.$settings->fcm_server_key])
            ->timeout(15)
            ->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $devices->pluck('token')->all(),
                'priority' => 'high',
                'content_available' => true,
                'data' => $data,
            ]);

        if (! $response->successful()) {
            Log::warning("FCM legacy send failed [status: {$response->status()}]: {$response->body()}");

            return 0;
        }

        $results = $response->json('results', []);
        foreach ($results as $index => $result) {
            if (in_array($result['error'] ?? null, ['NotRegistered', 'InvalidRegistration'], true)) {
                $devices->get($index)?->update(['revoked_at' => now()]);
            }
        }

        return (int) ($response->json('success') ?? 0);
    }

    private function accessToken(PushNotificationSetting $settings): string
    {
        return Cache::remember('fcm.oauth-token.'.sha1((string) $settings->fcm_project_id), now()->addMinutes(50), function () use ($settings) {
            $credentials = json_decode((string) $settings->fcm_service_account_json, true);

            if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                throw new RuntimeException('The configured FCM service account is incomplete.');
            }

            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the FCM service-account assertion.');
            }

            $assertion = "{$header}.{$claims}.".$this->base64Url($signature);
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * The tenant's own sound preferences from Store Profile ▸ Notifications
     * & Sounds (SettingsApiController::updateNotificationSounds), merged
     * into every data-only push payload alongside the platform-wide
     * high-importance channel settings above. Falls back to sane defaults
     * for a tenant that never visited the tab, so this is a no-op for every
     * existing caller/consumer until a client actually reads the new keys.
     *
     * @return array<string, string>
     */
    private function tenantSoundPayload(string $companyId): array
    {
        $configs = Configuration::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('key', [
                'order_sound_preset', 'order_sound_custom_url', 'delayed_order_sound', 'sound_vibration_enabled',
                'tenant_audio_notifications',
            ])
            ->pluck('value', 'key')
            ->all();

        $preset = $configs['order_sound_preset'] ?? 'chime';
        $customUrl = $configs['order_sound_custom_url'] ?? '';
        $sound = ($preset === 'custom' && $customUrl !== '') ? $customUrl : $preset;

        $payload = [
            'tenant_order_sound' => $sound,
            'tenant_order_sound_preset' => $preset,
            'tenant_order_sound_custom_url' => $customUrl,
            'tenant_delayed_order_sound' => $configs['delayed_order_sound'] ?? 'alarm',
            'tenant_sound_vibration_enabled' => ($configs['sound_vibration_enabled'] ?? '1') === '1' ? 'true' : 'false',
        ];

        if (! empty($configs['tenant_audio_notifications'])) {
            $granular = json_decode($configs['tenant_audio_notifications'], true);
            if (is_array($granular)) {
                $payload['tenant_audio_notifications'] = json_encode($granular);
                foreach ($granular as $chId => $ch) {
                    if (! is_array($ch)) continue;
                    $chSound = ($ch['sound_source'] ?? 'preset') === 'custom' && ! empty($ch['custom_audio_url'])
                        ? $ch['custom_audio_url']
                        : ($ch['sound_preset'] ?? 'kitchen_bell');
                    $payload["tenant_{$chId}_sound"] = (string) $chSound;
                    $payload["tenant_{$chId}_duration"] = (string) ($ch['duration_seconds'] ?? 10);
                    $payload["tenant_{$chId}_interval"] = (string) ($ch['recurring_interval_seconds'] ?? 0);
                    $payload["tenant_{$chId}_vibration"] = ! empty($ch['vibration_enabled']) ? 'true' : 'false';
                    $payload["tenant_{$chId}_vibration_pattern"] = (string) ($ch['vibration_pattern'] ?? 'short_pulse');
                }
            }
        }

        return $payload;
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) ($value ?? '');
    }

    /**
     * Map arbitrary preset or sound strings to Flutter-supported Android notification system sounds:
     * - 'notification': gentle Android notification ping/chime (content://settings/system/notification_sound)
     * - 'ringtone': melodic phone ringtone (content://settings/system/ringtone)
     * - 'alarm': loud siren / alarm beeping (content://settings/system/alarm_alert)
     */
    public static function mapToSystemSound(?string $preset): string
    {
        $normalized = strtolower(trim((string) $preset));

        return match ($normalized) {
            'notification', 'kitchen_chime', 'kitchen_bell', 'bell_ding', 'subtle_pop', 'chime', 'bell', 'gentle' => 'notification',
            'ringtone', 'phone', 'call', 'melody' => 'ringtone',
            default => 'alarm',
        };
    }
}
