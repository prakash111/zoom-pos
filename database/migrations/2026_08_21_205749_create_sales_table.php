<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable();
            $table->string('sale_number')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable(); // denormalized cache
            $table->string('user_id')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('status')->nullable(); // completed|cancelled|pending
            $table->json('items')->nullable();
            $table->string('operation_type')->nullable();
            $table->json('gst_invoice')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['company_id', 'external_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
