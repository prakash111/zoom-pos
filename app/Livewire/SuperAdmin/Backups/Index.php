<?php

namespace App\Livewire\SuperAdmin\Backups;

use App\Models\AuditLog;
use App\Models\PlatformSystem;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Backups'])]
class Index extends Component
{
    public string $frequency = 'manual';

    public int $retentionDays = 30;

    public function mount(): void
    {
        $this->frequency = (string) PlatformSystem::get('backup_frequency', 'manual');
        $this->retentionDays = (int) PlatformSystem::get('backup_retention_days', 30);
    }

    public function savePolicy(): void
    {
        $this->validate([
            'frequency' => ['required', 'in:manual,daily,weekly,monthly'],
            'retentionDays' => ['required', 'integer', 'min:1'],
        ]);

        PlatformSystem::set('backup_frequency', $this->frequency);
        PlatformSystem::set('backup_retention_days', (string) $this->retentionDays);

        AuditLog::record('backup_policy.updated', null, auth('platform_web')->id(), [
            'frequency' => $this->frequency, 'retention_days' => $this->retentionDays,
        ]);
        session()->flash('status', 'Backup policy saved.');
    }

    public function createSnapshot(BackupService $backups): void
    {
        $filename = $backups->create();
        AuditLog::record('backup.created', null, auth('platform_web')->id(), ['file' => $filename]);
        session()->flash('status', "Snapshot \"{$filename}\" created.");
    }

    public function deleteSnapshot(BackupService $backups, string $filename): void
    {
        $backups->delete($filename);
        AuditLog::record('backup.deleted', null, auth('platform_web')->id(), ['file' => $filename]);
        session()->flash('status', 'Snapshot deleted.');
    }

    public function download(BackupService $backups, string $filename)
    {
        return response()->streamDownload(
            fn () => print (Storage::disk('local')->get("backups/{$filename}")),
            $filename
        );
    }

    public function render(BackupService $backups)
    {
        return view('livewire.superadmin.backups.index', [
            'snapshots' => $backups->list(),
        ]);
    }
}
