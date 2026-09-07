<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant repair intake checklist definition, so a shoe-repair or
     * drone-servicing shop configures its own diagnostic checkpoints instead
     * of the hardcoded mobile-device checks. Null = fall back to defaults /
     * device-category checklist points.
     *
     * Shape: [{ "key": "power_boot", "label": "Power On / Boot", "default": "pass" }, ...]
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'repair_checklist_schema')) {
                $table->json('repair_checklist_schema')->nullable()->after('salon_policy_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('repair_checklist_schema');
        });
    }
};
