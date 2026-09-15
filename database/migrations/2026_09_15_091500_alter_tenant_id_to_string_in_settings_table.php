<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['tenant_settings', 'settings'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                try {
                    DB::statement("ALTER TABLE `{$tableName}` MODIFY `tenant_id` VARCHAR(191) NOT NULL;");
                } catch (\Throwable) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->string('tenant_id', 191)->change();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // No reverse required
    }
};
