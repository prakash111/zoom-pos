<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\Configuration;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Meta WhatsApp Cloud API. Credentials are per-tenant
 * (Settings > Notifications), read from the same `configurations` key-value
 * table SMTP/AI settings already use. With no credentials configured,
 * isConfigured() returns false and callers fall back to the existing wa.me
 * manual-link flow — this class never silently does nothing without saying so.
 */
class WhatsAppCloudApiClient
{
    protected string $apiVersion = 'v20.0';

    public function isConfigured(Company $company): bool
    {
        return filled($this->phoneNumberId($company)) && filled($this->accessToken($company));
    }

    /**
     * Send a plain-text WhatsApp message. Returns the provider message id.
     *
     * @throws \RuntimeException on missing config or a non-2xx API response.
     */
    public function sendText(Company $company, string $toPhone, string $message): string
    {
        if (! $this->isConfigured($company)) {
            throw new \RuntimeException('WhatsApp Cloud API credentials are not configured for this store.');
        }

        $sanitizedPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($sanitizedPhone)) {
            throw new \RuntimeException("Invalid WhatsApp recipient phone number: '{$toPhone}'.");
        }

        $response = Http::withToken($this->accessToken($company))
            ->timeout(15)
            ->baseUrl("https://graph.facebook.com/{$this->apiVersion}")
            ->post("/{$this->phoneNumberId($company)}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $sanitizedPhone,
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $message],
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException("WhatsApp Cloud API delivery failed: {$error}");
        }

        return (string) ($response->json('messages.0.id') ?: '');
    }

    protected function phoneNumberId(Company $company): ?string
    {
        return $this->config($company, 'whatsapp_phone_number_id');
    }

    protected function accessToken(Company $company): ?string
    {
        return $this->config($company, 'whatsapp_api_token');
    }

    protected function config(Company $company, string $key): ?string
    {
        return Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->value('value');
    }
}
