<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_queries', function (Blueprint $table) {
            $table->string('id')->primary(); // aiq_<hex>
            $table->string('company_id');
            $table->string('user_id')->nullable();
            $table->text('prompt');
            $table->text('response')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_queries');
    }
};
