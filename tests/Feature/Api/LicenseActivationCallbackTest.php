<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LicenseActivationCallbackTest extends TestCase
{
    use RefreshDatabase;

    private ?string $installedBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.license_server.secret', 'shared-secret');
        config()->set('app.url', 'https://acme.example.com');

        $path = storage_path('installed');
        $this->installedBackup = is_file($path) ? file_get_contents($path) : null;
    }

    protected function tearDown(): void
    {
        $path = storage_path('installed');
        if ($this->installedBackup === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $this->installedBackup);
        }

        parent::tearDown();
    }

    private function signedPost(array $body): TestResponse
    {
        $raw = json_encode($body);

        return $this->call('POST', '/api/license/activate', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LICENSE_SIGNATURE' => hash_hmac('sha256', $raw, 'shared-secret'),
        ], $raw);
    }

    private function packageRow(array $o = []): SduiModule
    {
        return SduiModule::create(array_merge([
            'name' => 'Widgets', 'slug' => 'widgets', 'source_type' => 'package', 'package_path' => 'widgets',
            'installed_at' => now(), 'is_active' => false, 'requires_license' => true,
            'license_status' => 'unlicensed', 'navigation' => [], 'features' => [],
        ], $o));
    }

    public function test_bad_signature_is_rejected(): void
    {
        $raw = json_encode(['product_slug' => 'widgets', 'license_key' => 'K-AAAA-BBBB-CCCC', 'domain' => 'acme.example.com']);

        $this->call('POST', '/api/license/activate', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LICENSE_SIGNATURE' => 'nope',
        ], $raw)->assertStatus(401);
    }

    public function test_domain_mismatch_is_rejected(): void
    {
        $this->signedPost(['product_slug' => 'widgets', 'license_key' => 'K-AAAA-BBBB-CCCC', 'domain' => 'evil.example.com'])
            ->assertStatus(422);
    }

    public function test_installed_module_is_licensed_by_the_callback(): void
    {
        $module = $this->packageRow();

        $this->signedPost([
            'product_slug' => 'widgets',
            'license_key' => 'K-AAAABBBBCCCCDDDD',
            'domain' => 'acme.example.com',
            'expires_at' => null,
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertSame('active', $module->fresh()->license_status);
    }

    public function test_uninstalled_module_stores_a_pending_license(): void
    {
        $this->signedPost([
            'product_slug' => 'notyet',
            'license_key' => 'K-AAAABBBBCCCCDDDD',
            'domain' => 'acme.example.com',
        ])->assertOk()->assertJsonPath('pending', true);

        $this->assertNotNull(PlatformSystem::get('license_pending_notyet'));
    }

    public function test_core_callback_marks_the_core_license_ok(): void
    {
        $this->signedPost([
            'product_slug' => 'core',
            'license_key' => 'CORE-AAAABBBBCCCC',
            'domain' => 'acme.example.com',
            'expires_at' => '2030-01-01T00:00:00Z',
        ])->assertOk();

        $this->assertSame('ok', PlatformSystem::get('core_license_status'));
        $this->assertSame('2030-01-01T00:00:00Z', PlatformSystem::get('core_license_expires_at'));
    }
}
