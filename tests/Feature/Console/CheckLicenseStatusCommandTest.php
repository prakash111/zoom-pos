<?php

namespace Tests\Feature\Console;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckLicenseStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    private function licensedModule(array $overrides = []): SduiModule
    {
        return SduiModule::create(array_merge([
            'name' => 'Widgets',
            'slug' => 'widgets',
            'source_type' => 'package',
            'package_path' => 'widgets',
            'installed_at' => now(),
            'is_active' => true,
            'requires_license' => true,
            'license_status' => 'active',
            'license_key_encrypted' => 'PH-1234-5678-ABCD',
            'license_driver' => 'custom',
            'navigation' => [],
            'features' => [],
        ], $overrides));
    }

    public function test_a_revoked_module_is_deactivated_and_dropped_from_registration_modes(): void
    {
        config()->set('services.license_server.url', 'https://license.test');
        PlatformSystem::set('allowed_registration_modes', json_encode(['retail', 'widgets']));
        Http::fake(['license.test/*' => Http::response(['status' => false, 'message' => 'revoked'], 200)]);

        $module = $this->licensedModule();

        $this->artisan('license:check-status', ['--sync' => true])->assertExitCode(0);

        $module->refresh();
        $this->assertFalse($module->is_active);
        $this->assertSame('revoked', $module->license_status);
        $this->assertNotContains('widgets', json_decode(PlatformSystem::get('allowed_registration_modes'), true));
        $this->assertDatabaseHas('audit_logs', ['action' => 'module.license_revoked']);
    }

    public function test_transport_failure_tolerates_two_runs_then_deactivates_on_the_third(): void
    {
        config()->set('services.license_server.url', 'https://license.test');
        Http::fake(['license.test/*' => fn () => throw new \RuntimeException('timeout')]);

        $module = $this->licensedModule();

        $this->artisan('license:check-status', ['--sync' => true]);
        $this->assertTrue($module->fresh()->is_active);
        $this->assertSame('1', PlatformSystem::get('license_module_widgets_fail_streak'));

        $this->artisan('license:check-status', ['--sync' => true]);
        $this->assertTrue($module->fresh()->is_active);

        $this->artisan('license:check-status', ['--sync' => true]);
        $this->assertFalse($module->fresh()->is_active);
        $this->assertSame('revoked', $module->fresh()->license_status);
    }

    public function test_expired_license_past_grace_is_deactivated(): void
    {
        config()->set('services.license_server.url', 'https://license.test');
        Http::fake(['license.test/*' => Http::response([
            'status' => true,
            'expires_at' => now()->subDays(10)->toIso8601String(),
            'message' => 'ok',
        ], 200)]);

        $module = $this->licensedModule();

        $this->artisan('license:check-status', ['--sync' => true, '--grace-days' => 3]);

        $module->refresh();
        $this->assertFalse($module->is_active);
        $this->assertSame('expired', $module->license_status);
    }

    public function test_core_license_failure_only_warns(): void
    {
        // Deterministic: a clearly-invalid installed blob so the check fails.
        $path = storage_path('installed');
        $backup = is_file($path) ? file_get_contents($path) : null;
        file_put_contents($path, json_encode(['license' => ['purchase_code' => 'x']]));

        try {
            $this->artisan('license:check-status', ['--core-only' => true, '--sync' => true])->assertExitCode(0);
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        }

        $this->assertSame('warn', PlatformSystem::get('core_license_status'));
        $this->assertNotNull(PlatformSystem::get('core_license_last_checked'));
    }
}
