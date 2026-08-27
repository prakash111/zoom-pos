<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy sync-compat fallback store (mirrors "of_kv_store"). Any table key the
 * offline/desktop client sends that isn't yet mapped in config/sync_tables.php
 * to a real Eloquent model lands here, keyed by company_id + store_key.
 */
class KvStore extends Model
{
    use BelongsToCompany;

    protected $table = 'of_kv_store';

    protected $fillable = ['company_id', 'store_key', 'value'];
}
