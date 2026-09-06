<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tenant-level navigation custom display names
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'navigation_labels')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('navigation_labels')->nullable()->after('navigation_menu_customization');
            });
        }

        // 2. Dynamic custom fields for Customers
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'custom_fields')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('allergies');
            });
        }

        // 3 & 4 & 6. Service Orders: extra_attributes, tax engine fields, sale_id linking
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('service_orders', 'extra_attributes')) {
                    $table->json('extra_attributes')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('service_orders', 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0.00)->after('discount');
                }
                if (! Schema::hasColumn('service_orders', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0.00)->after('tax_amount');
                }
                if (! Schema::hasColumn('service_orders', 'is_tax_inclusive')) {
                    $table->boolean('is_tax_inclusive')->default(false)->after('tax_rate');
                }
                if (! Schema::hasColumn('service_orders', 'tax_breakdown')) {
                    $table->json('tax_breakdown')->nullable()->after('is_tax_inclusive');
                }
                if (! Schema::hasColumn('service_orders', 'sale_id')) {
                    $table->unsignedBigInteger('sale_id')->nullable()->after('extra_attributes');
                }
            });
        }

        // 5. Products: soft deletes for historical sales safety
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        // 6. Central reminders table
        if (! Schema::hasTable('reminders')) {
            Schema::create('reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('type', 50)->default('follow_up')->index();
                $table->string('title', 255);
                $table->text('notes')->nullable();
                $table->timestamp('due_date')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->string('remindable_type', 100)->nullable();
                $table->unsignedBigInteger('remindable_id')->nullable();
                $table->timestamps();

                $table->index(['remindable_type', 'remindable_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'navigation_labels')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('navigation_labels');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'custom_fields')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $columns = ['extra_attributes', 'tax_amount', 'tax_rate', 'is_tax_inclusive', 'tax_breakdown', 'sale_id'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('service_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::dropIfExists('reminders');
    }
};
