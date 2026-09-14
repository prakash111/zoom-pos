<?php

namespace App\Services;

use App\Models\TenantNotificationGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsGatewayService
{
    /**
     * Dispatch SMS using the tenant's configured generic gateway.
     */
    public static function send(string $phone, string $message, mixed $tenantId = null): array
    {
        $tenantId = $tenantId
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? auth('tenant_api')->user()?->company_id
            ?? auth('web')->user()?->company_id
            ?? auth()->user()?->company_id
            ?? auth()->user()?->tenant_id
            ?? 1;

        $settings = function_exists('tenant_setting') ? tenant_setting($tenantId, 'sms_gateway') : null;
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        if (empty($settings) || ! is_array($settings)) {
            $gw = TenantNotificationGateway::withoutGlobalScope('company')
                ->where('company_id', $tenantId)
                ->where('channel', TenantNotificationGateway::CHANNEL_SMS)
                ->first();
            if ($gw && is_array($gw->credentials)) {
                $settings = [
                    'gateway_url' => $gw->credentials['url'] ?? '',
                    'method'      => $gw->credentials['method'] ?? 'GET',
                    'api_token'   => $gw->credentials['api_key'] ?? ($gw->credentials['api_token'] ?? ''),
                ];
            }
        }

        // Fallback default credentials if not stored in DB
        $endpoint = ! empty($settings['gateway_url'])
            ? $settings['gateway_url']
            : 'https://sms.zoomnearby.com/api/v1/messages/send?phone={phone}&message={message}';
        $method   = strtoupper($settings['method'] ?? 'GET');
        $apiToken = $settings['api_token'] ?? ($settings['api_key'] ?? '4HIXpW0OPsnPpzzebeA5KI7rI4fnAi7utMu5jwYl8dada339');

        // Format phone: ensure standard international digits (e.g., +919876543210 or 9876543210)
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        $encodedMessage = urlencode($message);

        // Replace dynamic template variables
        $resolvedUrl = str_replace(
            ['{phone}', '{message}', '{to}'],
            [$cleanPhone, $encodedMessage, $cleanPhone],
            $endpoint
        );

        // Ensure token is present in query if template lacks it and token exists
        if ($apiToken && ! str_contains($resolvedUrl, 'token=') && ! str_contains($resolvedUrl, 'key=')) {
            $separator = str_contains($resolvedUrl, '?') ? '&' : '?';
            $resolvedUrl .= "{$separator}token=" . urlencode($apiToken);
        }

        // Android Gateway (sms.zoomnearby.com) payload compatibility:
        // sms.zoomnearby.com requires mobile_numbers[] and sims[] parameters
        if (str_contains($resolvedUrl, 'sms.zoomnearby.com')) {
            if (! str_contains($resolvedUrl, 'sims') && ! str_contains($resolvedUrl, 'sender_ids')) {
                $separator = str_contains($resolvedUrl, '?') ? '&' : '?';
                $resolvedUrl .= "{$separator}sims[]=3";
            }
            if (! str_contains($resolvedUrl, 'mobile_numbers')) {
                $resolvedUrl .= '&mobile_numbers[]=' . urlencode($cleanPhone);
            }
        }

        try {
            $client = Http::timeout(15)->acceptJson();

            if ($apiToken) {
                $client = $client->withToken($apiToken);
            }

            if ($method === 'POST') {
                $postData = [
                    'phone'   => $cleanPhone,
                    'message' => $message,
                    'token'   => $apiToken,
                ];
                if (str_contains($resolvedUrl, 'sms.zoomnearby.com')) {
                    $postData['mobile_numbers'] = [$cleanPhone];
                    $postData['sims'] = [3];
                }
                $response = $client->post($resolvedUrl, $postData);
            } else {
                $response = $client->get($resolvedUrl);
            }

            Log::info("SMS Gateway Dispatch Result: [{$response->status()}] {$response->body()}");

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'body'    => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error("SMS Gateway Exception: " . $e->getMessage());

            return [
                'success' => false,
                'status'  => 500,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
