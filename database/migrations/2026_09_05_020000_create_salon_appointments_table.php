<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salon_appointments')) {
            return;
        }

        Schema::create('salon_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('appointment_number', 100);
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 50)->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('specialist_id')->nullable()->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 40)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->unique(['company_id', 'appointment_number']);
            $table->index(['company_id', 'specialist_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_appointments');
    }
};
