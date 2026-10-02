<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();
try {
    $pdo->query('SELECT order_token FROM payments LIMIT 0');
} catch (Throwable $ex) {
    lm_header('payments', 'Upgrade needed');
    echo '<section class="card"><h2>Order tracking upgrade needed</h2><p>Run <code>php bin/install.php</code> in the license directory, then reload this panel.</p></section>';
    lm_footer();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status' || $action === 'check_payment') {
        try {
            $payId = (int) ($_POST['payment_id'] ?? 0);
            if ($action === 'update_status') {
                $completion = lm_set_order_status($pdo, $payId, (string) ($_POST['order_status'] ?? ''));
                $mailResult = $completion ? lm_deliver_order($completion, true) : lm_send_order_status_email($pdo, $payId);
                $msg = 'Order status saved. '.$mailResult['message'];
            } else {
                $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
                $st->execute([$payId]);
                $pay = $st->fetch();
                if (!$pay || $pay['gateway'] !== 'stripe' || empty($pay['gateway_reference'])) {
                    throw new RuntimeException('No Stripe session found for this order.');
                }
                $res = http_request('GET', 'https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($pay['gateway_reference']), null, ['Authorization: Bearer '.setting('stripe_secret_key', '')]);
                if ($res['code'] !== 200) throw new RuntimeException('Unable to retrieve Stripe payment. Try again.');
                $result = lm_verify_stripe_order($pdo, $res['json']);
                $mailResult = lm_deliver_order($result, true);
                $msg = 'Payment verified. '.$mailResult['message'];
            }
        } catch (Throwable $ex) {
            $msg = $ex instanceof InvalidArgumentException || $ex instanceof RuntimeException ? $ex->getMessage() : 'Unable to update the order. Please check the server logs.';
        }
        header('Location: payments.php?msg='.urlencode($msg));
        exit;
    }
    if ($action === 'send_order_email' || $action === 'resend_email') {
        $mailResult = lm_send_order_status_email($pdo, (int) ($_POST['payment_id'] ?? 0));
        header('Location: payments.php?msg='.urlencode($mailResult['message']));
        exit;
    }
    if ($action === 'send_builder_email') {
        $payId = (int) ($_POST['payment_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $st->execute([$payId]);
        $order = $st->fetch();
        if ($order) {
            $items = json_decode($order['items_json'] ?? 'null', true) ?: [];
            if (!$items && !empty($order['license_id'])) {
                $stLic = $pdo->prepare('SELECT * FROM licenses WHERE id = ?');
                $stLic->execute([$order['license_id']]);
                $lRow = $stLic->fetch();
                if ($lRow) {
                    $items[] = [
                        'id' => (int)$lRow['id'],
                        'slug' => $lRow['product_slug'],
                        'name' => ucfirst($lRow['product_slug']),
                        'license_key' => $lRow['license_key'],
                        'valid_until' => $lRow['valid_until'],
                    ];
                }
            }
            $bRes = lm_send_app_builder_welcome_email($pdo, $order, $items, true);
            $msg = $bRes['message'];
        } else {
            $msg = 'Order not found.';
        }
        header('Location: payments.php?msg='.urlencode($msg));
        exit;
    }
}

$filterStatus = (string) ($_GET['status'] ?? '');
$filterEmail = trim((string) ($_GET['email'] ?? ''));
$where = [];
$args = [];
$statusExpr = "COALESCE(p.order_status, CASE WHEN p.status IN ('paid','redeemed') THEN 'completed' ELSE 'pending' END)";
if (in_array($filterStatus, ['pending', 'processing', 'completed'], true)) {
    $where[] = $statusExpr.' = ?';
    $args[] = $filterStatus;
}
if ($filterEmail !== '') {
    $where[] = 'p.email LIKE ?';
    $args[] = '%'.$filterEmail.'%';
}
$st = $pdo->prepare('SELECT p.*, l.license_key, COALESCE(p.target_domain, l.bound_domain) AS bound_domain FROM payments p LEFT JOIN licenses l ON l.id = p.license_id'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY p.id DESC LIMIT 500');
$st->execute($args);
$rows = $st->fetchAll();
$counts = ['pending' => 0, 'processing' => 0, 'completed' => 0];
foreach ($pdo->query('SELECT '.$statusExpr.' AS workflow_status, COUNT(*) AS total FROM payments p GROUP BY workflow_status')->fetchAll() as $count) {
    $counts[$count['workflow_status']] = (int) $count['total'];
}

$token = csrf_token();
lm_header('payments', 'Payment Orders');
?>
<section class="card orders-page">
    <style>
        .orders-page { min-width:0; }
        .orders-page .tag { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
        .orders-page .orders-summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .orders-page .orders-filters { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; margin-bottom:22px; }
        .orders-page .orders-filters label { flex:1 1 200px; min-width:0; }
        .orders-page .orders-filters input, .orders-page .orders-filters select { width:100%; min-width:0; box-sizing:border-box; }
        .orders-page .orders-filters > a { padding:9px 0; }
        .orders-page .orders-list { display:grid; gap:18px; }
        .orders-page .order-card { min-width:0; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
        .orders-page .order-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; padding:20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; border-radius:12px 12px 0 0; }
        .orders-page .order-heading { flex:1 1 250px; min-width:0; }
        .orders-page .order-heading h3 { margin:0 0 8px; font-size:16px; line-height:1.5; overflow-wrap:anywhere; }
        .orders-page .order-heading-meta { display:flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:12px; }
        .orders-page .order-totals { display:flex; flex-direction:column; align-items:flex-end; gap:7px; font-size:12px; }
        .orders-page .order-amount { font-size:17px; white-space:nowrap; color:#0f172a; }
        .orders-page .order-body { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.3fr); gap:28px; padding:20px; }
        .orders-page .order-body > section { min-width:0; }
        .orders-page .order-body h4 { margin:0 0 14px; font-size:12px; letter-spacing:.04em; text-transform:uppercase; color:#64748b; }
        .orders-page .order-details { display:grid; gap:14px; margin:0; }
        .orders-page .order-details dt { margin:0 0 4px; font-size:11px; color:#64748b; }
        .orders-page .order-details dd { margin:0; font-size:13px; font-weight:500; overflow-wrap:anywhere; }
        .orders-page .order-details code { font-size:12px; }
        .orders-page .order-license-list { display:grid; gap:10px; margin:0; padding:0; list-style:none; }
        .orders-page .order-license-list li { min-width:0; }
        .orders-page .order-license-name { display:block; margin-bottom:4px; font-size:12px; font-weight:600; overflow-wrap:anywhere; }
        .orders-page .order-license-value { display:flex; align-items:center; gap:8px; min-width:0; }
        .orders-page .order-license-value code { min-width:0; padding:5px 8px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:5px; color:#1e293b; font-size:12px; font-weight:600; overflow-wrap:anywhere; }
        .orders-page .order-license-value .copy-btn { flex-shrink:0; width:30px; height:30px; padding:4px; }
        .orders-page .order-product-list { margin:0 0 12px; padding-left:18px; font-size:13px; }
        .orders-page .order-product-list li { margin-bottom:6px; overflow-wrap:anywhere; }
        .orders-page .order-licenses > p { font-size:13px; }
        .orders-page .order-error { margin:0 20px 16px; padding:10px 12px; background:#fffbeb; color:#92400e; border-radius:6px; font-size:12px; overflow-wrap:anywhere; }
        .orders-page .order-actions { display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px; padding:16px 20px; border-top:1px solid #e2e8f0; }
        .orders-page .order-actions form { margin:0; min-width:0; }
        .orders-page .order-status-form { display:flex; flex-wrap:wrap; align-items:flex-end; gap:8px; }
        .orders-page .order-status-form label { min-width:150px; }
        .orders-page .order-actions button { min-height:38px; font-size:12px; }
        .orders-page .order-actions select { width:100%; min-height:38px; box-sizing:border-box; }
        .orders-page .orders-empty { margin:0; padding:28px 16px; text-align:center; }
        @media (max-width:1100px) { .orders-page .order-body { grid-template-columns:minmax(0,1fr); gap:22px; } }
        @media (max-width:560px) {
            .orders-page { padding:16px 12px; }
            .orders-page > h2 { font-size:18px; }
            .orders-page .order-header, .orders-page .order-body, .orders-page .order-actions { padding:16px 12px; }
            .orders-page .order-heading { flex-basis:100%; }
            .orders-page .order-totals { flex-direction:row; align-items:center; flex-wrap:wrap; justify-content:flex-start; }
            .orders-page .order-actions > form, .orders-page .order-actions button, .orders-page .order-status-form label { width:100%; }
            .orders-page .order-status-form { display:grid; grid-template-columns:minmax(0,1fr); }
        }
    </style>
    <h2><span>💳</span> Payment Orders &amp; Purchase History (<?= count($rows) ?>)</h2>
    <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:13px">
        Choosing Completed generates a license and private download link for every ordered product and emails them to the customer, including for manually approved orders. Pending and Processing send a status update. Send Order Email resends the full delivery for completed orders.
    </p>
    <div class="orders-summary">
        <?php foreach ($counts as $status => $count): ?>
            <a class="tag <?= $status === 'completed' ? 'green' : ($status === 'processing' ? 'blue' : 'amber') ?>" href="payments.php?status=<?= e($status) ?>"><?= e(ucfirst($status)) ?>: <?= $count ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="payments.php" class="orders-filters">
        <label>Order status <select name="status"><option value="">All statuses</option>
        <?php foreach (['pending', 'processing', 'completed'] as $status): ?>
            <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
        <?php endforeach; ?>
        </select></label>
        <label>Customer email <input name="email" value="<?= e($filterEmail) ?>" placeholder="Search email"></label>
        <button type="submit">Filter</button>
        <a href="payments.php">Clear</a>
    </form>
    <div class="orders-list">
        <?php foreach ($rows as $r):
            $items = !empty($r['items_json']) ? json_decode($r['items_json'], true) : [];
            $snapshot = !empty($r['checkout_json']) ? json_decode($r['checkout_json'], true) : [];
            $workflowStatus = lm_order_status($r);
            $title = $snapshot['title'] ?? ($r['bundle_slug'] ?: $r['product_slug']);
        ?>
        <article class="order-card" aria-labelledby="order-title-<?= (int) $r['id'] ?>">
            <header class="order-header">
                <div class="order-heading">
                    <h3 id="order-title-<?= (int) $r['id'] ?>"><?= e($title) ?></h3>
                    <div class="order-heading-meta">
                        <span class="tag blue"><?= e($r['bundle_slug'] ?: $r['product_slug']) ?></span>
                        <span class="muted">Order #<?= (int) $r['id'] ?> · <?= e($r['created_at']) ?></span>
                        <?php if (!empty($r['builder_email_sent'])): ?>
                            <span class="tag green" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;" title="App Builder access welcome email dispatched to customer">🔨 Builder Email Sent</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="order-totals">
                    <strong class="order-amount"><?= e(number_format((float) $r['amount'], 2)).' '.e($r['currency']) ?></strong>
                    <span class="tag <?= $workflowStatus === 'completed' ? 'green' : ($workflowStatus === 'processing' ? 'blue' : 'amber') ?>">● <?= e(ucfirst($workflowStatus)) ?></span>
                    <span class="muted">Payment: <?= e(ucfirst($r['status'])) ?></span>
                </div>
            </header>
            <div class="order-body">
                <section aria-label="Customer and payment details">
                    <h4>Customer &amp; payment</h4>
                    <dl class="order-details">
                        <div><dt>Customer email</dt><dd><?= e($r['email'] ?: 'Not provided') ?></dd></div>
                        <div><dt>Installation domain</dt><dd><?= e($r['bound_domain'] ?: 'Not specified') ?></dd></div>
                        <div><dt>Payment gateway</dt><dd><?= e(strtoupper($r['gateway'])) ?></dd></div>
                        <div><dt>Order reference</dt><dd><code><?= e($r['reference']) ?></code></dd></div>
                        <?php if (!empty($r['gateway_reference']) && $r['gateway_reference'] !== $r['reference']): ?>
                            <div><dt>Gateway reference</dt><dd><code><?= e($r['gateway_reference']) ?></code></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>
                <section class="order-licenses" aria-label="Ordered products and license keys">
                    <h4>Ordered products &amp; licenses</h4>
                    <?php if (is_array($items) && $items): ?>
                        <ul class="order-license-list">
                        <?php foreach ($items as $it): ?>
                            <li>
                                <span class="order-license-name"><?= e($it['name'] ?? $it['slug'] ?? 'License') ?></span>
                                <div class="order-license-value">
                                    <code><?= e($it['license_key'] ?? '') ?></code>
                                    <button type="button" class="copy-btn" onclick='copyKey(<?= json_encode($it['license_key'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, this)' aria-label="Copy license key for <?= e($it['name'] ?? $it['slug'] ?? 'product') ?>" title="Copy license key">📋</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php elseif (!empty($r['license_key'])): ?>
                        <div class="order-license-value">
                            <code><?= e($r['license_key']) ?></code>
                            <button type="button" class="copy-btn" onclick='copyKey(<?= json_encode($r['license_key'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, this)' aria-label="Copy license key" title="Copy license key">📋</button>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($snapshot['products'])): ?>
                            <ul class="order-product-list">
                                <?php foreach ($snapshot['products'] as $product): ?><li><?= e($product['name'] ?? $product['slug'] ?? '') ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p class="muted">License keys have not been issued yet.</p>
                    <?php endif; ?>
                </section>
            </div>
            <?php if (!empty($r['order_error'])): ?><p class="order-error" role="status"><?= e($r['order_error']) ?></p><?php endif; ?>
            <footer class="order-actions">
                <form method="post" action="payments.php" class="order-status-form">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="payment_id" value="<?= e($r['id']) ?>">
                    <label for="order-status-<?= (int) $r['id'] ?>">Order status
                        <select id="order-status-<?= (int) $r['id'] ?>" name="order_status">
                            <?php foreach (['pending', 'processing', 'completed'] as $status): ?>
                                <option value="<?= $status ?>" <?= $workflowStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Save &amp; Email</button>
                </form>
                <?php if (!empty($r['email'])): ?>
                    <form method="post" action="payments.php">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="send_order_email">
                        <input type="hidden" name="payment_id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="outline-secondary">✉️ Send Order Email</button>
                    </form>
                <?php endif; ?>
                <?php if ($r['gateway'] === 'stripe' && !empty($r['gateway_reference']) && $r['status'] !== 'paid'): ?>
                    <form method="post" action="payments.php">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="check_payment">
                        <input type="hidden" name="payment_id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="outline-secondary">Check Stripe Payment</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($r['email']) && !empty($r['license_id']) && $workflowStatus === 'completed'): ?>
                    <form method="post" action="payments.php" onsubmit="return confirm('Resend license keys and download links to the customer?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="resend_email">
                        <input type="hidden" name="payment_id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="outline-secondary">Resend Licenses &amp; Downloads</button>
                    </form>
                    <form method="post" action="payments.php" onsubmit="return confirm('Send App Builder access instructions and direct 1-click builder link to <?= e($r['email']) ?>?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="send_builder_email">
                        <input type="hidden" name="payment_id" value="<?= e($r['id']) ?>">
                        <button type="submit" class="outline-secondary" style="color:#4f46e5;border-color:#c7d2fe;background:#f5f3ff;">
                            🔨 <?= !empty($r['builder_email_sent']) ? 'Resend Builder Access Email' : 'Send Builder Access Email' ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($workflowStatus === 'completed' || $r['status'] === 'paid'): ?>
                    <a href="invoice.php?order=<?= (int) $r['id'] ?>" target="_blank" class="button outline-secondary" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-weight:600;color:#334155;background:#fff;min-height:38px;box-sizing:border-box">📄 View Invoice</a>
                    <a href="invoice.php?order=<?= (int) $r['id'] ?>&format=pdf" target="_blank" class="button outline-secondary" style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-weight:600;color:#4f46e5;background:#f5f3ff;min-height:38px;box-sizing:border-box">📥 Invoice PDF</a>
                <?php endif; ?>
            </footer>
        </article>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="orders-empty muted">No orders match these filters.</p><?php endif; ?>
    </div>
</section>
<?php lm_footer();
