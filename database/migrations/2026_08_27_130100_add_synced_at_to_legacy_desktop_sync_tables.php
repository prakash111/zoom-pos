<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products/categories/brands/units/suppliers/customers/sales already carry
 * external_id (used by the pre-existing PosSyncApiController wire format).
 * This adds the matching synced_at bookkeeping column so a local desktop
 * SQLite copy can track which of its own rows are dirty (unsynced()/
 * markSynced(), see App\Models\Concerns\TracksSyncState), the same way the
 * newer DesktopSyncEngine-covered tables already do.
 */
return new class extends Migration
{
    protected array $tables = [
        'products', 'categories', 'brands', 'units', 'suppliers', 'customers', 'sales',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'synced_at')) {
                    $blueprint->timestamp('synced_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'synced_at')) {
                    $blueprint->dropColumn('synced_at');
                }
            });
        }
    }
};
