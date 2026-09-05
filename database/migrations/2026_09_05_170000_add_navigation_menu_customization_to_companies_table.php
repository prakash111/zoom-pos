<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'navigation_menu_customization')) {
                $table->json('navigation_menu_customization')->nullable()->after('nav_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'navigation_menu_customization')) {
                $table->dropColumn('navigation_menu_customization');
            }
        });
    }
};
