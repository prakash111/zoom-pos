<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'terms')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->text('terms')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'terms')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }
    }
};
