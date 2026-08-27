<?php

namespace App\Livewire\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.installer', ['step' => 5])]
class FinishStep extends Component
{
    public bool $done = false;

    public array $licenseData = [];

    public function mount(): void
    {
        $this->done = file_exists(storage_path('installed'));
        $this->licenseData = session('installer_license_data', [
            'license_type' => 'Standard Commercial License',
            'purchase_code' => 'STANDARD-CODECANYON-LICENSE',
            'buyer' => 'Platform Owner',
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    public function finish(): void
    {
        if (empty(config('app.key'))) {
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (\Throwable $e) {
                Log::warning('Installer key:generate failed: '.$e->getMessage());
            }
        }

        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            Log::info('Installer storage:link notice: '.$e->getMessage());
        }

        $license = session('installer_license_data', [
            'license_type' => 'Standard Commercial License',
            'purchase_code' => 'STANDARD-CODECANYON-LICENSE',
            'buyer' => 'Platform Owner',
            'verified_at' => now()->toIso8601String(),
        ]);

        file_put_contents(storage_path('installed'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'installer_version' => '2.0',
            'app_version' => config('app.version', '1.0.0'),
            'license' => $license,
        ], JSON_PRETTY_PRINT));

        $this->done = true;
    }

    public function render()
    {
        return view('livewire.installer.finish-step');
    }
}
