<?php

require __DIR__.'/../lib/bootstrap.php';

// Catalog of active products is public for SaaS clients to display available modules and prices
require_schema_api();

$rows = db()->query('SELECT slug, name, description, price, currency FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$bundles = db()->query('SELECT slug, name, description, price, currency, included_modules FROM bundles WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

json_out(200, [
    'status' => true,
    'products' => array_map(fn ($r) => [
        'slug' => $r['slug'],
        'name' => $r['name'],
        'description' => $r['description'],
        'price' => (float) $r['price'],
        'currency' => $r['currency'],
    ], $rows),
    'bundles' => array_map(fn ($b) => [
        'slug' => $b['slug'],
        'name' => $b['name'],
        'description' => $b['description'],
        'price' => (float) $b['price'],
        'currency' => $b['currency'],
        'included_modules' => json_decode($b['included_modules'] ?? '[]', true) ?: [],
    ], $bundles),
]);