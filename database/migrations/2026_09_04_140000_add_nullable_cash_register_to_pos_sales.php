<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'cash_register_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('cash_register_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('cash_registers')
                    ->nullOnDelete();
                $table->index(['company_id', 'cash_register_id']);
            });
        }

        if (! Schema::hasColumn('order_payments', 'cash_register_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->foreignId('cash_register_id')
                    ->nullable()
                    ->after('sale_id')
                    ->constrained('cash_registers')
                    ->nullOnDelete();
                $table->index(['company_id', 'cash_register_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_payments', 'cash_register_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropForeign(['cash_register_id']);
                $table->dropIndex(['company_id', 'cash_register_id']);
                $table->dropColumn('cash_register_id');
            });
        }

        if (Schema::hasColumn('sales', 'cash_register_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['cash_register_id']);
                $table->dropIndex(['company_id', 'cash_register_id']);
                $table->dropColumn('cash_register_id');
            });
        }
    }
};
