<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update sales table with remarks, payment tracking and due dates
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'notes')) {
                $table->text('notes')->nullable()->after('items');
            }
            if (! Schema::hasColumn('sales', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('sales', 'due_amount')) {
                $table->decimal('due_amount', 12, 2)->default(0)->after('paid_amount');
            }
            if (! Schema::hasColumn('sales', 'due_date')) {
                $table->date('due_date')->nullable()->after('due_amount');
            }
            if (! Schema::hasColumn('sales', 'payment_status')) {
                $table->string('payment_status', 32)->default('paid')->after('status'); // paid, partially_paid, pending, overdue
            }
        });

        // 2. Create order_payments table for split and multi-method payments
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('payment_method', 64)->default('cash');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change_returned', 12, 2)->default(0);
            $table->string('reference_number', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'sale_id']);
            $table->index(['company_id', 'payment_method']);
        });

        // 3. Create vendor_bills table for Accounts Payable & Expense Management
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('vendor_name', 255)->nullable();
            $table->string('bill_number', 64)->nullable();
            $table->string('category', 64)->default('operational'); // inventory, utilities, rent, salaries, logistics, maintenance, marketing, office, other
            $table->string('title', 255);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->string('status', 32)->default('pending'); // pending, partially_paid, paid, overdue, cancelled
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'category']);
        });

        // 4. Create vendor_bill_payments table for installment settlements
        Schema::create('vendor_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 64)->default('cash');
            $table->date('payment_date');
            $table->string('reference_number', 128)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'vendor_bill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_payments');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('order_payments');

        Schema::table('sales', function (Blueprint $table) {
            $columns = ['notes', 'paid_amount', 'due_amount', 'due_date', 'payment_status'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('sales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
