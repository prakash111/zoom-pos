<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vertical-specific footer text for the "Receipt Prefixes & Bank Terms"
     * settings screen. Only the field(s) for a tenant's enabled module(s) are
     * ever shown / editable — see SchemaResponse::receiptsView().
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'dispensing_disclaimer')) {
                $table->text('dispensing_disclaimer')->nullable()->after('bank_details');
            }
            if (! Schema::hasColumn('companies', 'repair_warranty_terms')) {
                $table->text('repair_warranty_terms')->nullable()->after('dispensing_disclaimer');
            }
            if (! Schema::hasColumn('companies', 'salon_policy_terms')) {
                $table->text('salon_policy_terms')->nullable()->after('repair_warranty_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['dispensing_disclaimer', 'repair_warranty_terms', 'salon_policy_terms']);
        });
    }
};
