<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Modules\Index as ModulesIndex;
use App\Models\SduiModule;
use App\Services\Modular\ModulePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

/**
 * A modules/<key>/ directory with no sdui_modules row (left by a pre-fix
 * uninstall or a failed install) can be swept from the Super Admin panel.
 */
class OrphanModuleCleanupTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/ghostmod'));
        File::deleteDirectory(base_path('modules/realmod'));
        parent::tearDown();
    }

    private function makeDir(string $slug): void
    {
        $dir = base_path('modules/'.$slug);
        File::ensureDirectoryExists($dir.'/Http');
        File::put($dir.'/module.json', json_encode(['key' => $slug, 'name' => ucfirst($slug), 'version' => '1.0.0']));
        File::put($dir.'/Http/Thing.php', '<?php');
    }

    public function test_orphan_is_listed_and_can_be_deleted_from_the_panel(): void
    {
        $this->actingAsSuperAdmin();
        $this->makeDir('ghostmod');

        Livewire::test(ModulesIndex::class)
            ->assertViewHas('orphans', fn ($o) => array_key_exists('ghostmod', $o))
            ->call('pruneOrphan', 'ghostmod')
            ->assertHasNoErrors();

        $this->assertFalse(is_dir(base_path('modules/ghostmod')));
    }

    public function test_a_registered_module_directory_is_never_pruned_as_an_orphan(): void
    {
        $this->makeDir('realmod');
        SduiModule::create([
            'name' => 'Real', 'slug' => 'realmod', 'source_type' => 'package',
            'package_path' => 'realmod', 'is_active' => true, 'version' => '1.0.0',
        ]);

        $service = app(ModulePackageService::class);

        $this->assertArrayNotHasKey('realmod', $service->orphanedModuleDirs());

        $this->expectException(\InvalidArgumentException::class);
        $service->pruneOrphanDir('realmod');
    }

    public function test_prune_rejects_path_traversal(): void
    {
        $service = app(ModulePackageService::class);
        $this->expectException(\InvalidArgumentException::class);
        $service->pruneOrphanDir('../config');
    }
}
