<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Modules\Index as ModulesIndex;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\Modular\ModuleCatalog;
use App\Services\Modular\ModulePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ModuleLicensingTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    private function packageRow(array $overrides = []): SduiModule
    {
        return SduiModule::create(array_merge([
            'name' => 'Widgets',
            'slug' => 'widgets',
            'source_type' => 'package',
            'package_path' => 'widgets',
            'installed_at' => now(),
            'is_active' => false,
            'requires_license' => true,
            'license_status' => 'unlicensed',
            'navigation' => [],
            'features' => [],
        ], $overrides));
    }

    public function test_activate_refuses_an_unlicensed_package_module(): void
    {
        $module = $this->packageRow();

        $this->expectException(RuntimeException::class);
        app(ModulePackageService::class)->activate($module, null);
    }

    public function test_verify_and_record_license_persists_the_licence_columns(): void
    {
        $module = $this->packageRow();

        $result = app(ModulePackageService::class)
            ->verifyAndRecordLicense($module, 'demo-abcdefghijklmnop', null);

        $this->assertTrue($result['status']);

        $module->refresh();
        $this->assertSame('active', $module->license_status);
        $this->assertSame('custom', $module->license_driver);
        $this->assertNotNull($module->license_verified_at);
        $this->assertSame('demo-abcde', $module->license_key_prefix); // substr(key, 0, 10)
        $this->assertTrue(Hash::check('demo-abcdefghijklmnop', $module->license_key_hash));
        $this->assertSame('demo-abcdefghijklmnop', $module->license_key_encrypted); // encrypted cast round-trips
    }

    public function test_modules_index_activate_requires_a_key_then_activates(): void
    {
        $this->actingAsSuperAdmin();
        $module = $this->packageRow();

        Livewire::test(ModulesIndex::class)
            ->call('activate', $module->id)
            ->assertHasErrors('licenseKeys.'.$module->id);

        $this->assertFalse($module->fresh()->is_active);

        Livewire::test(ModulesIndex::class)
            ->set('licenseKeys.'.$module->id, 'demo-abcdefghijklmnop')
            ->call('activate', $module->id)
            ->assertHasNoErrors();

        $this->assertTrue($module->fresh()->is_active);
        $this->assertSame('active', $module->fresh()->license_status);
    }

    public function test_revalidate_license_deactivates_a_revoked_module(): void
    {
        config()->set('services.license_server.url', 'https://license.test');
        PlatformSystem::set('allowed_registration_modes', json_encode(['retail', 'widgets']));

        Http::fake([
            'license.test/*' => Http::response(['status' => false, 'message' => 'revoked'], 200),
        ]);

        $this->actingAsSuperAdmin();
        $module = $this->packageRow([
            'is_active' => true,
            'license_status' => 'active',
            'license_key_encrypted' => 'PH-1234-5678-ABCD',
            'license_driver' => 'custom',
        ]);

        Livewire::test(ModulesIndex::class)->call('revalidateLicense', $module->id);

        $module->refresh();
        $this->assertFalse($module->is_active);
        $this->assertSame('revoked', $module->license_status);
        $this->assertNotContains('widgets', json_decode(PlatformSystem::get('allowed_registration_modes'), true));
    }

    public function test_unlicensed_module_exposes_a_vendor_store_link(): void
    {
        config()->set('services.license_server.store_url', 'https://store.example.com/modules');
        $this->packageRow();

        $catalog = ModuleCatalog::for('widgets');

        $this->assertTrue($catalog['buy_enabled'] === false || is_string($catalog['buy_url']));
        $this->assertSame('https://store.example.com/modules?module=widgets', $catalog['buy_url']);
    }
}
