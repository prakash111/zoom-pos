<?php

require __DIR__.'/../lib/bootstrap.php';

// Catalog of active products is public for SaaS clients to display available modules and prices
require_schema_api();

$rows = db()->query('SELECT slug, name, description, price, currency FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();

json_out(200, [
    'status' => true,
    'products' => array_map(fn ($r) => [
        'slug' => $r['slug'],
        'name' => $r['name'],
        'description' => $r['description'],
        'price' => (float) $r['price'],
        'currency' => $r['currency'],
    ], $rows),
]);