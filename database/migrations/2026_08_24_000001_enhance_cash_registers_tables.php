<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_registers', 'terminal_id')) {
                $table->string('terminal_id', 64)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('cash_registers', 'opening_denominations')) {
                $table->json('opening_denominations')->nullable()->after('opening_balance');
            }
            if (! Schema::hasColumn('cash_registers', 'closing_denominations')) {
                $table->json('closing_denominations')->nullable()->after('counted_closing_balance');
            }
            if (! Schema::hasColumn('cash_registers', 'opening_notes')) {
                $table->text('opening_notes')->nullable()->after('notes');
            }
        });

        Schema::table('cash_register_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_register_transactions', 'voucher_number')) {
                $table->string('voucher_number', 64)->nullable()->after('cash_register_id');
            }
            if (! Schema::hasColumn('cash_register_transactions', 'category')) {
                $table->string('category', 64)->nullable()->after('type');
            }
            if (! Schema::hasColumn('cash_register_transactions', 'balance_before')) {
                $table->decimal('balance_before', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('cash_register_transactions', 'balance_after')) {
                $table->decimal('balance_after', 12, 2)->nullable()->after('balance_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn(['terminal_id', 'opening_denominations', 'closing_denominations', 'opening_notes']);
        });

        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $table->dropColumn(['voucher_number', 'category', 'balance_before', 'balance_after']);
        });
    }
};
