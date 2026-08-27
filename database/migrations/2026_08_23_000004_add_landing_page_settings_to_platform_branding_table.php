<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_branding', 'landing_page_enabled')) {
                $table->boolean('landing_page_enabled')->default(false)->after('favicon_url');
            }
            if (! Schema::hasColumn('platform_branding', 'landing_page_id')) {
                $table->foreignId('landing_page_id')->nullable()->after('landing_page_enabled')
                    ->constrained('pages')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (Schema::hasColumn('platform_branding', 'landing_page_id')) {
                $table->dropConstrainedForeignId('landing_page_id');
            }
            if (Schema::hasColumn('platform_branding', 'landing_page_enabled')) {
                $table->dropColumn('landing_page_enabled');
            }
        });
    }
};
