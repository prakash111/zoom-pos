<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags a tenant provisioned by DemoAccountsSeeder. Combined with
     * `config('app.demo_mode')` it drives PreventDemoModifications and the
     * SDUI "view-only" restrictions. (`users.is_demo` already exists.)
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_seeding_complete');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
