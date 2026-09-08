<?php

namespace App\Livewire\Installer;

use App\Models\PlatformAdmin;
use App\Services\License\LicenseService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.installer', ['step' => 4])]
class AdminAccountStep extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $licenseKey = '';

    public string $buyerUsername = '';

    public ?string $licenseStatus = null;

    public bool $alreadyBootstrapped = false;

    public function mount(): void
    {
        // Guard: only one super_admin may ever be created through this unauthenticated flow.
        $this->alreadyBootstrapped = PlatformAdmin::query()->exists();
    }

    public function getStoreLinkProperty(): ?string
    {
        $store = app(LicenseService::class)->storeUrl();
        if ($store === '') {
            return null;
        }

        return $store.'?'.http_build_query([
            'product' => 'core',
            'domain' => LicenseService::currentDomain(),
        ]);
    }

    public function save(LicenseService $licenseService)
    {
        if ($this->alreadyBootstrapped) {
            abort(403, 'A Super Admin account already exists.');
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'licenseKey' => ['required', 'string', 'min:8', 'max:191'],
            'buyerUsername' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $licenseService->verify(trim($this->licenseKey), 'core', LicenseService::currentDomain());

        if (! $result['status']) {
            $this->addError('licenseKey', $result['message'] ?: 'The license key could not be verified.');

            return;
        }

        session(['installer_license_data' => [
            'license_type' => $result['plan'] ?: 'Commercial License',
            'purchase_code' => trim($this->licenseKey),
            'buyer' => $result['buyer'] ?: ($this->buyerUsername ?: $this->name),
            'driver' => $result['driver'],
            'expires_at' => $result['expires_at'],
            'verified_at' => now()->toIso8601String(),
        ]]);

        PlatformAdmin::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        return redirect()->route('install.finish');
    }

    public function render()
    {
        return view('livewire.installer.admin-account-step');
    }
}
