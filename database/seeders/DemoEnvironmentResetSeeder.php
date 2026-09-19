<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Modular\ModuleRegistry;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the named demo workspaces without touching customer tenants.
 *
 * This is intentionally separate from DatabaseSeeder: it is an explicit
 * maintenance command for staging/demo refreshes and is safe to run again.
 */
class DemoEnvironmentResetSeeder extends Seeder
{
    public const PASSWORD = 'demo1234';

    private const DEMOS = [
        ['slug' => 'tenant-demo-all-enterprise', 'name' => 'ZoomNearby Enterprise Demo', 'email' => 'demo@zoomnearby.com', 'alias' => 'allmodules.demo@zoomnearby.com', 'mode' => 'retail'],
        ['slug' => 'tenant-demo-retail', 'name' => 'Metro Retail Mart', 'email' => 'retail.demo@zoomnearby.com', 'mode' => 'retail'],
        ['slug' => 'tenant-demo-restaurant', 'name' => 'Urban Bistro & Cafe', 'email' => 'restaurant.demo@zoomnearby.com', 'mode' => 'restaurant'],
        ['slug' => 'tenant-demo-pharmacy', 'name' => 'CareWell Chemist & Pharmacy', 'email' => 'pharmacy.demo@zoomnearby.com', 'mode' => 'pharmacy'],
        ['slug' => 'tenant-demo-salon', 'name' => 'Luna Salon & Spa', 'email' => 'salon.demo@zoomnearby.com', 'mode' => 'service_booking'],
        ['slug' => 'tenant-demo-repairs', 'name' => 'QuickFix Device Repair Center', 'email' => 'repairs.demo@zoomnearby.com', 'mode' => 'repair_technician'],
    ];

    public function run(): void
    {
        $this->purgePreviousDemos();

        $all = array_values(array_unique(array_merge(
            array_keys(ModuleRegistry::operatingModules()),
            ModuleRegistry::extensionKeys(),
            [
                // Core commerce and reporting capabilities.
                'pos', 'sales', 'quotations', 'consignments', 'customers', 'inventory',
                'cash_register', 'finance', 'reports', 'analytics',

                // Cafe and restaurant capabilities. Keep the aliases here as
                // well as the canonical `restaurant` key: older mobile builds
                // use these keys when deciding which shortcuts to display.
                'restaurant', 'dining_tables', 'kitchen_display', 'kot',

                // Other vertical capabilities used by the flagship workspace.
                'pharmacy', 'prescriptions', 'drug_batches',
                'service_booking', 'stylists', 'repair_technician',

                // Digital and integration extensions.
                'digital_catalog', 'dispatch_omnichannel', 'api_integrations',
            ]
        )));

        foreach (self::DEMOS as $definition) {
            $company = $this->createAccount($definition, $definition['email'] === 'demo@zoomnearby.com' ? $all : [$definition['mode'], 'customers', 'inventory', 'sales', 'finance']);

            // The existing sample services understand the real schema and
            // populate the vertical-specific fixtures (products, bookings,
            // repair intake, prescriptions, etc.). The flagship receives
            // each vertical's fixture so all enabled modules are explorable.
            $verticals = $definition['email'] === 'demo@zoomnearby.com'
                ? ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician']
                : [$definition['mode']];
            $admin = User::withoutGlobalScopes()->where('company_id', $company->id)->first();
            foreach ($verticals as $vertical) {
                app(TenantProvisioningService::class)->seedTenantDemoData($company, $vertical, $admin);
            }

            // The existing sample seeders understand the real schema and are
            // idempotent. Run the restaurant fixture where that vertical is
            // present; every workspace gets representative catalogue data.
            (new TenantDemoSeeder)->run($company);
            if ($definition['mode'] === 'restaurant') {
                (new RestaurantDemoSeeder)->run($company);
            }
        }

        $this->command?->info('Demo environment rebuilt: '.count(self::DEMOS).' workspaces.');
    }

    private function createAccount(array $definition, array $modules): Company
    {
        $provisioned = app(TenantProvisioningService::class)->registerTenant([
            'store_name' => $definition['name'],
            'slug' => $definition['slug'],
            'email' => $definition['email'],
            'admin_email' => $definition['email'],
            'owner_name' => $definition['name'].' Admin',
            'password' => self::PASSWORD,
            'admin_password' => self::PASSWORD,
            'pos_mode' => $definition['mode'],
            'plan_name' => 'professional',
            'seed_demo_data' => false,
            'sync_seed' => false,
        ]);

        $company = $provisioned['company'];
        $company->forceFill([
            'is_demo' => true,
            'is_seeding_complete' => true,
            'is_profile_completed' => true,
            // The all-in-one workspace has a retail operating mode for its
            // default checkout, but it must still expose the restaurant
            // vertical. Locking by operating mode alone hides KOT, tables and
            // KDS from that flagship account.
            'restaurant_mode_locked' => ! in_array('restaurant', $modules, true),
            'timezone' => 'Asia/Kolkata',
            'licensed_modules' => array_values(array_unique($modules)),
        ])->save();

        /** @var User $user */
        $user = $provisioned['user'];
        $user->forceFill([
            'is_demo' => true,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'status' => 'active',
        ])->save();

        if (! empty($definition['alias'])) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $definition['alias']],
                [
                    'company_id' => $company->id,
                    'name' => $definition['name'].' Alias Admin',
                    'login' => $definition['alias'],
                    'password' => Hash::make(self::PASSWORD),
                    'role' => User::ROLE_ADMINISTRATOR,
                    'status' => 'active',
                    'is_demo' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $company->id, 'key' => 'enabled_modules'],
                ['value' => json_encode(array_values(array_unique($modules))), 'updated_at' => now()]
            );
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $company->id, 'key' => 'store_operating_mode'],
                ['value' => json_encode(['mode' => $definition['mode']]), 'updated_at' => now()]
            );
        }

        return $company->fresh();
    }

    private function purgePreviousDemos(): void
    {
        $emails = array_values(array_filter(array_merge(
            array_column(self::DEMOS, 'email'),
            array_column(self::DEMOS, 'alias')
        )));
        $slugs = array_column(self::DEMOS, 'slug');
        $companyIds = Company::query()->withoutGlobalScopes()
            ->where(function ($query) use ($emails, $slugs) {
                $query->where('is_demo', true)->orWhereIn('email', $emails)->orWhereIn('slug', $slugs);
            })->pluck('id')->all();

        if ($companyIds === []) {
            return;
        }

        $mysql = DB::getDriverName() === 'mysql';
        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Delete dependent tenant rows first. This works across the optional
        // module tables, whose migrations do not all use the same FK name.
        foreach (Schema::getTableListing() as $table) {
            if ($table === 'companies' || $table === 'users') {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            foreach (['company_id', 'tenant_id'] as $column) {
                if (in_array($column, $columns, true)) {
                    DB::table($table)->whereIn($column, $companyIds)->delete();
                }
            }
        }

        User::query()->withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();
        Company::query()->withoutGlobalScopes()->whereIn('id', $companyIds)->delete();

        // Remove genuinely orphaned test users while leaving any legacy
        // super-admin user records protected by their role/email marker.
        $orphans = User::query()->withoutGlobalScopes()->whereNull('company_id');
        if (Schema::hasColumn('users', 'is_superadmin')) {
            $orphans->where(function ($query) {
                $query->whereNull('is_superadmin')->orWhere('is_superadmin', false);
            });
        }
        if (Schema::hasColumn('users', 'role')) {
            $orphans->whereNotIn('role', ['superadmin', 'super_admin']);
        }
        if (Schema::hasColumn('users', 'email')) {
            $orphans->where('email', 'not like', '%superadmin%');
        }
        $orphans->delete();

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
