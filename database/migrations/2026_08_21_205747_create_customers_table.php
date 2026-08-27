<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable();
            $table->string('name');
            $table->string('document')->nullable();
            $table->string('person_type')->nullable(); // individual|business
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->string('state_code', 8)->nullable();
            $table->string('gstin', 32)->nullable();
            $table->string('taxpayer_type')->nullable();
            $table->string('tax_id_label')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
