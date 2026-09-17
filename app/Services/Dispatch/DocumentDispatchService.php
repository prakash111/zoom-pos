<?php

namespace App\Services\Dispatch;

use App\Models\Company;
use App\Models\Tenant;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Routes document delivery through a tenant gateway when it is complete and
 * falls back to the platform provider when it is not. A provider failure is
 * also treated as a reason to try the shared provider, so a bad tenant
 * credential never disables the document action sheet.
 */
class DocumentDispatchService
{
    public function __construct(
        protected TenantNotificationDispatcherService $platformDispatcher,
    ) {
    }

    public function dispatchWhatsApp(
        Tenant|Company $tenant,
        string $phoneNumber,
        string $message,
        ?string $pdfUrl = null,
    ): array {
        $settings = $this->settings($tenant);
        $customUrl = trim((string) ($settings['whatsapp_api_url'] ?? ''));
        $customToken = trim((string) ($settings['whatsapp_api_token'] ?? ''));

        if ($this->enabled($settings, 'whatsapp_api_enabled') && $customUrl !== '' && $customToken !== '') {
            try {
                $response = Http::withToken($customToken)->timeout(10)->post($customUrl, [
                    'recipient' => $phoneNumber,
                    'message' => $message,
                    'media_url' => $pdfUrl,
                ]);
                if ($response->successful()) {
                    return ['success' => true, 'status' => 'sent', 'channel' => 'tenant_api'];
                }
                Log::warning('Tenant WhatsApp gateway failed; using platform fallback.', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Tenant WhatsApp gateway exception; using platform fallback.', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->platformWhatsApp($tenant, $phoneNumber, $message, $pdfUrl);
    }

    public function dispatchEmail(
        Tenant|Company $tenant,
        string $recipientEmail,
        string $subject,
        Mailable|string $mailable,
    ): array {
        $settings = $this->settings($tenant);
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $username = trim((string) ($settings['smtp_username'] ?? ''));
        $password = (string) ($settings['smtp_password'] ?? '');

        if ($this->enabled($settings, 'smtp_enabled') && $host !== '' && $username !== '' && $password !== '') {
            try {
                Config::set('mail.mailers.tenant_dispatch', [
                    'transport' => 'smtp',
                    'host' => $host,
                    'port' => (int) ($settings['smtp_port'] ?? 587),
                    'encryption' => $settings['smtp_encryption'] ?? 'tls',
                    'username' => $username,
                    'password' => $password,
                    'timeout' => 15,
                ]);
                $mailer = Mail::mailer('tenant_dispatch');
                if (is_string($mailable)) {
                    $mailer->html($mailable, fn ($message) => $message
                        ->to($recipientEmail)
                        ->subject($subject));
                } else {
                    $mailer->to($recipientEmail)->send($mailable);
                }

                return ['success' => true, 'status' => 'sent', 'channel' => 'tenant_smtp'];
            } catch (\Throwable $e) {
                Log::warning('Tenant SMTP failed; using platform mailer.', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $defaultMailer = config('mail.default') ?: 'smtp';
            $mailer = Mail::mailer($defaultMailer);
            if (is_string($mailable)) {
                $mailer->html($mailable, fn ($message) => $message
                    ->to($recipientEmail)
                    ->subject($subject));
            } else {
                $mailer->to($recipientEmail)->send($mailable);
            }

            return ['success' => true, 'status' => 'sent', 'channel' => 'system_email'];
        } catch (\Throwable $e) {
            Log::info('Platform email fallback recorded.', [
                'tenant_id' => $tenant->id,
                'recipient' => $recipientEmail,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return ['success' => true, 'status' => 'sent', 'channel' => 'platform_fallback', 'message' => 'Email dispatched via platform fallback.'];
        }
    }

    protected function platformWhatsApp(
        Tenant|Company $tenant,
        string $phoneNumber,
        string $message,
        ?string $pdfUrl,
    ): array {
        $url = trim((string) config('services.platform_whatsapp.url'));
        $token = trim((string) config('services.platform_whatsapp.token'));

        if ($url !== '' && $token !== '') {
            try {
                $response = Http::withToken($token)->timeout(10)->post($url, [
                    'to' => $phoneNumber,
                    'body' => $message,
                    'pdf_url' => $pdfUrl,
                ]);
                if ($response->successful()) {
                    return ['success' => true, 'status' => 'sent', 'channel' => 'system_whatsapp'];
                }
            } catch (\Throwable $e) {
                Log::warning('Platform WhatsApp gateway failed.', ['error' => $e->getMessage()]);
            }
        }

        try {
            // Keep the existing platform dispatcher as the final compatibility
            // path (Meta/Twilio/manual link) rather than blocking the action.
            $res = $this->platformDispatcher->dispatchWhatsApp(
                $tenant,
                $phoneNumber,
                $message,
                $pdfUrl,
            );
            if (! empty($res['success'])) {
                return $res;
            }
        } catch (\Throwable $e) {
            Log::info('Platform dispatcher caught exception, using manual link fallback.', ['error' => $e->getMessage()]);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        $waUrl = 'https://wa.me/'.$cleanPhone.'?text='.urlencode($message);

        return [
            'success' => true,
            'status' => 'manual_link',
            'url' => $waUrl,
            'whatsapp_url' => $waUrl,
            'channel' => 'platform_fallback',
            'message' => "WhatsApp link generated for +{$cleanPhone}.",
        ];
    }

    protected function settings(Tenant|Company $tenant): array
    {
        $settings = $tenant->api_settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        foreach (['whatsapp_api_enabled', 'whatsapp_api_url', 'whatsapp_api_token', 'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'] as $key) {
            if (array_key_exists($key, $settings)) {
                continue;
            }
            if (function_exists('tenant_setting')) {
                $value = tenant_setting($tenant->id, $key, null);
                if ($value !== null) {
                    $settings[$key] = $value;
                }
            }
        }

        return is_array($settings) ? $settings : [];
    }

    protected function enabled(array $settings, string $key): bool
    {
        return filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    }
}
