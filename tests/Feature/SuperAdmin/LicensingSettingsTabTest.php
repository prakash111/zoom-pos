<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index;
use App\Models\PlatformSystem;
use App\Services\License\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class LicensingSettingsTabTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_licensing_tab_is_an_allowed_tab(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::withQueryParams(['tab' => 'licensing'])
            ->test(Index::class)
            ->assertSet('activeTab', 'licensing');
    }

    public function test_save_licensing_persists_settings_and_clears_secret_inputs(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->set('licenseDriver', 'codecanyon')
            ->set('licenseServerUrl', 'https://license.test/')
            ->set('envatoApiToken', 'envato-token-123')
            ->set('licenseServerSecret', 'server-secret-123')
            ->call('saveLicensing')
            ->assertHasNoErrors()
            ->assertSet('envatoApiToken', '')
            ->assertSet('licenseServerSecret', '')
            ->assertSet('hasEnvatoApiToken', true)
            ->assertSet('hasLicenseServerSecret', true);

        $this->assertSame('codecanyon', PlatformSystem::get('license_driver'));
        $this->assertSame('https://license.test', PlatformSystem::get('license_server_url'));
        $this->assertSame('envato-token-123', PlatformSystem::get('envato_api_token'));
        $this->assertSame('server-secret-123', PlatformSystem::get('license_server_secret'));
        $this->assertSame('codecanyon', app(LicenseService::class)->getActiveDriver());
        $this->assertDatabaseHas('audit_logs', ['action' => 'license.settings_updated']);
    }
}
