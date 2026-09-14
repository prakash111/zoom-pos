<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dynamic_settings')) {
            Schema::create('dynamic_settings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->string('company_id', 100)->nullable()->index();
                $table->string('group', 100)->index();
                $table->string('key', 100)->nullable()->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_settings');
    }
};
