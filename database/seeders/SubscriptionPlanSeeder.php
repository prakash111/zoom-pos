<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanAddon;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Trial Plan
        Plan::query()->updateOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'has_hrm_module' => false,
            'max_staff_limit' => 2,
            'active' => true,
            'is_active' => true,
            'features' => [
                'quotations' => 1,
                'online_store' => 1,
                'cash_register' => 1,
                'thermal_printing' => 1,
                'hrm_module' => 0,
            ],
            'limits' => [
                'filiais' => 1,
                'invoices' => 50,
                'products' => 100,
                'usuarios' => 2,
                'dispositivos' => 1,
                'armazenamento_mb' => 500,
            ],
            'invoice_limit' => 50,
            'products_limit' => 100,
            'device_limit' => 1,
            'staff_limit' => 2,
            'store_limit' => 1,
        ]);

        // 2. Starter Plan
        Plan::query()->updateOrCreate(['name' => 'starter'], [
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'has_hrm_module' => false,
            'max_staff_limit' => 5,
            'active' => true,
            'is_active' => true,
            'features' => [
                'api_access' => 1,
                'quotations' => 1,
                'consignments' => 1,
                'customer_crm' => 1,
                'online_store' => 1,
                'cash_register' => 1,
                'multi_location' => 0,
                'automatic_backup' => 0,
                'thermal_printing' => 1,
                'analytics_reports' => 1,
                'hrm_module' => 0,
            ],
            'limits' => [
                'filiais' => 1,
                'invoices' => 500,
                'products' => 1000,
                'usuarios' => 5,
                'dispositivos' => 3,
                'armazenamento_mb' => 2048,
            ],
            'invoice_limit' => 500,
            'products_limit' => 1000,
            'device_limit' => 3,
            'staff_limit' => 5,
            'store_limit' => 1,
        ]);

        // 3. Professional Plan
        Plan::query()->updateOrCreate(['name' => 'professional'], [
            'display_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 199.00,
            'currency' => 'USD',
            'has_hrm_module' => true,
            'max_staff_limit' => 25,
            'active' => true,
            'is_active' => true,
            'features' => [
                'api_access' => 1,
                'quotations' => 1,
                'consignments' => 1,
                'customer_crm' => 1,
                'online_store' => 1,
                'cash_register' => 1,
                'multi_location' => 1,
                'restaurant_mode' => 1,
                'automatic_backup' => 1,
                'thermal_printing' => 1,
                'analytics_reports' => 1,
                'app_builder_access' => 1,
                'white_label_custom_branding' => 1,
                'android_web_and_windows_builds' => 1,
                'cloud_build_history_and_alerts' => 1,
                'hrm_module' => 1,
            ],
            'limits' => [
                'filiais' => 5,
                'invoices' => -1,
                'products' => -1,
                'usuarios' => 25,
                'dispositivos' => 10,
                'armazenamento_mb' => 10240,
            ],
            'invoice_limit' => -1,
            'products_limit' => -1,
            'device_limit' => 10,
            'staff_limit' => 25,
            'store_limit' => 5,
        ]);

        // 4. Enterprise Plan
        Plan::query()->updateOrCreate(['name' => 'enterprise'], [
            'display_name' => 'Enterprise',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 499.00,
            'currency' => 'USD',
            'has_hrm_module' => true,
            'max_staff_limit' => 100,
            'active' => true,
            'is_active' => true,
            'features' => [
                'api_access' => 1,
                'quotations' => 1,
                'consignments' => 1,
                'customer_crm' => 1,
                'online_store' => 1,
                'cash_register' => 1,
                'multi_location' => 1,
                'restaurant_mode' => 1,
                'automatic_backup' => 1,
                'thermal_printing' => 1,
                'analytics_reports' => 1,
                'app_builder_access' => 1,
                'white_label_custom_branding' => 1,
                'android_web_and_windows_builds' => 1,
                'cloud_build_history_and_alerts' => 1,
                'hrm_module' => 1,
                'dedicated_account_manager' => 1,
                'custom_integrations' => 1,
            ],
            'limits' => [
                'filiais' => 20,
                'invoices' => -1,
                'products' => -1,
                'usuarios' => 100,
                'dispositivos' => 50,
                'armazenamento_mb' => 51200,
            ],
            'invoice_limit' => -1,
            'products_limit' => -1,
            'device_limit' => 50,
            'staff_limit' => 100,
            'store_limit' => 20,
        ]);

        // 5. Seed Plan Addons (Modular Addon Marketplace)
        PlanAddon::query()->updateOrCreate(['slug' => 'hrm_payroll'], [
            'name' => 'HRM & Payroll Module',
            'description' => 'Complete Staff Directory, PIN Clock In/Out attendance, leave requests, and commission-based payroll calculation.',
            'price_monthly' => 499.00,
            'price_yearly' => 4999.00,
            'is_active' => true,
        ]);
    }
}
