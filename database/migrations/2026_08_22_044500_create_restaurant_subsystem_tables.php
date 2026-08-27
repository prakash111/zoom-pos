<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dining Floors
        Schema::create('dining_floors', function (Blueprint $table) {
            $table->string('id')->primary(); // flr_<hex>
            $table->string('company_id');
            $table->string('name');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // 2. Dining Tables
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->string('id')->primary(); // tbl_<hex>
            $table->string('company_id');
            $table->string('dining_floor_id')->nullable();
            $table->string('table_number');
            $table->integer('seating_capacity')->default(4);
            $table->string('status', 30)->default('available'); // available|occupied|reserved|billed
            $table->unsignedBigInteger('current_sale_id')->nullable();
            $table->integer('guest_count')->default(0);
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('dining_floor_id')->references('id')->on('dining_floors')->nullOnDelete();
        });

        // 3. Add Restaurant Fields to Sales
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'service_type')) {
                $table->string('service_type', 30)->default('dine_in')->after('operation_type');
            }
            if (! Schema::hasColumn('sales', 'dining_table_id')) {
                $table->string('dining_table_id')->nullable()->after('service_type');
            }
            if (! Schema::hasColumn('sales', 'table_name')) {
                $table->string('table_name')->nullable()->after('dining_table_id');
            }
            if (! Schema::hasColumn('sales', 'guest_count')) {
                $table->integer('guest_count')->default(1)->after('table_name');
            }
            if (! Schema::hasColumn('sales', 'pickup_time')) {
                $table->string('pickup_time')->nullable()->after('guest_count');
            }
            if (! Schema::hasColumn('sales', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('pickup_time');
            }
            if (! Schema::hasColumn('sales', 'driver_name')) {
                $table->string('driver_name')->nullable()->after('delivery_address');
            }
            if (! Schema::hasColumn('sales', 'driver_phone')) {
                $table->string('driver_phone')->nullable()->after('driver_name');
            }
            if (! Schema::hasColumn('sales', 'dispatch_status')) {
                $table->string('dispatch_status', 30)->nullable()->after('driver_phone');
            }
            if (! Schema::hasColumn('sales', 'kot_status')) {
                $table->string('kot_status', 30)->nullable()->after('dispatch_status');
            }
        });

        // 4. Add Food Variants, Modifiers and Image to Products
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('name');
            }
            if (! Schema::hasColumn('products', 'variants')) {
                $table->json('variants')->nullable()->after('sale_price');
            }
            if (! Schema::hasColumn('products', 'modifiers')) {
                $table->json('modifiers')->nullable()->after('variants');
            }
        });

        // 5. Kitchen Order Tickets (KOT)
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->string('id')->primary(); // kot_<hex>
            $table->string('company_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('kot_number', 50);
            $table->string('dining_table_id')->nullable();
            $table->string('table_name')->nullable();
            $table->string('service_type', 30)->default('dine_in');
            $table->string('status', 30)->default('pending'); // pending|preparing|ready|served|cancelled
            $table->string('server_name')->nullable();
            $table->json('items')->nullable();
            $table->text('kitchen_notes')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'variants', 'modifiers']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'service_type', 'dining_table_id', 'table_name', 'guest_count',
                'pickup_time', 'delivery_address', 'driver_name', 'driver_phone',
                'dispatch_status', 'kot_status',
            ]);
        });

        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_floors');
    }
};
