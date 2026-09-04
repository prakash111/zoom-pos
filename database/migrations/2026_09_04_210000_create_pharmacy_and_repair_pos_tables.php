<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend products table with pharmacy & generic medicine fields
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'generic_name')) {
                $table->string('generic_name')->nullable()->index()->after('name');
            }
            if (! Schema::hasColumn('products', 'composition')) {
                $table->text('composition')->nullable()->after('generic_name');
            }
            if (! Schema::hasColumn('products', 'narcotic_schedule')) {
                $table->string('narcotic_schedule', 50)->nullable()->index()->after('requires_prescription');
            }
        });

        // 2. Pharmacy Batches table (FEFO & Expiry tracking)
        if (! Schema::hasTable('pharmacy_batches')) {
            Schema::create('pharmacy_batches', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('batch_number', 100)->index();
                $table->date('manufacturing_date')->nullable();
                $table->date('expiry_date')->index();
                $table->decimal('cost_price', 12, 2)->default(0);
                $table->decimal('selling_price', 12, 2)->default(0);
                $table->integer('stock_qty')->default(0);
                $table->unsignedSmallInteger('alert_days_before_expiry')->default(90);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_demo')->default(false)->index();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->index(['company_id', 'product_id', 'expiry_date']);
                $table->index(['company_id', 'is_active', 'expiry_date']);
            });
        }

        // 3. Pharmacy Prescriptions table (Rx queue & validation)
        if (! Schema::hasTable('pharmacy_prescriptions')) {
            Schema::create('pharmacy_prescriptions', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('prescription_number', 100)->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('patient_name', 150)->index();
                $table->string('patient_phone', 50)->nullable();
                $table->string('doctor_name', 150)->index();
                $table->string('doctor_registration_no', 100)->nullable();
                $table->date('prescription_date')->index();
                $table->text('diagnosis')->nullable();
                $table->json('medicines')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 50)->default('pending')->index(); // pending, dispensed, cancelled
                $table->dateTime('dispensed_at')->nullable()->index();
                $table->string('dispensed_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('sale_id')->nullable()->index();
                $table->boolean('is_demo')->default(false)->index();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        // 4. Repair & Technician Tickets table
        if (! Schema::hasTable('repair_tickets')) {
            Schema::create('repair_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('ticket_number', 100)->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name', 150);
                $table->string('customer_phone', 50)->index();
                $table->string('device_type', 100)->index(); // Smartphone, Laptop, Tablet, Audio, Appliance
                $table->string('brand', 100)->index();
                $table->string('model', 100)->index();
                $table->string('serial_or_imei', 100)->nullable()->index();
                $table->string('passcode_or_pattern', 100)->nullable();
                $table->text('issue_description');
                $table->text('physical_condition_notes')->nullable();
                $table->string('status', 50)->default('active')->index(); // active, diagnosing, waiting_parts, in_progress, repaired, delivered, cancelled
                $table->string('priority', 50)->default('normal')->index(); // low, normal, high, urgent
                $table->string('technician_id')->nullable()->index();
                $table->decimal('estimated_cost', 12, 2)->default(0);
                $table->decimal('advance_paid', 12, 2)->default(0);
                $table->decimal('labor_fee', 12, 2)->default(0);
                $table->decimal('parts_cost', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->unsignedBigInteger('final_sale_id')->nullable()->index();
                $table->text('internal_notes')->nullable();
                $table->dateTime('intake_at')->nullable()->index();
                $table->dateTime('completed_at')->nullable()->index();
                $table->dateTime('delivered_at')->nullable()->index();
                $table->boolean('is_demo')->default(false)->index();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'technician_id', 'status']);
            });
        }

        // 5. Repair Ticket Parts table (Spare parts billed & deducted from stock)
        if (! Schema::hasTable('repair_ticket_parts')) {
            Schema::create('repair_ticket_parts', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('repair_ticket_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('part_name', 200);
                $table->integer('quantity')->default(1);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->boolean('billed_to_customer')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('repair_ticket_id')->references('id')->on('repair_tickets')->cascadeOnDelete();
            });
        }

        // 6. Repair Checklists table (Intake & Post-repair quality checks)
        if (! Schema::hasTable('repair_checklists')) {
            Schema::create('repair_checklists', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('repair_ticket_id')->index();
                $table->string('item_name', 150);
                $table->string('type', 50)->default('intake'); // intake, post_repair
                $table->string('status', 50)->default('pass'); // pass, fail, not_tested
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('repair_ticket_id')->references('id')->on('repair_tickets')->cascadeOnDelete();
                $table->index(['repair_ticket_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_checklists');
        Schema::dropIfExists('repair_ticket_parts');
        Schema::dropIfExists('repair_tickets');
        Schema::dropIfExists('pharmacy_prescriptions');
        Schema::dropIfExists('pharmacy_batches');

        Schema::table('products', function (Blueprint $table) {
            $cols = array_filter(['generic_name', 'composition', 'narcotic_schedule'], fn ($c) => Schema::hasColumn('products', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
