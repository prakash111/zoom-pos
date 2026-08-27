<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'theme_color')) {
                $table->string('theme_color', 30)->default('blue')->after('primary_color');
            }
            if (! Schema::hasColumn('companies', 'pos_layout')) {
                $table->string('pos_layout', 30)->default('standard')->after('theme_color');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['theme_color', 'pos_layout']);
        });
    }
};
