<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the desktop offline-sync surface beyond products/categories/customers/
 * suppliers/sales (already synced by PosSyncApiController) to the remaining
 * tenant modules: cash register, sales targets, consignments, service orders,
 * and payables. Each table gets the same `external_id` + `synced_at` pair the
 * already-synced tables use, so DesktopSyncEngine can treat every table the
 * same way (upsert by company_id + external_id, stamp synced_at on ack).
 */
return new class extends Migration
{
    protected array $tables = [
        'cash_registers',
        'cash_register_transactions',
        'sales_targets',
        'consignments',
        'service_orders',
        'vendor_bills',
        'vendor_bill_payments',
        'order_payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'external_id')) {
                    $blueprint->string('external_id', 100)->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, 'synced_at')) {
                    $blueprint->timestamp('synced_at')->nullable();
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $indexName = $table.'_company_external_id_unique';
                if (! $this->indexExists($table, $indexName)) {
                    $blueprint->unique(['company_id', 'external_id'], $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $indexName = $table.'_company_external_id_unique';
                if ($this->indexExists($table, $indexName)) {
                    $blueprint->dropUnique($indexName);
                }
                if (Schema::hasColumn($table, 'external_id')) {
                    $blueprint->dropColumn('external_id');
                }
                if (Schema::hasColumn($table, 'synced_at')) {
                    $blueprint->dropColumn('synced_at');
                }
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getSchemaBuilder()->getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
