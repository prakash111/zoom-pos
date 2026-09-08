<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Modules\Index as ModulesIndex;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\Modular\ModuleCatalog;
use App\Services\Modular\ModulePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ModuleLicensingTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['pharmacy', 'salon', 'repairtechnician', 'widgets'] as $slug) {
            File::deleteDirectory(base_path('modules/'.$slug));
        }

        parent::tearDown();
    }

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

    public function test_available_catalog_lists_the_bundled_verticals(): void
    {
        $slugs = collect(ModuleCatalog::available())->pluck('slug')->all();

        $this->assertContains('pharmacy', $slugs);
        $this->assertContains('salon', $slugs);
        $this->assertContains('repairtechnician', $slugs);
        $this->assertNotContains('core', $slugs);
    }

    public function test_install_bundled_registers_a_module_row_without_an_upload(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ModulesIndex::class)
            ->call('installBundled', 'pharmacy')
            ->assertHasNoErrors();

        $module = SduiModule::where('slug', 'pharmacy')->first();
        $this->assertNotNull($module);
        $this->assertSame('package', $module->source_type);
        $this->assertFalse($module->is_active);
        $this->assertTrue($module->requires_license);
        $this->assertTrue(is_dir(base_path('modules/pharmacy')));

        // cleanup
        app(ModulePackageService::class)->uninstall($module, true, null);
    }

    public function test_unlicensed_module_exposes_a_vendor_store_link(): void
    {
        config()->set('services.license_server.store_url', 'https://store.example.com/buy.php');
        config()->set('app.url', 'https://acme.example.com');
        $this->packageRow();

        $link = ModuleCatalog::storeLink('widgets');

        $this->assertStringStartsWith('https://store.example.com/buy.php?', $link);
        $this->assertStringContainsString('product=widgets', $link);
        $this->assertStringContainsString('domain=acme.example.com', $link);
    }
}
