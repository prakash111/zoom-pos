<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Service Orders & Warranty Repair Tracking Table
        if (! Schema::hasTable('service_orders')) {
            Schema::create('service_orders', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('order_number')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name');
                $table->string('customer_phone')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('equipment_name');
                $table->string('brand_model')->nullable();
                $table->string('serial_number')->nullable()->index(); // Serial / IMEI
                $table->text('reported_defect');
                $table->text('technical_diagnosis')->nullable();
                $table->json('parts_used')->nullable(); // [{product_id, name, quantity, unit_price, total}]
                $table->decimal('parts_total', 14, 2)->default(0);
                $table->decimal('labor_cost', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('status', 30)->default('received'); // received, under_diagnosis, waiting_parts_approval, ready_for_pickup, delivered_settled, cancelled
                $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
                $table->string('warranty_period', 50)->nullable()->default('90 days');
                $table->text('warranty_terms')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->string('technician_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        // 2. Enable Consignments Toggle on Companies
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'enable_consignments')) {
                $table->boolean('enable_consignments')->default(true)->after('pos_mode');
            }
        });

        // 3. Commission Type on Sales table
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'commission_type')) {
                $table->string('commission_type', 30)->default('percentage')->after('commission_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'enable_consignments')) {
                $table->dropColumn('enable_consignments');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'commission_type')) {
                $table->dropColumn('commission_type');
            }
        });
    }
};
