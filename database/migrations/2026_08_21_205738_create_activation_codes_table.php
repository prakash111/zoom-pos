<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_codes', function (Blueprint $table) {
            $table->string('id')->primary(); // code_<hex>
            $table->string('code_hash'); // password_hash of the plaintext code (shown once at creation)
            $table->string('code_prefix', 16)->index(); // first chars of plaintext, for fast lookup pre-verify
            $table->string('plan_name')->nullable();
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited redemptions
            $table->unsignedInteger('current_uses')->default(0);
            $table->unsignedInteger('validity_days')->nullable(); // null/0 = perpetual subscription grant
            $table->timestamp('expires_at')->nullable(); // redemption window expiry
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->boolean('revoked')->default(false);
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_name')->references('name')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_codes');
    }
};
