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
        $planVal = trim($_POST['plan'] ?? '') ?: null;
        $rawLimit = trim((string)($_POST['app_builder_monthly_limit'] ?? ''));
        $isCore = in_array($slug, ['core', 'main', 'pos', 'zoom-pos'], true);
        $appBuilderLimit = $isCore
            ? ($rawLimit !== '' ? (int)$rawLimit : ($planVal === 'extended' ? -1 : 10))
            : null;

        $lic = issue_license(
            $pdo,
            $slug,
            strtolower(trim($_POST['bound_domain'] ?? '')) ?: null,
            trim($_POST['client_email'] ?? ''),
            $planVal,
            trim($_POST['valid_until'] ?? '') !== '' ? null : null,
            $appBuilderLimit
        );
        if (trim($_POST['valid_until'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET valid_until = ? WHERE id = ?')->execute([$_POST['valid_until'], $lic['id']]);
        }
        if (trim($_POST['license_key'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET license_key = ? WHERE id = ?')->execute([trim($_POST['license_key']), $lic['id']]);
            $lic['license_key'] = trim($_POST['license_key']);
        }
        try {
            $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = ? WHERE id = ?')->execute([$appBuilderLimit, $lic['id']]);
        } catch (Throwable $e) {}

        $limitMsg = $isCore 
            ? ' (App Builder limit: '.($appBuilderLimit === -1 ? 'Unlimited' : $appBuilderLimit.' builds/mo').')'
            : ' (Module / Extension — No App Builder limit)';
        $flash = 'Issued '.$lic['license_key'].$limitMsg;
        $redirectKey = $lic['license_key'];
    } elseif ($action === 'update_license_limit' && $id) {
        $stChk = $pdo->prepare('SELECT product_slug FROM licenses WHERE id = ? LIMIT 1');
        $stChk->execute([$id]);
        $prodSlug = (string)$stChk->fetchColumn();
        if (!in_array($prodSlug, ['core', 'main', 'pos', 'zoom-pos'], true)) {
            $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = NULL WHERE id = ?')->execute([$id]);
            $flash = 'App Builder limits are applicable for the Core script only (not for modules or extensions). Limit removed.';
        } else {
            $limVal = trim((string)($_POST['app_builder_monthly_limit'] ?? ''));
            $lim = $limVal !== '' ? (int)$limVal : 10;
            try {
                $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = ? WHERE id = ?')->execute([$lim, $id]);
                $flash = 'App Builder limit for license updated to ' . ($lim === -1 ? 'Unlimited' : $lim . ' builds/month') . '.';
            } catch (Throwable $e) {
                $flash = 'Could not update limit: ' . $e->getMessage();
            }
        }
        $curr = $pdo->prepare('SELECT license_key FROM licenses WHERE id = ?');
        $curr->execute([$id]);
        $redirectKey = (string) $curr->fetchColumn();
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
        <thead><tr><th>License Key</th><th>Product</th><th>Client</th><th>Domain</th><th>Status</th><th>Valid until</th><th>Build Limit</th><th>Last seen</th><th>Actions</th></tr></thead>
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
                <td>
                    <?php if (!in_array($l['product_slug'], ['core', 'main', 'pos', 'zoom-pos'], true)): ?>
                        <span class="muted" style="font-size:11.5px;" title="App Builder applies only to Core SaaS script">N/A (Module)</span>
                    <?php elseif ($l['plan'] === 'extended' || (isset($l['app_builder_monthly_limit']) && (int)$l['app_builder_monthly_limit'] === -1)): ?>
                        <span class="tag green" title="Unlimited App Builds">Unlimited</span>
                    <?php elseif (isset($l['app_builder_monthly_limit']) && $l['app_builder_monthly_limit'] !== null && $l['app_builder_monthly_limit'] !== ''): ?>
                        <span class="tag blue" title="User Build Limit"><?= (int)$l['app_builder_monthly_limit'] ?> / mo</span>
                    <?php elseif (!empty($l['bundle_id'])): ?>
                        <span class="tag purple" title="Bundle Limit">Bundle</span>
                    <?php else: ?>
                        <span class="muted" title="Default Build Limit">10 / mo</span>
                    <?php endif; ?>
                </td>
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
        <?php if (! $licenses): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No licenses found matching your filters.</td></tr><?php endif; ?>
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
                    <?php $isCoreSel = in_array($selected['product_slug'], ['core', 'main', 'pos', 'zoom-pos'], true); ?>
                    <div class="kv-item">
                        <span class="kv-label">App Builder Monthly Limit</span>
                        <span class="kv-val">
                            <?php if ($isCoreSel): ?>
                                <form method="post" action="index.php" style="display:inline-flex;align-items:center;gap:8px;margin:0;">
                                    <input type="hidden" name="csrf" value="<?= e($token) ?>">
                                    <input type="hidden" name="action" value="update_license_limit">
                                    <input type="hidden" name="id" value="<?= (int) $selected['id'] ?>">
                                    <input type="number" name="app_builder_monthly_limit" 
                                           value="<?= isset($selected['app_builder_monthly_limit']) && $selected['app_builder_monthly_limit'] !== null && $selected['app_builder_monthly_limit'] !== '' ? (int)$selected['app_builder_monthly_limit'] : ($selected['plan'] === 'extended' ? -1 : 10) ?>" 
                                           min="-1" step="1" 
                                           style="width:80px;padding:4px 8px;font-size:12px;border:1px solid #cbd5e1;border-radius:6px;font-weight:700;">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:11.5px;background:#4f46e5;color:#fff;border-radius:6px;border:none;font-weight:700;cursor:pointer;">
                                        Save Limit
                                    </button>
                                    <span class="muted" style="font-size:11px;margin-left:6px;">(-1 = Unlimited)</span>
                                </form>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;">N/A (Core script only &mdash; not applicable for modules/extensions)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="kv-item">
                        <span class="kv-label">App Builder Access</span>
                        <span class="kv-val">
                            <?php if ($isCoreSel): ?>
                                <a href="../app-builder/?key=<?= urlencode($selected['license_key']) ?>" target="_blank" class="btn" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:4px 10px;font-size:12px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
                                    🔨 Open App Builder as Licensee &rarr;
                                </a>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;">Core script only</span>
                            <?php endif; ?>
                        </span>
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
                                    <td><span class="tag <?= lm_order_status($ord) === 'completed' ? 'green' : 'amber' ?>">● <?= e(ucfirst(lm_order_status($ord))) ?></span><div class="muted">Payment: <?= e($ord['status']) ?></div><a href="payments.php?email=<?= e(urlencode($selected['client_email'])) ?>">Manage order</a></td>
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
            <select name="product_slug" id="field_issue_product" required onchange="toggleBuilderLimitField(this.value)">
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= $p['slug'] === 'core' ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Client email (optional)<input type="email" name="client_email" placeholder="client@example.com"></label>
        <label>Bind domain (optional)<input name="bound_domain" placeholder="crm.example.com"></label>
        <label>Valid until (optional)<input type="date" name="valid_until"></label>
        <label>License Tier / Plan
            <select name="plan" id="field_issue_plan" onchange="updatePlanDefaultLimit(this.value)">
                <option value="regular" selected>regular (Standard Self-Service Domain)</option>
                <option value="extended">extended (Full White-label Branding & Custom APK/EXE)</option>
            </select>
        </label>
        <div id="container_builder_limit" style="grid-column: 1 / -1;">
            <label>App Builder Monthly Limit
                <input type="number" name="app_builder_monthly_limit" id="field_issue_limit" value="10" min="-1" step="1" placeholder="10 (-1 for unlimited)">
            </label>
            <span class="muted" style="display:block; font-size: 11.5px; color: #64748b; margin-top: 4px; margin-bottom: 6px;">
                🔨 <strong>Core Script Build Limit:</strong> Enter monthly compilation quota (Android, Web, Windows, iOS). Use <strong>-1</strong> for Unlimited builds, <strong>0</strong> to disable builder, or a specific count (e.g. 5, 10, 25).
            </span>
        </div>
        <div id="container_module_notice" style="display:none; grid-column: 1 / -1; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12.5px; color: #475569; line-height: 1.5;">
            📦 <strong>Module / Extension License:</strong> Build limits are applicable for the <strong>Core SaaS Script only</strong>. Modules and extensions do not have build limits.
        </div>
        <label>Key (optional)<input name="license_key" placeholder="auto-generated"></label>
        <button type="submit">✨ Issue license</button>
    </form>
</section>

<script>
function toggleBuilderLimitField(slug) {
    const isCore = ['core', 'main', 'pos', 'zoom-pos'].includes((slug || '').toLowerCase().trim());
    const limitContainer = document.getElementById('container_builder_limit');
    const noticeContainer = document.getElementById('container_module_notice');
    const input = document.getElementById('field_issue_limit');
    if (limitContainer) limitContainer.style.display = isCore ? 'block' : 'none';
    if (noticeContainer) noticeContainer.style.display = isCore ? 'none' : 'block';
    if (input) {
        if (!isCore) {
            input.value = '';
        } else if (input.value === '') {
            const plan = document.getElementById('field_issue_plan')?.value || 'regular';
            input.value = (plan === 'extended') ? '-1' : '10';
        }
    }
}

function updatePlanDefaultLimit(plan) {
    const input = document.getElementById('field_issue_limit');
    const product = document.getElementById('field_issue_product')?.value || 'core';
    const isCore = ['core', 'main', 'pos', 'zoom-pos'].includes(product.toLowerCase().trim());
    if (!input || !isCore) return;
    if (plan === 'extended') {
        input.value = '-1';
    } else if (input.value === '-1') {
        input.value = '10';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const prodSelect = document.getElementById('field_issue_product');
    if (prodSelect) {
        toggleBuilderLimitField(prodSelect.value);
    }
});
</script>
<?php lm_footer();