<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generic platform-wide key/value settings: maintenance_mode, maintenance_message,
        // min_client_build_version, app_version, expiration_reminder_thresholds, etc.
        Schema::create('platform_system', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_system');
    }
};
