<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Was legacy "tb_unidades".
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('external_id', 64)->nullable();
            $table->string('name');
            $table->string('abbreviation', 16)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
