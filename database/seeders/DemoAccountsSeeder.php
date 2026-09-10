<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * One shared, self-service demo tenant per store type. Only runs while
 * `config('app.demo_mode')` is true; every account (company + admin user) is
 * flagged `is_demo` so PreventDemoModifications + the SDUI view-only layer
 * kick in. Idempotent — re-running just refreshes the password / flags.
 */
class DemoAccountsSeeder extends Seeder
{
    /**
     * store_type (as advertised) => [email, store_id/slug, pos_mode, label].
     */
    public const ACCOUNTS = [
        'RETAIL' => [
            'email' => 'retail@demo.com',
            'store_id' => 'RETAIL-DEMO',
            'pos_mode' => 'retail',
            'label' => 'Retail',
        ],
        'RESTAURANT' => [
            'email' => 'cafe@demo.com',
            'store_id' => 'CAFE-DEMO',
            'pos_mode' => 'restaurant',
            'label' => 'Cafe & Restaurant',
        ],
        'PHARMACY' => [
            'email' => 'pharmacy@demo.com',
            'store_id' => 'PHARMA-DEMO',
            'pos_mode' => 'pharmacy',
            'label' => 'Pharmacy',
        ],
        'REPAIR_TECHNICIAN' => [
            'email' => 'repair@demo.com',
            'store_id' => 'REPAIR-DEMO',
            'pos_mode' => 'repair_technician',
            'label' => 'Repair Technician',
        ],
        'SALON_BOOKINGS' => [
            'email' => 'salon@demo.com',
            'store_id' => 'SALON-DEMO',
            'pos_mode' => 'service_booking',
            'label' => 'Salon & Bookings',
        ],
    ];

    public const PASSWORD = 'demo1234';

    public function run(): void
    {
        if (! config('app.demo_mode')) {
            $this->command?->warn('DEMO_MODE is off — DemoAccountsSeeder skipped.');

            return;
        }

        $provisioner = app(TenantProvisioningService::class);

        foreach (self::ACCOUNTS as $storeType => $meta) {
            $existing = User::query()->withoutGlobalScopes()
                ->where('email', $meta['email'])->first();

            if ($existing) {
                $this->refresh($existing, $storeType);
                $this->command?->info("Refreshed demo account {$meta['email']}");

                continue;
            }

            try {
                $result = $provisioner->registerTenant([
                    'store_name' => $meta['label'].' Demo Store',
                    'slug' => strtolower($meta['store_id']),
                    'email' => $meta['email'],
                    'admin_email' => $meta['email'],
                    'name' => $meta['label'].' Demo Admin',
                    'admin_name' => $meta['label'].' Demo Admin',
                    'password' => self::PASSWORD,
                    'admin_password' => self::PASSWORD,
                    'pos_mode' => $meta['pos_mode'],
                    'plan_name' => 'professional',
                    'seed_demo_data' => true,
                    'sync_seed' => true,
                ]);

                /** @var Company $company */
                $company = $result['company'];
                /** @var User $user */
                $user = $result['user'];

                $company->forceFill([
                    'is_demo' => true,
                    'is_seeding_complete' => true,
                    'is_profile_completed' => true,
                    // `restaurant_mode_locked` HIDES the restaurant vertical
                    // (Tables / KOT / KDS nav section + dashboard shortcuts).
                    // Lock it for every demo store EXCEPT the cafe one, which
                    // must show the Cafe & Restaurant navigation.
                    'restaurant_mode_locked' => $meta['pos_mode'] !== 'restaurant',
                ])->save();

                $user->forceFill([
                    'is_demo' => true,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ])->save();

                $this->command?->info("Created demo account {$meta['email']} ({$storeType})");
            } catch (\Throwable $e) {
                Log::error("DemoAccountsSeeder: {$meta['email']} failed: ".$e->getMessage());
                $this->command?->error("Failed {$meta['email']}: ".$e->getMessage());
            }
        }
    }

    private function refresh(User $user, string $storeType): void
    {
        $user->forceFill([
            'is_demo' => true,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'status' => 'active',
        ])->save();

        $posMode = self::ACCOUNTS[$storeType]['pos_mode'];
        $company = Company::query()->withoutGlobalScopes()->find($user->company_id);
        $company?->forceFill([
            'is_demo' => true,
            'pos_mode' => $posMode,
            // Show the Cafe & Restaurant nav only for the restaurant demo.
            'restaurant_mode_locked' => $posMode !== 'restaurant',
        ])->save();
    }
}
