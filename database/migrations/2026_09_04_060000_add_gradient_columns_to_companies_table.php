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
            if (! Schema::hasColumn('companies', 'drawer_gradient_enabled')) {
                $table->boolean('drawer_gradient_enabled')->default(false)->after('drawer_bg');
            }
            if (! Schema::hasColumn('companies', 'drawer_gradient_start')) {
                $table->string('drawer_gradient_start', 16)->nullable()->after('drawer_gradient_enabled');
            }
            if (! Schema::hasColumn('companies', 'drawer_gradient_end')) {
                $table->string('drawer_gradient_end', 16)->nullable()->after('drawer_gradient_start');
            }
            if (! Schema::hasColumn('companies', 'drawer_gradient_direction')) {
                $table->string('drawer_gradient_direction', 32)->default('top_to_bottom')->after('drawer_gradient_end');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'drawer_gradient_direction')) {
                $table->dropColumn('drawer_gradient_direction');
            }
            if (Schema::hasColumn('companies', 'drawer_gradient_end')) {
                $table->dropColumn('drawer_gradient_end');
            }
            if (Schema::hasColumn('companies', 'drawer_gradient_start')) {
                $table->dropColumn('drawer_gradient_start');
            }
            if (Schema::hasColumn('companies', 'drawer_gradient_enabled')) {
                $table->dropColumn('drawer_gradient_enabled');
            }
        });
    }
};
