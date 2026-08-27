<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe helper to add indexes only if not already present
        $addIndexSafely = function (string $tableName, array $columns, ?string $indexName = null) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    if ($indexName) {
                        $table->index($columns, $indexName);
                    } else {
                        $table->index($columns);
                    }
                });
            } catch (Throwable $e) {
                // Ignore if index already exists in DB
            }
        };

        if (Schema::hasTable('sales')) {
            $addIndexSafely('sales', ['company_id', 'created_at']);
            $addIndexSafely('sales', ['company_id', 'operation_type', 'status']);
            $addIndexSafely('sales', ['company_id', 'user_id', 'status']);
            $addIndexSafely('sales', ['company_id', 'customer_id', 'status']);
            $addIndexSafely('sales', ['company_id', 'due_date']);
            $addIndexSafely('sales', ['company_id', 'sale_number']);
        }

        if (Schema::hasTable('order_payments')) {
            $addIndexSafely('order_payments', ['company_id', 'created_at']);
            $addIndexSafely('order_payments', ['company_id', 'payment_method']);
        }

        if (Schema::hasTable('cash_registers')) {
            $addIndexSafely('cash_registers', ['company_id', 'status', 'opened_at']);
        }

        if (Schema::hasTable('products')) {
            $addIndexSafely('products', ['company_id', 'active']);
            $addIndexSafely('products', ['company_id', 'barcode']);
        }

        if (Schema::hasTable('audit_logs')) {
            $addIndexSafely('audit_logs', ['company_id', 'created_at']);
            $addIndexSafely('audit_logs', ['action', 'created_at']);
        }
    }

    public function down(): void
    {
        // No-op for safety
    }
};
