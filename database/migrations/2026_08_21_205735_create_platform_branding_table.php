<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton row (id = 1): platform white-label branding + SMTP settings.
        Schema::create('platform_branding', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name')->default('Smart Inventory & Sales');
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('primary_color', 16)->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted cast
            $table->string('smtp_encryption', 16)->nullable(); // tls|ssl|null
            $table->json('expiration_reminder_thresholds')->nullable(); // e.g. [30,15,7,3,1]
            $table->boolean('otp_registration_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_branding');
    }
};
