<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable(); // legacy client-generated offline id
            $table->string('name');
            $table->string('color', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
