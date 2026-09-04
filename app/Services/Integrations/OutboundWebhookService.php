<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\MessageQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboundWebhookService
{
    public const EVENT_ORDER_CREATED = 'order.created';
    public const EVENT_ORDER_SETTLED = 'order.settled';
    public const EVENT_ORDER_CANCELLED = 'order.cancelled';
    public const EVENT_STOCK_LOW_ALERT = 'stock.low_alert';

    public const SUPPORTED_EVENTS = [
        self::EVENT_ORDER_CREATED,
        self::EVENT_ORDER_SETTLED,
        self::EVENT_ORDER_CANCELLED,
        self::EVENT_STOCK_LOW_ALERT,
    ];

    /**
     * Dispatch an outbound webhook event for a given company.
     */
    public function dispatch(Company $company, string $event, array $data): bool
    {
        $configs = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'outbound_webhook_url',
                'webhook_url',
                'outbound_webhook_secret',
                'webhook_hmac_secret',
                'outbound_events',
            ])
            ->pluck('value', 'key');

        $url = $configs->get('outbound_webhook_url') ?: $configs->get('webhook_url');
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $rawEvents = $configs->get('outbound_events');
        if ($rawEvents !== null && $rawEvents !== '') {
            $subscribedEvents = json_decode($rawEvents, true);
            if (is_array($subscribedEvents) && ! empty($subscribedEvents) && ! in_array($event, $subscribedEvents, true)) {
                return false;
            }
        }

        $secret = $configs->get('outbound_webhook_secret') ?: $configs->get('webhook_hmac_secret') ?: '';

        $payload = [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'company_id' => (string) $company->id,
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-Event' => $event,
            'X-ZoomPOS-Event' => $event,
        ];

        if (! empty($secret)) {
            $headers['X-Webhook-Signature'] = 'sha256='.hash_hmac('sha256', $jsonPayload, $secret);
            $headers['X-Signature-SHA256'] = hash_hmac('sha256', $jsonPayload, $secret);
        }

        $queueRow = MessageQueue::create([
            'company_id' => $company->id,
            'type' => 'webhook',
            'recipient' => $url,
            'payload' => [
                'event' => $event,
                'body' => $payload,
            ],
            'status' => 'sending',
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->withBody($jsonPayload, 'application/json')
                ->post($url);

            $success = $response->successful();

            $queueRow->update([
                'status' => $success ? 'sent' : 'failed',
                'attempts' => 1,
                'last_error' => $success ? null : "HTTP {$response->status()}: ".substr($response->body(), 0, 500),
                'sent_at' => $success ? now() : null,
            ]);

            return $success;
        } catch (Throwable $e) {
            Log::warning("Outbound webhook dispatch failed for company {$company->id}: ".$e->getMessage());

            $queueRow->update([
                'status' => 'failed',
                'attempts' => 1,
                'last_error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
