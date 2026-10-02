<?php

/** Called by authenticated admin completion or a verified gateway callback. */
function lm_complete_order(PDO $pdo, int $id, ?array $payment = null): array
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?'.$lock);
        $st->execute([$id]);
        $order = $st->fetch();
        if (!$order) throw new RuntimeException('Order not found.');
        if ($payment !== null) {
            if ((int) round($order['amount'] * 100) !== $payment['amount'] || strtoupper($order['currency']) !== strtoupper($payment['currency'])) {
                throw new RuntimeException('Payment does not match the saved order.');
            }
            if ($order['status'] === 'paid' && $order['reference'] !== $payment['reference']) {
                throw new RuntimeException('This order already has a different payment. Contact support.');
            }
        }
        if (!filter_var($order['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set a valid customer email before completing this order.');
        }
        $oldItems = json_decode($order['items_json'] ?? 'null', true) ?: [];
        $st = $pdo->prepare('SELECT * FROM licenses WHERE id = ? OR payment_reference = ?');
        $st->execute([$order['license_id'], $order['reference']]);
        $existing = [];
        foreach ($st->fetchAll() as $license) $existing[$license['product_slug']] = $license;
        // A deleted license must not be silently replaced by resending an order.
        foreach ($oldItems as $item) {
            if (!empty($item['id']) && (!isset($existing[$item['slug']]) || (int) $existing[$item['slug']]['id'] !== (int) $item['id'])) {
                throw new RuntimeException('An issued license is missing. Restore it before resending this order.');
            }
        }
        $snapshot = json_decode($order['checkout_json'] ?? 'null', true);
        $products = $snapshot['products'] ?? $oldItems;
        if (!$products && $existing) {
            $products = array_map(fn ($license) => ['slug' => $license['product_slug'], 'name' => ucfirst($license['product_slug'])], array_values($existing));
        }
        if (!$products) throw new RuntimeException('This order has no saved products to fulfill.');
        $domain = $order['target_domain'] ?: ($existing ? (reset($existing)['bound_domain'] ?? '') : '');
        if ($domain === '') throw new RuntimeException('Set an installation domain before completing this order.');
        $reference = $payment['reference'] ?? $order['reference'];
        $items = [];
        $seen = [];
        $new = false;
        foreach ($products as $product) {
            $slug = (string) ($product['slug'] ?? '');
            if (!preg_match('/^[a-z0-9_-]+$/', $slug)) throw new RuntimeException('Invalid product in the saved order.');
            if (isset($seen[$slug])) continue;
            $seen[$slug] = true;
            if (isset($existing[$slug])) {
                $license = $existing[$slug];
            } else {
                $license = issue_license($pdo, $slug, $domain, $order['email']);
                $new = true;
            }
            $pdo->prepare('UPDATE licenses SET payment_reference = ? WHERE id = ?')->execute([$reference, $license['id']]);

            // Build limit is applicable for the Core Script only (never for modules or extensions)
            try {
                $isCoreScript = in_array($slug, ['core', 'main', 'pos', 'zoom-pos'], true);
                if ($isCoreScript) {
                    if (!empty($order['bundle_slug'])) {
                        $bStmt = $pdo->prepare('SELECT id, app_builder_limit FROM bundles WHERE slug = ? LIMIT 1');
                        $bStmt->execute([$order['bundle_slug']]);
                        $bundleRow = $bStmt->fetch();
                        if ($bundleRow) {
                            $pdo->prepare('UPDATE licenses SET bundle_id = ?, app_builder_monthly_limit = ? WHERE id = ?')
                                ->execute([(int)$bundleRow['id'], (int)$bundleRow['app_builder_limit'], (int)$license['id']]);
                        }
                    }
                } else {
                    // For modules and extensions, remove/clear any build limit
                    $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = NULL WHERE id = ?')
                        ->execute([(int)$license['id']]);
                }
            } catch (Throwable $e) {}
            $token = '';
            foreach ($oldItems as $old) {
                if ((int) ($old['id'] ?? 0) === (int) $license['id'] && ($old['license_key'] ?? '') === $license['license_key']) {
                    $token = (string) ($old['download_token'] ?? '');
                    break;
                }
            }
            $items[] = [
                'id' => (int) $license['id'], 'slug' => $slug, 'name' => $product['name'] ?? ucfirst($slug),
                'license_key' => $license['license_key'], 'valid_until' => $license['valid_until'],
                'download_token' => preg_match('/^[a-f0-9]{64}$/', $token) ? $token : bin2hex(random_bytes(32)),
            ];
        }
        if (!$items) throw new RuntimeException('The order contains no license products.');
        $pdo->prepare("UPDATE payments SET reference = ?, status = ?, order_status = 'completed', target_domain = ?, license_id = ?, items_json = ? WHERE id = ?")
            ->execute([$reference, $payment !== null ? 'paid' : $order['status'], $domain, $items[0]['id'], json_encode($items, JSON_THROW_ON_ERROR), $id]);
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $st->execute([$id]);
        $completed = $st->fetch();
        $pdo->commit();
        return ['order' => $completed, 'licenses' => $items, 'new' => $new];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

function lm_download_url(int $orderId, string $token): string
{
    $base = rtrim((string) setting('license_public_url', 'https://license.zoomnearby.com'), '/');
    if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https' || parse_url($base, PHP_URL_QUERY) || parse_url($base, PHP_URL_FRAGMENT)) {
        throw new RuntimeException('Set a valid HTTPS License Server Public URL in Settings.');
    }
    return $base.'/download.php?'.http_build_query(['order' => $orderId, 'token' => $token]);
}

/** The token grants access to one ordered product only, never to a filesystem path. */
function lm_download_license(PDO $pdo, int $orderId, string $token): array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new RuntimeException('Invalid download link.');
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order || lm_order_status($order) !== 'completed') throw new RuntimeException('This download is not available. Contact support.');
    foreach (json_decode($order['items_json'] ?? 'null', true) ?: [] as $item) {
        if (empty($item['download_token']) || !hash_equals($item['download_token'], $token)) continue;
        $st = $pdo->prepare('SELECT * FROM licenses WHERE id = ? AND product_slug = ?');
        $st->execute([$item['id'], $item['slug']]);
        $license = $st->fetch();
        if (!$license || !hash_equals($license['license_key'], $item['license_key']) || $license['status'] !== 'active') {
            throw new RuntimeException('This license is no longer active. Contact support.');
        }
        if (!empty($license['valid_until']) && strtotime($license['valid_until'].' 23:59:59 UTC') < time()) {
            throw new RuntimeException('This license has expired. Contact support.');
        }
        return $license;
    }
    throw new RuntimeException('Invalid download link.');
}

function lm_send_fulfillment_email(PDO $pdo, array $result): array
{
    $order = $result['order'];
    $items = $result['licenses'];
    $missing = [];
    try {
        foreach ($items as &$item) {
            $item['download_url'] = lm_download_url((int) $order['id'], $item['download_token']);
            $item['package_available'] = is_file(package_path($item['slug']));
            if (!$item['package_available']) $missing[] = $item['name'];
        }
        unset($item);

        $attachments = [];
        if (setting('send_invoice_attachment', '1') === '1') {
            try {
                $siteName = (string) setting('invoice_company_name', setting('site_name', 'ZoomNearby SaaS Platform'));
                $pdfData = lm_generate_invoice_pdf($order + ['domain' => $order['target_domain']], $items, $siteName);
                $ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($order['reference'] ?? ''));
                $filename = 'Invoice-' . ($ref ?: $order['id']) . '.pdf';
                $attachments[] = [
                    'filename' => $filename,
                    'content' => $pdfData,
                    'mime' => 'application/pdf',
                ];
            } catch (Throwable $e) {
                error_log('Invoice PDF generation failed: ' . $e->getMessage());
            }
        }

        $mail = lm_send_license_delivery_email($order + ['domain' => $order['target_domain']], $items, $attachments);

        // Automatically send App Builder Access Email if the purchased plan includes App Builder
        try {
            if (empty($order['builder_email_sent'])) {
                lm_send_app_builder_welcome_email($pdo, $order, $items);
            }
        } catch (Throwable $e) {
            error_log('App Builder access email dispatch error: ' . $e->getMessage());
        }
    } catch (Throwable $ex) {
        $mail = ['ok' => false, 'message' => 'Check Email / SMTP settings and the License Server Public URL.'];
    }
    if ($mail['ok']) {
        $pdo->prepare('UPDATE payments SET order_error = NULL WHERE id = ? AND order_error = ?')->execute([$order['id'], 'License email failed; use Resend Email.']);
    } else {
        $pdo->prepare('UPDATE payments SET order_error = ? WHERE id = ?')->execute(['License email failed; use Resend Email.', $order['id']]);
    }
    return ['ok' => $mail['ok'], 'message' => ($mail['ok']
        ? 'License keys and download links submitted to the mail server for '.$order['email'].'.'
        : 'Licenses saved, but email failed: '.$mail['message'])
        .($missing ? ' Upload package ZIPs in Products for: '.implode(', ', $missing).'. Their links will work after upload.' : '')];
}
