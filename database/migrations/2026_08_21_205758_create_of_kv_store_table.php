<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generic per-tenant KV fallback store for the legacy sync-compat layer
        // (mirrors legacy "of_kv_store", key "of_app_{companyId}_{key}") — this is what lets
        // save_table/load_all round-trip any table the offline client sends, even before a
        // module has a real Eloquent-backed table registered in config/sync_tables.php.
        Schema::create('of_kv_store', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('store_key');
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'store_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('of_kv_store');
    }
};
