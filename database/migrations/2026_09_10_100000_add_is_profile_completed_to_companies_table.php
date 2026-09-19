<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether the store owner has walked the Store Profile setup wizard
     * to its final tab at least once. Drives the "Save & Continue" vs
     * "Complete Setup & Open Dashboard" onboarding flow.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_profile_completed')->default(false)->after('is_seeding_complete');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_profile_completed');
        });
    }
};
