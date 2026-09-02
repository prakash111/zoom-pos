<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'nav_config')) {
                // {"hidden_tiles": ["quotations", ...], "section_order": ["financial_management", ...]}
                // Lets a tenant hide mobile-app nav destinations it doesn't use and
                // reorder the drawer's section groups, without a client update —
                // see AppBootstrapController.
                $table->json('nav_config')->nullable()->after('restaurant_mode_locked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('nav_config');
        });
    }
};
