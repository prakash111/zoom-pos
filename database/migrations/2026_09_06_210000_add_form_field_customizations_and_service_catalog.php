<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tenant-level customizable form field labels
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'form_field_customizations')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('form_field_customizations')->nullable()->after('navigation_labels');
            });
        }

        // 2. Dynamic Service Catalog: type, category_type, price on products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'type')) {
                    $table->string('type', 50)->default('product')->index()->after('unit');
                }
                if (! Schema::hasColumn('products', 'category_type')) {
                    $table->string('category_type', 50)->nullable()->index()->after('category_name');
                }
                if (! Schema::hasColumn('products', 'price')) {
                    $table->decimal('price', 12, 2)->nullable()->after('sale_price');
                }
            });

            // Backfill existing products
            try {
                DB::table('products')
                    ->where(function ($q) {
                        $q->where('unit', 'service')
                          ->orWhere('duration_minutes', '>', 0);
                    })
                    ->update(['type' => 'service']);

                DB::table('products')
                    ->whereNull('price')
                    ->update(['price' => DB::raw('sale_price')]);

                // Sync category_type from categories if category_id matches
                if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'type')) {
                    $categories = DB::table('categories')->whereNotNull('type')->pluck('type', 'id');
                    foreach ($categories as $catId => $catType) {
                        DB::table('products')->where('category_id', $catId)->whereNull('category_type')->update(['category_type' => $catType]);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore backfill errors in test environments
            }
        }

        // 3. Dynamic custom fields for Salon & Service Appointments
        if (Schema::hasTable('salon_appointments') && ! Schema::hasColumn('salon_appointments', 'custom_fields')) {
            Schema::table('salon_appointments', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('notes');
            });
        }

        if (Schema::hasTable('service_appointments') && ! Schema::hasColumn('service_appointments', 'custom_fields')) {
            Schema::table('service_appointments', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'form_field_customizations')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('form_field_customizations');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $cols = ['type', 'category_type', 'price'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('salon_appointments') && Schema::hasColumn('salon_appointments', 'custom_fields')) {
            Schema::table('salon_appointments', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasTable('service_appointments') && Schema::hasColumn('service_appointments', 'custom_fields')) {
            Schema::table('service_appointments', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }
    }
};
