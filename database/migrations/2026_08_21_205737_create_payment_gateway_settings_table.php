<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per gateway (stripe/paypal/razorpay). Credentials belong to the platform
        // operator (collecting subscription payments from tenants), never per-tenant.
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->unique(); // stripe|paypal|razorpay
            $table->boolean('enabled')->default(false);
            $table->string('mode')->default('test'); // test|live
            $table->string('public_key')->nullable();
            $table->text('secret_key')->nullable(); // encrypted cast
            $table->text('webhook_secret')->nullable(); // encrypted cast
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
