<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $newPermissionDefinitions = [
            // 1. Consignments
            ['module' => 'consignments', 'action' => 'view', 'label' => 'View Consignment Records'],
            ['module' => 'consignments', 'action' => 'create', 'label' => 'Create & Dispatch Items'],
            ['module' => 'consignments', 'action' => 'edit', 'label' => 'Reconcile Returns/Sold Qty'],
            ['module' => 'consignments', 'action' => 'delete', 'label' => 'Void / Delete Consignments'],
            ['module' => 'consignments', 'action' => 'export', 'label' => 'Export Consignment Ledgers'],

            // 2. Targets & Sales Quotas
            ['module' => 'targets', 'action' => 'view', 'label' => 'View Target Metrics & Progress'],
            ['module' => 'targets', 'action' => 'edit', 'label' => 'Set Store & Staff Monthly Targets'],
            ['module' => 'targets', 'action' => 'create', 'label' => 'Create Target Milestones'],
            ['module' => 'targets', 'action' => 'delete', 'label' => 'Delete Target Quotas'],
            ['module' => 'targets', 'action' => 'export', 'label' => 'Export Target Reports'],

            // 3. Cash Register & Drawer Management
            ['module' => 'cash_register', 'action' => 'view', 'label' => 'View Current Shift Balance'],
            ['module' => 'cash_register', 'action' => 'create', 'label' => 'Open Register Shift'],
            ['module' => 'cash_register', 'action' => 'edit', 'label' => 'Perform Sangria / Suprimento'],
            ['module' => 'cash_register', 'action' => 'delete', 'label' => 'Close Register & Z-Report'],
            ['module' => 'cash_register', 'action' => 'export', 'label' => 'View Historical Shift Ledgers'],
        ];

        // Ensure default permissions exist for seeded non-admin users
        $users = User::withoutGlobalScopes()->whereNotIn('role', User::PRIVILEGED_ROLES)->get();

        foreach ($users as $user) {
            $roleDefaults = PermissionChecker::getRoleDefaults($user->role);
            foreach (['consignments', 'targets', 'cash_register'] as $mod) {
                $allowedActions = $roleDefaults[$mod] ?? [];
                foreach ($allowedActions as $action) {
                    Permission::firstOrCreate([
                        'company_id' => $user->company_id,
                        'user_id' => $user->id,
                        'module' => $mod,
                        'action' => $action,
                    ], [
                        'allowed' => true,
                    ]);
                }
            }
        }
    }
}
