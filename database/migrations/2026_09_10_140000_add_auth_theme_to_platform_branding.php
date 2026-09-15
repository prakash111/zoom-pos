<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superadmin-controlled global branding + theme for the pre-auth screens
     * (Splash, Login, Register, Forgot Password). `primary_color` already
     * exists; these add the rest of the palette plus the auth marketing copy.
     */
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->string('secondary_color', 32)->nullable()->default('#0F172A')->after('primary_color');
            $table->string('accent_color', 32)->nullable()->default('#FF7A00')->after('secondary_color');
            $table->string('splash_bg_color', 32)->nullable()->default('#0F172A')->after('accent_color');
            $table->string('auth_bg_color', 32)->nullable()->default('#F8FAFC')->after('splash_bg_color');
            $table->string('auth_headline', 150)->nullable()->after('auth_bg_color');
            $table->string('auth_description', 255)->nullable()->after('auth_headline');
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->dropColumn([
                'secondary_color', 'accent_color', 'splash_bg_color',
                'auth_bg_color', 'auth_headline', 'auth_description',
            ]);
        });
    }
};
