<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('name');
                }
                if (! Schema::hasColumn('categories', 'code')) {
                    $table->string('code', 50)->nullable()->after('name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
                if (Schema::hasColumn('categories', 'code')) {
                    $table->dropColumn('code');
                }
            });
        }
    }
};
