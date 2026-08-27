<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PwaShellTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_manifest_uses_authenticated_tenant_branding(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $company->update([
            'trade_name' => 'Fast Lane Market',
            'primary_color' => '#7C3AED',
        ]);

        $response = $this->get(route('tenant.pwa.manifest'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertJsonPath('name', 'Fast Lane Market POS')
            ->assertJsonPath('theme_color', '#7c3aed')
            ->assertJsonPath('start_url', '/tenant/?source=pwa')
            ->assertJsonPath('scope', '/tenant/')
            ->assertJsonPath('icons.2.purpose', 'maskable');
    }

    public function test_manifest_rejects_invalid_brand_color(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $company->update(['primary_color' => 'javascript:alert(1)']);

        $this->get(route('tenant.pwa.manifest'))
            ->assertOk()
            ->assertJsonPath('theme_color', '#2563eb');
    }

    public function test_manifest_requires_an_authenticated_verified_tenant(): void
    {
        $this->get('/tenant/app.webmanifest')
            ->assertRedirect(route('tenant.login'));
    }

    public function test_service_worker_only_caches_public_static_assets(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("'/build/assets/'", $worker);
        $this->assertStringNotContainsString("'/livewire/'", $worker);
        $this->assertStringNotContainsString("'/storage/'", $worker);
    }
}
