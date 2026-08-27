<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable(); // legacy client-generated offline id
            $table->string('code', 50)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name')->nullable(); // denormalized cache for offline sync payloads
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->string('hsn_code', 20)->nullable();
            $table->string('sac_code', 20)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->boolean('taxable')->default(true);
            $table->boolean('tax_exempt')->default(false);
            $table->boolean('zero_rate')->default(false);
            $table->boolean('reverse_charge')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'external_id']);
            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
