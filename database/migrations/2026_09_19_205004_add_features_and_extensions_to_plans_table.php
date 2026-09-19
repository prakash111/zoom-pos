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
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'invoice_limit')) {
                $table->integer('invoice_limit')->default(-1)->after('limits');
            }
            if (! Schema::hasColumn('plans', 'device_limit')) {
                $table->integer('device_limit')->default(-1)->after('invoice_limit');
            }
            if (! Schema::hasColumn('plans', 'staff_limit')) {
                $table->integer('staff_limit')->default(-1)->after('device_limit');
            }
            if (! Schema::hasColumn('plans', 'extensions')) {
                $table->json('extensions')->nullable()->after('staff_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('plans', 'invoice_limit')) {
                $columns[] = 'invoice_limit';
            }
            if (Schema::hasColumn('plans', 'device_limit')) {
                $columns[] = 'device_limit';
            }
            if (Schema::hasColumn('plans', 'staff_limit')) {
                $columns[] = 'staff_limit';
            }
            if (Schema::hasColumn('plans', 'extensions')) {
                $columns[] = 'extensions';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
