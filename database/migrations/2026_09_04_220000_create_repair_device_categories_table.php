<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_device_categories')) {
            Schema::create('repair_device_categories', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name', 150)->index();
                $table->string('slug', 150)->nullable()->index();
                $table->string('icon', 100)->default('devices');
                $table->json('brands')->nullable();
                $table->json('checklist_items')->nullable();
                $table->string('identifier_type', 100)->default('Serial / IMEI');
                $table->json('common_issues')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_demo')->default(false)->index();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'is_active', 'sort_order']);
            });
        }

        Schema::table('repair_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_tickets', 'device_category_id')) {
                $table->unsignedBigInteger('device_category_id')->nullable()->after('customer_phone')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('repair_tickets', 'device_category_id')) {
                $table->dropColumn('device_category_id');
            }
        });

        Schema::dropIfExists('repair_device_categories');
    }
};
