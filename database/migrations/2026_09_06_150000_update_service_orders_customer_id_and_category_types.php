<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                // Modify customer_id to string/char(36) to accept UUIDs safely
                $table->string('customer_id', 64)->nullable()->change();

                // Also defensively check technician_id & company_id types if using UUIDs
                $table->string('technician_id', 64)->nullable()->change();
            });
        }

        // Defensively backfill category types for known vertical categories
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'type')) {
            DB::table('categories')
                ->whereIn('name', ['Hair & Styling', 'Facials & Skincare', 'Spa & Body Treatments'])
                ->update(['type' => 'salon']);

            DB::table('categories')
                ->whereIn('name', ['Display Assemblies', 'Batteries & Power', 'Charging & Ports', 'Workshop Consumables'])
                ->update(['type' => 'repair']);

            DB::table('categories')
                ->whereIn('name', ['Pain Relief', 'First Aid', 'Vitamins & Supplements', 'Antibiotics'])
                ->update(['type' => 'pharmacy']);

            DB::table('categories')
                ->whereIn('name', ['Starters', 'Main Course', 'Hot Beverages', 'Desserts'])
                ->update(['type' => 'restaurant']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable()->change();
            });
        }
    }
};
