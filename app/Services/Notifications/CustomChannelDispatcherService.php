<?php

namespace App\Services\Notifications;

use App\Models\CustomNotificationChannel;
use App\Models\MessageQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomChannelDispatcherService
{
    /**
     * Dispatch an event to all active custom channels subscribed to it.
     *
     * @param  array<string, mixed>  $variables
     */
    public function dispatchEvent(string $companyId, string $eventType, array $variables): int
    {
        $channels = CustomNotificationChannel::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (CustomNotificationChannel $c) => $c->handlesEvent($eventType));

        $dispatched = 0;
        foreach ($channels as $channel) {
            $res = $this->dispatch($channel, $variables);
            if ($res['success']) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Dispatch a single channel with tag substitutions.
     *
     * @param  array<string, mixed>  $variables
     * @return array{success: bool, status_code: int, response: string, error: ?string}
     */
    public function dispatch(CustomNotificationChannel $channel, array $variables): array
    {
        // 1. Substitute dynamic tags in URL and Template
        $renderedUrl = $this->renderTemplate($channel->url, $variables);
        $template = (string) ($channel->payload_template ?? '');
        $renderedBody = $this->renderBodyTemplate($template, $variables);

        $method = strtoupper($channel->method ?: 'POST');
        $format = strtolower($channel->payload_format ?: ($method === 'GET' ? 'query_params' : 'json'));

        // 2. Prepare Headers & Authentication
        $headers = (array) ($channel->headers ?? []);
        if ($channel->auth_type === 'bearer' && filled($channel->auth_value)) {
            $headers['Authorization'] = 'Bearer '.$channel->auth_value;
        } elseif ($channel->auth_type === 'api_key' && filled($channel->auth_value)) {
            $headers['X-API-Key'] = $channel->auth_value;
        }

        // 3. Prepare Queue Log
        $queueRow = MessageQueue::create([
            'company_id' => $channel->company_id,
            'type' => 'webhook',
            'recipient' => $renderedUrl,
            'payload' => [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'method' => $method,
                'format' => $format,
                'body' => $renderedBody,
            ],
            'status' => 'sending',
        ]);

        try {
            $httpClient = Http::withHeaders($headers)->timeout(15);

            $response = match ($format) {
                'query_params' => $this->dispatchQueryParams($httpClient, $method, $renderedUrl, $renderedBody),
                'form_data' => $this->dispatchFormData($httpClient, $method, $renderedUrl, $renderedBody),
                default => $this->dispatchJson($httpClient, $method, $renderedUrl, $renderedBody),
            };

            $statusCode = $response->status();
            $responseBody = substr($response->body(), 0, 1000);
            $isSuccess = $response->successful();

            $queueRow->update([
                'status' => $isSuccess ? 'sent' : 'failed',
                'attempts' => 1,
                'last_error' => $isSuccess ? null : "HTTP {$statusCode}: {$responseBody}",
                'sent_at' => $isSuccess ? now() : null,
            ]);

            return [
                'success' => $isSuccess,
                'status_code' => $statusCode,
                'response' => $responseBody,
                'error' => $isSuccess ? null : "HTTP {$statusCode}: {$responseBody}",
            ];
        } catch (Throwable $e) {
            Log::warning("Custom notification channel #{$channel->id} dispatch failed: ".$e->getMessage());

            $queueRow->update([
                'status' => 'failed',
                'attempts' => 1,
                'last_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'response' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Replaces dynamic tags: {phone}, {customer_name}, {invoice_id}, {amount}, {order_link}, etc.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderTemplate(string $template, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        // Standard tag fallbacks if not explicitly provided
        $defaults = [
            '{phone}' => '',
            '{customer_name}' => 'Valued Customer',
            '{invoice_id}' => '',
            '{amount}' => '0.00',
            '{order_link}' => '',
            '{date}' => date('Y-m-d'),
        ];

        foreach ($defaults as $tag => $fallback) {
            if (! in_array($tag, $search, true)) {
                $search[] = $tag;
                $replace[] = $fallback;
            }
        }

        return str_replace($search, $replace, $template);
    }

    private function renderBodyTemplate(string $template, array $variables): string
    {
        $decoded = json_decode($template, true);
        if (! is_array($decoded)) {
            return $this->renderTemplate($template, $variables);
        }

        // Render values before encoding so message newlines and quotes remain valid JSON.
        $render = function (mixed $value) use (&$render, $variables): mixed {
            if (is_array($value)) {
                return array_map($render, $value);
            }
            if (is_string($value)) {
                if (preg_match('/^\{([^{}]+)\}$/', $value, $match) && is_array($variables[$match[1]] ?? null)) {
                    return $variables[$match[1]];
                }

                return $this->renderTemplate($value, $variables);
            }

            return $value;
        };

        return json_encode($render($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function dispatchQueryParams($client, string $method, string $url, string $renderedBody)
    {
        $params = [];
        $decoded = json_decode($renderedBody, true);
        if (is_array($decoded)) {
            $params = $decoded;
        } elseif (! empty($renderedBody)) {
            parse_str($renderedBody, $params);
        }

        return $method === 'POST'
            ? $client->post($url, $params)
            : $client->get($url, $params);
    }

    protected function dispatchFormData($client, string $method, string $url, string $renderedBody)
    {
        $params = [];
        $decoded = json_decode($renderedBody, true);
        if (is_array($decoded)) {
            $params = $decoded;
        } elseif (! empty($renderedBody)) {
            parse_str($renderedBody, $params);
        }

        $formClient = $client->asForm();

        return $method === 'GET'
            ? $formClient->get($url, $params)
            : $formClient->post($url, $params);
    }

    protected function dispatchJson($client, string $method, string $url, string $renderedBody)
    {
        $decoded = json_decode($renderedBody, true);

        if (is_array($decoded)) {
            return $client->send($method, $url, ['json' => $decoded]);
        }

        return $client->withBody($renderedBody, 'application/json')->send($method, $url);
    }
}
