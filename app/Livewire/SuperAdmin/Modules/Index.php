<?php

namespace App\Livewire\SuperAdmin\Modules;

use App\Models\SduiModule;
use App\Services\Modular\ModulePackageService;
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

        try {
            $service->activate($module, auth('platform_web')->id());
            session()->flash('status', "Module \"{$module->name}\" activated.");
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
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

    public function render()
    {
        return view('livewire.superadmin.modules.index', [
            'modules' => $this->packageModules(),
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
