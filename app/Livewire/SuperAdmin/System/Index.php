<?php

namespace App\Livewire\SuperAdmin\System;

use App\Models\AuditLog;
use App\Models\PlatformSystem;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Maintenance & System'])]
class Index extends Component
{
    public bool $maintenanceMode = false;

    public string $maintenanceMessage = '';

    public string $minClientBuildVersion = '0';

    public string $appVersion = '1.0.0';

    public function mount(): void
    {
        abort_unless(auth('platform_web')->user()->hasRole('super_admin'), 403);

        $this->maintenanceMode = filter_var(PlatformSystem::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
        $this->maintenanceMessage = (string) PlatformSystem::get('maintenance_message', '');
        $this->minClientBuildVersion = (string) PlatformSystem::get('min_client_build_version', '0');
        $this->appVersion = (string) PlatformSystem::get('app_version', '1.0.0');
    }

    public function save(): void
    {
        $this->validate([
            'maintenanceMessage' => ['nullable', 'string', 'max:500'],
            'minClientBuildVersion' => ['required', 'string', 'max:50'],
            'appVersion' => ['required', 'string', 'max:50'],
        ]);

        $before = [
            'maintenance_mode' => PlatformSystem::get('maintenance_mode', '0'),
            'min_client_build_version' => PlatformSystem::get('min_client_build_version', '0'),
        ];

        PlatformSystem::set('maintenance_mode', $this->maintenanceMode ? '1' : '0');
        PlatformSystem::set('maintenance_message', $this->maintenanceMessage);
        PlatformSystem::set('min_client_build_version', $this->minClientBuildVersion);
        PlatformSystem::set('app_version', $this->appVersion);

        AuditLog::record('system.settings_updated', null, auth('platform_web')->id(), [
            'before' => $before,
            'after' => [
                'maintenance_mode' => $this->maintenanceMode ? '1' : '0',
                'min_client_build_version' => $this->minClientBuildVersion,
            ],
        ]);

        session()->flash('status', 'System settings saved.');
    }

    public function render()
    {
        return view('livewire.superadmin.system.index');
    }
}
