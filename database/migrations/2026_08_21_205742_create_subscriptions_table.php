<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->string('id')->primary(); // sub_<hex>
            $table->string('company_id');
            $table->string('plan_name')->nullable();
            $table->string('status')->default('active'); // active|expired|suspended|cancelled
            $table->string('origin')->default('manual_admin');
            // trial|codigo_ativacao_local|payment_stripe|payment_paypal|payment_razorpay|checkout_direct|manual_admin
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null = perpetual/lifetime
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('plan_name')->references('name')->on('plans')->nullOnDelete();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
