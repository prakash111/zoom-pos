<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Platform defaults, landing-page content and permissions are always
     * seeded. The demo tenants (a sample retail store and a sample restaurant)
     * are only seeded when DEMO_MODE=true, so a production install starts with
     * a clean database.
     */
    public function run(): void
    {
        $this->call([
            PlatformDefaultsSeeder::class,
            LandingPageSeeder::class,
            PermissionsTableSeeder::class,
        ]);

        if (config('app.demo_mode')) {
            $this->call([
                TenantDemoSeeder::class,
                RestaurantDemoSeeder::class,
            ]);
        } else {
            $this->command?->info('DEMO_MODE is off — skipping demo tenant seeders.');
        }
    }
}
