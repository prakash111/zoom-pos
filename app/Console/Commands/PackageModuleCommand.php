<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class PackageModuleCommand extends Command
{
    protected $signature = 'module:package {name=Hrm : Name of the module}';
    protected $description = 'Package a module into a zip archive and deploy it to /public/downloads/modules for distribution';

    public function handle(): int
    {
        $moduleName = ucfirst($this->argument('name'));
        $modulePath = base_path("modules/{$moduleName}");

        if (!File::isDirectory($modulePath)) {
            $this->error("Target module '{$moduleName}' not found at path: {$modulePath}");
            return Command::FAILURE;
        }

        $manifestFile = "{$modulePath}/module.json";
        $version = '1.0.0';
        if (File::exists($manifestFile)) {
            $manifest = json_decode(File::get($manifestFile), true);
            $version = $manifest['version'] ?? '1.0.0';
        }

        // Public output destination
        $publicDir = public_path('downloads/modules');
        if (!File::isDirectory($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        $slug = strtolower($moduleName);
        $versionedZip = "{$publicDir}/module-{$slug}-v{$version}.zip";
        $latestZip    = "{$publicDir}/module-{$slug}-latest.zip";

        $this->info("Starting packaging for '{$moduleName}' (v{$version})...");

        $zip = new ZipArchive();
        if ($zip->open($versionedZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to initialize zip file at: {$versionedZip}");
            return Command::FAILURE;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                // Store files under folder structure: Hrm/...
                $relativePath = $moduleName . '/' . substr($filePath, strlen($modulePath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        // Copy as latest release link
        File::copy($versionedZip, $latestZip);

        $this->newLine();
        $this->info("==================================================================");
        $this->info(" Module [{$moduleName}] successfully packaged and deployed to /public!");
        $this->info("==================================================================");
        $this->line("Versioned Download: " . url("downloads/modules/module-{$slug}-v{$version}.zip"));
        $this->line("Latest Download:    " . url("downloads/modules/module-{$slug}-latest.zip"));
        $this->newLine();

        return Command::SUCCESS;
    }
}
