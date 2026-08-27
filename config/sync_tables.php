<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;

/**
 * Legacy sync-compat table-key -> Eloquent model map (see
 * App\Services\Sync\SyncCompatService). A table key not listed here falls
 * through to the generic KvStore ("of_kv_store") fallback, which is what
 * keeps save_table/load_all working for the entire legacy action surface
 * even before a module is modeled relationally — this map simply grows by
 * one entry per module as it lands. The wire contract never changes.
 *
 * Keys are the legacy Portuguese table names the desktop/browser client
 * already sends (see browser-tauri-adapter.js), mapped to their Milestone 3
 * relational home.
 */
return [
    'produtos' => Product::class,
    'clientes' => Customer::class,
    'fornecedores' => Supplier::class,
    'categorias' => Category::class,
    'vendas' => Sale::class,
    // 'marcas' (brands) / 'unidades' (units): left on the KV fallback —
    // the exact legacy key name for these two wasn't confirmed in the
    // source analysis, and guessing wrong here is silently harmless
    // (KV fallback still works), so add them once confirmed against the
    // real client rather than risk mapping the wrong key.
];
