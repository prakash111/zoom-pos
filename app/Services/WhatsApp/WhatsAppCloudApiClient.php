<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\TenantNotificationGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::hasTable('tenant_notification_gateways')) {
            $gateway = TenantNotificationGateway::withoutGlobalScopes()
                ->where('company_id', $company->id)->where('channel', 'whatsapp')->first();
            if ($gateway) {
                return $gateway->provider === TenantNotificationGateway::PROVIDER_META_CLOUD && $gateway->isConfigured();
            }
        }

        $settings = (array) ($company->api_settings ?? []);
        $enabled = $settings['whatsapp_api_enabled'] ?? $this->config($company, 'whatsapp_api_enabled');
        if ($enabled !== null && ! filter_var($enabled, FILTER_VALIDATE_BOOL)) {
            return false;
        }

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

    /**
     * Upload binary media to the tenant's WhatsApp number and send it as a
     * document message with an optional caption. Used to deliver the actual
     * receipt/invoice PDF to the customer's chat (deep links cannot carry a
     * file, so this path only runs when the Cloud API is configured).
     *
     * @throws \RuntimeException on missing config or a non-2xx API response.
     */
    public function sendDocument(
        Company $company,
        string $toPhone,
        string $binary,
        string $filename,
        ?string $caption = null,
        string $mimeType = 'application/pdf',
    ): string {
        if (! $this->isConfigured($company)) {
            throw new \RuntimeException('WhatsApp Cloud API credentials are not configured for this store.');
        }

        $sanitizedPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($sanitizedPhone)) {
            throw new \RuntimeException("Invalid WhatsApp recipient phone number: '{$toPhone}'.");
        }

        $token = $this->accessToken($company);
        $phoneNumberId = $this->phoneNumberId($company);
        $baseUrl = "https://graph.facebook.com/{$this->apiVersion}";

        // 1. Upload the file, get a media id scoped to this phone number.
        $upload = Http::withToken($token)
            ->timeout(30)
            ->attach('file', $binary, $filename, ['Content-Type' => $mimeType])
            ->post("{$baseUrl}/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (! $upload->successful() || ! filled($upload->json('id'))) {
            $error = $upload->json('error.message') ?: $upload->body();
            throw new \RuntimeException("WhatsApp Cloud API media upload failed: {$error}");
        }

        $mediaId = (string) $upload->json('id');

        // 2. Send the document message referencing the uploaded media.
        $document = ['id' => $mediaId, 'filename' => $filename];
        if (filled($caption)) {
            $document['caption'] = $caption;
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$baseUrl}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $sanitizedPhone,
                'type' => 'document',
                'document' => $document,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException("WhatsApp Cloud API document delivery failed: {$error}");
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
        if (Schema::hasTable('tenant_notification_gateways')) {
            $gateway = TenantNotificationGateway::withoutGlobalScopes()
                ->where('company_id', $company->id)->where('channel', 'whatsapp')->first();
            if ($gateway) {
                return $gateway->provider === TenantNotificationGateway::PROVIDER_META_CLOUD && $gateway->isConfigured()
                    ? $gateway->getCredential($key === 'whatsapp_phone_number_id' ? 'phone_number_id' : 'access_token')
                    : null;
            }
        }

        return Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->value('value');
    }
}
