<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $flash = 'Done.';
    $redirectKey = '';

    if ($action === 'issue') {
        $slug = strtolower(trim($_POST['product_slug'] ?? ''));
        $lic = issue_license(
            $pdo,
            $slug,
            strtolower(trim($_POST['bound_domain'] ?? '')) ?: null,
            trim($_POST['client_email'] ?? ''),
            trim($_POST['plan'] ?? '') ?: null,
            trim($_POST['valid_until'] ?? '') !== '' ? null : null
        );
        if (trim($_POST['valid_until'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET valid_until = ? WHERE id = ?')->execute([$_POST['valid_until'], $lic['id']]);
        }
        if (trim($_POST['license_key'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET license_key = ? WHERE id = ?')->execute([trim($_POST['license_key']), $lic['id']]);
            $lic['license_key'] = trim($_POST['license_key']);
        }
        $flash = 'Issued '.$lic['license_key'];
        $redirectKey = $lic['license_key'];
    } elseif ($action === 'regenerate' && $id) {
        $newKey = generate_license_key();
        $pdo->prepare('UPDATE licenses SET license_key = ? WHERE id = ?')->execute([$newKey, $id]);
        $flash = 'License key regenerated: '.$newKey;
        $redirectKey = $newKey;
    } elseif (in_array($action, ['suspend', 'activate', 'revoke', 'expire'], true) && $id) {
        $map = ['suspend' => 'suspended', 'activate' => 'active', 'revoke' => 'revoked', 'expire' => 'expired'];
        $pdo->prepare('UPDATE licenses SET status = ? WHERE id = ?')->execute([$map[$action], $id]);
        $flash = 'License '.$map[$action].'.';
        $curr = $pdo->prepare('SELECT license_key FROM licenses WHERE id = ?');
        $curr->execute([$id]);
        $redirectKey = (string) $curr->fetchColumn();
    } elseif ($action === 'reset_domain' && $id) {
        $pdo->prepare('UPDATE licenses SET bound_domain = NULL, bound_ip = NULL WHERE id = ?')->execute([$id]);
        $flash = 'Domain binding cleared.';
        $curr = $pdo->prepare('SELECT license_key FROM licenses WHERE id = ?');
        $curr->execute([$id]);
        $redirectKey = (string) $curr->fetchColumn();
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM licenses WHERE id = ?')->execute([$id]);
        $flash = 'License deleted.';
    }

    $url = 'index.php?msg='.urlencode($flash);
    if ($redirectKey !== '') {
        $url .= '&key='.urlencode($redirectKey);
    }
    header('Location: '.$url);
    exit;
}

$fProduct = strtolower(trim($_GET['product'] ?? ''));
$fStatus = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

$where = [];
$args = [];
if ($fProduct !== '') { $where[] = 'product_slug = ?'; $args[] = $fProduct; }
if (in_array($fStatus, ['active', 'suspended', 'revoked', 'expired'], true)) { $where[] = 'status = ?'; $args[] = $fStatus; }
if ($q !== '') {
    $where[] = '(license_key LIKE ? OR client_email LIKE ? OR bound_domain LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT * FROM licenses'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY id DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($args);
$licenses = $st->fetchAll();

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$productsBySlug = [];
foreach ($products as $p) {
    $productsBySlug[$p['slug']] = $p;
}
$token = csrf_token();

$totalLicenses = (int) $pdo->query('SELECT COUNT(*) FROM licenses')->fetchColumn();
$activeLicenses = (int) $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
$boundDomains = (int) $pdo->query("SELECT COUNT(*) FROM licenses WHERE bound_domain IS NOT NULL AND bound_domain != ''")->fetchColumn();
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

// Determine inspected license — only when the admin actually clicked "Inspect"
// (no more auto-selecting the first row, so the list is what greets you by default).
$selectedKey = trim($_GET['key'] ?? '');
$selected = null;
if ($selectedKey !== '') {
    $selStmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ?');
    $selStmt->execute([$selectedKey]);
    $selected = $selStmt->fetch();
}

$selectedProduct = $selected ? ($productsBySlug[$selected['product_slug']] ?? null) : null;
$relatedOrders = [];
if ($selected) {
    $payStmt = $pdo->prepare('SELECT * FROM payments WHERE license_id = ? OR (email != "" AND email = ?) ORDER BY id DESC LIMIT 5');
    $payStmt->execute([$selected['id'], $selected['client_email']]);
    $relatedOrders = $payStmt->fetchAll();
}

lm_header('index', 'Licenses');
?>

<!-- Overview stats -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Total licenses</span>
        <span class="stat-num"><?= number_format($totalLicenses) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Active</span>
        <span class="stat-num"><?= number_format($activeLicenses) ?></span>
        <span class="stat-sub"><?= $totalLicenses ? round($activeLicenses / $totalLicenses * 100) : 0 ?>% of total</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Domain-bound</span>
        <span class="stat-num"><?= number_format($boundDomains) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Products</span>
        <span class="stat-num"><?= number_format($totalProducts) ?></span>
    </div>
</div>

<!-- All Licenses Ledger Card -->
<section class="card" style="margin-bottom:24px">
    <h3 class="card-title"><span>🔑</span> All Licenses (<?= count($licenses) ?>)</h3>
    <form method="get" class="grid" style="margin-bottom:18px">
        <label>Product
            <select name="product"><option value="">All Products</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= $fProduct === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status"><option value="">All Statuses</option>
                <?php foreach (['active', 'suspended', 'revoked', 'expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Search key / email / domain<input name="q" value="<?= e($q) ?>" placeholder="Search..."></label>
        <button type="submit" class="outline-secondary">🔍 Filter</button>
    </form>
    <div class="table-wrap">
    <table>
        <thead><tr><th>License Key</th><th>Product</th><th>Client</th><th>Domain</th><th>Status</th><th>Valid until</th><th>Last seen</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($licenses as $l): ?>
            <?php
            $isSelectedRow = ($selected && $selected['id'] === $l['id']);
            ?>
            <tr style="<?= $isSelectedRow ? 'background:#f5f3ff;' : '' ?>">
                <td>
                    <div class="key-badge">
                        <a href="index.php?key=<?= urlencode($l['license_key']) ?>" style="color:#4338ca;text-decoration:none;font-weight:700">
                            <?= e($l['license_key']) ?>
                        </a>
                        <button type="button" class="copy-btn" onclick="copyKey('<?= e($l['license_key']) ?>', this)" title="Copy key">📋</button>
                    </div>
                </td>
                <td><span class="tag blue"><?= e($l['product_slug']) ?></span></td>
                <td><?= $l['client_email'] !== '' ? e($l['client_email']) : '<span class="muted">—</span>' ?></td>
                <td><?= $l['bound_domain'] ? '<strong>'.e($l['bound_domain']).'</strong>' : '<span class="muted">unbound</span>' ?></td>
                <td>
                    <?php
                    $statusClass = $l['status'] === 'active' ? 'active' : ($l['status'] === 'suspended' ? 'suspended' : 'revoked');
                    ?>
                    <span class="status-pill <?= $statusClass ?>">● <?= ucfirst(e($l['status'])) ?></span>
                </td>
                <td><?= $l['valid_until'] ? e($l['valid_until']) : '<span class="muted">Perpetual</span>' ?></td>
                <td class="muted"><?= e($l['last_verified_at'] ?: 'Never') ?></td>
                <td class="acts">
                    <a href="index.php?key=<?= urlencode($l['license_key']) ?>" class="btn" style="background:#f3f4f6;color:#111827;border:1px solid #d1d5db;padding:4px 8px;font-size:11px">Inspect</a>
                    <?php
                    $btns = $l['status'] === 'active' ? ['suspend' => 'Suspend', 'revoke' => 'Revoke'] : ['activate' => 'Activate', 'revoke' => 'Revoke'];
                    foreach ($btns as $a => $label): ?>
                        <form method="post" action="index.php"><input type="hidden" name="csrf" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <button type="submit" class="<?= $a === 'activate' ? 'green' : ($a === 'revoke' ? 'danger' : '') ?>"><?= $label ?></button></form>
                    <?php endforeach; ?>
                    <form method="post" action="index.php"><input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="reset_domain"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <button type="submit">Reset</button></form>
                    <form method="post" action="index.php" onsubmit="return confirm('Delete this license permanently?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <button type="submit" class="danger">&times;</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $licenses): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:24px">No licenses found matching your filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($selected): ?>
    <?php
    $statusPillClass = $selected['status'] === 'active' ? 'active' : ($selected['status'] === 'suspended' ? 'suspended' : 'revoked');
    $productDisplayName = $selectedProduct ? $selectedProduct['name'] : ucfirst($selected['product_slug']);
    $clientDisplay = $selected['client_email'] ? explode('@', $selected['client_email'])[0] : 'Client #'.substr($selected['license_key'], 0, 5);
    $clientDisplay = ucwords(str_replace(['.', '_', '-'], ' ', $clientDisplay));
    $avatarInitial = strtoupper(substr($clientDisplay, 0, 1)) ?: 'C';
    $purchaseDate = date('M d Y', strtotime($selected['created_at']));
    $expirationDate = $selected['valid_until'] ? date('M d Y', strtotime($selected['valid_until'])) : 'Lifetime';
    ?>
    <!-- Breadcrumb and Top Action Bar -->
    <div class="page-action-header" id="license-detail" style="scroll-margin-top:76px">
        <div>
            <div class="breadcrumb-title">
                <span style="color:#94a3b8;font-size:15px">📄 &rsaquo;</span>
                <span class="breadcrumb-key"><?= e($selected['license_key']) ?></span>
                <span class="status-pill <?= $statusPillClass ?>">● <?= ucfirst(e($selected['status'])) ?></span>
            </div>
            <div class="breadcrumb-subtitle">
                <?= e($productDisplayName) ?> &middot; <span class="muted">Purchased <?= e($purchaseDate) ?></span>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <?php if ($selected['status'] === 'active'): ?>
                <form method="post" action="index.php" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                    <button type="submit" class="outline-danger">⃠ Disable License</button>
                </form>
            <?php else: ?>
                <form method="post" action="index.php" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                    <button type="submit" class="btn" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0">✓ Enable License</button>
                </form>
            <?php endif; ?>
            <?php if ($selected['bound_domain']): ?>
                <form method="post" action="index.php" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="reset_domain">
                    <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                    <button type="submit" class="outline-secondary" title="Unbind domain lock">Unbind Domain</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quantro Two-Column Inspector Layout -->
    <div class="detail-grid">
        <!-- Main Column (Left) -->
        <div class="detail-col-main">
            <!-- Card 1: License key -->
            <div class="card">
                <h3 class="card-title"><span class="icon">💻</span> License key</h3>
                <div class="key-box-row">
                    <input type="text" class="key-display-input" value="<?= e($selected['license_key']) ?>" readonly id="current-key-val">
                    <button type="button" class="btn" style="background:#f3f4f6;color:#1f2937;border:1px solid #e5e7eb;white-space:nowrap" onclick="copyKey('<?= e($selected['license_key']) ?>', this)">📋 Copy key</button>
                    <form method="post" action="index.php" style="margin:0" onsubmit="return confirm('Regenerate this license key? The previous key will stop functioning immediately.')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="regenerate">
                        <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                        <button type="submit" class="outline-secondary" style="white-space:nowrap">⟳ Regenerate key</button>
                    </form>
                </div>
            </div>

            <!-- Card 2: License details -->
            <div class="card">
                <h3 class="card-title"><span class="icon">📄</span> License details</h3>
                <div class="kv-list">
                    <div class="kv-item">
                        <span class="kv-label">Product name</span>
                        <span class="kv-val">
                            <?= e($productDisplayName) ?>
                            <span class="tag blue" style="font-size:11px"><?= e($selected['product_slug']) ?></span>
                        </span>
                    </div>
                    <div class="kv-item">
                        <span class="kv-label">Purchase date</span>
                        <span class="kv-val"><?= e($purchaseDate) ?></span>
                    </div>
                    <div class="kv-item">
                        <span class="kv-label">Expirations date</span>
                        <span class="kv-val"><?= e($expirationDate) ?> <span style="color:#9ca3af;margin-left:4px;font-size:12px">✎</span></span>
                    </div>
                    <div class="kv-item">
                        <span class="kv-label">Bound domain</span>
                        <span class="kv-val">
                            <?php if ($selected['bound_domain']): ?>
                                <strong style="color:#111827"><?= e($selected['bound_domain']) ?></strong>
                            <?php else: ?>
                                <span class="muted">Unbound (binds on first verification)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="kv-item">
                        <span class="kv-label">License tier / plan</span>
                        <span class="kv-val"><?= e($selected['plan'] ?: 'Standard Production') ?></span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Related orders -->
            <div class="card">
                <h3 class="card-title"><span class="icon">⏱</span> Related orders (<?= count($relatedOrders) ?>)</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatedOrders as $ord): ?>
                                <tr>
                                    <td><code>#<?= e(substr($ord['reference'], 0, 10)) ?></code></td>
                                    <td><?= date('M d Y H:i', strtotime($ord['created_at'])) ?></td>
                                    <td><strong><?= e(number_format((float) $ord['amount'], 2).' '.$ord['currency']) ?></strong></td>
                                    <td><span class="status-pill active">● <?= ucfirst(e($ord['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (! $relatedOrders): ?>
                                <tr><td colspan="4" class="muted" style="text-align:center;padding:16px">No orders recorded for this license.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar Column (Right) -->
        <div class="detail-col-side">
            <!-- Card 1: Customer Information -->
            <div class="card">
                <h3 class="card-title"><span class="icon">👤</span> Customer Information</h3>
                <div class="customer-header">
                    <div class="customer-avatar"><?= $avatarInitial ?></div>
                    <div>
                        <div style="font-weight:700;font-size:13.5px;color:#111827"><?= e($clientDisplay) ?></div>
                        <div class="muted"><?= count($relatedOrders) ?: 1 ?> orders</div>
                    </div>
                </div>

                <!-- Info Box 1: Email -->
                <div class="customer-info-box">
                    <div style="display:flex;align-items:center">
                        <div class="icon-box icon-purple">✉</div>
                        <div>
                            <div class="meta-title">Email address</div>
                            <div class="meta-val"><?= e($selected['client_email'] ?: 'No email configured') ?></div>
                        </div>
                    </div>
                    <span style="color:#9ca3af;font-size:14px;cursor:pointer">&vellip;</span>
                </div>

                <!-- Info Box 2: Domain -->
                <div class="customer-info-box">
                    <div style="display:flex;align-items:center">
                        <div class="icon-box icon-pink">⌂</div>
                        <div>
                            <div class="meta-title">Bound domain</div>
                            <div class="meta-val"><?= e($selected['bound_domain'] ?: 'Unbound') ?></div>
                        </div>
                    </div>
                    <span style="color:#9ca3af;font-size:14px;cursor:pointer">&vellip;</span>
                </div>

                <!-- Info Box 3: Verification IP & Date -->
                <div style="font-size:11.5px;color:#6b7280;padding:4px 2px;margin-top:6px;line-height:1.6">
                    <div><b>IP Binding:</b> <?= e($selected['bound_ip'] ?: ($selected['last_verified_ip'] ?: 'None')) ?></div>
                    <div><b>Last Check:</b> <?= $selected['last_verified_at'] ? date('M d Y, H:i', strtotime($selected['last_verified_at'])) : 'Never verified' ?></div>
                </div>
            </div>

            <!-- Card 2: Notes -->
            <div class="card">
                <h3 class="card-title"><span class="icon">📝</span> Notes</h3>
                <div style="font-size:11px;color:#6b7280;margin-bottom:10px">Write down order &amp; deployment notes ⓘ</div>
                <div style="background:#f9fafb;border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;font-size:12.5px;color:#374151;line-height:1.5">
                    Customer license active for <b><?= e($productDisplayName) ?></b>. Bound to <code><?= e($selected['bound_domain'] ?: 'unbound environment') ?></code>. Ready for automatic updates and package downloads.
                </div>
            </div>

            <!-- Card 3: Labels -->
            <div class="card">
                <h3 class="card-title"><span class="icon">🏷</span> Labels</h3>
                <div style="font-size:11px;color:#6b7280;margin-bottom:8px">Add label for this order ⓘ</div>
                <div class="tag-container">
                    <span class="pill-tag">Enterprise &times;</span>
                    <span class="pill-tag"><?= ucfirst(e($selected['product_slug'])) ?> &times;</span>
                    <span class="pill-tag"><?= $selected['valid_until'] ? 'Subscription' : 'Lifetime' ?> &times;</span>
                    <span class="pill-tag"><?= $selected['bound_domain'] ? 'Domain-Locked' : 'Portable' ?> &times;</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Issue License Card -->
<section class="card">
    <h3 class="card-title"><span>✨</span> Issue a license</h3>
    <form method="post" action="index.php" class="grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="issue">
        <label>Product
            <select name="product_slug" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Client email (optional)<input type="email" name="client_email" placeholder="client@example.com"></label>
        <label>Bind domain (optional)<input name="bound_domain" placeholder="crm.example.com"></label>
        <label>Valid until (optional)<input type="date" name="valid_until"></label>
        <label>License Tier / Plan
            <select name="plan">
                <option value="regular" selected>regular (Standard Self-Service Domain)</option>
                <option value="extended">extended (Full White-label Branding & Custom APK/EXE)</option>
            </select>
        </label>
        <label>Key (optional)<input name="license_key" placeholder="auto-generated"></label>
        <button type="submit">✨ Issue license</button>
    </form>
</section>
<?php lm_footer();