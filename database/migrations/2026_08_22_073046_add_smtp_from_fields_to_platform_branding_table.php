<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->string('smtp_from_address')->nullable()->after('smtp_encryption');
            $table->string('smtp_from_name')->nullable()->after('smtp_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('platform_branding', function (Blueprint $table) {
            $table->dropColumn(['smtp_from_address', 'smtp_from_name']);
        });
    }
};
