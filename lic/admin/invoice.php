<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_guard.php';

$orderId = (int) ($_GET['order'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Missing or invalid order ID.');
}

$pdo = db();
$st = $pdo->prepare('SELECT p.*, l.license_key, COALESCE(p.target_domain, l.bound_domain) AS bound_domain FROM payments p LEFT JOIN licenses l ON l.id = p.license_id WHERE p.id = ?');
$st->execute([$orderId]);
$order = $st->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$items = json_decode($order['items_json'] ?? 'null', true) ?: [];
if (!$items) {
    $st = $pdo->prepare('SELECT * FROM licenses WHERE id = ? OR payment_reference = ?');
    $st->execute([$order['license_id'], $order['reference']]);
    $found = $st->fetchAll();
    if ($found) {
        foreach ($found as $lic) {
            $items[] = [
                'id' => (int) $lic['id'],
                'slug' => $lic['product_slug'],
                'name' => ucfirst($lic['product_slug']),
                'license_key' => $lic['license_key'],
                'valid_until' => $lic['valid_until'],
            ];
        }
    } else {
        $snapshot = json_decode($order['checkout_json'] ?? 'null', true);
        $products = $snapshot['products'] ?? [];
        if ($products) {
            foreach ($products as $p) {
                $items[] = [
                    'slug' => $p['slug'] ?? 'software',
                    'name' => $p['name'] ?? 'Software License',
                    'license_key' => $order['license_key'] ?? 'Pending',
                    'valid_until' => null,
                ];
            }
        } else {
            $items[] = [
                'slug' => $order['product_slug'] ?: 'core',
                'name' => ucfirst($order['product_slug'] ?: 'Core License'),
                'license_key' => $order['license_key'] ?? 'Pending',
                'valid_until' => null,
            ];
        }
    }
}

$format = strtolower(trim((string) ($_GET['format'] ?? 'html')));
$orderData = $order + ['domain' => $order['bound_domain'] ?? $order['target_domain'] ?? ''];

if ($format === 'pdf') {
    $pdf = lm_generate_invoice_pdf($orderData, $items);
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($order['reference'] ?? ''));
    $filename = 'Invoice-' . ($ref ?: $order['id']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}

echo lm_invoice_html($orderData, $items);
