<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('company_id');
                $table->foreign('tenant_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index('tenant_id');
            }

            if (! Schema::hasColumn('stores', 'branch_code')) {
                $table->string('branch_code', 64)->nullable()->after('code');
                $table->index('branch_code');
            }

            if (! Schema::hasColumn('stores', 'address_line_1')) {
                $table->string('address_line_1')->nullable()->after('tax_id');
            }

            if (! Schema::hasColumn('stores', 'address_line_2')) {
                $table->string('address_line_2')->nullable()->after('address_line_1');
            }

            if (! Schema::hasColumn('stores', 'city')) {
                $table->string('city', 100)->nullable()->after('address_line_2');
            }

            if (! Schema::hasColumn('stores', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }

            if (! Schema::hasColumn('stores', 'pincode')) {
                $table->string('pincode', 20)->nullable()->after('state');
            }

            if (! Schema::hasColumn('stores', 'receipt_header')) {
                $table->text('receipt_header')->nullable()->after('pincode');
            }

            if (! Schema::hasColumn('stores', 'receipt_footer')) {
                $table->text('receipt_footer')->nullable()->after('receipt_header');
            }

            if (! Schema::hasColumn('stores', 'invoice_prefix')) {
                $table->string('invoice_prefix', 30)->default('INV-')->after('receipt_footer');
            }
        });

        // Backfill columns for existing stores
        DB::statement('UPDATE stores SET tenant_id = company_id WHERE tenant_id IS NULL');
        DB::statement('UPDATE stores SET branch_code = code WHERE branch_code IS NULL AND code IS NOT NULL');
        DB::statement('UPDATE stores SET address_line_1 = address WHERE address_line_1 IS NULL AND address IS NOT NULL');
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE stores SET invoice_prefix = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings, '$.invoice_prefix')), 'INV-') WHERE invoice_prefix IS NULL OR invoice_prefix = ''");
        } else {
            foreach (DB::table('stores')->whereNull('invoice_prefix')->orWhere('invoice_prefix', '')->get() as $s) {
                $settings = json_decode($s->settings ?? '{}', true) ?: [];
                $prefix = $settings['invoice_prefix'] ?? 'INV-';
                DB::table('stores')->where('id', $s->id)->update(['invoice_prefix' => $prefix]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'invoice_prefix', 'receipt_footer', 'receipt_header',
                'pincode', 'state', 'city', 'address_line_2', 'address_line_1',
                'branch_code',
            ] as $col) {
                if (Schema::hasColumn('stores', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            if (Schema::hasColumn('stores', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
