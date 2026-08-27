<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Broadcast source authored by Super Admin; delivered/read per-tenant via tenant_notifications.
        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->string('id')->primary(); // pnotif_<hex>
            $table->string('title');
            $table->text('message');
            $table->string('target')->default('todos'); // todos|planos|trial|expirando
            $table->string('target_plan')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notifications');
    }
};
