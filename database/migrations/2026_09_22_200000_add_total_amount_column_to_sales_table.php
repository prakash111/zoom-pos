<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'total_amount')) {
            try {
                DB::statement('ALTER TABLE sales ADD COLUMN total_amount DECIMAL(15,2) GENERATED ALWAYS AS (total) VIRTUAL');
            } catch (\Throwable $e) {
                Schema::table('sales', function (Blueprint $table) {
                    $table->decimal('total_amount', 15, 2)->nullable()->after('total');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'total_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('total_amount');
            });
        }
    }
};
