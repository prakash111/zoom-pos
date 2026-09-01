<?php

namespace App\Livewire\SuperAdmin\Tenants;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Tenancy\TenantProvisioningService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Tenant Detail'])]
class Show extends Component
{
    public Company $company;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $planName = '';

    public string $status = '';

    public ?string $expiresAt = null;

    public ?int $maxUsers = null;

    public ?int $maxDevices = null;

    public bool $restaurantModeLocked = false;

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->name = $company->name;
        $this->email = (string) $company->email;
        $this->phone = (string) $company->phone;
        $this->planName = (string) $company->plan_name;
        $this->status = $company->status;
        $this->expiresAt = $company->expires_at?->format('Y-m-d');
        $this->maxUsers = $company->max_users;
        $this->maxDevices = $company->max_devices;
        $this->restaurantModeLocked = (bool) $company->restaurant_mode_locked;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'planName' => ['nullable', 'string', 'exists:plans,name'],
            'status' => ['required', 'in:active,suspended,cancelled'],
            'expiresAt' => ['nullable', 'date'],
            'maxUsers' => ['nullable', 'integer', 'min:0'],
            'maxDevices' => ['nullable', 'integer', 'min:0'],
            'restaurantModeLocked' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $before = $this->company->only(['name', 'email', 'phone', 'plan_name', 'status', 'max_users', 'max_devices']);

        $this->company->update([
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'plan_name' => $data['planName'] ?: null,
            'status' => $data['status'],
            'expires_at' => $data['expiresAt'] ?: null,
            'max_users' => $data['maxUsers'],
            'max_devices' => $data['maxDevices'],
            'restaurant_mode_locked' => $data['restaurantModeLocked'] ?? false,
        ]);

        AuditLog::record('tenant.updated', $this->company->id, auth('platform_web')->id(), [
            'before' => $before,
            'after' => $this->company->only(['name', 'email', 'phone', 'plan_name', 'status', 'max_users', 'max_devices']),
        ]);

        session()->flash('status', 'Tenant updated.');
    }

    public function suspend(): void
    {
        $this->company->update(['status' => 'suspended']);
        $this->status = 'suspended';
        AuditLog::record('tenant.suspended', $this->company->id, auth('platform_web')->id());
        session()->flash('status', 'Tenant suspended.');
    }

    public function activate(): void
    {
        $this->company->update(['status' => 'active']);
        $this->status = 'active';
        AuditLog::record('tenant.activated', $this->company->id, auth('platform_web')->id());
        session()->flash('status', 'Tenant activated.');
    }

    public function extendExpiry(TenantProvisioningService $provisioning): void
    {
        $newExpiry = $provisioning->calculateExpiry($this->company->plan_name);
        $this->company->update(['expires_at' => $newExpiry]);
        $this->expiresAt = $newExpiry?->format('Y-m-d');
        AuditLog::record('tenant.expiry_extended', $this->company->id, auth('platform_web')->id(), [
            'new_expiry' => $this->expiresAt,
        ]);
        session()->flash('status', 'Subscription extended.');
    }

    public function render()
    {
        return view('livewire.superadmin.tenants.show', [
            'plans' => Plan::query()->where('active', true)->orderBy('display_name')->get(),
            'userCount' => $this->company->users()->count(),
        ]);
    }
}
