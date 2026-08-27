<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Backups\Index;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class BackupsTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_creating_a_snapshot_produces_a_downloadable_zip(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)->call('createSnapshot');

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);
    }

    public function test_retention_policy_never_deletes_the_last_remaining_backup(): void
    {
        $service = app(BackupService::class);
        $service->create();

        $deleted = $service->applyRetention(0, dryRun: false);

        $this->assertCount(0, $deleted);
        $this->assertCount(1, $service->list());
    }

    public function test_deleting_a_snapshot_removes_it(): void
    {
        $this->actingAsSuperAdmin();
        $service = app(BackupService::class);
        $service->create();
        $filename = $service->list()[0]['name'];

        Livewire::test(Index::class)->call('deleteSnapshot', $filename);

        $this->assertCount(0, $service->list());
    }
}
