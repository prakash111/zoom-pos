<?php

namespace App\Services;

use App\Models\TenantNotificationGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsGatewayService
{
    /**
     * Dispatch SMS using the tenant's configured generic gateway (ZoomNearby SMS API Compatible).
     * Reference: https://sms.zoomnearby.com/docs/api#authenticating-requests
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
                    'method'      => $gw->credentials['method'] ?? 'POST',
                    'api_token'   => $gw->credentials['api_key'] ?? ($gw->credentials['api_token'] ?? ''),
                ];
            }
        }

        // Endpoint defaults to official ZoomNearby SMS API
        $endpoint = ! empty($settings['gateway_url'])
            ? trim($settings['gateway_url'])
            : 'https://sms.zoomnearby.com/api/v1/messages/send';
        $method   = strtoupper($settings['method'] ?? 'POST');
        $apiToken = trim($settings['api_token'] ?? ($settings['api_key'] ?? ''));

        // Format phone: ensure standard international digits with leading +
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        $cleanPhone = str_starts_with(trim($phone), '+') ? '+'.$digitsOnly : (strlen($digitsOnly) >= 10 ? '+'.$digitsOnly : $digitsOnly);

        $isZoomSms = str_contains($endpoint, 'sms.zoomnearby.com');

        // Replace dynamic template variables for custom endpoints
        $resolvedUrl = str_replace(
            ['{phone}', '{message}', '{to}'],
            [$cleanPhone, urlencode($message), $cleanPhone],
            $endpoint
        );

        try {
            $client = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            if ($apiToken !== '') {
                // Official Authentication per https://sms.zoomnearby.com/docs/api#authenticating-requests
                $client = $client->withToken($apiToken);
            }

            if ($method === 'POST') {
                if ($isZoomSms) {
                    // ZoomNearby SMS Gateway API payload
                    $postData = [
                        'mobile_numbers' => [$cleanPhone],
                        'type'           => 'SMS',
                        'message'        => $message,
                        'sims'           => ['*'], // Default to all available SIMs
                    ];
                } else {
                    $postData = [
                        'phone'          => $cleanPhone,
                        'to'             => $cleanPhone,
                        'mobile_numbers' => [$cleanPhone],
                        'message'        => $message,
                    ];
                    if ($apiToken !== '') {
                        $postData['token'] = $apiToken;
                    }
                }

                $response = $client->post($resolvedUrl, $postData);
            } else {
                $params = [];
                if ($isZoomSms) {
                    if (! str_contains($resolvedUrl, 'mobile_numbers')) {
                        $params['mobile_numbers'] = [$cleanPhone];
                    }
                    if (! str_contains($resolvedUrl, 'message')) {
                        $params['message'] = $message;
                    }
                    if (! str_contains($resolvedUrl, 'type')) {
                        $params['type'] = 'SMS';
                    }
                    if (! str_contains($resolvedUrl, 'sims') && ! str_contains($resolvedUrl, 'sender_ids')) {
                        $params['sims'] = ['*'];
                    }
                } else {
                    if (! str_contains($endpoint, '{phone}') && ! str_contains($endpoint, '{to}')) {
                        $params['phone'] = $cleanPhone;
                    }
                    if (! str_contains($endpoint, '{message}')) {
                        $params['message'] = $message;
                    }
                }

                if ($params) {
                    $resolvedUrl .= (str_contains($resolvedUrl, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
                }

                $response = $client->get($resolvedUrl);
            }

            $body = $response->json() ?? $response->body();
            $success = $response->successful();

            Log::info("SMS Gateway Dispatch Result: [{$response->status()}] " . (is_array($body) ? json_encode($body) : $body));

            $errorMsg = null;
            if (! $success) {
                $errorMsg = is_array($body)
                    ? ($body['message'] ?? ($body['error'] ?? json_encode($body)))
                    : (string) $body;
            }

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $body,
                'error'   => $errorMsg,
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
