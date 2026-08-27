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
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->string('superadmin_sidebar_color', 32)->nullable()->default('#4338ca')->after('primary_color');
            $table->string('landing_primary_color', 32)->nullable()->default('#10b981')->after('superadmin_sidebar_color');
            $table->string('landing_accent_color', 32)->nullable()->default('#d7f24e')->after('landing_primary_color');
            $table->string('landing_hero_badge')->nullable()->after('landing_accent_color');
            $table->string('landing_hero_title')->nullable()->after('landing_hero_badge');
            $table->text('landing_hero_subtitle')->nullable()->after('landing_hero_title');
            $table->string('landing_hero_cta_primary_text')->nullable()->after('landing_hero_subtitle');
            $table->string('landing_hero_cta_primary_url')->nullable()->after('landing_hero_cta_primary_text');
            $table->string('landing_hero_cta_secondary_text')->nullable()->after('landing_hero_cta_primary_url');
            $table->string('landing_hero_cta_secondary_url')->nullable()->after('landing_hero_cta_secondary_text');
            $table->string('landing_hero_banner_image_url')->nullable()->after('landing_hero_cta_secondary_url');
            $table->json('landing_sections_config')->nullable()->after('landing_hero_banner_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->dropColumn([
                'superadmin_sidebar_color',
                'landing_primary_color',
                'landing_accent_color',
                'landing_hero_badge',
                'landing_hero_title',
                'landing_hero_subtitle',
                'landing_hero_cta_primary_text',
                'landing_hero_cta_primary_url',
                'landing_hero_cta_secondary_text',
                'landing_hero_cta_secondary_url',
                'landing_hero_banner_image_url',
                'landing_sections_config',
            ]);
        });
    }
};
