<?php

require_once __DIR__.'/fulfillment.php';

function lm_order_status(array $order): string
{
    return $order['order_status'] ?? (in_array($order['status'] ?? '', ['paid', 'redeemed'], true) ? 'completed' : 'pending');
}

function lm_order_by_token(PDO $pdo, string $token): ?array
{
    $st = $pdo->prepare('SELECT * FROM payments WHERE order_token = ? LIMIT 1');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function lm_register_order(PDO $pdo, array $snapshot, string $token = ''): array
{
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $existing = $token !== '' ? lm_order_by_token($pdo, $token) : null;
    if ($existing && json_decode($existing['checkout_json'], true) == $snapshot) {
        return $existing;
    }
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO payments (reference, gateway, amount, currency, product_slug, bundle_slug, email, status, order_status, order_token, target_domain, checkout_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            'ord_'.$token, $snapshot['gateway'], $snapshot['amount'], $snapshot['currency'],
            $snapshot['bundle'] !== '' ? 'bundle:'.$snapshot['bundle'] : $snapshot['products'][0]['slug'],
            $snapshot['bundle'] ?: null, $snapshot['email'], 'unpaid', 'pending', $token,
            $snapshot['domain'], $json,
        ]);

    try {
        $pdo->prepare('INSERT IGNORE INTO orders (order_token, reference, email, amount, currency, product_slug, bundle_slug, order_status, checkout_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $token, 'ord_'.$token, $snapshot['email'], $snapshot['amount'], $snapshot['currency'],
                $snapshot['bundle'] !== '' ? 'bundle:'.$snapshot['bundle'] : $snapshot['products'][0]['slug'],
                $snapshot['bundle'] ?: null, 'pending', $json
            ]);
    } catch (\Throwable $e) {}

    return lm_order_by_token($pdo, $token);
}

function lm_set_order_status(PDO $pdo, int $id, string $status): ?array
{
    if (!in_array($status, ['pending', 'processing', 'completed'], true)) {
        throw new InvalidArgumentException('Choose Pending, Processing or Completed.');
    }
    if ($status === 'completed') return lm_complete_order($pdo, $id);
    $pdo->prepare('UPDATE payments SET order_status = ? WHERE id = ?')->execute([$status, $id]);

    try {
        $pdo->prepare('UPDATE orders SET order_status = ? WHERE payment_id = ? OR reference = (SELECT reference FROM payments WHERE id = ? LIMIT 1)')
            ->execute([$status, $id, $id]);
    } catch (\Throwable $e) {}

    return null;
}

/** Order updates are available before payment and never create license keys. */
function lm_order_status_email_content(array $order, array $products, string $siteName): array
{
    $status = ucfirst(lm_order_status($order));
    $paymentStatus = match ($order['status'] ?? '') {
        'paid' => 'Paid',
        'redeemed' => 'Redeemed',
        default => 'Not verified',
    };
    $reference = (string) ($order['reference'] ?? '');
    $domain = (string) ($order['target_domain'] ?? '');
    $amount = number_format((float) ($order['amount'] ?? 0), 2).' '.strtoupper($order['currency'] ?? 'USD');
    $note = $paymentStatus === 'Not verified'
        ? 'This is an order status update. Payment has not been verified. License keys will be delivered separately after payment verification.'
        : 'This is an order status update. Any issued license keys are sent in a separate license delivery email.';
    $rows = '';
    $productText = '';
    foreach ($products as $product) {
        $name = (string) ($product['name'] ?? $product['slug'] ?? 'Software license');
        $rows .= '<li style="margin-bottom:8px">'.e($name).'</li>';
        $productText .= '- '.$name."\n";
    }
    $subject = preg_replace('/[\r\n]+/', ' ', $siteName.' — Order '.$status.' ['.$reference.']');
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'.e($subject).'</title></head>'
        .'<body style="margin:0;padding:24px 12px;background:#f8fafc;font-family:Arial,sans-serif;color:#334155;line-height:1.6">'
        .'<div style="max-width:620px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">'
        .'<div style="background:#312e81;color:#fff;padding:24px"><h1 style="margin:0;font-size:22px">'.e($siteName).'</h1><p style="margin:6px 0 0">Order update</p></div>'
        .'<div style="padding:24px"><h2 style="margin-top:0">Order status: '.e($status).'</h2>'
        .'<p>'.e($note).'</p><table style="width:100%;text-align:left;font-size:14px">';
    foreach (['Order reference' => $reference, 'Order total' => $amount, 'Payment status' => $paymentStatus, 'Installation domain' => $domain ?: 'Not specified', 'Customer email' => (string) ($order['email'] ?? '')] as $label => $value) {
        $html .= '<tr><th style="padding:6px 8px 6px 0;vertical-align:top">'.e($label).'</th><td style="overflow-wrap:anywhere">'.e($value).'</td></tr>';
    }
    $html .= '</table><h3>Ordered products</h3><ul>'.$rows.'</ul></div></div></body></html>';
    $text = $siteName."\nOrder status: ".$status."\nOrder reference: ".$reference."\nOrder total: ".$amount
        ."\nPayment status: ".$paymentStatus."\nInstallation domain: ".($domain ?: 'Not specified')."\n\n".$note."\n\nOrdered products:\n".$productText;
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function lm_send_order_status_email(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$id]);
    $order = $st->fetch();
    if (!$order) return ['ok' => false, 'message' => 'Order not found.'];
    if (!filter_var($order['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'The order has no valid customer email address.'];
    }
    if (lm_order_status($order) === 'completed') {
        try {
            return lm_deliver_order(lm_complete_order($pdo, $id), true);
        } catch (Throwable $ex) {
            return ['ok' => false, 'message' => 'Unable to prepare license delivery: '.$ex->getMessage()];
        }
    }
    $snapshot = json_decode($order['checkout_json'] ?? 'null', true);
    $products = $snapshot['products'] ?? json_decode($order['items_json'] ?? 'null', true);
    if (!is_array($products) || !$products) {
        $products = [['name' => $order['bundle_slug'] ?: $order['product_slug']]];
    }
    if (empty($order['target_domain']) && !empty($order['license_id'])) {
        $st = $pdo->prepare('SELECT bound_domain FROM licenses WHERE id = ?');
        $st->execute([$order['license_id']]);
        $order['target_domain'] = $st->fetchColumn() ?: '';
    }
    $content = lm_order_status_email_content($order, $products, (string) setting('site_name', 'ZoomNearby SaaS Platform'));
    try {
        $result = lm_send_mail($order['email'], $content['subject'], $content['html'], $content['text']);
    } catch (Throwable $ex) {
        return ['ok' => false, 'message' => 'Unable to send email. Check Email / SMTP settings and try again.'];
    }
    return ['ok' => $result['ok'], 'message' => $result['ok']
        ? 'Order email submitted to the mail server for '.$order['email'].'. Check the inbox and spam folder.'
        : 'Order email failed: '.$result['message']];
}

function lm_license_terms(): string
{
    $ttl = defined('DEFAULT_LICENSE_TTL_DAYS') ? (int) DEFAULT_LICENSE_TTL_DAYS : 0;
    return 'Regular self-hosted license. '.($ttl > 0 ? 'Valid for '.$ttl.' days from issue.' : 'Perpetual validity.').
        ' Bound to the installation domain. Separate keys for each included product, issued after verified payment and sent by email.';
}

function lm_stripe_details(array $snapshot): array
{
    // Preserve the configured package price, even when it differs from list prices.
    $parts = [$snapshot['description'], 'Included products:'];
    foreach ($snapshot['products'] as $product) {
        $parts[] = ($product['slug'] === 'core' ? 'Core: ' : 'Module: ').$product['name'].(!empty($product['description']) ? ' — '.$product['description'] : '');
    }
    $parts[] = lm_license_terms();
    $parts[] = 'Installation domain: '.$snapshot['domain'];
    return [
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => strtolower($snapshot['currency']),
        'line_items[0][price_data][unit_amount]' => (int) round($snapshot['amount'] * 100),
        'line_items[0][price_data][product_data][name]' => mb_substr($snapshot['title'], 0, 250),
        'line_items[0][price_data][product_data][description]' => mb_substr(implode("\n", array_filter($parts)), 0, 40000),
        'custom_text[submit][message]' => mb_substr('License delivery: '.$snapshot['email'].'. Installation domain: '.$snapshot['domain'].'. '.lm_license_terms(), 0, 1200),
    ];
}

/** Only call after server-side gateway verification. */
function lm_fulfill_order(PDO $pdo, string $token, string $reference, int $amountMinor, string $currency): array
{
    $order = lm_order_by_token($pdo, $token);
    if (!$order) throw new RuntimeException('Payment does not match a saved order.');
    return lm_complete_order($pdo, (int) $order['id'], ['reference' => $reference, 'amount' => $amountMinor, 'currency' => $currency]);
}

function lm_deliver_order(array $result, bool $forceEmail = false): array
{
    if (!$result['new'] && !$forceEmail && ($result['order']['order_error'] ?? '') !== 'License email failed; use Resend Email.') {
        return ['ok' => true, 'message' => 'Order already fulfilled. Use Send Order Email to resend the keys and download links.'];
    }
    $mail = lm_send_fulfillment_email(db(), $result);
    if ($result['new']) {
        foreach ($result['licenses'] as $license) {
            notify_saas_activation($result['order']['target_domain'], $license['slug'], $license['license_key'], $license['valid_until'], null);
        }
    }
    return $mail;
}

function lm_verify_stripe_order(PDO $pdo, array $session): array
{
    $token = (string) ($session['metadata']['order_token'] ?? '');
    $order = lm_order_by_token($pdo, $token);
    if (!$order || $order['gateway'] !== 'stripe' || $order['gateway_reference'] !== ($session['id'] ?? '')) {
        throw new RuntimeException('Stripe session does not match a saved checkout order. Contact support.');
    }
    if (($session['payment_status'] ?? '') !== 'paid') {
        throw new RuntimeException('Payment has not been confirmed by Stripe. The order remains available in the Orders panel.');
    }
    return lm_fulfill_order($pdo, $token, $session['id'], (int) ($session['amount_total'] ?? -1), (string) ($session['currency'] ?? ''));
}

function lm_valid_stripe_signature(string $payload, string $header, string $secret, int $now): bool
{
    $timestamp = 0;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't' && ctype_digit($value)) $timestamp = (int) $value;
        if ($key === 'v1') $signatures[] = $value;
    }
    if ($secret === '' || !$timestamp || abs($now - $timestamp) > 300) return false;
    $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) return true;
    }
    return false;
}
