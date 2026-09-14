<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'lead_mod_sources',
            'lead_mod_leads',
            'lead_mod_activities',
            'pharmacy_mod_drug_batches',
            'pharmacy_mod_prescriptions',
            'repair_mod_device_categories',
            'repair_mod_tickets',
            'salon_mod_appointments',
            'salon_mod_services',
            'salon_mod_stylists',
            'reminders',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                try {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` VARCHAR(255) NOT NULL");
                } catch (\Throwable $e) {
                    // Fallback to nullable varchar if not null fails
                    try {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` VARCHAR(255) NULL");
                    } catch (\Throwable) {
                        // ignore if already modified or engine constraint
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as company_id must remain string to match companies.id
    }
};
