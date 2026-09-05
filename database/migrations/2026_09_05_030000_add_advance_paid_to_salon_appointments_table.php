<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salon_appointments')) {
            Schema::table('salon_appointments', function (Blueprint $table) {
                if (! Schema::hasColumn('salon_appointments', 'advance_paid')) {
                    $table->decimal('advance_paid', 12, 2)->default(0.00)->after('sale_id');
                }
                if (! Schema::hasColumn('salon_appointments', 'deposit_payment_method')) {
                    $table->string('deposit_payment_method', 50)->nullable()->after('advance_paid');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salon_appointments')) {
            Schema::table('salon_appointments', function (Blueprint $table) {
                if (Schema::hasColumn('salon_appointments', 'advance_paid')) {
                    $table->dropColumn('advance_paid');
                }
                if (Schema::hasColumn('salon_appointments', 'deposit_payment_method')) {
                    $table->dropColumn('deposit_payment_method');
                }
            });
        }
    }
};
