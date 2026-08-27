<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parks a self-registration payload while the OTP-registration feature flag is on;
        // materialized into companies/users only after verifyOtp succeeds.
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->string('id')->primary(); // pend_<hex>
            $table->string('email');
            $table->json('payload');
            $table->string('otp_hash')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
