<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/AccountManager.php';
require_once __DIR__ . '/lib/QuotaManager.php';
require_once __DIR__ . '/lib/layout.php';

ensure_app_builder_schema();

$authError = '';

// Handle login via GET parameter (e.g. from tenant settings link)
$queryKey = trim($_GET['key'] ?? $_GET['license_key'] ?? '');
if (!Auth::check() && !empty($queryKey) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $res = Auth::validateAndLogin($queryKey);
    if ($res['success']) {
        header('Location: index.php');
        exit;
    } else {
        $authError = $res['message'];
    }
}

// Handle login POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    csrf_check();
    $key = trim($_POST['license_key'] ?? '');
    $res = Auth::validateAndLogin($key);
    if ($res['success']) {
        header('Location: index.php');
        exit;
    } else {
        $authError = $res['message'];
    }
}

// 1. IF NOT LOGGED IN, RENDER LICENSE AUTHENTICATION SCREEN
if (!Auth::check()) {
    $token = csrf_token();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
        <title>License Authentication — ZoomNearby App Builder</title>
        <link rel="stylesheet" href="assets/app.css">
        <style>
            body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
            .login-card { max-width: 460px; width: 100%; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 36px 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
            .logo-badge { width: 52px; height: 52px; border-radius: 14px; background: #4f46e5; color: #fff; font-size: 26px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3); }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="logo-badge">🔨</div>
            <h2 style="text-align:center;margin:0 0 6px;font-size:22px;font-weight:800;color:#0f172a;">App Builder Access</h2>
            <p style="text-align:center;margin:0 0 24px;font-size:13px;color:#64748b;">Enter your purchase license key to customize and compile your Flutter applications.</p>

            <?php if (!empty($authError)): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;">
                    ⚠️ <?= e($authError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php">
                <input type="hidden" name="action" value="login">
                <?= csrf_field() ?>

                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Purchase License Key</label>
                    <input type="text" name="license_key" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required autofocus
                           value="<?= e($queryKey ?: ($_POST['license_key'] ?? '')) ?>"
                           style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;font-family:monospace;letter-spacing:1px;text-transform:uppercase;">
                    <span style="display:block;font-size:11px;color:#94a3b8;margin-top:6px;">Your license key received upon purchase or in your receipt email.</span>
                </div>

                <button type="submit" style="width:100%;padding:13px;background:#4f46e5;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(79, 70, 229, 0.25);">
                    Verify License & Open Dashboard &rarr;
                </button>
            </form>

            <div style="margin-top:24px;padding-top:18px;border-top:1px solid #f1f5f9;text-align:center;font-size:12px;color:#94a3b8;">
                Protected by Central License Management Engine &middot; <a href="https://license.zoomnearby.com" target="_blank" style="color:#6366f1;text-decoration:none;">License Server</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. USER IS AUTHENTICATED: RENDER BUILDER DASHBOARD
$licenseKey = Auth::key();
$plan = Auth::plan();

try {
    $stats = QuotaManager::getStatsForLicense($licenseKey, $plan);
} catch (Throwable $e) {
    $stats = [
        'plan' => $plan ?: 'regular',
        'limit' => 10,
        'used_this_month' => 0,
        'remaining_this_month' => 10,
        'can_build' => true,
        'is_unlimited' => false,
        'reset_date' => date('Y-m-01 00:00:00', strtotime('+1 month')),
    ];
}

// Fetch recent builds for this license via BuildManager (syncing in background)
require_once __DIR__ . '/lib/BuildManager.php';
$recentBuilds = [];
try {
    $pdo = db();
    $recentBuilds = BuildManager::getBuildsForUser($pdo, $licenseKey, Auth::email(), [], 10);
} catch (Throwable $e) {
    $recentBuilds = [];
}

// Check if any recent build is in-progress and poll
$hasActiveBuild = false;
$activeBuildUid = '';
foreach ($recentBuilds as $b) {
    if (in_array($b['status'] ?? '', ['queued', 'preparing', 'building'], true)) {
        $hasActiveBuild = true;
        $activeBuildUid = $b['build_uid'] ?? '';
        break;
    }
}

$account = Auth::account();
if (empty($account)) {
    try {
        $account = AccountManager::getLinkedDetails($licenseKey, Auth::email());
    } catch (Throwable $e) {
        $account = AccountManager::buildFinalDefaults($licenseKey, [], Auth::email());
    }
}

builder_header('dashboard', 'Dashboard');
?>

<?php if (!empty($account['company_name'])): ?>
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:8px;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:bold;">🏢</div>
            <div>
                <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Linked Business Account</div>
                <div style="font-size:14px;font-weight:800;color:#0f172a;"><?= e($account['company_name']) ?> <span style="font-size:12px;font-weight:400;color:#64748b;">(<?= e($account['product_name'] ?? 'Zoom Sales CRM') ?>)</span></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
            <?php if (!empty($account['server_url'])): ?>
                <span style="color:#64748b;">API: <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?= e(parse_url($account['server_url'], PHP_URL_HOST) ?: $account['server_url']) ?></code></span>
            <?php endif; ?>
            <a href="new-build.php" class="btn" style="background:#4f46e5;color:#fff;text-decoration:none;padding:5px 12px;border-radius:6px;font-weight:700;font-size:12px;">Configure Branding &rarr;</a>
        </div>
    </div>
<?php endif; ?>

<!-- Monthly Build Limit Banner -->
<div class="quota-box">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div>
            <span style="background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;">
                <?= e(ucfirst($plan)) ?> Plan Quota
            </span>
            <h2 style="margin:8px 0 2px;font-size:24px;font-weight:800;">Monthly App Builder Quota</h2>
            <p style="margin:0;font-size:13px;opacity:0.9;">
                1 Build Pack includes all 4 platforms (Android, Web, Windows &amp; iOS). Failed builds are never counted. Builds renew on <?= date('M 01, Y', strtotime($stats['reset_date'])) ?>.
            </p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:28px;font-weight:900;">
                <?= $stats['is_unlimited'] ? 'Unlimited' : ($stats['used_this_month'] . ' / ' . $stats['limit']) ?>
            </div>
            <div style="font-size:12px;opacity:0.85;">
                <?= $stats['is_unlimited'] ? 'Unlimited Monthly Builds' : ($stats['remaining_this_month'] . ' build pack' . ($stats['remaining_this_month'] != 1 ? 's' : '') . ' remaining') ?>
            </div>
        </div>
    </div>

    <?php if (!$stats['is_unlimited']): ?>
        <?php $pct = min(100, round(($stats['used_this_month'] / max(1, $stats['limit'])) * 100)); ?>
        <div class="quota-bar-bg">
            <div class="quota-bar-fill" style="width: <?= $pct ?>%; background: <?= $pct > 80 ? '#f59e0b' : '#10b981' ?>;"></div>
        </div>
    <?php endif; ?>
</div>

<!-- Statistics Cards Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:16px;margin-bottom:28px;">
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Total Builds</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $stats['total_builds'] ?></div>
        <div style="font-size:11px;color:#10b981;margin-top:2px;">All-time builds triggered</div>
    </div>
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">🤖 Android</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $stats['platforms']['android'] ?? 0 ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;">APK & AAB packages</div>
    </div>
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">🌐 Web</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $stats['platforms']['web'] ?? 0 ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;">PWA Web archives</div>
    </div>
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">💻 Windows</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $stats['platforms']['windows'] ?? 0 ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;">Desktop installers</div>
    </div>
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">🍏 iOS</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $stats['platforms']['ios'] ?? 0 ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;">Runner IPA archives</div>
    </div>
</div>

<!-- Recent Builds Section -->
<div class="card" style="padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div>
            <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;">Recent Build Activity</h3>
            <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Monitor active builds or download finished application packages.</p>
        </div>
        <a href="new-build.php" class="btn" style="background:#4f46e5;color:#fff;text-decoration:none;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;">+ New Build</a>
    </div>

    <div class="table-wrap">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:1.5px solid #e2e8f0;text-align:left;color:#64748b;font-size:11.5px;text-transform:uppercase;letter-spacing:0.5px;">
                    <th style="padding:10px 12px;">Build ID</th>
                    <th style="padding:10px 12px;">License / Order</th>
                    <th style="padding:10px 12px;">App &amp; Version</th>
                    <th style="padding:10px 12px;">Platform</th>
                    <th style="padding:10px 12px;">Status</th>
                    <th style="padding:10px 12px;">Date &amp; Time</th>
                    <th style="padding:10px 12px;">Output / Diagnostics</th>
                    <th style="padding:10px 12px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBuilds)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
                            No builds have been created yet. Tap <strong>"+ New Build"</strong> to generate your first Flutter app!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentBuilds as $b): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:12px;">
                                <a href="view-build.php?id=<?= urlencode($b['build_uid']) ?>" style="font-family:monospace;font-weight:700;color:#4f46e5;text-decoration:none;">
                                    <?= e($b['build_uid']) ?>
                                </a>
                            </td>
                            <td style="padding:12px;">
                                <div style="font-weight:700;color:#1e293b;font-size:12px;font-family:monospace;">
                                    <?= e($b['order_reference'] ?? (!empty($b['order_id']) ? ('ORD-#' . $b['order_id']) : substr($b['license_key'] ?? '', 0, 11) . '••••')) ?>
                                </div>
                                <?php if (!empty($b['license_id'])): ?>
                                    <div style="font-size:11px;color:#94a3b8;">Lic #<?= (int)$b['license_id'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px;">
                                <div style="font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
                                    <?= e($b['app_name']) ?>
                                    <span style="font-size:10.5px;background:#f1f5f9;color:#475569;padding:1px 5px;border-radius:4px;font-family:monospace;font-weight:600;">v<?= e($b['build_version'] ?? '1.0.0') ?></span>
                                </div>
                                <div style="font-size:11px;color:#64748b;font-family:monospace;"><?= e($b['package_id'] ?? '') ?></div>
                            </td>
                            <td style="padding:12px;">
                                <?php
                                $platformIcons = ['android' => '🤖 Android', 'web' => '🌐 Web', 'windows' => '💻 Windows', 'ios' => '🍏 iOS'];
                                ?>
                                <span class="tag blue" style="font-size:11px;font-weight:700;"><?= $platformIcons[$b['platform']] ?? ucfirst(e($b['platform'])) ?></span>
                            </td>
                            <td style="padding:12px;">
                                <span class="status-pill <?= e($b['status']) ?>">● <?= ucfirst(e($b['status'])) ?></span>
                            </td>
                            <td style="padding:12px;color:#64748b;font-size:12px;">
                                <div><?= !empty($b['created_at']) ? date('M d, Y H:i', strtotime($b['created_at'])) : '—' ?></div>
                                <div style="font-size:11px;color:#94a3b8;"><?= time_ago($b['created_at']) ?></div>
                            </td>
                            <td style="padding:12px;font-size:12px;">
                                <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                                    <span style="color:#059669;font-weight:600;">📦 <?= e($b['artifact_filename'] ?? 'Binary Package') ?></span>
                                    <?php if (!empty($b['artifact_size_bytes'])): ?>
                                        <span style="font-size:11px;color:#64748b;">(<?= round($b['artifact_size_bytes'] / (1024 * 1024), 1) ?> MB)</span>
                                    <?php endif; ?>
                                <?php elseif ($b['status'] === 'failed'): ?>
                                    <span style="color:#b91c1c;font-size:11.5px;display:block;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($b['error_message'] ?? 'Build failed during compilation.') ?>">
                                        ⚠️ <?= e($b['error_message'] ?: 'Compilation error') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#2563eb;font-size:11.5px;">⏳ In cloud pipeline...</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px;text-align:right;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                    <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                                        <a href="download.php?id=<?= urlencode($b['build_uid']) ?>" class="btn" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;padding:4px 10px;font-size:12px;border-radius:8px;text-decoration:none;font-weight:700;">
                                            ⬇️ Download
                                        </a>
                                    <?php endif; ?>
                                    <a href="view-build.php?id=<?= urlencode($b['build_uid']) ?>" class="btn" style="background:#f3f4f6;color:#1f2937;border:1px solid #e5e7eb;padding:4px 9px;font-size:12px;border-radius:8px;text-decoration:none;">
                                        Inspect
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($hasActiveBuild): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        initBuildStatusPoller('<?= e($activeBuildUid) ?>', true);
    });
</script>
<?php endif; ?>

<?php
builder_footer();
