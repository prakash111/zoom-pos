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

    private function fakeModuleZip(string $slug): string
    {
        $path = tempnam(sys_get_temp_dir(), 'modtest_').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode([
            'key' => $slug,
            'name' => ucfirst($slug),
            'version' => '1.0.0',
            'requires_license' => true,
            'navigation' => [[
                'key' => $slug.'_sec',
                'title' => ucfirst($slug),
                'items' => [['key' => $slug.'_x', 'title' => 'X', 'target_endpoint' => '/api/tenant/'.$slug.'/x']],
            ]],
        ]));
        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    public function test_get_module_downloads_from_the_license_server_and_activates(): void
    {
        config()->set('services.license_server.url', 'https://lm.test');
        Http::fake([
            'lm.test/api/download.php' => Http::response($this->fakeModuleZip('widgets'), 200, ['Content-Type' => 'application/zip']),
            'lm.test/api/verify.php' => Http::response(['status' => true, 'expires_at' => null, 'message' => 'ok', 'plan' => null], 200),
        ]);

        $this->actingAsSuperAdmin();

        Livewire::test(ModulesIndex::class)
            ->set('catalogKeys.widgets', 'WGT-1234-5678-ABCD')
            ->call('getModule', 'widgets')
            ->assertHasNoErrors();

        $module = SduiModule::where('slug', 'widgets')->first();
        $this->assertNotNull($module);
        $this->assertSame('package', $module->source_type);
        $this->assertSame('active', $module->license_status);
        $this->assertTrue($module->is_active);
        $this->assertTrue(is_dir(base_path('modules/widgets')));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/download.php') && $r->hasHeader('X-Server-Secret'));
    }

    public function test_get_module_reports_a_declined_download(): void
    {
        config()->set('services.license_server.url', 'https://lm.test');
        Http::fake([
            'lm.test/api/download.php' => Http::response(['status' => false, 'message' => 'License is revoked.'], 403),
        ]);

        $this->actingAsSuperAdmin();

        Livewire::test(ModulesIndex::class)
            ->set('catalogKeys.widgets', 'WGT-1234-5678-ABCD')
            ->call('getModule', 'widgets')
            ->assertHasErrors('catalogKeys.widgets');

        $this->assertNull(SduiModule::where('slug', 'widgets')->first());
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
