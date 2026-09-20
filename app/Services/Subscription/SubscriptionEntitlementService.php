<?php

namespace App\Services\Subscription;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PushDevice;
use App\Models\Sale;
use App\Models\User;
use App\Services\Modular\ModuleRegistry;

class SubscriptionEntitlementService
{
    /**
     * Resolve Company instance from model or identifier.
     */
    protected function resolveCompany(int|string|Company|null $tenant): ?Company
    {
        if ($tenant instanceof Company) {
            return $tenant;
        }

        if (empty($tenant)) {
            $companyId = app()->bound('tenant.company_id')
                ? app('tenant.company_id')
                : null;

            return $companyId ? Company::find($companyId) : null;
        }

        return Company::find($tenant);
    }

    /**
     * Check if tenant has access to a particular feature or extension.
     */
    public function tenantHasFeature(int|string|Company|null $tenant, string $featureKey): bool
    {
        $company = $this->resolveCompany($tenant);
        if (! $company) {
            return false;
        }

        $cleanKey = strtolower(trim($featureKey));

        // 1. Direct module/extension licensing check
        if ($company->hasModule($cleanKey)) {
            return true;
        }

        $plan = $company->plan;
        if (! $plan && $company->plan_name) {
            $plan = Plan::find($company->plan_name);
        }

        if (! $plan) {
            return false;
        }

        // 2. Check bundled extensions
        if (is_array($plan->extensions) && in_array($cleanKey, array_map('strtolower', $plan->extensions), true)) {
            return true;
        }

        // 3. Check features JSON
        if (is_array($plan->features)) {
            if (isset($plan->features[$cleanKey]) && (bool) $plan->features[$cleanKey]) {
                return true;
            }
            if (in_array($cleanKey, array_map('strtolower', array_values($plan->features)), true)) {
                return true;
            }
        }

        // 4. Check limits JSON for numeric limits > 0 or -1
        if (is_array($plan->limits) && isset($plan->limits[$cleanKey])) {
            return (int) $plan->limits[$cleanKey] !== 0;
        }

        return false;
    }

    /**
     * Return the numeric limit for a specific feature (-1 represents unlimited).
     */
    public function tenantFeatureLimit(int|string|Company|null $tenant, string $featureKey): int
    {
        $company = $this->resolveCompany($tenant);
        if (! $company) {
            return -1;
        }

        $plan = $company->plan;
        if (! $plan && $company->plan_name) {
            $plan = Plan::find($company->plan_name);
        }

        $norm = strtolower(trim($featureKey));

        if (in_array($norm, ['invoice_limit', 'invoices', 'invoice', 'sales', 'sale'], true)) {
            if ($plan && $plan->invoice_limit !== null) {
                return (int) $plan->invoice_limit;
            }

            return -1;
        }

        if (in_array($norm, ['device_limit', 'devices', 'device'], true)) {
            if ($plan && $plan->device_limit !== null && (int) $plan->device_limit !== -1) {
                return (int) $plan->device_limit;
            }
            if ($company->max_devices !== null && (int) $company->max_devices > 0) {
                return (int) $company->max_devices;
            }

            return -1;
        }

        if (in_array($norm, ['staff_limit', 'staff', 'users', 'user', 'max_users'], true)) {
            if ($plan && $plan->staff_limit !== null && (int) $plan->staff_limit !== -1) {
                return (int) $plan->staff_limit;
            }
            if ($company->max_users !== null && (int) $company->max_users > 0) {
                return (int) $company->max_users;
            }

            return -1;
        }

        if ($plan && is_array($plan->limits) && isset($plan->limits[$norm]) && is_numeric($plan->limits[$norm])) {
            return (int) $plan->limits[$norm];
        }

        return -1;
    }

    /**
     * Check if tenant can use a specific modular extension.
     */
    public function tenantCanUseExtension(int|string|Company|null $tenant, string $extensionKey): bool
    {
        $company = $this->resolveCompany($tenant);
        if (! $company) {
            return false;
        }

        return $company->hasModule($extensionKey);
    }

    /**
     * Calculate remaining quota for a feature (-1 if unlimited).
     */
    public function tenantLimitRemaining(int|string|Company|null $tenant, string $featureKey): int
    {
        $company = $this->resolveCompany($tenant);
        if (! $company) {
            return -1;
        }

        $limit = $this->tenantFeatureLimit($company, $featureKey);
        if ($limit < 0) {
            return -1;
        }

        $norm = strtolower(trim($featureKey));

        if (in_array($norm, ['invoice_limit', 'invoices', 'invoice', 'sales', 'sale'], true)) {
            $query = Sale::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($q) {
                    $q->whereNull('operation_type')
                        ->orWhere('operation_type', '!=', 'quotation');
                });

            $activeSub = $company->subscriptions()->first();
            if ($activeSub && $activeSub->started_at) {
                $query->where('created_at', '>=', $activeSub->started_at);
            }

            $used = $query->count();

            return max(0, $limit - $used);
        }

        if (in_array($norm, ['device_limit', 'devices', 'device'], true)) {
            $used = PushDevice::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->count();

            return max(0, $limit - $used);
        }

        if (in_array($norm, ['staff_limit', 'staff', 'users', 'user', 'max_users'], true)) {
            $used = User::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->count();

            return max(0, $limit - $used);
        }

        return $limit;
    }

    /**
     * Can tenant create a new invoice / sale?
     */
    public function canCreateInvoice(int|string|Company|null $tenant): bool
    {
        $rem = $this->tenantLimitRemaining($tenant, 'invoice_limit');

        return $rem < 0 || $rem > 0;
    }

    /**
     * Can tenant register a new device?
     */
    public function canCreateDevice(int|string|Company|null $tenant): bool
    {
        $rem = $this->tenantLimitRemaining($tenant, 'device_limit');

        return $rem < 0 || $rem > 0;
    }

    /**
     * Can tenant add a new staff member?
     */
    public function canCreateStaff(int|string|Company|null $tenant): bool
    {
        $rem = $this->tenantLimitRemaining($tenant, 'staff_limit');

        return $rem < 0 || $rem > 0;
    }

    /**
     * Full entitlement payload for API and frontend consumption.
     */
    public function getEntitlements(int|string|Company|null $tenant): array
    {
        $company = $this->resolveCompany($tenant);
        if (! $company) {
            return [];
        }

        $plan = $company->plan;
        if (! $plan && $company->plan_name) {
            $plan = Plan::find($company->plan_name);
        }

        $invLimit = $this->tenantFeatureLimit($company, 'invoice_limit');
        $invRemaining = $this->tenantLimitRemaining($company, 'invoice_limit');
        $invUsed = $invLimit < 0 ? 0 : max(0, $invLimit - $invRemaining);

        $devLimit = $this->tenantFeatureLimit($company, 'device_limit');
        $devRemaining = $this->tenantLimitRemaining($company, 'device_limit');
        $devUsed = $devLimit < 0 ? 0 : max(0, $devLimit - $devRemaining);

        $staffLimit = $this->tenantFeatureLimit($company, 'staff_limit');
        $staffRemaining = $this->tenantLimitRemaining($company, 'staff_limit');
        $staffUsed = $staffLimit < 0 ? 0 : max(0, $staffLimit - $staffRemaining);

        $bundledExtensions = is_array($plan?->extensions) ? $plan->extensions : [];
        $registeredExtensions = ModuleRegistry::extensionKeys();

        return [
            'company_id' => $company->id,
            'plan' => [
                'name' => $plan?->name ?? $company->plan_name ?? 'Free',
                'display_name' => $plan?->display_name ?? $company->plan_name ?? 'Free Plan',
                'billing_cycle' => $plan?->billing_cycle ?? 'monthly',
                'price' => (float) ($plan?->price ?? 0),
                'currency' => $plan?->currency ?? $company->currency ?? 'USD',
                'active' => (bool) ($plan?->active ?? true),
            ],
            'limits' => [
                'invoice_limit' => [
                    'limit' => $invLimit,
                    'used' => $invUsed,
                    'remaining' => $invRemaining,
                    'unlimited' => $invLimit === -1,
                ],
                'device_limit' => [
                    'limit' => $devLimit,
                    'used' => $devUsed,
                    'remaining' => $devRemaining,
                    'unlimited' => $devLimit === -1,
                ],
                'staff_limit' => [
                    'limit' => $staffLimit,
                    'used' => $staffUsed,
                    'remaining' => $staffRemaining,
                    'unlimited' => $staffLimit === -1,
                ],
            ],
            'features' => is_array($plan?->features) ? $plan->features : [],
            'bundled_extensions' => $bundledExtensions,
            'licensed_modules' => $company->licensedModuleKeys(),
            'available_extensions' => $registeredExtensions,
            'can_create_invoice' => $this->canCreateInvoice($company),
            'can_create_device' => $this->canCreateDevice($company),
            'can_create_staff' => $this->canCreateStaff($company),
        ];
    }
}
