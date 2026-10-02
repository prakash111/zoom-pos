<?php

/**
 * Public Landing Page API
 * 
 * Delivers full pricing, products, bundles, hero copy, features, metrics,
 * discount tiers, and FAQs to marketing landing pages hosted on ANY domain.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__.'/../lib/bootstrap.php';
require_schema_api();

$payload = build_landing_api_payload();
json_out(200, $payload);
