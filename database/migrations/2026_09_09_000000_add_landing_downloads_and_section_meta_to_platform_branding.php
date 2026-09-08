<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_branding', 'landing_playstore_url')) {
                $table->string('landing_playstore_url', 500)->nullable()->after('landing_hero_banner_image_url');
            }
            if (! Schema::hasColumn('platform_branding', 'landing_playstore_enabled')) {
                $table->boolean('landing_playstore_enabled')->default(false)->after('landing_playstore_url');
            }
            if (! Schema::hasColumn('platform_branding', 'landing_windows_url')) {
                $table->string('landing_windows_url', 500)->nullable()->after('landing_playstore_enabled');
            }
            if (! Schema::hasColumn('platform_branding', 'landing_windows_enabled')) {
                $table->boolean('landing_windows_enabled')->default(false)->after('landing_windows_url');
            }
            // Per-section title / subtitle overrides keyed by section slug
            // (hero, features, downloads, pricing, faq). Enable flags stay in
            // landing_sections_config.
            if (! Schema::hasColumn('platform_branding', 'landing_section_meta')) {
                $table->json('landing_section_meta')->nullable()->after('landing_sections_config');
            }
            // Editable FAQ entries: [{ "q": "...", "a": "..." }, ...]
            if (! Schema::hasColumn('platform_branding', 'landing_faqs')) {
                $table->json('landing_faqs')->nullable()->after('landing_section_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            foreach ([
                'landing_playstore_url', 'landing_playstore_enabled',
                'landing_windows_url', 'landing_windows_enabled',
                'landing_section_meta', 'landing_faqs',
            ] as $column) {
                if (Schema::hasColumn('platform_branding', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
