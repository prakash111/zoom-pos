<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Modules\Index;
use App\Models\SduiModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;
use ZipArchive;

class ModulesTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/testmod'));
        Schema::dropIfExists('test_module_widgets');

        parent::tearDown();
    }

    private function buildTestModuleZip(string $key = 'testmod'): UploadedFile
    {
        $sourceDir = storage_path('framework/testing/'.$key.'_src');
        File::deleteDirectory($sourceDir);
        File::makeDirectory($sourceDir.'/Database/Migrations', 0700, true);

        File::put($sourceDir.'/module.json', json_encode([
            'key' => $key,
            'name' => 'Test Module',
            'version' => '1.0.0',
            'author' => 'Test Suite',
            'navigation' => [
                [
                    'key' => $key.'_section',
                    'title' => 'Test Module',
                    'items' => [
                        ['key' => $key.'_widgets', 'title' => 'Widgets', 'target_endpoint' => '/api/tenant/'.$key.'/widgets'],
                    ],
                ],
            ],
        ]));

        File::put(
            $sourceDir.'/Database/Migrations/2026_01_01_000000_create_test_module_widgets_table.php',
            <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration {
                public function up(): void {
                    Schema::create('test_module_widgets', function (Blueprint $table) {
                        $table->id();
                        $table->timestamps();
                    });
                }
                public function down(): void {
                    Schema::dropIfExists('test_module_widgets');
                }
            };
            PHP
        );

        $zipPath = storage_path('framework/testing/'.$key.'.zip');
        @unlink($zipPath);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($sourceDir.'/module.json', 'module.json');
        $zip->addEmptyDir('Database');
        $zip->addEmptyDir('Database/Migrations');
        $zip->addFile(
            $sourceDir.'/Database/Migrations/2026_01_01_000000_create_test_module_widgets_table.php',
            'Database/Migrations/2026_01_01_000000_create_test_module_widgets_table.php'
        );
        $zip->close();

        return UploadedFile::fake()->createWithContent($key.'.zip', file_get_contents($zipPath));
    }

    public function test_uploading_a_valid_zip_installs_an_inactive_module(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->set('zipFile', $this->buildTestModuleZip())
            ->call('install')
            ->assertSet('zipFile', null);

        $module = SduiModule::where('slug', 'testmod')->first();
        $this->assertNotNull($module);
        $this->assertFalse($module->is_active);
        $this->assertSame('package', $module->source_type);
        $this->assertTrue(is_dir(base_path('modules/testmod')));
    }

    public function test_activating_a_module_runs_its_migrations(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)->set('zipFile', $this->buildTestModuleZip())->call('install');
        $module = SduiModule::where('slug', 'testmod')->firstOrFail();

        Livewire::test(Index::class)->call('activate', $module->id);

        $this->assertTrue(Schema::hasTable('test_module_widgets'));
        $this->assertTrue($module->fresh()->is_active);
    }

    public function test_deactivating_a_module_keeps_its_data(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)->set('zipFile', $this->buildTestModuleZip())->call('install');
        $module = SduiModule::where('slug', 'testmod')->firstOrFail();
        Livewire::test(Index::class)->call('activate', $module->id);

        Livewire::test(Index::class)->call('deactivate', $module->id);

        $this->assertFalse($module->fresh()->is_active);
        $this->assertTrue(Schema::hasTable('test_module_widgets'));
    }

    public function test_uninstalling_without_dropping_data_removes_files_but_keeps_the_table(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)->set('zipFile', $this->buildTestModuleZip())->call('install');
        $module = SduiModule::where('slug', 'testmod')->firstOrFail();
        Livewire::test(Index::class)->call('activate', $module->id);

        Livewire::test(Index::class)->call('uninstall', $module->id, false);

        $this->assertNull(SduiModule::where('slug', 'testmod')->first());
        $this->assertFalse(is_dir(base_path('modules/testmod')));
        $this->assertTrue(Schema::hasTable('test_module_widgets'));

        Schema::dropIfExists('test_module_widgets');
    }

    public function test_uninstalling_with_drop_data_rolls_back_migrations(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)->set('zipFile', $this->buildTestModuleZip())->call('install');
        $module = SduiModule::where('slug', 'testmod')->firstOrFail();
        Livewire::test(Index::class)->call('activate', $module->id);

        Livewire::test(Index::class)->call('uninstall', $module->id, true);

        $this->assertFalse(Schema::hasTable('test_module_widgets'));
    }

    public function test_reserved_module_keys_are_rejected(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->set('zipFile', $this->buildTestModuleZip('pharmacy'))
            ->call('install');

        $this->assertNull(SduiModule::where('slug', 'pharmacy')->where('source_type', 'package')->first());
    }
}
