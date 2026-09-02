<?php

namespace App\Livewire\Auth;

use App\Models\Company;
use App\Models\PendingRegistration;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Services\Auth\DesktopAuthBootstrapService;
use App\Services\Auth\OtpVerificationService;
use App\Services\Tenancy\TenantProvisioningService;
use App\Support\Desktop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class TenantRegister extends Component
{
    public function mount(): void
    {
        if ($profile = session()->pull('social_registration')) {
            $this->ownerName = (string) ($profile['name'] ?? '');
            $this->email = (string) ($profile['email'] ?? '');
        }

        if ($this->allowedRegistrationModes === 'restaurant_only') {
            $this->posMode = 'restaurant';
        } elseif ($this->allowedRegistrationModes === 'retail_only') {
            $this->posMode = 'general';
        }
    }

    public function getAllowedRegistrationModesProperty(): string
    {
        return (string) PlatformSystem::get('allowed_registration_modes', 'both');
    }

    public int $step = 1; // 1 = Registration Form, 2 = OTP Verification

    public string $otp = '';

    public string $otpStatusMessage = '';

    public string $storeName = '';

    public string $slug = '';

    public string $customDomain = '';

    public bool $showDomainSettings = false;

    public string $posMode = 'general';

    public string $ownerName = '';

    public string $email = '';

    public string $phone = '';

    public string $taxId = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Plan & Activation Code
    public string $planName = 'trial';

    public string $activationCode = '';

    public bool $hasActivationCode = false;

    public string $errorMessage = '';

    public function updatedStoreName(string $value): void
    {
        if (empty($this->slug) || Str::slug($this->slug) === Str::slug(substr($value, 0, -1))) {
            $this->slug = Str::slug($value);
        }
    }

    public function toggleDomainSettings(): void
    {
        $this->showDomainSettings = ! $this->showDomainSettings;
    }

    public function selectPlan(string $plan): void
    {
        $this->planName = $plan;
        $this->hasActivationCode = false;
        $this->activationCode = '';
    }

    public function toggleActivationCode(): void
    {
        $this->hasActivationCode = ! $this->hasActivationCode;
        if ($this->hasActivationCode) {
            $this->planName = 'professional';
        }
    }

    public function backToForm(): void
    {
        $this->step = 1;
        $this->otp = '';
        $this->errorMessage = '';
        $this->otpStatusMessage = '';
    }

    public function register(TenantProvisioningService $provisioner, OtpVerificationService $otpService, DesktopAuthBootstrapService $desktopBootstrap)
    {
        $this->errorMessage = '';
        $this->otpStatusMessage = '';

        if (empty($this->slug) && ! empty($this->storeName)) {
            $this->slug = Str::slug($this->storeName);
        }

        $cleanCustomDomain = ! empty($this->customDomain)
            ? strtolower(trim(preg_replace('#^https?://#i', '', $this->customDomain)))
            : null;

        $rules = [
            'storeName' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => [
                'nullable', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::notIn(Company::RESERVED_SLUGS),
                Rule::unique('companies', 'slug'),
            ],
            'customDomain' => ['nullable', 'string', 'min:3', 'max:100'],
            'posMode' => ['required', 'in:general,restaurant'],
            'ownerName' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'taxId' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'planName' => ['required', 'string', 'in:trial,starter,professional'],
            'activationCode' => ['nullable', 'string'],
        ];

        $this->validate($rules);

        $allowedModes = $this->allowedRegistrationModes;
        if ($allowedModes === 'retail_only' && $this->posMode === 'restaurant') {
            $this->addError('posMode', 'Cafe & Restaurant registration is currently disabled.');

            return;
        }
        if ($allowedModes === 'restaurant_only' && $this->posMode !== 'restaurant') {
            $this->addError('posMode', 'Retail registration is currently disabled.');

            return;
        }

        $payload = [
            'store_name' => $this->storeName,
            'slug' => $this->slug,
            'custom_domain' => $cleanCustomDomain,
            'pos_mode' => $this->posMode,
            'owner_name' => $this->ownerName,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_id' => $this->taxId,
            'password' => $this->password,
            'plan_name' => $this->planName,
            'activation_code' => $this->hasActivationCode ? $this->activationCode : null,
        ];

        // The Windows app is an offline-capable client, not the source of
        // truth for accounts. Create the tenant on the server and then cache
        // the returned account locally for future offline logins.
        if (Desktop::isRunning()) {
            try {
                $user = $desktopBootstrap->registerOnline($payload);
                $result = ['user' => $user, 'company' => $user->company];
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();

                return;
            }

            Auth::guard('web')->login($result['user']);
            app()->instance('tenant.company_id', $result['company']->id);
            // See TenantLogin::login() — this is how the background
            // RunDesktopSyncCycle job (a separate process with no session of
            // its own) knows which account is actually signed in on this
            // device.
            Desktop::rememberActiveCompany($result['company']->id);
            session()->flash('status', "🎉 Welcome to {$result['company']->name}! Your store is ready and available offline on this device.");

            return $this->redirect(route('tenant.dashboard'), navigate: false);
        }

        $branding = PlatformBranding::current();

        // When SMTP is configured in SuperAdmin, require OTP verification before creating account
        if ($branding->isSmtpConfigured()) {
            try {
                $otpService->sendOtpForRegistration($payload);
                $this->step = 2;
                $this->otpStatusMessage = __('A 6-digit verification code has been sent to :email.', ['email' => $this->email]);

                return;
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();

                return;
            }
        }

        // When SMTP is not configured, provision tenant directly without OTP
        try {
            $result = $provisioner->registerTenant($payload);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        Auth::guard('web')->login($result['user']);
        app()->instance('tenant.company_id', $result['company']->id);

        session()->flash('status', "🎉 Welcome to {$result['company']->name}! Your store environment and {$result['subscription']->plan_name} plan are active.");

        return $this->redirect(route('tenant.dashboard'), navigate: false);
    }

    public function verifyOtp(TenantProvisioningService $provisioner, OtpVerificationService $otpService)
    {
        $this->errorMessage = '';
        $this->otpStatusMessage = '';

        $this->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ], [
            'otp.required' => __('Please enter the 6-digit verification code.'),
            'otp.size' => __('The verification code must be exactly 6 digits.'),
            'otp.regex' => __('The verification code must contain only numbers.'),
        ]);

        try {
            $pending = $otpService->verifyRegistrationOtp($this->email, $this->otp);
            $result = $provisioner->registerTenant($pending->payload);
            $pending->delete();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        Auth::guard('web')->login($result['user']);
        app()->instance('tenant.company_id', $result['company']->id);

        session()->flash('status', "🎉 Welcome to {$result['company']->name}! Your store environment is verified and active.");

        return $this->redirect(route('tenant.dashboard'), navigate: false);
    }

    public function resendOtp(OtpVerificationService $otpService)
    {
        $this->errorMessage = '';
        $pending = PendingRegistration::where('email', strtolower(trim($this->email)))->first();

        if (! $pending) {
            $this->errorMessage = __('Registration session expired. Please start over.');
            $this->step = 1;

            return;
        }

        try {
            $otpService->sendOtpForRegistration($pending->payload);
            $this->otpStatusMessage = __('A new 6-digit verification code has been sent to :email.', ['email' => $this->email]);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $plans = Plan::where('active', true)->orderBy('price')->get();
        $branding = PlatformBranding::current();

        return view('livewire.auth.tenant-register', [
            'plans' => $plans,
            'branding' => $branding,
        ]);
    }
}
