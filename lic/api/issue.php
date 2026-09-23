<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();
require_schema_api();

$in = read_json_body();
$payment = is_array($in['payment'] ?? null) ? $in['payment'] : [];
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));
$ref = trim($payment['reference'] ?? '');

if ($slug === '' || $domain === '' || $ref === '') {
    json_out(422, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'product_slug, domain and payment.reference are required.', 'plan' => null]);
}

$pdo = db();

$prod = $pdo->prepare('SELECT 1 FROM products WHERE slug = ? LIMIT 1');
$prod->execute([$slug]);
if (! $prod->fetch()) {
    json_out(200, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'Unknown product "'.$slug.'".', 'plan' => null]);
}

$seen = $pdo->prepare(
    'SELECT l.license_key, l.valid_until, l.plan
       FROM payments p JOIN licenses l ON l.id = p.license_id
      WHERE p.reference = ? LIMIT 1'
);
$seen->execute([$ref]);
if ($row = $seen->fetch()) {
    json_out(200, [
        'status' => true,
        'license_key' => $row['license_key'],
        'expires_at' => iso8601_from_date($row['valid_until']),
        'message' => 'License already issued for this payment.',
        'plan' => $row['plan'],
    ]);
}

try {
    $pdo->beginTransaction();
    $lic = issue_license($pdo, $slug, $domain, trim($payment['payer_email'] ?? ''));
    $pdo->prepare(
        'INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id, email, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ref,
        substr((string) ($payment['gateway'] ?? 'api'), 0, 40),
        (float) ($payment['amount'] ?? 0),
        strtoupper(substr((string) ($payment['currency'] ?? 'USD'), 0, 8)),
        $slug,
        $lic['id'],
        trim($payment['payer_email'] ?? ''),
        'paid',
    ]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $seen->execute([$ref]);
    if ($row = $seen->fetch()) {
        json_out(200, ['status' => true, 'license_key' => $row['license_key'], 'expires_at' => iso8601_from_date($row['valid_until']), 'message' => 'License already issued for this payment.', 'plan' => $row['plan']]);
    }
    json_out(200, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'Could not issue license: '.$ex->getMessage(), 'plan' => null]);
}

json_out(200, [
    'status' => true,
    'license_key' => $lic['license_key'],
    'expires_at' => iso8601_from_date($lic['valid_until']),
    'message' => 'License issued.',
    'plan' => null,
]);