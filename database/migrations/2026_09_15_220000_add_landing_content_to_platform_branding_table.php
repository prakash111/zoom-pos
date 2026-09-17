<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_branding', 'landing_content')) {
                $table->json('landing_content')->nullable()->after('landing_testimonials');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (Schema::hasColumn('platform_branding', 'landing_content')) {
                $table->dropColumn('landing_content');
            }
        });
    }
};
