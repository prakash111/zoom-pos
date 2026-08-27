<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->string('id')->primary(); // ptx_<hex>
            $table->string('company_id');
            $table->string('plan_name')->nullable();
            $table->string('gateway'); // stripe|paypal|razorpay
            $table->string('gateway_ref')->nullable()->index();
            $table->string('gateway_secondary_ref')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('status')->default('pending'); // pending|paid|failed
            $table->string('checkout_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
