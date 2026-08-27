<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'payment_terms')) {
                $table->string('payment_terms', 50)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('sales', 'commission_rate')) {
                $table->decimal('commission_rate', 8, 2)->default(0)->after('payment_terms');
            }
            if (! Schema::hasColumn('sales', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_rate');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'commission_rate')) {
                $table->decimal('commission_rate', 8, 2)->default(0)->after('role');
            }
            if (! Schema::hasColumn('users', 'commission_type')) {
                $table->string('commission_type', 20)->default('percentage')->after('commission_rate');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'receipt_format')) {
                $table->string('receipt_format', 10)->default('80mm')->after('pos_layout');
            }
            if (! Schema::hasColumn('companies', 'default_commission_rate')) {
                $table->decimal('default_commission_rate', 8, 2)->default(0)->after('receipt_format');
            }
            if (! Schema::hasColumn('companies', 'default_commission_type')) {
                $table->string('default_commission_type', 20)->default('percentage')->after('default_commission_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'commission_rate', 'commission_amount']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_type']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['receipt_format', 'default_commission_rate', 'default_commission_type']);
        });
    }
};
