<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Local dirty-row bookkeeping for the desktop offline-sync engine: a row is
 * "unsynced" if it has never been synced or has changed since its last sync.
 * Used on both sides of the wire — a local desktop SQLite copy uses it to
 * find rows to push; either side can call markSynced() after a successful
 * round-trip.
 */
trait TracksSyncState
{
    public function scopeUnsynced(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('synced_at')
                ->orWhereColumn('updated_at', '>', 'synced_at');
        });
    }

    public function markSynced(): void
    {
        $this->timestamps = false;
        $this->forceFill(['synced_at' => now()])->save();
        $this->timestamps = true;
    }
}
