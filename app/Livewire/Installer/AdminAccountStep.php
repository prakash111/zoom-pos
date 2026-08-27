<?php

namespace App\Livewire\Installer;

use App\Models\PlatformAdmin;
use App\Services\License\EnvatoLicenseVerificationService;
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

    public string $purchaseCode = '';

    public string $buyerUsername = '';

    public ?string $licenseStatus = null;

    public bool $alreadyBootstrapped = false;

    public function mount(): void
    {
        // Guard: only one super_admin may ever be created through this unauthenticated flow.
        $this->alreadyBootstrapped = PlatformAdmin::query()->exists();
    }

    public function save(EnvatoLicenseVerificationService $licenseService)
    {
        if ($this->alreadyBootstrapped) {
            abort(403, 'A Super Admin account already exists.');
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'purchaseCode' => ['nullable', 'string', 'max:100'],
            'buyerUsername' => ['nullable', 'string', 'max:100'],
        ]);

        // License validation
        $licenseData = [
            'license_type' => 'Standard Commercial License',
            'purchase_code' => $this->purchaseCode ?: 'STANDARD-CODECANYON-LICENSE',
            'buyer' => $this->buyerUsername ?: $this->name,
            'verified_at' => now()->toIso8601String(),
        ];

        if (! empty($this->purchaseCode)) {
            $verification = $licenseService->verify($this->purchaseCode, $this->buyerUsername);
            if (! $verification['success']) {
                $this->addError('purchaseCode', $verification['message']);

                return;
            }
            $licenseData['license_type'] = $verification['license'] ?? 'Regular License';
            $licenseData['buyer'] = $verification['buyer'] ?? ($this->buyerUsername ?: $this->name);
        }

        session(['installer_license_data' => $licenseData]);

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
