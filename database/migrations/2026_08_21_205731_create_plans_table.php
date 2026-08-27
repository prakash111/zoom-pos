<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('display_name');
            $table->string('billing_cycle')->default('monthly'); // trial|monthly|quarterly|biannual|yearly|lifetime
            $table->unsignedInteger('duration_days')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->json('features')->nullable();
            $table->json('limits')->nullable(); // usuarios, dispositivos, armazenamento_mb, filiais
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
