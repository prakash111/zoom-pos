<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->string('version', 40)->nullable()->after('slug');
            $table->string('author', 160)->nullable()->after('version');
            $table->string('min_system_version', 40)->nullable()->after('author');
            $table->string('source_type', 20)->default('manual')->after('min_system_version');
            $table->string('package_path', 255)->nullable()->after('source_type');
            $table->timestamp('installed_at')->nullable()->after('package_path');
        });
    }

    public function down(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->dropColumn(['version', 'author', 'min_system_version', 'source_type', 'package_path', 'installed_at']);
        });
    }
};
