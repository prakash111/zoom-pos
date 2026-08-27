<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add slug / subdomain to companies table if not present
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }
        });

        // 2. Create subscription_invoices table for compliant tax invoicing
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->string('id')->primary(); // sinv_<hex>
            $table->string('company_id');
            $table->string('subscription_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->string('plan_name');
            $table->string('billing_cycle', 30)->default('monthly'); // monthly|yearly|trial|lifetime|activation_key
            $table->string('currency', 8)->default('USD');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->string('tax_type', 30)->default('GST'); // GST|VAT|SALES_TAX|ZERO_TAX
            $table->json('tax_breakdown')->nullable();
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('payment_method', 50)->default('free_trial'); // activation_key|credit_card|stripe|paypal|bank_transfer|free_trial
            $table->string('payment_reference')->nullable();
            $table->string('status', 30)->default('paid'); // paid|pending|void|refunded
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('seller_details')->nullable();
            $table->json('buyer_details')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
