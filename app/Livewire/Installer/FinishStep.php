<?php

namespace App\Livewire\Installer;

use App\Models\PlatformSystem;
use App\Support\Installation;
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
        $this->done = Installation::isInstalled();
        $this->licenseData = session('installer_license_data', [
            'license_type' => 'Standard Commercial License',
            'purchase_code' => 'STANDARD-CODECANYON-LICENSE',
            'buyer' => 'Platform Owner',
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    public function finish(): void
    {
        try {
            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Installer key:generate failed: '.$e->getMessage());
        }

        try {
            $publicStorage = public_path('storage');
            $appPublic = storage_path('app/public');
            if (! is_dir($appPublic)) {
                mkdir($appPublic, 0755, true);
            }
            if (is_dir($publicStorage) && ! is_link($publicStorage)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($publicStorage, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $target = $appPublic.DIRECTORY_SEPARATOR.$iterator->getSubPathname();
                    if ($item->isDir()) {
                        if (! is_dir($target)) {
                            mkdir($target, 0755, true);
                        }
                    } else {
                        if (! file_exists($target)) {
                            copy($item->getRealPath(), $target);
                        }
                    }
                }
                \Illuminate\Support\Facades\File::deleteDirectory($publicStorage);
            }
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

        Installation::markAsInstalled($license);

        try {
            PlatformSystem::set('core_license_status', 'ok');
            PlatformSystem::set('core_license_last_checked', now()->toIso8601String());
            PlatformSystem::set('core_license_message', 'Activated during installation.');
            PlatformSystem::set('core_license_expires_at', (string) ($license['expires_at'] ?? ''));
        } catch (\Throwable $e) {
            Log::info('Installer core_license_status notice: '.$e->getMessage());
        }

        $this->done = true;
    }

    public function render()
    {
        return view('livewire.installer.finish-step');
    }
}
