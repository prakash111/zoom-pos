<?php

use App\Services\Navigation\NavigationMenuService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        NavigationMenuService::cleanTenantNavigation('pharmacy.demo@zoomnearby.com');
    }

    public function down(): void
    {
        // No down migration needed for clearing stale navigation cache
    }
};
