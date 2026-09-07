<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables owned by the "repairtechnician" package module. Prefixed
 * `repair_mod_` so the package is self-contained and never collides with a
 * host that already ships a built-in repair vertical.
 *
 * Run by ModulePackageService::activate(); rolled back by
 * ModulePackageService::uninstall($dropData = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_mod_device_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->decimal('default_diagnostic_fee', 12, 2)->default(0);
            $table->json('checklist_points')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_mod_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('ticket_number')->unique();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('imei_serial')->nullable();
            $table->text('reported_issue')->nullable();
            $table->string('status')->default('received'); // received|diagnosing|waiting_parts|in_progress|ready|delivered|cancelled
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('advance_paid', 12, 2)->default(0);
            $table->json('inspection_checklist')->nullable();
            $table->text('technician_notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('repair_mod_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->string('item_type')->default('part'); // part | labor
            $table->string('name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_mod_ticket_items');
        Schema::dropIfExists('repair_mod_tickets');
        Schema::dropIfExists('repair_mod_device_categories');
    }
};
