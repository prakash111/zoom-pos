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

        // Auto-heal: If android_api_key is missing but service account is configured, discover from Google Firebase API
        if (empty($androidApiKey) && ! empty($this->fcm_service_account_json)) {
            $discovered = static::discoverFirebaseConfig();
            if ($discovered && ! empty($discovered['android_api_key'])) {
                $androidApiKey = $discovered['android_api_key'];
                $this->update([
                    'android_api_key'     => $discovered['android_api_key'],
                    'android_app_id'      => $this->android_app_id ?: $discovered['android_app_id'],
                    'messaging_sender_id' => $this->messaging_sender_id ?: $discovered['messaging_sender_id'],
                    'fcm_project_id'      => $this->fcm_project_id ?: $discovered['project_id'],
                ]);
            }
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

    /**
     * Automatically discover Android client configuration from Google Firebase Management API
     * using the service account credentials.
     *
     * @param  array<string, mixed>|string|null  $serviceAccount
     * @return array{project_id: string, messaging_sender_id: string, android_app_id: string, android_api_key: string}|null
     */
    public static function discoverFirebaseConfig(array|string|null $serviceAccount = null, ?string $targetPackage = 'com.zoomnearby.zoompos'): ?array
    {
        try {
            $credentials = is_string($serviceAccount)
                ? json_decode($serviceAccount, true)
                : ($serviceAccount ?: json_decode((string) static::current()->fcm_service_account_json, true));

            if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                return null;
            }

            $now = time();
            $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $claims = rtrim(strtr(base64_encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

            $signature = '';
            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $assertion = "{$header}.{$claims}." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $res = \Illuminate\Support\Facades\Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            $accessToken = $res->json('access_token');
            if (! $accessToken) {
                return null;
            }

            $projectId = $credentials['project_id'] ?? null;
            if (! $projectId) {
                return null;
            }

            $appsRes = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://firebase.googleapis.com/v1beta1/projects/{$projectId}/androidApps");

            if (! $appsRes->successful()) {
                return null;
            }

            $apps = $appsRes->json('apps', []);
            if (empty($apps)) {
                return null;
            }

            $selectedApp = null;
            foreach ($apps as $app) {
                if (($app['packageName'] ?? '') === $targetPackage) {
                    $selectedApp = $app;
                    break;
                }
            }
            if (! $selectedApp) {
                $selectedApp = $apps[0];
            }

            $appId = $selectedApp['appId'];
            $cfgRes = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://firebase.googleapis.com/v1beta1/projects/{$projectId}/androidApps/{$appId}/config");

            if (! $cfgRes->successful()) {
                return null;
            }

            $encoded = $cfgRes->json('configFileContents');
            if (! $encoded) {
                return null;
            }

            $json = json_decode(base64_decode($encoded), true);
            if (! is_array($json)) {
                return null;
            }

            $clients = $json['client'] ?? [];
            $matchingClient = null;
            foreach ($clients as $client) {
                if (($client['client_info']['android_client_info']['package_name'] ?? '') === $targetPackage) {
                    $matchingClient = $client;
                    break;
                }
            }
            if (! $matchingClient && ! empty($clients)) {
                $matchingClient = $clients[0];
            }

            $apiKey = $matchingClient['api_key'][0]['current_key'] ?? '';
            $appId = $matchingClient['client_info']['mobilesdk_app_id'] ?? $appId;
            $senderId = (string) ($json['project_info']['project_number'] ?? '');
            $resolvedProjectId = $json['project_info']['project_id'] ?? $projectId;

            return [
                'project_id'          => $resolvedProjectId,
                'messaging_sender_id' => $senderId,
                'android_app_id'      => $appId,
                'android_api_key'     => $apiKey,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-discover Firebase Android config: {$e->getMessage()}");

            return null;
        }
    }
}
