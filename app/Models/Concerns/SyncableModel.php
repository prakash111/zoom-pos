<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Marks a tenant-scoped model as part of the desktop offline-sync surface,
 * with external_id auto-generated on creation (including rows created from
 * the web UI) so DesktopSyncEngine never has to fall back to a numeric
 * server id as a stand-in wire identifier.
 *
 * The legacy synced models (Product/Sale/Customer/Category/Brand/Unit/
 * Supplier) predate this trait and only get an external_id when a client
 * supplies one (see PosSyncApiController's `?:` fallback) — changing that
 * now would be a behavior change to already-shipped models, so they use
 * TracksSyncState directly instead of this trait.
 */
trait SyncableModel
{
    use TracksSyncState;

    protected static function bootSyncableModel(): void
    {
        static::creating(function ($model) {
            if (empty($model->external_id)) {
                $model->external_id = (string) Str::uuid();
            }
        });
    }
}
