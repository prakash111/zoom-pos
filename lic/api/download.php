<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

// Module package downloads are authenticated by verify_license() using the license_key for the specified product and domain.
// Requiring the vendor's private SERVER_SECRET prevents legitimate client installations from downloading their purchased modules.
require_schema_api();

$in = read_json_body();
$key = trim($in['license_key'] ?? '');
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));

if ($key === '' || $slug === '' || $domain === '') {
    json_out(422, ['status' => false, 'message' => 'license_key, product_slug and domain are required.']);
}

$r = verify_license(db(), $key, $slug, $domain);
if (! $r['ok']) {
    json_out(403, ['status' => false, 'message' => $r['message']]);
}

$zip = package_path($slug);
if (! is_file($zip)) {
    json_out(404, ['status' => false, 'message' => 'No package uploaded for "'.$slug.'" yet.']);
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$slug.'.zip"');
header('Content-Length: '.filesize($zip));
header('X-License-Expires-At: '.($r['expires_at'] ?? ''));
readfile($zip);
exit;