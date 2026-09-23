<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = trim($_POST['purchase_code'] ?? '');
    $slug = strtolower(trim($_POST['product_slug'] ?? ''));
    $email = trim($_POST['client_email'] ?? '');
    $domain = strtolower(trim($_POST['bound_domain'] ?? '')) ?: null;

    $v = envato_verify($code);
    if (! $v['ok']) {
        $result = ['ok' => false, 'message' => $v['message']];
    } else {
        $ttl = $v['supported_until'] ? max(1, (int) ceil((strtotime($v['supported_until']) - time()) / 86400)) : null;
        $lic = issue_license($pdo, $slug, $domain, $email ?: (string) $v['buyer'], 'codecanyon', $ttl);
        $pdo->prepare('INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id, email, status) VALUES (?, ?, 0, ?, ?, ?, ?, ?)')
            ->execute(['envato:'.substr($code, 0, 24), 'envato', setting('currency', 'USD'), $slug, $lic['id'], $email, 'redeemed']);
        $result = ['ok' => true, 'message' => 'Verified for buyer "'.$v['buyer'].'" ('.$v['item'].').', 'key' => $lic['license_key']];
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$token = csrf_token();
lm_header('redeem', 'Redeem Code');
?>
<section class="card">
    <h2><span>🎟️</span> Redeem a CodeCanyon purchase code</h2>
    <p class="muted" style="margin-top:-6px;margin-bottom:20px;font-size:13px">
        Verifies the Envato purchase code via the Envato API (token set in Settings) and mints a native license key bound to the buyer's domain.
    </p>
    <?php if ($result): ?>
        <div class="<?= $result['ok'] ? 'ok' : 'err' ?>"><?= e($result['message']) ?></div>
        <?php if ($result['ok']): ?>
            <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;display:block">Generated License Key</span>
                    <span style="font-family:ui-monospace,monospace;font-size:18px;font-weight:800;color:#0f172a"><?= e($result['key']) ?></span>
                </div>
                <button type="button" onclick="copyKey('<?= e($result['key']) ?>', this)" style="padding:8px 14px">📋 Copy Key</button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="redeem.php" class="grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>Purchase code<input name="purchase_code" required placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
        <label>Product
            <select name="product_slug" required>
                <?php foreach ($products as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option><?php endforeach; ?>
            </select>
        </label>
        <label>Client email (optional)<input type="email" name="client_email" placeholder="client@example.com"></label>
        <label>Bind domain (optional)<input name="bound_domain" placeholder="crm.example.com"></label>
        <button type="submit">🛡️ Verify &amp; issue key</button>
    </form>
</section>
<?php lm_footer();