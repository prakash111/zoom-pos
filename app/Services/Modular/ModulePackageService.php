<?php

namespace App\Services\Modular;

use App\Models\AuditLog;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Lifecycle manager for ZIP-packaged business modules (Perfex-style plugin
 * engine). Validates and extracts an uploaded archive, tracks it as a
 * `sdui_modules` row, and runs/rolls back its migrations on
 * activate/uninstall. Navigation and route wiring for an active module is
 * handled elsewhere (ModuleRegistry / TenantNavRegistry already surface
 * database-backed modules automatically; ModuleServiceProvider loads a
 * module's routes.php once its row is active).
 *
 * Uploading a module here is equivalent to installing a WordPress/Perfex
 * plugin: the zip's PHP (migrations, controllers, routes) executes
 * server-side. This must remain reachable only via the platform_web guard.
 */
class ModulePackageService
{
    private const MAX_ZIP_BYTES = 10 * 1024 * 1024;

    private const RESERVED_KEYS = ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician'];

    public function install(UploadedFile $zip, int|string|null $adminUserId): SduiModule
    {
        $this->validateZip($zip);

        $tmpDir = storage_path('app/tmp_module_'.Str::random(12));
        File::makeDirectory($tmpDir, 0700, true);

        try {
            $this->extractSafely($zip->getRealPath(), $tmpDir);
            $manifestDir = $this->locateManifestDir($tmpDir);
            $manifest = $this->readManifest($manifestDir);

            $existing = SduiModule::where('slug', $manifest['key'])->first();
            $this->validateManifest($manifest, $existing);

            $modulesRoot = base_path('modules');
            File::ensureDirectoryExists($modulesRoot);
            $destination = $modulesRoot.'/'.$manifest['key'];

            if (File::isDirectory($destination)) {
                File::deleteDirectory($destination);
            }

            if (! @rename($manifestDir, $destination)) {
                throw new RuntimeException('Could not move extracted module into place.');
            }

            $inheritsUi = $manifest['inherits_ui'] ?? null;
            $features = $manifest['features'] ?? [];
            if ($inheritsUi !== null) {
                $features['inherits_ui'] = $inheritsUi;
            }

            $module = SduiModule::updateOrCreate(
                ['slug' => $manifest['key']],
                [
                    'name' => $manifest['name'],
                    'description' => $manifest['description'] ?? null,
                    'icon' => $manifest['icon'] ?? 'widgets',
                    'layout_type' => $manifest['layout_type'] ?? ($inheritsUi === 'universal_pos' ? 'universal_pos' : 'standard_grid'),
                    'features' => $features,
                    'navigation' => $manifest['navigation'] ?? [],
                    'version' => $manifest['version'],
                    'author' => $manifest['author'] ?? null,
                    'min_system_version' => $manifest['min_system_version'] ?? null,
                    'source_type' => 'package',
                    'package_path' => $manifest['key'],
                    'installed_at' => now(),
                    'is_active' => false,
                ]
            );

            AuditLog::record('module.installed', null, $adminUserId !== null ? (string) $adminUserId : null, [
                'key' => $manifest['key'],
                'version' => $manifest['version'],
            ]);

            return $module;
        } finally {
            if (File::isDirectory($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    public function activate(SduiModule $module, int|string|null $adminUserId): void
    {
        $this->assertPackageModule($module);

        $migrationsPath = 'modules/'.$module->package_path.'/Database/Migrations';
        if (File::isDirectory(base_path($migrationsPath))) {
            try {
                Artisan::call('migrate', [
                    '--path' => $migrationsPath,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                throw new RuntimeException("Migration failed for module '{$module->slug}': ".$e->getMessage(), 0, $e);
            }
        }

        $module->update(['is_active' => true]);

        AuditLog::record('module.activated', null, $adminUserId !== null ? (string) $adminUserId : null, [
            'key' => $module->slug,
        ]);

        // A cached route/config/view table baked before this activation does
        // not know about the module's routes.php — flush so it resolves now.
        $this->flushPlatformCaches();
    }

    public function deactivate(SduiModule $module, int|string|null $adminUserId): void
    {
        $this->assertPackageModule($module);

        $module->update(['is_active' => false]);

        AuditLog::record('module.deactivated', null, $adminUserId !== null ? (string) $adminUserId : null, [
            'key' => $module->slug,
        ]);

        // Drop the module's routes/nav/settings from every cached layer
        // immediately, not just from fresh (uncached) requests.
        $this->flushPlatformCaches();
    }

    public function uninstall(SduiModule $module, bool $dropData, int|string|null $adminUserId): void
    {
        $this->assertPackageModule($module);

        $migrationsPath = 'modules/'.$module->package_path.'/Database/Migrations';
        $migrationNames = $this->migrationNamesIn(base_path($migrationsPath));

        if ($dropData && $module->installed_at !== null && File::isDirectory(base_path($migrationsPath))) {
            try {
                Artisan::call('migrate:rollback', [
                    '--path' => $migrationsPath,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                // Best-effort: still proceed with removing files/row even if rollback fails
                // (e.g. module was never activated so nothing was ever migrated).
            }

            // migrate:rollback already deletes the tracking rows on a clean
            // down(); this sweeps up anything a partial/failed down() left
            // behind, scoped to this module's exact migration filenames so a
            // re-install never hits "table already exists" / silently skips.
            $this->purgeMigrationRecords($migrationNames);
        }

        $moduleDir = base_path('modules/'.$module->package_path);
        if (File::isDirectory($moduleDir)) {
            File::deleteDirectory($moduleDir);
        }

        $slug = $module->slug;
        $module->delete();

        // Drop a dangling reference to this module from the platform-wide
        // "which store types can register" list so it can't reappear as a
        // selectable option or a governance toggle.
        $this->purgeFromRegistrationModes($slug);

        AuditLog::record('module.uninstalled', null, $adminUserId !== null ? (string) $adminUserId : null, [
            'key' => $slug,
            'drop_data' => $dropData,
        ]);

        // route:clear + config:clear + view:clear + cache:clear + event:clear
        // + clear-compiled — otherwise cached routes keep pointing at the
        // controller files we just deleted (500s) and the SuperAdmin panel
        // keeps rendering the module until the next deploy.
        $this->flushPlatformCaches();
    }

    private function assertPackageModule(SduiModule $module): void
    {
        if ($module->source_type !== 'package' || empty($module->package_path)) {
            throw new InvalidArgumentException('This module is not a package-installed module.');
        }
    }

    /**
     * Best-effort `php artisan optimize:clear`. A wedged cache driver must not
     * abort an activate / deactivate / uninstall that has otherwise succeeded.
     */
    private function flushPlatformCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ModulePackageService: optimize:clear failed after a module lifecycle change.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Migration class-names (filename without .php) in a module's migrations
     * directory — read while the files still exist, used afterwards to sweep
     * the `migrations` table.
     *
     * @return list<string>
     */
    private function migrationNamesIn(string $absPath): array
    {
        if (! File::isDirectory($absPath)) {
            return [];
        }

        return collect(File::files($absPath))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'php')
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $migrationNames
     */
    private function purgeMigrationRecords(array $migrationNames): void
    {
        if ($migrationNames === []) {
            return;
        }

        try {
            if (Schema::hasTable('migrations')) {
                DB::table('migrations')->whereIn('migration', $migrationNames)->delete();
            }
        } catch (Throwable $e) {
            // Non-fatal.
        }
    }

    private function purgeFromRegistrationModes(string $slug): void
    {
        try {
            $raw = PlatformSystem::get('allowed_registration_modes');
            $modes = is_string($raw) ? json_decode($raw, true) : $raw;
            if (! is_array($modes) || ! in_array($slug, $modes, true)) {
                return;
            }
            PlatformSystem::set(
                'allowed_registration_modes',
                json_encode(array_values(array_diff($modes, [$slug])))
            );
        } catch (Throwable $e) {
            // Non-fatal — the module is still gone from sdui_modules, which is
            // what every read path actually filters on.
        }
    }

    private function validateZip(UploadedFile $zip): void
    {
        if ($zip->getSize() > self::MAX_ZIP_BYTES) {
            throw new InvalidArgumentException('Module package exceeds the 10MB size limit.');
        }

        $extension = strtolower((string) $zip->getClientOriginalExtension());
        if ($extension !== 'zip') {
            throw new InvalidArgumentException('Module package must be a .zip file.');
        }

        $mime = @mime_content_type($zip->getRealPath());
        if (! in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            throw new InvalidArgumentException('Uploaded file does not appear to be a valid zip archive.');
        }
    }

    /**
     * Extracts $zipPath into $destDir, rejecting any entry that would
     * traverse outside $destDir (zip-slip) or that is a symlink.
     */
    private function extractSafely(string $zipPath, string $destDir): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Could not open the uploaded archive.');
        }

        $realDest = realpath($destDir);
        if ($realDest === false) {
            throw new RuntimeException('Extraction directory does not exist.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }

                if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('#^[A-Za-z]:#', $name)) {
                    throw new InvalidArgumentException("Unsafe path in archive: {$name}");
                }

                $stat = $zip->statIndex($i);
                // External attributes' high 16 bits hold unix mode; S_IFLNK = 0xA000.
                $externalAttr = is_array($stat) ? (int) ($stat['external_attr'] ?? 0) : 0;
                if ((($externalAttr >> 16) & 0xF000) === 0xA000) {
                    throw new InvalidArgumentException("Symlink entries are not allowed in module archives: {$name}");
                }

                $targetPath = $realDest.DIRECTORY_SEPARATOR.$name;
                $targetDir = str_ends_with($name, '/') ? $targetPath : dirname($targetPath);
                if (! str_starts_with(str_replace('\\', '/', $targetDir).'/', str_replace('\\', '/', $realDest).'/')) {
                    throw new InvalidArgumentException("Archive entry escapes the extraction directory: {$name}");
                }
            }

            if (! $zip->extractTo($destDir)) {
                throw new RuntimeException('Failed to extract module archive.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Locates the extracted module root: either $tmpDir itself (module.json
     * at the archive root) or exactly one subdirectory containing
     * module.json (archive wraps everything in a single folder).
     */
    private function locateManifestDir(string $tmpDir): string
    {
        if (File::exists($tmpDir.'/module.json')) {
            return $tmpDir;
        }

        $candidates = collect(File::directories($tmpDir))
            ->filter(fn (string $dir) => File::exists($dir.'/module.json'))
            ->values();

        if ($candidates->count() !== 1) {
            throw new InvalidArgumentException('Archive must contain exactly one module.json, either at its root or one level inside a single folder.');
        }

        return $candidates->first();
    }

    /** @return array<string, mixed> */
    private function readManifest(string $manifestDir): array
    {
        $raw = File::get($manifestDir.'/module.json');
        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            throw new InvalidArgumentException('module.json is not valid JSON.');
        }

        return $manifest;
    }

    /**
     * Validates required fields, normalizes the key, checks for reserved
     * key collisions, and validates the navigation shape against the same
     * rules SduiModule::booted() enforces on save (so a bad manifest fails
     * here with a clear message instead of an uncaught exception later).
     *
     * @param  array<string, mixed>  $manifest
     */
    private function validateManifest(array &$manifest, ?SduiModule $existing): void
    {
        foreach (['key', 'name', 'version'] as $field) {
            if (trim((string) ($manifest[$field] ?? '')) === '') {
                throw new InvalidArgumentException("module.json is missing required field: {$field}");
            }
        }

        $key = $this->normalizeKey((string) $manifest['key']);
        if ($key === '') {
            throw new InvalidArgumentException('module.json "key" must resolve to a non-empty slug.');
        }

        $inheritsUi = $manifest['inherits_ui'] ?? null;

        if (in_array($key, self::RESERVED_KEYS, true) && ($existing === null || $existing->slug !== $key)) {
            if ($inheritsUi !== 'universal_pos') {
                throw new InvalidArgumentException("Module key \"{$key}\" is reserved for a built-in module.");
            }
        }

        $manifest['key'] = $key;

        $navigation = $manifest['navigation'] ?? [];
        if (! is_array($navigation)) {
            throw new InvalidArgumentException('module.json "navigation" must be an array of sections.');
        }

        foreach ($navigation as $sectionIndex => &$section) {
            if (is_array($section) && ! isset($section['key']) && isset($section['id'])) {
                $section['key'] = (string) $section['id'];
            }
            if (! is_array($section) || trim((string) ($section['key'] ?? '')) === '' || ! is_array($section['items'] ?? null)) {
                throw new InvalidArgumentException("Invalid navigation section at index {$sectionIndex}: each section needs a non-empty \"key\" and an \"items\" array.");
            }

            foreach ($section['items'] as $itemIndex => &$item) {
                if (is_array($item)) {
                    if (! isset($item['key'])) {
                        if (isset($item['id'])) {
                            $item['key'] = (string) $item['id'];
                        } elseif (! empty($item['title'])) {
                            $item['key'] = Str::slug($item['title'], '_');
                        } elseif (! empty($item['route'])) {
                            $item['key'] = Str::slug(basename($item['route']), '_');
                        }
                    }
                    if (! isset($item['target_endpoint']) && isset($item['route'])) {
                        $item['target_endpoint'] = $item['route'];
                    }
                }

                if (! is_array($item) || trim((string) ($item['key'] ?? '')) === '') {
                    throw new InvalidArgumentException("Invalid navigation item at section {$sectionIndex}, item {$itemIndex}: missing \"key\".");
                }

                $endpoint = trim((string) ($item['target_endpoint'] ?? ''));
                if (empty($item['children']) && ! str_starts_with($endpoint, '/api/')) {
                    throw new InvalidArgumentException("Navigation item \"{$item['key']}\" needs a \"target_endpoint\" starting with /api/.");
                }
            }
            unset($item);
        }
        unset($section);

        $manifest['navigation'] = $navigation;
    }

    /**
     * Normalizes a module key into a plain lowercase alphanumeric token
     * (separators stripped entirely, not replaced). This value is used as
     * the `sdui_modules.slug` column, the `modules/{key}` directory name,
     * AND the `Modules\{key}\...` PHP namespace segment (see
     * ModuleServiceProvider) — all three must stay identical.
     *
     * A separator-free result is required for two independent reasons:
     * PHP namespace segments cannot contain hyphens, and
     * SduiModule::booted() re-runs Str::slug() on whatever slug is saved
     * (turning underscores into hyphens) — since Str::slug() is a no-op on
     * a string that already has no separator characters, stripping them
     * here guarantees the DB-assigned slug matches the directory/namespace
     * we already created on disk before the row was saved.
     */
    private function normalizeKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        if ($normalized !== '' && ctype_digit($normalized[0])) {
            throw new InvalidArgumentException('module.json "key" must start with a letter.');
        }

        return $normalized;
    }
}
