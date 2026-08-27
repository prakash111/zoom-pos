<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
