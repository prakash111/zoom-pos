<?php

namespace App\Services\Push;

use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        $payload = collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->stringValue($value)])
            ->all();

        $payload += [
            'order_channel_id' => $settings->order_channel_id,
            'order_channel_name' => $settings->order_channel_name,
            'order_sound' => $settings->order_sound,
            'invoice_channel_id' => $settings->invoice_channel_id,
            'invoice_channel_name' => $settings->invoice_channel_name,
            'invoice_sound' => $settings->invoice_sound,
            'alarm_repeat_seconds' => (string) $settings->alarm_repeat_seconds,
        ];

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
                continue;
            }

            $body = $response->body();
            if ($response->status() === 404 || Str::contains($body, ['UNREGISTERED', 'registration-token-not-registered'])) {
                $device->update(['revoked_at' => now()]);
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
}
