<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Generic platform-wide export/snapshot tool. This replaces the legacy
 * "Migration Wizard" (which existed specifically to move a tenant between
 * BYODB database backends — moot now that BYODB is dropped) with a much
 * simpler capability: dump every table to JSON, zip it, store it privately.
 * Not a point-in-time restore system (no BYODB-style restore flow exists
 * here) — this is a downloadable export for the operator's own safekeeping.
 */
class BackupService
{
    protected string $disk = 'local';

    protected string $directory = 'backups';

    public function create(): string
    {
        $timestamp = now()->format('Ymd_His');
        $filename = "backup_{$timestamp}.zip";
        $tmpDir = storage_path('app/tmp_backup_'.Str::random(8));
        mkdir($tmpDir, 0700, true);

        try {
            foreach ($this->tables() as $table) {
                $rows = DB::table($table)->get();
                file_put_contents(
                    "{$tmpDir}/{$table}.json",
                    json_encode($rows, JSON_PRETTY_PRINT)
                );
            }

            $zipPath = "{$tmpDir}/{$filename}";
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE);
            foreach (glob("{$tmpDir}/*.json") as $jsonFile) {
                $zip->addFile($jsonFile, basename($jsonFile));
            }
            $zip->close();

            Storage::disk($this->disk)->makeDirectory($this->directory);
            Storage::disk($this->disk)->put(
                "{$this->directory}/{$filename}",
                file_get_contents($zipPath)
            );
        } finally {
            foreach (glob("{$tmpDir}/*") as $file) {
                @unlink($file);
            }
            @rmdir($tmpDir);
        }

        return $filename;
    }

    /** @return array{name: string, size: int, created_at: Carbon}[] */
    public function list(): array
    {
        $disk = Storage::disk($this->disk);
        if (! $disk->exists($this->directory)) {
            return [];
        }

        $files = collect($disk->files($this->directory))
            ->filter(fn ($path) => str_ends_with($path, '.zip'))
            ->map(fn ($path) => [
                'name' => basename($path),
                'path' => $path,
                'size' => $disk->size($path),
                'created_at' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return $files->all();
    }

    public function delete(string $filename): void
    {
        Storage::disk($this->disk)->delete("{$this->directory}/{$filename}");
    }

    public function applyRetention(int $keepDays, bool $dryRun = true): array
    {
        $cutoff = now()->subDays($keepDays);
        $all = collect($this->list());
        $toDelete = $all->filter(fn ($f) => $f['created_at']->lt($cutoff));

        // Never delete below 1 remaining backup.
        if ($all->count() - $toDelete->count() < 1) {
            $toDelete = $toDelete->slice(0, max(0, $all->count() - 1));
        }

        if (! $dryRun) {
            foreach ($toDelete as $f) {
                $this->delete($f['name']);
            }
        }

        return $toDelete->pluck('name')->all();
    }

    protected function tables(): array
    {
        return collect(DB::select(
            DB::getDriverName() === 'sqlite'
                ? "select name from sqlite_master where type='table' and name not like 'sqlite_%'"
                : 'show tables'
        ))->map(fn ($row) => array_values((array) $row)[0])->all();
    }
}
