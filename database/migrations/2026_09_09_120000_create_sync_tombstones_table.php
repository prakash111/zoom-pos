<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tombstone ledger for the offline-first desktop client.
 *
 * The desktop app can delete a record while offline; it queues that delete
 * and replays it through PosSyncApiController::syncBatch() once online. This
 * table records "entity X (identified by its client external_id) was deleted"
 * so that:
 *   - a second device that only pulls deltas (sync-pull?since=) learns the
 *     row is gone (`deleted_ids` in the pull response), and
 *   - replaying the same offline delete batch never errors on an
 *     already-gone row.
 *
 * A dedicated table (rather than adding SoftDeletes to seven models) keeps
 * the change fully additive: no model, global-scope, or web-query behaviour
 * changes anywhere else in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            // 'product' | 'customer' | 'quotation' | 'category' | 'brand' |
            // 'supplier' | 'unit' | 'tax_rule'
            $table->string('entity', 40);
            // The client-generated external_id the row was known by on the device.
            $table->string('external_id', 100);
            // The server primary key at delete time, when it was known — lets a
            // client that only ever saw the numeric id still reconcile.
            $table->string('server_id', 100)->nullable();
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'entity', 'external_id'], 'sync_tombstones_unique');
            $table->index(['company_id', 'entity', 'deleted_at'], 'sync_tombstones_delta_idx');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');
    }
};
