<?php

namespace App\Services\License;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * HTTP client for the self-hosted / standalone license server
 * (e.g. https://license.zoomnearby.com). Contract lives in
 * docs/LICENSE_SERVER_CONTRACT.md.
 *
 * The server URL is hardcoded (config/services.php, not the environment); only
 * the shared secret (LICENSE_SERVER_SECRET) is configurable. There is no in-app
 * screen for either.
 *
 * When no base URL is configured (tests only) this degrades to a local format
 * check. In production the URL is always set, so verification is strict: an
 * unreachable server or a non-2xx response is a failure, never a silent pass.
 */
class CustomLicenseServerClient
{
    private string $baseUrl;

    private string $secret;

    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $secret = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('services.license_server.url')), '/');
        $this->secret = (string) ($secret ?? config('services.license_server.secret'));
        $this->timeout = $timeout ?? (int) config('services.license_server.timeout', 10);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function storeUrl(): string
    {
        return rtrim((string) config('services.license_server.store_url'), '/');
    }

    /**
     * Verify a key, then download that product's package ZIP. The module source
     * files live only on the license server — this is the only way to get them.
     *
     * @return array{status: bool, path: ?string, message: string}
     */
    public function downloadModule(string $key, string $slug, string $domain): array
    {
        if (! $this->isConfigured()) {
            return ['status' => false, 'path' => null, 'message' => 'License server is not configured.'];
        }

        try {
            $response = Http::withHeaders(['X-Server-Secret' => $this->secret])
                ->timeout(max($this->timeout, 60))
                ->post($this->baseUrl.'/api/download.php', [
                    'license_key' => trim($key),
                    'product_slug' => $slug,
                    'domain' => $domain,
                ]);

            if ($response->successful() && str_contains(strtolower($response->header('Content-Type') ?? ''), 'zip')) {
                $tmp = tempnam(sys_get_temp_dir(), 'modpkg_').'.zip';
                file_put_contents($tmp, $response->body());

                return ['status' => true, 'path' => $tmp, 'message' => 'Package downloaded.'];
            }

            $message = $response->json('message')
                ?: 'License server declined the download (HTTP '.$response->status().').';

            return ['status' => false, 'path' => null, 'message' => (string) $message];
        } catch (Throwable $e) {
            Log::warning('License server module download failed: '.$e->getMessage(), ['slug' => $slug]);

            return ['status' => false, 'path' => null, 'message' => 'License server unreachable: '.$e->getMessage()];
        }
    }

    /**
     * Active products the vendor sells, for the "Buy module" list.
     *
     * @return list<array{slug: string, name: string, description: ?string, price: float, currency: string}>
     */
    public function catalog(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::withHeaders(['X-Server-Secret' => $this->secret])
                ->acceptJson()
                ->timeout($this->timeout)
                ->get($this->baseUrl.'/api/catalog.php');

            if ($response->successful() && $response->json('status')) {
                return array_map(fn ($p) => [
                    'slug' => (string) ($p['slug'] ?? ''),
                    'name' => (string) ($p['name'] ?? ''),
                    'description' => $p['description'] ?? null,
                    'price' => (float) ($p['price'] ?? 0),
                    'currency' => strtoupper((string) ($p['currency'] ?? 'USD')),
                ], (array) $response->json('products', []));
            }
        } catch (Throwable $e) {
            Log::warning('License server catalog fetch failed: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Verify a license key for a product against the license server.
     *
     * @return array{status: bool, expires_at: ?string, message: string, plan: ?string, http: ?int}
     */
    public function verify(string $key, string $slug, string $domain): array
    {
        $key = trim($key);

        if (! $this->isConfigured()) {
            $ok = $this->looksLikeKey($key);

            return [
                'status' => $ok,
                'expires_at' => null,
                'message' => $ok
                    ? 'Local format check passed (license server not configured).'
                    : 'License key format is not valid.',
                'plan' => null,
                'http' => null,
            ];
        }

        try {
            $response = Http::withHeaders(['X-Server-Secret' => $this->secret])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/api/verify.php', [
                    'license_key' => $key,
                    'product_slug' => $slug,
                    'domain' => $domain,
                ]);

            if ($response->successful()) {
                return [
                    'status' => (bool) $response->json('status', false),
                    'expires_at' => $response->json('expires_at'),
                    'message' => (string) $response->json('message', $response->json('status') ? 'License verified.' : 'License rejected.'),
                    'plan' => $response->json('plan'),
                    'http' => $response->status(),
                ];
            }

            Log::warning('License server verify returned non-2xx', [
                'slug' => $slug,
                'http' => $response->status(),
            ]);

            return [
                'status' => false,
                'expires_at' => null,
                'message' => 'License server rejected the request (HTTP '.$response->status().').',
                'plan' => null,
                'http' => $response->status(),
            ];
        } catch (Throwable $e) {
            Log::warning('License server verify failed: '.$e->getMessage(), ['slug' => $slug]);

            return [
                'status' => false,
                'expires_at' => null,
                'message' => 'License server unreachable: '.$e->getMessage(),
                'plan' => null,
                'http' => null,
            ];
        }
    }

    /**
     * Ask the license server to issue + register a key after a successful
     * payment. Idempotent on $payment['reference'] server-side.
     *
     * @param  array<string, mixed>  $payment  {gateway, reference, amount, currency, payer_email}
     * @return array{status: bool, license_key: ?string, expires_at: ?string, message: string, plan: ?string}
     */
    public function issue(array $payment, string $slug, string $domain, ?string $itemId = null): array
    {
        if (! $this->isConfigured()) {
            $key = 'DEV-'.strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
            Log::warning('CustomLicenseServerClient::issue minted a DEV-ONLY key (license server not configured).', [
                'slug' => $slug,
                'reference' => $payment['reference'] ?? null,
            ]);

            return [
                'status' => true,
                'license_key' => $key,
                'expires_at' => now()->addYear()->toIso8601String(),
                'message' => 'DEV-ONLY local key issued (license server not configured).',
                'plan' => null,
            ];
        }

        try {
            $response = Http::withHeaders(['X-Server-Secret' => $this->secret])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/api/issue.php', array_filter([
                    'payment' => $payment,
                    'product_slug' => $slug,
                    'domain' => $domain,
                    'item_id' => $itemId,
                ], fn ($v) => $v !== null));

            if ($response->successful() && $response->json('status')) {
                return [
                    'status' => true,
                    'license_key' => $response->json('license_key'),
                    'expires_at' => $response->json('expires_at'),
                    'message' => (string) $response->json('message', 'License issued.'),
                    'plan' => $response->json('plan'),
                ];
            }

            Log::warning('License server issue failed', [
                'slug' => $slug,
                'http' => $response->status(),
                'reference' => $payment['reference'] ?? null,
            ]);

            return [
                'status' => false,
                'license_key' => null,
                'expires_at' => null,
                'message' => (string) $response->json('message', 'License server could not issue a key (HTTP '.$response->status().').'),
                'plan' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('License server issue exception: '.$e->getMessage(), ['slug' => $slug]);

            return [
                'status' => false,
                'license_key' => null,
                'expires_at' => null,
                'message' => 'License server unreachable: '.$e->getMessage(),
                'plan' => null,
            ];
        }
    }

    /**
     * Loose structural check used only by the offline fallback: a UUID v4, a
     * demo/test/codecanyon/envato-prefixed token, or any 16+ char string.
     */
    private function looksLikeKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key)) {
            return true;
        }

        if (preg_match('/^(demo|test|codecanyon|envato)-[a-z0-9-]+$/i', $key)) {
            return true;
        }

        return strlen($key) >= 16;
    }
}
