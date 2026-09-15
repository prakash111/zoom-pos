<?php

namespace App\Services\Delivery;

use App\Models\CustomNotificationChannel;
use App\Models\MessageQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches a tenant-configured custom notification channel (Settings >
 * Notifications > Custom Notification Channels) for invoice, quotation, or
 * due-reminder events. Attempts are logged through the existing
 * message_queue table (type = 'webhook') rather than a separate queue.
 */
class WebhookDispatchService
{
    /**
     * Fire every active channel subscribed to $eventType for the channel's
     * own company. $variables are substituted into each channel's
     * payload_template as {key} placeholders.
     */
    public function dispatchEvent(string $companyId, string $eventType, array $variables): void
    {
        $channels = CustomNotificationChannel::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (CustomNotificationChannel $c) => $c->handlesEvent($eventType));

        foreach ($channels as $channel) {
            $this->dispatch($channel, $variables);
        }
    }

    public function dispatch(CustomNotificationChannel $channel, array $variables): bool
    {
        $body = $this->renderTemplate($channel->payload_template ?? '', $variables);

        $queueRow = MessageQueue::create([
            'company_id' => $channel->company_id,
            'type' => 'webhook',
            'recipient' => $channel->url,
            'payload' => ['channel_id' => $channel->id, 'channel_name' => $channel->name, 'body' => $body],
            'status' => 'sending',
        ]);

        try {
            $headers = (array) ($channel->headers ?? []);
            if ($channel->auth_type === 'bearer' && filled($channel->auth_value)) {
                $headers['Authorization'] = 'Bearer '.$channel->auth_value;
            } elseif ($channel->auth_type === 'api_key' && filled($channel->auth_value)) {
                $headers['X-API-Key'] = $channel->auth_value;
            }

            $request = Http::withHeaders($headers)->timeout(15);
            $decoded = json_decode($body, true);

            $response = strtoupper($channel->method) === 'GET'
                ? $request->get($channel->url, is_array($decoded) ? $decoded : [])
                : $request->send($channel->method ?: 'POST', $channel->url, [
                    is_array($decoded) ? 'json' : 'body' => $decoded ?? $body,
                ]);

            $queueRow->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'attempts' => 1,
                'last_error' => $response->successful() ? null : "HTTP {$response->status()}: ".$response->body(),
                'sent_at' => $response->successful() ? now() : null,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Custom notification channel dispatch failed for channel #{$channel->id}: ".$e->getMessage());
            $queueRow->update([
                'status' => 'failed',
                'attempts' => 1,
                'last_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function renderTemplate(string $template, array $variables): string
    {
        $search = [];
        $replace = [];
        foreach ($variables as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }
}
