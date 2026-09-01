<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only customer receivables ledger. Every due-creating sale and every
 * payment applied against a due sale gets one row here, with a running
 * balance snapshot — the dedicated, durable alternative to summing
 * sales.due_amount live (see Customer::due_balance, which this ledger keeps
 * in sync with).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('order_payment_id')->nullable()->constrained('order_payments')->nullOnDelete();
            $table->string('type', 20); // invoice | payment | adjustment
            $table->decimal('amount', 12, 2); // signed: +invoice due, -payment applied
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }
};
