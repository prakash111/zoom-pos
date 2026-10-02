<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../lib/helpers.php';
require_schema_web();

$pdo = db();
ensure_app_builder_schema($pdo);

// Handle POST actions
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        set_setting('github_repo', trim($_POST['github_repo'] ?? 'prakash111/zoom-pos'));
        set_setting('github_branch', trim($_POST['github_branch'] ?? 'feat/windows-offline-sync'));
        if (!empty($_POST['github_token']) && !str_starts_with($_POST['github_token'], '••••')) {
            set_setting('github_token', trim($_POST['github_token']));
        }
        set_setting('builder_default_monthly_limit', (string)(int)($_POST['builder_default_monthly_limit'] ?? 10));

        $planLimits = [
            'trial'        => (int)($_POST['limit_trial'] ?? 2),
            'free'         => (int)($_POST['limit_free'] ?? 2),
            'basic'        => (int)($_POST['limit_basic'] ?? 10),
            'regular'      => (int)($_POST['limit_regular'] ?? 10),
            'pro'          => (int)($_POST['limit_pro'] ?? 30),
            'extended'     => -1, // Unlimited
            'unlimited'    => -1,
        ];
        set_setting('builder_plan_limits', json_encode($planLimits));

        $flash = 'App Builder settings saved successfully.';
    } elseif ($action === 'update_product_limits' && !empty($_POST['product_limits']) && is_array($_POST['product_limits'])) {
        $stUpd = $pdo->prepare('UPDATE products SET app_builder_limit = ? WHERE slug = ?');
        foreach ($_POST['product_limits'] as $slug => $limitVal) {
            $lim = (int)$limitVal;
            $stUpd->execute([$lim, (string)$slug]);
        }
        $flash = 'Product build limits updated successfully.';
    } elseif ($action === 'update_bundle_limits' && !empty($_POST['bundle_limits']) && is_array($_POST['bundle_limits'])) {
        $stUpd = $pdo->prepare('UPDATE bundles SET app_builder_limit = ? WHERE slug = ?');
        foreach ($_POST['bundle_limits'] as $slug => $limitVal) {
            $lim = (int)$limitVal;
            $stUpd->execute([$lim, (string)$slug]);
        }
        $flash = 'Bundle build limits updated successfully.';
    } elseif ($action === 'update_license_limit' && !empty($_POST['license_id'])) {
        $licId = (int)$_POST['license_id'];
        $customLim = trim((string)($_POST['custom_limit'] ?? ''));
        if ($customLim === '' || $customLim === 'default') {
            $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = NULL WHERE id = ?')->execute([$licId]);
            $flash = 'License build quota reset to default bundle/plan limit.';
        } else {
            $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = ? WHERE id = ?')->execute([(int)$customLim, $licId]);
            $flash = 'License build quota override updated successfully.';
        }
    } elseif ($action === 'cancel_build' && !empty($_POST['id'])) {
        $pdo->prepare('UPDATE app_builds SET status = "cancelled" WHERE id = ?')->execute([(int)$_POST['id']]);
        $flash = 'Build cancelled.';
    }

    header('Location: build-stats.php?msg=' . urlencode($flash));
    exit;
}

// Global Statistics (Defensive try/catch)
$totalBuilds = 0;
$buildsThisMonth = 0;
$activeBuilds = 0;
$completedBuilds = 0;
$failedBuilds = 0;
$startOfMonth = gmdate('Y-m-01 00:00:00');

try {
    $totalBuilds = (int)$pdo->query('SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds')->fetchColumn();
    $buildsThisMonth = (int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds WHERE created_at >= '{$startOfMonth}' AND status = 'completed'")->fetchColumn();
    $activeBuilds = (int)$pdo->query("SELECT COUNT(*) FROM app_builds WHERE status IN ('queued', 'preparing', 'building')")->fetchColumn();
    $completedBuilds = (int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds WHERE status = 'completed'")->fetchColumn();
    $failedBuilds = (int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds WHERE status = 'failed'")->fetchColumn();
} catch (Throwable $e) {
    error_log('build-stats.php counts error: ' . $e->getMessage());
}

// Fetch Bundles for limit lookup
$bundles = [];
try {
    $bundles = $pdo->query('SELECT id, slug, name, price, currency, app_builder_limit, is_active, included_modules FROM bundles ORDER BY id DESC')->fetchAll();
} catch (Throwable $e) {}

// Build maps for quick lookup
$bundleLimitMap = [];
$bundleIdMap = [];
foreach ($bundles as $bn) {
    $bLim = (int)($bn['app_builder_limit'] ?? 20);
    $bundleLimitMap[$bn['slug']] = $bLim;
    $bundleIdMap[(int)$bn['id']] = $bLim;
}

// Filter parameters
$q = trim($_GET['q'] ?? '');
$fPlan = trim($_GET['plan'] ?? '');
$fPlatform = trim($_GET['platform'] ?? '');
$fStatus = trim($_GET['status'] ?? '');
$fDateFrom = trim($_GET['date_from'] ?? '');
$fDateTo = trim($_GET['date_to'] ?? '');

// 1. License Holder Build Statistics Query
$licenseStats = [];
$licWhere = [];
$licArgs = [':som' => $startOfMonth];
if ($q !== '') {
    $licWhere[] = '(l.license_key LIKE :q1 OR l.client_email LIKE :q2)';
    $licArgs[':q1'] = "%$q%";
    $licArgs[':q2'] = "%$q%";
}
if ($fPlan !== '') {
    $licWhere[] = 'l.plan = :plan';
    $licArgs[':plan'] = $fPlan;
}

$licSql = '
    SELECT 
        l.id as license_id,
        l.license_key,
        l.client_email,
        l.plan,
        l.product_slug,
        l.bundle_id,
        l.app_builder_monthly_limit,
        l.status as license_status,
        COUNT(DISTINCT COALESCE(b.batch_id, b.build_uid)) as total_builds,
        COUNT(DISTINCT CASE WHEN b.created_at >= :som AND b.status = "completed" THEN COALESCE(b.batch_id, b.build_uid) END) as builds_this_month,
        COUNT(DISTINCT CASE WHEN b.status = "completed" THEN COALESCE(b.batch_id, b.build_uid) END) as successful_builds,
        COUNT(DISTINCT CASE WHEN b.status = "failed" THEN COALESCE(b.batch_id, b.build_uid) END) as failed_builds,
        MAX(b.created_at) as last_build_date
    FROM licenses l
    LEFT JOIN app_builds b ON l.license_key = b.license_key
    ' . ($licWhere ? 'WHERE ' . implode(' AND ', $licWhere) : '') . '
    GROUP BY l.id, l.license_key, l.client_email, l.plan, l.product_slug, l.bundle_id, l.app_builder_monthly_limit, l.status
    ORDER BY builds_this_month DESC, total_builds DESC
    LIMIT 100
';

try {
    $stLic = $pdo->prepare($licSql);
    $stLic->execute($licArgs);
    $licenseStats = $stLic->fetchAll();
} catch (Throwable $e) {
    error_log('build-stats.php licSql error: ' . $e->getMessage());
}

// 2. All Builds Log Query
$allBuilds = [];
$bWhere = [];
$bArgs = [];
if ($q !== '') {
    $bWhere[] = '(b.build_uid LIKE ? OR b.license_key LIKE ? OR b.client_email LIKE ? OR b.app_name LIKE ?)';
    array_push($bArgs, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($fPlatform !== '') {
    $bWhere[] = 'b.platform = ?';
    $bArgs[] = $fPlatform;
}
if ($fStatus !== '') {
    $bWhere[] = 'b.status = ?';
    $bArgs[] = $fStatus;
}
if ($fDateFrom !== '') {
    $bWhere[] = 'b.created_at >= ?';
    $bArgs[] = $fDateFrom . ' 00:00:00';
}
if ($fDateTo !== '') {
    $bWhere[] = 'b.created_at <= ?';
    $bArgs[] = $fDateTo . ' 23:59:59';
}

$bSql = 'SELECT * FROM app_builds b ' . ($bWhere ? 'WHERE ' . implode(' AND ', $bWhere) : '') . ' ORDER BY b.id DESC LIMIT 100';
try {
    $stB = $pdo->prepare($bSql);
    $stB->execute($bArgs);
    $allBuilds = $stB->fetchAll();
} catch (Throwable $e) {
    error_log('build-stats.php bSql error: ' . $e->getMessage());
}

$token = csrf_token();
$tokenVal = setting('github_token', '');
$maskedToken = $tokenVal ? substr($tokenVal, 0, 7) . '••••••••••••••••••••' : '';
$limitsMap = json_decode((string)setting('builder_plan_limits', '{}'), true) ?: [];

lm_header('build-stats', 'Tenant App Build Statistics');
?>

<div class="content-header">
    <div>
        <h1 class="page-title">App Builder Management &amp; Build Limits</h1>
        <p class="page-subtitle">Configure monthly compilation quotas for the Main Core Script, Products, and Bundles, and monitor builds.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="../app-builder/" target="_blank" class="btn" style="background:#4f46e5;color:#fff;border-radius:8px;padding:8px 16px;text-decoration:none;font-weight:700;font-size:13px;">
            Open Builder UI &rarr;
        </a>
    </div>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="flash success" style="margin-bottom:20px;">
        ✓ <?= e($_GET['msg']) ?>
    </div>
<?php endif; ?>

<!-- Overview Stat Cards -->
<div class="stat-grid" style="grid-template-columns:repeat(5, 1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-label">Total Builds</div>
        <div class="stat-value"><?= number_format($totalBuilds) ?></div>
        <div class="stat-meta">Lifetime compilations</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Builds This Month</div>
        <div class="stat-value" style="color:#4f46e5;"><?= number_format($buildsThisMonth) ?></div>
        <div class="stat-meta">Current billing cycle</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Runners</div>
        <div class="stat-value" style="color:#2563eb;"><?= number_format($activeBuilds) ?></div>
        <div class="stat-meta">In-progress builds</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Successful Builds</div>
        <div class="stat-value" style="color:#059669;"><?= number_format($completedBuilds) ?></div>
        <div class="stat-meta">Artifacts generated</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Failed Builds</div>
        <div class="stat-value" style="color:#ef4444;"><?= number_format($failedBuilds) ?></div>
        <div class="stat-meta">Errors logged</div>
    </div>
</div>

<!-- SECTION: USER-SPECIFIC LICENSE QUOTA POLICY -->
<div class="card" style="margin-bottom:28px;padding:22px;border-left:4px solid #4f46e5;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div style="max-width:800px;">
            <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
                <span>👤</span> User-Specific License Quotas &amp; Allocation
            </h3>
            <p style="margin:6px 0 0;font-size:13px;color:#64748b;line-height:1.5;">
                App Builder limits are configured directly for each individual license upon issuance or edited on the fly in the licenses manager. Product-wise limits have been removed so each customer receives an independent quota. Bundled software packages continue to use dedicated bundle build limits.
            </p>
        </div>
        <div>
            <a href="index.php" class="btn" style="background:#4f46e5;color:#fff;text-decoration:none;padding:9px 18px;border-radius:8px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                ➕ Issue License with Custom Limit
            </a>
        </div>
    </div>
</div>

<!-- SECTION: BUNDLE BUILD LIMITS -->
<div class="card" style="margin-bottom:28px;padding:22px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;">
                <span>🎁</span> Bundle Monthly Build Limits
            </h3>
            <p style="margin:2px 0 0;font-size:12.5px;color:#64748b;">
                Define App Builder monthly quotas granted to buyers of bundled packages (e.g. All-in-One Enterprise Suite Bundle).
            </p>
        </div>
        <a href="bundles.php" class="outline-secondary" style="font-size:12px;text-decoration:none;padding:6px 12px;">Manage Bundles &rarr;</a>
    </div>

    <form method="post" action="build-stats.php">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="update_bundle_limits">

        <div class="table-wrap" style="margin-bottom:14px;">
            <table>
                <thead>
                    <tr>
                        <th style="width:160px;">Bundle Slug</th>
                        <th>Bundle Name</th>
                        <th>Included Products</th>
                        <th style="width:120px;">Price</th>
                        <th style="width:180px;">App Builder Monthly Limit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bundles)): ?>
                        <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:16px;">No bundles created yet. Create bundles in Bundles menu.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bundles as $bn): ?>
                            <?php 
                            $bLimit = (int)($bn['app_builder_limit'] ?? 20);
                            $modules = json_decode((string)($bn['included_modules'] ?? '[]'), true) ?: [];
                            ?>
                            <tr>
                                <td><code style="font-size:12px;font-weight:700;color:#0f172a;"><?= e($bn['slug']) ?></code></td>
                                <td style="font-weight:600;"><?= e($bn['name']) ?></td>
                                <td>
                                    <?php foreach ($modules as $mod): ?>
                                        <span class="tag <?= $mod === 'core' ? 'purple' : 'gray' ?>" style="font-size:11px;margin-right:3px;"><?= e($mod) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= number_format((float)$bn['price'], 2) ?> <?= e($bn['currency']) ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <input type="number" name="bundle_limits[<?= e($bn['slug']) ?>]" value="<?= $bLimit ?>" min="-1" step="1" 
                                               style="width:90px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;font-weight:700;">
                                        <span class="muted" style="font-size:11px;"><?= $bLimit === -1 ? 'Unlimited' : ($bLimit === 0 ? 'Disabled' : 'builds/mo') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag <?= !empty($bn['is_active']) ? 'green' : 'gray' ?>"><?= !empty($bn['is_active']) ? 'Active' : 'Inactive' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn" style="background:#4f46e5;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">
            💾 Save Bundle Build Limits
        </button>
    </form>
</div>

<!-- SECTION: LICENSE HOLDER BUILD STATISTICS -->
<div class="card" style="margin-bottom:28px;">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">License Holder Build Statistics &amp; Monthly Usage</h3>
            <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Real-time monthly build usage and custom quota overrides per customer license.</p>
        </div>
        <form method="get" action="build-stats.php" style="display:flex;gap:8px;align-items:center;">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search key or email..." style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;min-width:180px;">
            <button type="submit" class="outline-secondary" style="padding:5px 12px;font-size:12px;">Search</button>
            <?php if ($q !== ''): ?>
                <a href="build-stats.php" class="outline-secondary" style="padding:5px 10px;font-size:12px;text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>License Key</th>
                    <th>Customer / Email</th>
                    <th>Product / Bundle</th>
                    <th>Plan</th>
                    <th>Build Limit / mo</th>
                    <th>Used (Mo)</th>
                    <th>Remaining</th>
                    <th>Last Build</th>
                    <th>Success</th>
                    <th>Failed</th>
                    <th style="text-align:right;">Custom Quota</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licenseStats)): ?>
                    <tr><td colspan="11" style="text-align:center;padding:24px;color:#9ca3af;">No license holder records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($licenseStats as $ls): ?>
                        <?php
                        $p = strtolower((string)($ls['plan'] ?: 'regular'));
                        $prodSlug = (string)($ls['product_slug'] ?: 'core');
                        $isCore = in_array($prodSlug, ['core', 'main', 'pos', 'zoom-pos'], true);
                        $hasCustomOverride = ($ls['app_builder_monthly_limit'] !== null && $ls['app_builder_monthly_limit'] !== '');
                        
                        if (!$isCore) {
                            $limit = 0;
                            $limitSource = 'N/A (Module / Extension)';
                        } elseif ($hasCustomOverride) {
                            $limit = (int)$ls['app_builder_monthly_limit'];
                            $limitSource = 'User Quota';
                        } elseif ($p === 'extended' || $p === 'unlimited' || $p === 'enterprise') {
                            $limit = -1;
                            $limitSource = 'Tier (Extended)';
                        } elseif (!empty($ls['bundle_id']) && isset($bundleIdMap[(int)$ls['bundle_id']])) {
                            $limit = $bundleIdMap[(int)$ls['bundle_id']];
                            $limitSource = 'Bundle';
                        } elseif (isset($limitsMap[$p])) {
                            $limit = (int)$limitsMap[$p];
                            $limitSource = 'Global Plan (' . ucfirst($p) . ')';
                        } else {
                            $limit = 10;
                            $limitSource = 'Default';
                        }

                        $used = (int)$ls['builds_this_month'];
                        $rem = !$isCore ? '—' : (($limit === -1) ? '∞' : max(0, $limit - $used));
                        ?>
                        <tr>
                            <td>
                                <a href="index.php?key=<?= urlencode($ls['license_key']) ?>" style="font-family:monospace;font-weight:700;color:#4338ca;text-decoration:none;">
                                    <?= e($ls['license_key']) ?>
                                </a>
                            </td>
                            <td><?= $ls['client_email'] !== '' ? e($ls['client_email']) : '<span class="muted">—</span>' ?></td>
                            <td>
                                <span class="tag <?= $prodSlug === 'core' ? 'purple' : 'gray' ?>"><?= e($prodSlug) ?></span>
                            </td>
                            <td><span class="tag blue"><?= e($ls['plan'] ?: 'Regular') ?></span></td>
                            <td style="font-weight:700;">
                                <?php if (!$isCore): ?>
                                    <span class="muted" style="font-size:12px;">N/A (Module)</span>
                                <?php elseif ($limit === -1): ?>
                                    <span style="color:#059669;">Unlimited (-1)</span>
                                <?php elseif ($limit === 0): ?>
                                    <span style="color:#ef4444;">Disabled (0)</span>
                                <?php else: ?>
                                    <?= $limit ?>
                                <?php endif; ?>
                                <small style="display:block;font-size:10px;font-weight:normal;color:#94a3b8;"><?= e($limitSource) ?></small>
                            </td>
                            <td style="font-weight:700;color:#4f46e5;"><?= $used ?></td>
                            <td style="font-weight:700;color:<?= (!$isCore || $rem === '—') ? '#94a3b8' : (($rem === 0) ? '#ef4444' : '#059669') ?>;"><?= $rem ?></td>
                            <td class="muted" style="font-size:12px;"><?= !empty($ls['last_build_date']) ? time_ago($ls['last_build_date']) : 'Never' ?></td>
                            <td style="color:#059669;font-weight:700;"><?= (int)$ls['successful_builds'] ?></td>
                            <td style="color:#ef4444;font-weight:700;"><?= (int)$ls['failed_builds'] ?></td>
                            <td style="text-align:right;">
                                <?php if ($isCore): ?>
                                    <button type="button" class="outline-secondary" style="padding:3px 8px;font-size:11px;"
                                            onclick="openQuotaModal(<?= (int)$ls['license_id'] ?>, '<?= e($ls['license_key']) ?>', '<?= $hasCustomOverride ? (int)$ls['app_builder_monthly_limit'] : '' ?>')">
                                        ⚙ Set Limit
                                    </button>
                                <?php else: ?>
                                    <span class="muted" style="font-size:11px;">Core script only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION: ALL BUILDS LOG -->
<div class="card" style="margin-bottom:28px;">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border);">
        <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">All Build Requests Log</h3>
        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Complete historical record of all application builds processed by the Cloud Build Engine.</p>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Build UID</th>
                    <th>License Key</th>
                    <th>Customer</th>
                    <th>Platform</th>
                    <th>Application</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allBuilds)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:#9ca3af;">No build records found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($allBuilds as $b): ?>
                        <tr>
                            <td style="font-family:monospace;font-weight:700;color:#4338ca;"><?= e($b['build_uid']) ?></td>
                            <td style="font-family:monospace;font-size:12px;"><?= e(substr($b['license_key'], 0, 11)) ?>•••••</td>
                            <td><?= e($b['client_email']) ?></td>
                            <td><span class="tag blue"><?= ucfirst(e($b['platform'])) ?></span></td>
                            <td style="font-weight:600;"><?= e($b['app_name']) ?></td>
                            <td><span class="status-pill <?= e($b['status']) ?>">● <?= ucfirst(e($b['status'])) ?></span></td>
                            <td class="muted"><?= !empty($b['build_duration_seconds']) ? gmdate('i\m s\s', $b['build_duration_seconds']) : '—' ?></td>
                            <td class="muted"><?= time_ago($b['created_at']) ?></td>
                            <td style="text-align:right;">
                                <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                                    <a href="../app-builder/download.php?id=<?= urlencode($b['build_uid']) ?>" class="btn" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;padding:4px 8px;font-size:11px;text-decoration:none;border-radius:6px;font-weight:700;">
                                        ⬇ Download
                                    </a>
                                <?php elseif (in_array($b['status'], ['queued', 'preparing', 'building'], true)): ?>
                                    <form method="post" action="build-stats.php" style="display:inline;margin:0;" onsubmit="return confirm('Cancel this active build job?')">
                                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                                        <input type="hidden" name="action" value="cancel_build">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="danger" style="padding:4px 8px;font-size:11px;">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted" title="<?= e($b['error_message'] ?? '') ?>"><?= !empty($b['error_message']) ? 'Failed' : '—' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION: GITHUB & PLAN TIER SETTINGS -->
<div class="card" style="padding:24px;margin-bottom:28px;">
    <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;color:#0f172a;">Cloud Build Engine &amp; Global Plan Limits</h3>
    <p style="margin:0 0 20px;font-size:12px;color:#64748b;">Configure Cloud Build Engine credentials and monthly build quotas per pricing tier. 1 Build Pack includes all 4 platforms, and failed builds are excluded from quota consumption.</p>

    <form method="post" action="build-stats.php">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save_settings">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Repository Identifier</label>
                <input type="text" name="github_repo" value="<?= e(setting('github_repo', 'prakash111/zoom-pos')) ?>" required
                       style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                <span style="font-size:11px;color:#94a3b8;margin-top:4px;display:block;">Format: <code>owner/repo</code></span>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Default Compilation Branch</label>
                <input type="text" name="github_branch" value="<?= e(setting('github_branch', 'feat/windows-offline-sync')) ?>" required
                       style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                <span style="font-size:11px;color:#94a3b8;margin-top:4px;display:block;">Target branch for Option A (Latest Source)</span>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Cloud Engine Access Token (Workflow Scope)</label>
                <input type="password" name="github_token" placeholder="<?= e($maskedToken) ?>"
                       style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                <span style="font-size:11px;color:#94a3b8;margin-top:4px;display:block;">Leave blank to preserve current active token. Required scopes: <code>repo</code>, <code>workflow</code>.</span>
            </div>
        </div>

        <h4 style="margin:20px 0 12px;font-size:14px;font-weight:800;color:#0f172a;border-top:1px solid #f1f5f9;padding-top:16px;">
            Monthly Build Limits By Plan Tier (-1 = Unlimited)
        </h4>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:16px;margin-bottom:24px;">
            <div>
                <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;">Trial / Free</label>
                <input type="number" name="limit_free" value="<?= (int)($limitsMap['free'] ?? 2) ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
            </div>
            <div>
                <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;">Basic / Starter</label>
                <input type="number" name="limit_basic" value="<?= (int)($limitsMap['basic'] ?? 10) ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
            </div>
            <div>
                <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;">Regular / Standard</label>
                <input type="number" name="limit_regular" value="<?= (int)($limitsMap['regular'] ?? 10) ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
            </div>
            <div>
                <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;">Pro / Professional</label>
                <input type="number" name="limit_pro" value="<?= (int)($limitsMap['pro'] ?? 30) ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
            </div>
            <div>
                <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;">Extended / Unlimited</label>
                <input type="text" value="Unlimited (-1)" disabled style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#f1f5f9;color:#64748b;font-weight:700;">
            </div>
        </div>

        <button type="submit" class="btn" style="background:#4f46e5;color:#fff;padding:10px 24px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">
            Save App Builder Configuration
        </button>
    </form>
</div>

<!-- Modal Dialog for Custom Quota Override -->
<dialog id="quotaModal" style="border:1px solid #cbd5e1;border-radius:12px;padding:24px;width:100%;max-width:440px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);">
    <h3 style="margin:0 0 8px;font-size:16px;font-weight:800;color:#0f172a;">Set Custom Build Quota Override</h3>
    <p style="margin:0 0 16px;font-size:12.5px;color:#64748b;" id="quotaModalDesc">
        Override monthly build quota for license key.
    </p>

    <form method="post" action="build-stats.php">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="update_license_limit">
        <input type="hidden" name="license_id" id="modalLicenseId" value="">

        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Monthly Build Quota</label>
            <input type="number" name="custom_limit" id="modalCustomLimit" placeholder="e.g. 50 (or leave empty for default)" min="-1" step="1"
                   style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box;">
            <span style="font-size:11px;color:#64748b;margin-top:6px;display:block;">
                • Set positive number (e.g. <code>25</code>) for custom limit.<br>
                • Set <code>-1</code> for Unlimited builds.<br>
                • Set <code>0</code> to disable App Builder access.<br>
                • Leave empty to reset to default Bundle/Plan limit.
            </span>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <button type="button" class="outline-secondary" onclick="document.getElementById('quotaModal').close()" style="padding:7px 16px;font-size:13px;">Cancel</button>
            <button type="submit" class="btn" style="background:#4f46e5;color:#fff;padding:7px 18px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">Save Quota</button>
        </div>
    </form>
</dialog>

<script>
function openQuotaModal(id, key, currentVal) {
    document.getElementById('modalLicenseId').value = id;
    document.getElementById('modalCustomLimit').value = currentVal;
    document.getElementById('quotaModalDesc').innerHTML = 'Override monthly build quota for license <code>' + key + '</code>:';
    document.getElementById('quotaModal').showModal();
}
</script>

<?php
echo '</main></div></div></body></html>';
