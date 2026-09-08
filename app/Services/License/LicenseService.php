<?php

namespace App\Services\License;

use Illuminate\Support\Facades\Cache;

/**
 * Hybrid license verification dispatcher.
 *
 * The driver is fixed in config/services.php (`custom`) — the self-hosted
 * license server at the hardcoded https://license.zoomnearby.com. The
 * "codecanyon" branch (Envato Market API) is retained but unused.
 *
 * All callers get one unified response shape regardless of driver.
 */
class LicenseService
{
    public const DRIVER_CODECANYON = 'codecanyon';

    public const DRIVER_CUSTOM = 'custom';

    public function __construct(
        private EnvatoLicenseVerificationService $envato,
        private CustomLicenseServerClient $custom,
    ) {}

    /**
     * Active driver from config, defaulting to 'custom'. Any unknown value
     * collapses to 'custom'.
     */
    public function getActiveDriver(): string
    {
        $driver = (string) config('services.license_server.driver', self::DRIVER_CUSTOM);

        return in_array($driver, [self::DRIVER_CODECANYON, self::DRIVER_CUSTOM], true)
            ? $driver
            : self::DRIVER_CUSTOM;
    }

    /**
     * True when the custom driver is active but no license server URL is set,
     * i.e. keys are only format-checked. Surfaced in the UI as a warning.
     */
    public function isOfflineFallback(): bool
    {
        return $this->getActiveDriver() === self::DRIVER_CUSTOM && ! $this->custom->isConfigured();
    }

    /**
     * Verify a license/purchase code for a product ("core" for the platform
     * itself, otherwise a module slug).
     *
     * @return array{status: bool, driver: string, message: string, expires_at: ?string, buyer: ?string, plan: ?string}
     */
    public function verify(string $code, string $productSlug, string $domain): array
    {
        $driver = $this->getActiveDriver();

        if ($driver === self::DRIVER_CODECANYON) {
            $r = $this->envato->verify($code, null);

            return [
                'status' => (bool) ($r['success'] ?? false),
                'driver' => self::DRIVER_CODECANYON,
                'message' => (string) ($r['message'] ?? ''),
                'expires_at' => $r['supported_until'] ?? null,
                'buyer' => $r['buyer'] ?? null,
                'plan' => $r['license'] ?? null,
            ];
        }

        $r = $this->custom->verify($code, $productSlug, $domain);

        return [
            'status' => (bool) $r['status'],
            'driver' => self::DRIVER_CUSTOM,
            'message' => (string) $r['message'],
            'expires_at' => $r['expires_at'] ?? null,
            'buyer' => null,
            'plan' => $r['plan'] ?? null,
        ];
    }

    /**
     * Issue + register a key after a successful payment. Always routed through
     * the custom license server (Envato has no issuance API).
     *
     * @param  array<string, mixed>  $payment
     * @return array{status: bool, driver: string, license_key: ?string, expires_at: ?string, message: string, plan: ?string}
     */
    public function issue(array $payment, string $productSlug, string $domain, ?string $itemId = null): array
    {
        $r = $this->custom->issue($payment, $productSlug, $domain, $itemId);

        return [
            'status' => (bool) $r['status'],
            'driver' => self::DRIVER_CUSTOM,
            'license_key' => $r['license_key'] ?? null,
            'expires_at' => $r['expires_at'] ?? null,
            'message' => (string) $r['message'],
            'plan' => $r['plan'] ?? null,
        ];
    }

    /**
     * Active vendor products for the "Buy module" list. Cached briefly so the
     * Modules screen does not hit the license server on every render.
     *
     * @return list<array{slug: string, name: string, description: ?string, price: float, currency: string}>
     */
    public function catalog(): array
    {
        return Cache::remember(
            'license.catalog',
            now()->addMinutes(30),
            fn () => $this->custom->catalog(),
        );
    }

    public function storeUrl(): string
    {
        return $this->custom->storeUrl();
    }

    /**
     * Download a module's package ZIP from the license server (key must verify).
     *
     * @return array{status: bool, path: ?string, message: string}
     */
    public function downloadModule(string $key, string $slug, string $domain): array
    {
        return $this->custom->downloadModule($key, $slug, $domain);
    }

    /**
     * The host this installation identifies as, for domain-locked licenses.
     */
    public static function currentDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $host) {
            $host = request()->getHost();
        }

        return strtolower(trim((string) $host));
    }
}
