<?php

namespace Tests\Feature\License;

use App\Models\PlatformSystem;
use App\Services\License\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LicenseService
    {
        return app(LicenseService::class);
    }

    public function test_active_driver_defaults_to_custom_and_reads_platform_system(): void
    {
        $this->assertSame('custom', $this->service()->getActiveDriver());

        PlatformSystem::set('license_driver', 'codecanyon');
        $this->assertSame('codecanyon', $this->service()->getActiveDriver());

        PlatformSystem::set('license_driver', 'garbage');
        $this->assertSame('custom', $this->service()->getActiveDriver());
    }

    public function test_custom_driver_offline_fallback_format_checks_only(): void
    {
        Http::fake();

        $this->assertTrue($this->service()->isOfflineFallback());

        $ok = $this->service()->verify('demo-abcdefghijklmnop', 'pharmacy', 'acme.example.com');
        $this->assertTrue($ok['status']);
        $this->assertSame('custom', $ok['driver']);

        $bad = $this->service()->verify('short', 'pharmacy', 'acme.example.com');
        $this->assertFalse($bad['status']);

        Http::assertNothingSent();
    }

    public function test_custom_driver_is_strict_once_a_url_is_configured(): void
    {
        PlatformSystem::set('license_server_url', 'https://license.test');
        PlatformSystem::set('license_server_secret', 's3cret');

        Http::fake([
            'license.test/api/v1/license/verify' => Http::response([
                'status' => true,
                'expires_at' => '2027-01-01T00:00:00Z',
                'message' => 'ok',
                'plan' => 'extended',
            ], 200),
        ]);

        $res = $this->service()->verify('PH-1234-5678-ABCD', 'pharmacy', 'acme.example.com');

        $this->assertTrue($res['status']);
        $this->assertSame('2027-01-01T00:00:00Z', $res['expires_at']);
        $this->assertSame('extended', $res['plan']);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Server-Secret', 's3cret')
            && $request['license_key'] === 'PH-1234-5678-ABCD'
            && $request['product_slug'] === 'pharmacy'
            && $request['domain'] === 'acme.example.com');
    }

    public function test_unreachable_license_server_is_a_failure_not_a_pass(): void
    {
        PlatformSystem::set('license_server_url', 'https://license.test');

        Http::fake(['license.test/*' => fn () => throw new \RuntimeException('connection refused')]);

        $res = $this->service()->verify('PH-1234-5678-ABCD', 'pharmacy', 'acme.example.com');

        $this->assertFalse($res['status']);
        $this->assertStringContainsString('unreachable', strtolower($res['message']));
    }

    public function test_codecanyon_driver_routes_to_envato_api(): void
    {
        PlatformSystem::set('license_driver', 'codecanyon');
        PlatformSystem::set('envato_api_token', 'envato-token');

        Http::fake([
            'api.envato.com/*' => Http::response([
                'buyer' => 'jane',
                'licence' => 'Regular License',
                'item' => ['name' => 'POS SaaS'],
            ], 200),
        ]);

        $res = $this->service()->verify('11111111-2222-3333-4444-555555555555', 'core', 'acme.example.com');

        $this->assertTrue($res['status']);
        $this->assertSame('codecanyon', $res['driver']);
        $this->assertSame('jane', $res['buyer']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.envato.com'));
    }

    public function test_issue_offline_mints_a_dev_key(): void
    {
        $res = $this->service()->issue(['gateway' => 'razorpay', 'reference' => 'pay_1'], 'pharmacy', 'acme.example.com');

        $this->assertTrue($res['status']);
        $this->assertStringStartsWith('DEV-', $res['license_key']);
        $this->assertNotNull($res['expires_at']);
    }
}
