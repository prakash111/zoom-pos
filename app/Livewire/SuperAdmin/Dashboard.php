<?php

namespace App\Livewire\SuperAdmin;

use App\Models\ActivationCode;
use App\Models\Company;
use App\Models\PlatformSystem;
use App\Models\User;
use App\Services\License\LicenseService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public string $coreLicenseKey = '';

    public function activateCore(): void
    {
        $key = trim($this->coreLicenseKey);
        $this->validate(['coreLicenseKey' => ['required', 'string', 'min:8']]);

        $result = app(LicenseService::class)->verify($key, 'core', LicenseService::currentDomain());
        if (! $result['status']) {
            $this->addError('coreLicenseKey', $result['message'] ?: 'License could not be verified.');

            return;
        }

        $path = storage_path('installed');
        $blob = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $blob['license'] = array_merge($blob['license'] ?? [], [
            'purchase_code' => $key,
            'verified_at' => now()->toIso8601String(),
        ]);
        @file_put_contents($path, json_encode($blob, JSON_PRETTY_PRINT));

        PlatformSystem::set('core_license_status', 'ok');
        PlatformSystem::set('core_license_last_checked', now()->toIso8601String());
        PlatformSystem::set('core_license_message', 'Core license activated.');
        PlatformSystem::set('core_license_expires_at', (string) ($result['expires_at'] ?? ''));

        $this->coreLicenseKey = '';
        $this->dispatch('notify', ['type' => 'success', 'message' => 'License activated.']);
        session()->flash('status', 'License activated.');
    }

    public function render()
    {
        $companies = Company::query();

        return view('livewire.superadmin.dashboard', [
            'totalTenants' => (clone $companies)->count(),
            'activeTenants' => (clone $companies)->where('status', 'active')->count(),
            'suspendedTenants' => (clone $companies)->where('status', 'suspended')->count(),
            'expiringSoon' => (clone $companies)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->count(),
            'totalUsers' => User::query()->count(),
            'activationCodesAvailable' => ActivationCode::query()
                ->where('revoked', false)
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('current_uses', '<', 'max_uses'))
                ->count(),
            'recentTenants' => (clone $companies)->orderByDesc('created_at')->limit(5)->get(),
            'coreLicenseWarn' => PlatformSystem::get('core_license_status') === 'warn',
            'coreLicenseMessage' => (string) PlatformSystem::get('core_license_message', ''),
        ]);
    }
}
