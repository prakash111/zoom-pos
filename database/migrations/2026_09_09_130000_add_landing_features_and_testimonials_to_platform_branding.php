<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            // Editable "Feature Modules" entries: [{ "icon", "title", "body",
            // "mockup"? }, ...]. Empty/null -> the built-in default set is
            // rendered (see PlatformBranding::landingFeatures()).
            if (! Schema::hasColumn('platform_branding', 'landing_features')) {
                $table->json('landing_features')->nullable()->after('landing_faqs');
            }
            // Editable customer testimonials: [{ "quote", "name", "role" }, ...].
            if (! Schema::hasColumn('platform_branding', 'landing_testimonials')) {
                $table->json('landing_testimonials')->nullable()->after('landing_features');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            foreach (['landing_features', 'landing_testimonials'] as $column) {
                if (Schema::hasColumn('platform_branding', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
