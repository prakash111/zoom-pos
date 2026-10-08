<?php

namespace App\Services\Module;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ModuleInstallService
{
    public function installFromUrl(string $url): array
    {
        $tempZip = storage_path('app/temp_module_' . time() . '.zip');

        $content = @file_get_contents($url);
        if ($content === false) {
            return ['success' => false, 'message' => 'Unable to download module package from source URL.'];
        }

        File::put($tempZip, $content);

        $zip = new ZipArchive();
        if ($zip->open($tempZip) === true) {
            $modulesDir = base_path('modules');
            if (!File::isDirectory($modulesDir)) {
                File::makeDirectory($modulesDir, 0755, true);
            }

            $zip->extractTo($modulesDir);
            $zip->close();
            File::delete($tempZip);

            // Run database migrations and seed permissions automatically
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Modules\\Hrm\\Database\\Seeders\\HrmPermissionSeeder', '--force' => true]);
            Artisan::call('optimize:clear');

            return ['success' => true, 'message' => 'Module installed and permissions seeded successfully.'];
        }

        File::delete($tempZip);
        return ['success' => false, 'message' => 'Failed to extract the zip archive.'];
    }
}
