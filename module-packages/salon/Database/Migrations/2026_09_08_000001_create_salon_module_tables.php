<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables owned by the "salon" package module. Prefixed `salon_mod_` so the
 * package is self-contained and never collides with a host that already
 * ships a built-in salon / service-booking vertical.
 *
 * Run by ModulePackageService::activate(); rolled back by
 * ModulePackageService::uninstall($dropData = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_mod_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->integer('duration_minutes')->default(30);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salon_mod_stylists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('specialties')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salon_mod_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('stylist_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('booked'); // booked|confirmed|in_service|completed|no_show|cancelled
            $table->decimal('price', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_mod_appointments');
        Schema::dropIfExists('salon_mod_stylists');
        Schema::dropIfExists('salon_mod_services');
    }
};
