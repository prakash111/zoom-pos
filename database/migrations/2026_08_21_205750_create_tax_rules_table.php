<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->string('id')->primary(); // tax_<hex>
            $table->string('company_id');
            $table->string('tax_name');
            $table->string('tax_code')->nullable();
            $table->decimal('rate', 6, 3)->default(0);
            $table->string('country', 2);
            $table->string('region')->nullable();
            $table->string('category')->nullable();
            $table->string('calc_type')->nullable(); // inclusive|exclusive
            $table->date('effective_from')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'country', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
