<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

// License key verification is authenticated by the license key itself against the database.
// Requiring the vendor's private SERVER_SECRET here prevents fresh client installs (like core script activation)
// from verifying their license keys since client installations do not possess the vendor's private secret.
require_schema_api();

$in = read_json_body();
$key = trim($in['license_key'] ?? '');
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));

if ($key === '' || $slug === '' || $domain === '') {
    json_out(422, ['status' => false, 'message' => 'license_key, product_slug and domain are required.']);
}

$r = verify_license(db(), $key, $slug, $domain);

json_out(200, [
    'status' => $r['ok'],
    'expires_at' => $r['expires_at'],
    'message' => $r['message'],
    'plan' => $r['plan'],
]);