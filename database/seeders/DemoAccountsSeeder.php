<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioningService;
use App\Services\Tenancy\TenantSampleDataService;
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

                $licensed = is_array($company->licensed_modules) ? $company->licensed_modules : [];
                if (! in_array('chat', $licensed, true)) {
                    $licensed[] = 'chat';
                }

                $company->forceFill([
                    'is_demo' => true,
                    'is_seeding_complete' => true,
                    'is_profile_completed' => true,
                    // `restaurant_mode_locked` HIDES the restaurant vertical
                    // (Tables / KOT / KDS nav section + dashboard shortcuts).
                    // Lock it for every demo store EXCEPT the cafe one, which
                    // must show the Cafe & Restaurant navigation.
                    'restaurant_mode_locked' => $meta['pos_mode'] !== 'restaurant',
                    // Every self-service demo store runs on India time so the
                    // dashboard's "today" hourly chart and KOT/alarm timers
                    // line up with when people actually try the demo.
                    'timezone' => 'Asia/Kolkata',
                    'licensed_modules' => array_values(array_unique($licensed)),
                ])->save();

                $user->forceFill([
                    'is_demo' => true,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ])->save();

                if (class_exists(DemoChatSeeder::class)) {
                    (new DemoChatSeeder)->run($company);
                }

                $this->applyAiConfigurations($company);

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
        if (! $company) {
            return;
        }

        $licensed = is_array($company->licensed_modules) ? $company->licensed_modules : [];
        if (! in_array('chat', $licensed, true)) {
            $licensed[] = 'chat';
        }

        $company->forceFill([
            'is_demo' => true,
            'pos_mode' => $posMode,
            // Show the Cafe & Restaurant nav only for the restaurant demo.
            'restaurant_mode_locked' => $posMode !== 'restaurant',
            'timezone' => 'Asia/Kolkata',
            'licensed_modules' => array_values(array_unique($licensed)),
        ])->save();

        if (class_exists(DemoChatSeeder::class)) {
            (new DemoChatSeeder)->run($company);
        }

        // Re-apply the store profile, T&C / bank details and placeholder logo
        // for an already-provisioned demo tenant (a plain refresh otherwise
        // only touches auth/flags).
        app(TenantSampleDataService::class)
            ->seedBusinessProfile($company, $posMode);

        $this->applyAiConfigurations($company);
    }

    private function applyAiConfigurations(Company $company): void
    {
        $geminiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY', '');
        \App\Models\Configuration::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'default_ai_provider'],
            ['value' => 'gemini']
        );
        \App\Models\Configuration::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'gemini_api_key'],
            ['value' => $geminiKey]
        );
        \App\Models\Configuration::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'gemini_model'],
            ['value' => 'gemini-2.5-flash']
        );
        \App\Models\Configuration::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'enable_ai'],
            ['value' => '1']
        );
        \App\Models\Configuration::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'ai_enabled'],
            ['value' => '1']
        );
    }
}
