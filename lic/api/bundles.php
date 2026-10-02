<?php

require __DIR__.'/../lib/bootstrap.php';
require_schema_api();

// Public API returning active bundles for landing page marketing scripts
$bundles = db()->query('SELECT slug, name, description, price, currency, included_modules FROM bundles WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

json_out(200, [
    'status' => true,
    'bundles' => array_map(fn ($b) => [
        'slug' => $b['slug'],
        'name' => $b['name'],
        'description' => $b['description'],
        'price' => (float) $b['price'],
        'currency' => $b['currency'],
        'included_modules' => json_decode($b['included_modules'] ?? '[]', true) ?: [],
    ], $bundles),
]);
