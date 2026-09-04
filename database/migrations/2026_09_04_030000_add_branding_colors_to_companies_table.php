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
            if (! Schema::hasColumn('companies', 'accent_color')) {
                $table->string('accent_color', 16)->nullable()->after('primary_color');
            }
            if (! Schema::hasColumn('companies', 'drawer_bg')) {
                $table->string('drawer_bg', 16)->nullable()->after('accent_color');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'drawer_bg')) {
                $table->dropColumn('drawer_bg');
            }
            if (Schema::hasColumn('companies', 'accent_color')) {
                $table->dropColumn('accent_color');
            }
        });
    }
};
