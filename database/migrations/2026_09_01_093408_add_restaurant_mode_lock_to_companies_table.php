<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Superadmin master lock: when true, Restaurant Mode is disabled for
            // this tenant regardless of its own pos_mode selection.
            $table->boolean('restaurant_mode_locked')->default(false)->after('pos_mode');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('restaurant_mode_locked');
        });
    }
};
