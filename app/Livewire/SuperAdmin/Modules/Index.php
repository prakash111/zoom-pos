<?php

namespace App\Livewire\SuperAdmin\Modules;

use App\Models\SduiModule;
use App\Services\License\LicenseService;
use App\Services\Modular\ModuleCatalog;
use App\Services\Modular\ModulePackageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.superadmin', ['title' => 'Modules'])]
class Index extends Component
{
    use WithFileUploads;

    public $zipFile = null;

    /** @var array<int, string>  moduleId => license key typed in the activate row */
    public array $licenseKeys = [];

    public function install(ModulePackageService $service): void
    {
        $this->validate([
            'zipFile' => ['required', 'file', 'extensions:zip', 'max:10240'],
        ]);

        try {
            $module = $service->install($this->zipFile, auth('platform_web')->id());
            session()->flash('status', "Module \"{$module->name}\" installed as inactive. Review it, then Activate when ready.");
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->reset('zipFile');
    }

    public function activate(ModulePackageService $service, int $moduleId): void
    {
        $module = SduiModule::findOrFail($moduleId);

        // Licensed package modules need a valid key before the first activation.
        if ($module->requires_license && $module->license_status !== 'active') {
            $this->validate(
                ["licenseKeys.$moduleId" => ['required', 'string', 'min:8', 'max:191']],
                [],
                ["licenseKeys.$moduleId" => 'license key'],
            );

            $result = $service->verifyAndRecordLicense(
                $module,
                (string) $this->licenseKeys[$moduleId],
                auth('platform_web')->id(),
            );

            if (! $result['status']) {
                $this->addError("licenseKeys.$moduleId", $result['message']);

                return;
            }

            $module->refresh();
            unset($this->licenseKeys[$moduleId]);
        }

        try {
            $service->activate($module, auth('platform_web')->id());
            session()->flash('status', "Module \"{$module->name}\" activated.");
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Re-verify an already-licensed module's stored key on demand. On a
     * definitive failure the module is deactivated immediately.
     */
    public function revalidateLicense(ModulePackageService $service, int $moduleId): void
    {
        $module = SduiModule::findOrFail($moduleId);

        if (! $module->requires_license) {
            session()->flash('status', "Module \"{$module->name}\" does not require a license.");

            return;
        }

        $key = (string) $module->license_key_encrypted;
        if ($key === '') {
            $service->clearLicense($module, 'unlicensed', 'No stored key to re-verify.');
            if ($module->is_active) {
                $service->deactivate($module, auth('platform_web')->id());
            }
            session()->flash('error', "Module \"{$module->name}\" has no stored license key — deactivated.");

            return;
        }

        $result = app(LicenseService::class)->verify($key, $module->slug, LicenseService::currentDomain());

        if ($result['status']) {
            $module->forceFill([
                'license_status' => 'active',
                'license_driver' => $result['driver'],
                'license_verified_at' => now(),
                'license_expires_at' => $result['expires_at']
                    ? Carbon::parse($result['expires_at'])
                    : null,
            ])->save();
            session()->flash('status', "License for \"{$module->name}\" re-verified.");

            return;
        }

        $service->clearLicense($module, 'revoked', $result['message']);
        if ($module->is_active) {
            $service->deactivate($module, auth('platform_web')->id());
        }
        session()->flash('error', "License for \"{$module->name}\" is no longer valid — deactivated. ({$result['message']})");
    }

    public function deactivate(ModulePackageService $service, int $moduleId): void
    {
        $module = SduiModule::findOrFail($moduleId);

        try {
            $service->deactivate($module, auth('platform_web')->id());
            session()->flash('status', "Module \"{$module->name}\" deactivated.");
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function uninstall(ModulePackageService $service, int $moduleId, bool $dropData = false): void
    {
        $module = SduiModule::findOrFail($moduleId);
        $name = $module->name;

        try {
            $service->uninstall($module, $dropData, auth('platform_web')->id());
            session()->flash('status', "Module \"{$name}\" uninstalled".($dropData ? ' and its data tables were dropped.' : ', its data was kept.'));
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function pruneOrphan(ModulePackageService $service, string $slug): void
    {
        try {
            $removed = $service->pruneOrphanDir($slug);
            session()->flash(
                $removed ? 'status' : 'error',
                $removed
                    ? "Leftover files for \"{$slug}\" were deleted from disk."
                    : "Could not remove the \"{$slug}\" directory — check filesystem permissions."
            );
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $licenses = app(LicenseService::class);
        $modules = $this->packageModules();

        return view('livewire.superadmin.modules.index', [
            'modules' => $modules,
            'orphans' => app(ModulePackageService::class)->orphanedModuleDirs(),
            'licenseDriver' => $licenses->getActiveDriver(),
            'offlineFallback' => $licenses->isOfflineFallback(),
            'catalog' => $modules->mapWithKeys(fn ($m) => [
                $m->id => ModuleCatalog::for($m->slug),
            ])->all(),
        ]);
    }

    private function packageModules(): Collection
    {
        return SduiModule::where('source_type', 'package')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
