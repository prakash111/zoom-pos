<?php

namespace App\Livewire\SuperAdmin\Tenants;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\Tenancy\TenantProvisioningService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Create Tenant'])]
class Create extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $country = 'US';

    public string $planName = '';

    public string $adminName = '';

    public string $adminLogin = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['required', 'string', 'size:2'],
            'planName' => ['nullable', 'string', 'exists:plans,name'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminLogin' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email'],
            'adminPassword' => ['required', 'string', 'min:8'],
        ];
    }

    public function save(TenantProvisioningService $provisioning)
    {
        $data = $this->validate();

        $company = $provisioning->create([
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'country' => $data['country'],
            'plan_name' => $data['planName'] ?: null,
            'admin_name' => $data['adminName'],
            'admin_login' => $data['adminLogin'],
            'admin_email' => $data['adminEmail'],
            'admin_password' => $data['adminPassword'],
        ]);

        AuditLog::record('tenant.created', $company->id, auth('platform_web')->id(), [
            'name' => $company->name, 'plan' => $company->plan_name,
        ]);

        session()->flash('status', "Tenant \"{$company->name}\" created.");

        $this->redirectRoute('superadmin.tenants.show', ['company' => $company], navigate: true);
    }

    public function render()
    {
        return view('livewire.superadmin.tenants.create', [
            'plans' => Plan::query()->where('active', true)->orderBy('display_name')->get(),
        ]);
    }
}
