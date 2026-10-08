<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LoyaltyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $moduleSeeder = new \Modules\Loyalty\Database\Seeders\LoyaltyPermissionSeeder();
        $moduleSeeder->run();
    }
}
