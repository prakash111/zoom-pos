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

    public int $invoiceLimit = -1;

    public int $productsLimit = -1;

    public int $deviceLimit = 3;

    public int $staffLimit = 5;

    public array $extensions = [];

    public string $customExtensionInput = '';

    public string $customFeaturesText = '';

    public bool $featureMultiLocation = false;

    public bool $featureAutomaticBackup = false;

    public bool $featureOnlineStore = true;

    public bool $featureQuotations = true;

    public bool $featureConsignments = true;

    public bool $featureCashRegister = true;

    public bool $featureCustomerCrm = true;

    public bool $featureAnalyticsReports = true;

    public bool $featureRestaurantMode = false;

    public bool $featureServiceBooking = false;

    public bool $featureRepairWorkbench = false;

    public bool $featurePharmacyBatches = false;

    public bool $featureThermalPrinting = true;

    public bool $featureApiAccess = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'displayName' => ['required', 'string', 'max:255'],
            'billingCycle' => ['required', 'in:trial,monthly,quarterly,biannual,yearly,lifetime'],
            'durationDays' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'limitUsers' => ['required', 'integer', 'min:-1'],
            'limitDevices' => ['required', 'integer', 'min:-1'],
            'limitStorageMb' => ['required', 'integer', 'min:0'],
            'limitBranches' => ['required', 'integer', 'min:0'],
            'invoiceLimit' => ['required', 'integer', 'min:-1'],
            'productsLimit' => ['required', 'integer', 'min:-1'],
            'deviceLimit' => ['required', 'integer', 'min:-1'],
            'staffLimit' => ['required', 'integer', 'min:-1'],
            'extensions' => ['nullable', 'array'],
            'customFeaturesText' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function addCustomExtension(): void
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $this->customExtensionInput)));
        if (! empty($slug)) {
            if (! in_array($slug, $this->extensions, true)) {
                $this->extensions[] = $slug;
            }
            $this->customExtensionInput = '';
        }
    }

    public function newPlan(): void
    {
        $this->reset([
            'editingName', 'name', 'displayName', 'billingCycle', 'durationDays', 'price', 'currency',
            'active', 'limitUsers', 'limitDevices', 'limitStorageMb', 'limitBranches',
            'invoiceLimit', 'productsLimit', 'deviceLimit', 'staffLimit', 'extensions', 'customFeaturesText',
            'featureMultiLocation', 'featureAutomaticBackup', 'featureOnlineStore', 'featureQuotations',
            'featureConsignments', 'featureCashRegister', 'featureCustomerCrm', 'featureAnalyticsReports',
            'featureRestaurantMode', 'featureServiceBooking', 'featureRepairWorkbench',
            'featurePharmacyBatches', 'featureThermalPrinting', 'featureApiAccess',
        ]);
        $this->billingCycle = 'monthly';
        $this->durationDays = 30;
        $this->currency = 'USD';
        $this->active = true;
        $this->limitUsers = 5;
        $this->limitDevices = 3;
        $this->limitStorageMb = 1024;
        $this->limitBranches = 1;
        $this->invoiceLimit = -1;
        $this->productsLimit = -1;
        $this->deviceLimit = 3;
        $this->staffLimit = 5;
        $this->extensions = [];
        $this->customExtensionInput = '';
        $this->customFeaturesText = '';
        $this->featureOnlineStore = true;
        $this->featureQuotations = true;
        $this->featureConsignments = true;
        $this->featureCashRegister = true;
        $this->featureCustomerCrm = true;
        $this->featureAnalyticsReports = true;
        $this->featureThermalPrinting = true;
        $this->featureApiAccess = true;
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
        $this->staffLimit = $plan->staff_limit ?? ($plan->limits['usuarios'] ?? 5);
        $this->deviceLimit = $plan->device_limit ?? ($plan->limits['dispositivos'] ?? 3);
        $this->invoiceLimit = $plan->invoice_limit ?? ($plan->limits['invoices'] ?? -1);
        $this->productsLimit = $plan->products_limit ?? ($plan->limits['products'] ?? -1);
        $this->limitUsers = $this->staffLimit;
        $this->limitDevices = $this->deviceLimit;
        $this->limitStorageMb = $plan->limits['armazenamento_mb'] ?? 1024;
        $this->limitBranches = $plan->limits['filiais'] ?? 1;
        $this->extensions = is_array($plan->extensions) ? $plan->extensions : [];
        $this->customExtensionInput = '';
        $this->featureMultiLocation = (bool) ($plan->features['multi_location'] ?? false);
        $this->featureAutomaticBackup = (bool) ($plan->features['automatic_backup'] ?? false);
        $this->featureOnlineStore = (bool) ($plan->features['online_store'] ?? true);
        $this->featureQuotations = (bool) ($plan->features['quotations'] ?? true);
        $this->featureConsignments = (bool) ($plan->features['consignments'] ?? true);
        $this->featureCashRegister = (bool) ($plan->features['cash_register'] ?? true);
        $this->featureCustomerCrm = (bool) ($plan->features['customer_crm'] ?? true);
        $this->featureAnalyticsReports = (bool) ($plan->features['analytics_reports'] ?? true);
        $this->featureRestaurantMode = (bool) ($plan->features['restaurant_mode'] ?? false);
        $this->featureServiceBooking = (bool) ($plan->features['service_booking'] ?? false);
        $this->featureRepairWorkbench = (bool) ($plan->features['repair_workbench'] ?? false);
        $this->featurePharmacyBatches = (bool) ($plan->features['pharmacy_batches'] ?? false);
        $this->featureThermalPrinting = (bool) ($plan->features['thermal_printing'] ?? true);
        $this->featureApiAccess = (bool) ($plan->features['api_access'] ?? true);

        $knownFeatureKeys = [
            'multi_location', 'automatic_backup', 'online_store', 'quotations', 'consignments',
            'cash_register', 'customer_crm', 'analytics_reports', 'restaurant_mode',
            'service_booking', 'repair_workbench', 'pharmacy_batches', 'thermal_printing', 'api_access',
        ];

        $custom = [];
        if (is_array($plan->features)) {
            foreach ($plan->features as $k => $v) {
                if (in_array($k, $knownFeatureKeys, true)) {
                    continue;
                }
                if (is_string($v) && is_numeric($k)) {
                    $custom[] = $v;
                } elseif ($v === true || $v === 1 || $v === '1') {
                    $custom[] = is_string($k) ? str_replace('_', ' ', ucfirst($k)) : $v;
                } elseif (is_string($v)) {
                    $custom[] = "$k: $v";
                }
            }
        }
        $this->customFeaturesText = implode("\n", $custom);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $features = [
            'multi_location' => $this->featureMultiLocation,
            'automatic_backup' => $this->featureAutomaticBackup,
            'online_store' => $this->featureOnlineStore,
            'quotations' => $this->featureQuotations,
            'consignments' => $this->featureConsignments,
            'cash_register' => $this->featureCashRegister,
            'customer_crm' => $this->featureCustomerCrm,
            'analytics_reports' => $this->featureAnalyticsReports,
            'restaurant_mode' => $this->featureRestaurantMode,
            'service_booking' => $this->featureServiceBooking,
            'repair_workbench' => $this->featureRepairWorkbench,
            'pharmacy_batches' => $this->featurePharmacyBatches,
            'thermal_printing' => $this->featureThermalPrinting,
            'api_access' => $this->featureApiAccess,
        ];

        if (! empty(trim($this->customFeaturesText))) {
            $lines = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $this->customFeaturesText))));
            foreach ($lines as $line) {
                $features[$line] = true;
            }
        }

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
                'invoice_limit' => $this->invoiceLimit,
                'products_limit' => $this->productsLimit,
                'device_limit' => $this->limitDevices !== 3 ? $this->limitDevices : $this->deviceLimit,
                'staff_limit' => $this->limitUsers !== 5 ? $this->limitUsers : $this->staffLimit,
                'extensions' => array_values(array_unique(array_filter($this->extensions))),
                'limits' => [
                    'usuarios' => $this->limitUsers !== 5 ? $this->limitUsers : $this->staffLimit,
                    'dispositivos' => $this->limitDevices !== 3 ? $this->limitDevices : $this->deviceLimit,
                    'invoices' => $this->invoiceLimit,
                    'products' => $this->productsLimit,
                    'armazenamento_mb' => $this->limitStorageMb,
                    'filiais' => $this->limitBranches,
                ],
                'features' => $features,
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

    public function getAvailableExtensionsProperty(): array
    {
        $keys = \App\Services\Modular\ModuleRegistry::extensionKeys();
        $fromConfig = (array) config('modules.extensions', []);
        $allKeys = array_values(array_unique(array_merge($keys, $fromConfig, $this->extensions)));

        $catalog = collect(config('modules.catalog', []))->keyBy('slug');
        $allModules = \App\Services\Modular\ModuleRegistry::allModules();

        $result = [];
        foreach ($allKeys as $key) {
            $name = null;
            if (isset($allModules[$key])) {
                $name = $allModules[$key]['title'] ?? $allModules[$key]['name'] ?? null;
            }
            if (! $name && isset($catalog[$key])) {
                $name = $catalog[$key]['name'] ?? null;
            }
            if (! $name) {
                $name = ucwords(str_replace(['_', '-'], ' ', $key));
            }

            $result[$key] = $name;
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.superadmin.plans.index', [
            'plans' => Plan::query()->orderBy('display_name')->get(),
        ]);
    }
}
