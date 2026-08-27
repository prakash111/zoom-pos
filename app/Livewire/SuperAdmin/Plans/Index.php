<?php

namespace App\Livewire\SuperAdmin\Plans;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Plans'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?string $editingName = null;

    public string $name = '';

    public string $displayName = '';

    public string $billingCycle = 'monthly';

    public ?int $durationDays = 30;

    public float $price = 0;

    public string $currency = 'USD';

    public bool $active = true;

    public int $limitUsers = 5;

    public int $limitDevices = 3;

    public int $limitStorageMb = 1024;

    public int $limitBranches = 1;

    public bool $featureMultiLocation = false;

    public bool $featureAutomaticBackup = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'displayName' => ['required', 'string', 'max:255'],
            'billingCycle' => ['required', 'in:trial,monthly,quarterly,biannual,yearly,lifetime'],
            'durationDays' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'limitUsers' => ['required', 'integer', 'min:0'],
            'limitDevices' => ['required', 'integer', 'min:0'],
            'limitStorageMb' => ['required', 'integer', 'min:0'],
            'limitBranches' => ['required', 'integer', 'min:0'],
        ];
    }

    public function newPlan(): void
    {
        $this->reset([
            'editingName', 'name', 'displayName', 'billingCycle', 'durationDays', 'price', 'currency',
            'active', 'limitUsers', 'limitDevices', 'limitStorageMb', 'limitBranches',
            'featureMultiLocation', 'featureAutomaticBackup',
        ]);
        $this->billingCycle = 'monthly';
        $this->durationDays = 30;
        $this->currency = 'USD';
        $this->active = true;
        $this->limitUsers = 5;
        $this->limitDevices = 3;
        $this->limitStorageMb = 1024;
        $this->limitBranches = 1;
        $this->showForm = true;
    }

    public function edit(string $name): void
    {
        $plan = Plan::findOrFail($name);
        $this->editingName = $plan->name;
        $this->name = $plan->name;
        $this->displayName = $plan->display_name;
        $this->billingCycle = $plan->billing_cycle;
        $this->durationDays = $plan->duration_days;
        $this->price = (float) $plan->price;
        $this->currency = $plan->currency;
        $this->active = $plan->active;
        $this->limitUsers = $plan->limits['usuarios'] ?? 0;
        $this->limitDevices = $plan->limits['dispositivos'] ?? 0;
        $this->limitStorageMb = $plan->limits['armazenamento_mb'] ?? 0;
        $this->limitBranches = $plan->limits['filiais'] ?? 1;
        $this->featureMultiLocation = (bool) ($plan->features['multi_location'] ?? false);
        $this->featureAutomaticBackup = (bool) ($plan->features['automatic_backup'] ?? false);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $plan = Plan::updateOrCreate(
            ['name' => $this->editingName ?? $data['name']],
            [
                'name' => $data['name'],
                'display_name' => $data['displayName'],
                'billing_cycle' => $data['billingCycle'],
                'duration_days' => $data['billingCycle'] === 'lifetime' ? null : $data['durationDays'],
                'price' => $data['price'],
                'currency' => strtoupper($data['currency']),
                'active' => $this->active,
                'limits' => [
                    'usuarios' => $this->limitUsers,
                    'dispositivos' => $this->limitDevices,
                    'armazenamento_mb' => $this->limitStorageMb,
                    'filiais' => $this->limitBranches,
                ],
                'features' => [
                    'multi_location' => $this->featureMultiLocation,
                    'automatic_backup' => $this->featureAutomaticBackup,
                ],
            ]
        );

        AuditLog::record($this->editingName ? 'plan.updated' : 'plan.created', null, auth('platform_web')->id(), ['plan' => $plan->name]);

        $this->showForm = false;
        session()->flash('status', "Plan \"{$plan->display_name}\" saved.");
    }

    public function delete(string $name): void
    {
        $inUse = Company::query()->where('plan_name', $name)->exists();
        if ($inUse) {
            session()->flash('error', 'Cannot delete a plan that tenants are currently on.');

            return;
        }

        Plan::where('name', $name)->delete();
        AuditLog::record('plan.deleted', null, auth('platform_web')->id(), ['plan' => $name]);
        session()->flash('status', 'Plan deleted.');
    }

    public function render()
    {
        return view('livewire.superadmin.plans.index', [
            'plans' => Plan::query()->orderBy('display_name')->get(),
        ]);
    }
}
