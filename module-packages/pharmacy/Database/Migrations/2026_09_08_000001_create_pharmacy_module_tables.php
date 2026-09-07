<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables owned by the "pharmacy" package module. Prefixed `pharmacy_mod_` so
 * the package is self-contained and never collides with a host that already
 * ships a built-in pharmacy vertical.
 *
 * Run by ModulePackageService::activate(); rolled back by
 * ModulePackageService::uninstall($dropData = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_mod_drug_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('product_name');
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'expiry_date']);
        });

        Schema::create('pharmacy_mod_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('rx_number')->unique();
            $table->string('patient_name');
            $table->string('patient_phone')->nullable();
            $table->string('doctor_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending | dispensed | cancelled
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('pharmacy_mod_prescription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id')->index();
            $table->string('drug_name');
            $table->string('dosage')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_mod_prescription_items');
        Schema::dropIfExists('pharmacy_mod_prescriptions');
        Schema::dropIfExists('pharmacy_mod_drug_batches');
    }
};
