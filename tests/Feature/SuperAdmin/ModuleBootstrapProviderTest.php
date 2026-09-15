<?php

namespace Tests\Feature\SuperAdmin;

use App\Providers\ModuleServiceProvider;
use App\Services\Modular\ModulePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;
use ZipArchive;

/**
 * A packaged module must be able to inject its own ServiceProvider, split
 * route files and a view namespace purely from modules/<key>/ — no core file
 * is touched. ModuleServiceProvider::bootModule() is the injection point.
 */
class ModuleBootstrapProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/bootmod'));
        parent::tearDown();
    }

    private function buildZip(): UploadedFile
    {
        $src = storage_path('framework/testing/bootmod_src');
        File::deleteDirectory($src);
        File::makeDirectory($src.'/Providers', 0700, true);
        File::makeDirectory($src.'/routes', 0700, true);
        File::makeDirectory($src.'/Resources/views', 0700, true);

        File::put($src.'/module.json', json_encode([
            'key' => 'bootmod',
            'name' => 'Boot Module',
            'version' => '1.0.0',
            'requires_license' => false,
            'navigation' => [[
                'key' => 'bootmod_section',
                'title' => 'Boot Module',
                'items' => [['key' => 'bootmod_ping', 'title' => 'Ping', 'target_endpoint' => '/api/module-bootmod/ping']],
            ]],
        ]));

        File::put($src.'/Providers/ModuleProvider.php', <<<'PHP'
        <?php
        namespace Modules\bootmod\Providers;
        use Illuminate\Support\ServiceProvider;
        class ModuleProvider extends ServiceProvider {
            public function register(): void { $this->app->singleton('bootmod.marker', fn () => 'wired'); }
        }
        PHP);

        File::put($src.'/routes/api.php', <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('api/module-bootmod/ping', fn () => response()->json([
            'pong' => app()->bound('bootmod.marker') ? app('bootmod.marker') : 'unbound',
            'view' => \Illuminate\Support\Facades\View::exists('module-bootmod::hello'),
        ]));
        PHP);

        File::put($src.'/Resources/views/hello.blade.php', 'MODULE BOOTMOD VIEW');

        $zipPath = storage_path('framework/testing/bootmod.zip');
        @unlink($zipPath);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (File::allFiles($src, true) as $f) {
            $zip->addFile($f->getPathname(), $f->getRelativePathname());
        }
        $zip->close();

        return UploadedFile::fake()->createWithContent('bootmod.zip', file_get_contents($zipPath));
    }

    public function test_active_module_provider_routes_and_views_are_injected(): void
    {
        $service = app(ModulePackageService::class);
        $module = $service->install($this->buildZip(), null);
        $service->activate($module, null);

        // Simulate the next request's boot now that the module is active.
        (new ModuleServiceProvider($this->app))->bootModule('bootmod');

        // 1. The module's own ServiceProvider registered its binding.
        $this->assertTrue($this->app->bound('bootmod.marker'));
        $this->assertSame('wired', $this->app->make('bootmod.marker'));

        // 2. Its view namespace resolves.
        $this->assertTrue(View::exists('module-bootmod::hello'));
        $this->assertSame('MODULE BOOTMOD VIEW', trim(view('module-bootmod::hello')->render()));

        // 3. Its split route file is live and sees both of the above.
        $this->getJson('/api/module-bootmod/ping')
            ->assertOk()
            ->assertJson(['pong' => 'wired', 'view' => true]);
    }

    public function test_inactive_module_is_not_booted(): void
    {
        $service = app(ModulePackageService::class);
        $service->install($this->buildZip(), null); // installed, never activated

        // The real provider already booted at app start; a fresh boot() must
        // skip an inactive module.
        (new ModuleServiceProvider($this->app))->boot();

        $this->assertFalse($this->app->bound('bootmod.marker'));
        $this->getJson('/api/module-bootmod/ping')->assertNotFound();
    }
}
