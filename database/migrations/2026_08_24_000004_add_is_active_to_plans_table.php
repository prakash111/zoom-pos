<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans') && ! Schema::hasColumn('plans', 'is_active')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('active');
            });

            DB::table('plans')->update([
                'is_active' => DB::raw('active'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'is_active')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
