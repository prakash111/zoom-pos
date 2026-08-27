<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_sync_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('operation_type', 40);
            $table->string('external_id', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'operation_type', 'external_id'], 'desktop_sync_receipts_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_sync_receipts');
    }
};
