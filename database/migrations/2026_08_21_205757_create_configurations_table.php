<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-tenant generic settings (company profile, app config, backup config, etc.)
        // beyond the dedicated columns already on companies.
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
