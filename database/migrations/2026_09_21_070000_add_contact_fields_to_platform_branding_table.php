<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_branding', 'head_office_address')) {
                $table->string('head_office_address', 255)->nullable()->after('support_phone');
            }
            if (! Schema::hasColumn('platform_branding', 'working_hours')) {
                $table->string('working_hours', 150)->nullable()->after('head_office_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (Schema::hasColumn('platform_branding', 'working_hours')) {
                $table->dropColumn('working_hours');
            }
            if (Schema::hasColumn('platform_branding', 'head_office_address')) {
                $table->dropColumn('head_office_address');
            }
        });
    }
};
