<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Wipe legacy repair tables completely
        Schema::dropIfExists('repair_ticket_items');
        Schema::dropIfExists('repair_checklists');
        Schema::dropIfExists('repair_ticket_parts');
        Schema::dropIfExists('repair_items');
        Schema::dropIfExists('repair_workbench_logs');
        Schema::dropIfExists('repair_device_categories');
        Schema::dropIfExists('repair_categories');
        Schema::dropIfExists('repair_tickets');

        // 2. Create fresh clean repair_tickets table
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('ticket_number', 100);
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number_or_imei', 100)->nullable()->index();
            $table->string('passcode_pattern', 100)->nullable();
            $table->text('problem_reported');
            $table->text('technician_diagnosis')->nullable();
            $table->text('physical_condition_notes')->nullable();
            $table->string('assigned_technician_id')->nullable()->index();
            $table->string('status', 40)->default('received')->index(); // received, diagnosing, waiting_parts, in_progress, ready, delivered, cancelled
            $table->string('priority', 30)->default('normal')->index(); // low, normal, high, urgent
            $table->decimal('estimated_cost', 12, 2)->default(0.00);
            $table->decimal('advance_deposit', 12, 2)->default(0.00);
            $table->string('advance_payment_method', 50)->nullable();
            $table->unsignedBigInteger('advance_sale_id')->nullable()->index();
            $table->unsignedBigInteger('final_sale_id')->nullable()->index();
            $table->json('inspection_checklist')->nullable();
            $table->dateTime('expected_delivery_at')->nullable()->index();
            $table->dateTime('intake_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable()->index();
            $table->dateTime('delivered_at')->nullable()->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('assigned_technician_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('advance_sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('final_sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->unique(['company_id', 'ticket_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_technician_id', 'status']);
        });

        // 3. Create fresh clean repair_ticket_items table (Parts & Labor Lines)
        Schema::create('repair_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('item_name', 200);
            $table->string('item_type', 40)->default('spare_part')->index(); // spare_part, service_labor
            $table->decimal('quantity', 12, 2)->default(1.00);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->string('tax_id')->nullable()->index();
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->boolean('billed_to_customer')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('repair_tickets')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('tax_id')->references('id')->on('tax_rules')->nullOnDelete();
            $table->index(['company_id', 'ticket_id', 'item_type']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('repair_ticket_items');
        Schema::dropIfExists('repair_tickets');
        Schema::enableForeignKeyConstraints();
    }
};
