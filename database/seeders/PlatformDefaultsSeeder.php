<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use Illuminate\Database\Seeder;

class PlatformDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0,
            'features' => ['multi_location' => false, 'automatic_backup' => false],
            'limits' => ['usuarios' => 2, 'dispositivos' => 1, 'armazenamento_mb' => 100, 'filiais' => 1],
        ]);

        Plan::query()->firstOrCreate(['name' => 'starter'], [
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19,
            'features' => ['multi_location' => false, 'automatic_backup' => true],
            'limits' => ['usuarios' => 5, 'dispositivos' => 3, 'armazenamento_mb' => 1024, 'filiais' => 1],
        ]);

        Plan::query()->firstOrCreate(['name' => 'professional'], [
            'display_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 199,
            'features' => ['multi_location' => true, 'automatic_backup' => true],
            'limits' => ['usuarios' => 25, 'dispositivos' => 10, 'armazenamento_mb' => 10240, 'filiais' => 5],
        ]);

        PlatformBranding::current();

        foreach ([
            'maintenance_mode' => '0',
            'maintenance_message' => 'The platform is undergoing scheduled maintenance. Please try again shortly.',
            'min_client_build_version' => '0',
            'app_version' => '1.0.0',
            'otp_registration_enabled' => '0',
        ] as $key => $value) {
            PlatformSystem::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
