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

// 1. IF NOT LOGGED IN, RENDER MODERN LICENSE AUTHENTICATION SCREEN
if (!Auth::check()) {
    $token = csrf_token();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>License Authentication · App Builder</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f7fb;--surface:#fff;--ink:#12142a;--muted:#5d6280;--line:#e4e6f0;--brand:#4f46e5;--brand-ink:#3730a3;--brand-soft:#eef0ff;--ok:#0f9d6a;--ok-soft:#e6f6ef;--warn:#b4540a;--warn-soft:#fff3e4;--r:12px;--font:'Onest',system-ui,-apple-system,'Segoe UI',sans-serif;--mono:ui-monospace,'SF Mono',Menlo,monospace}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12}}
:root[data-theme=dark]{--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 var(--font);-webkit-font-smoothing:antialiased;display:grid;place-items:center;min-height:100vh;padding:24px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:36px 32px;max-width:440px;width:100%;box-shadow:0 12px 35px rgba(0,0,0,0.06)}
.logo-i{width:46px;height:46px;border-radius:12px;background:var(--brand);display:grid;place-items:center;margin:0 auto 16px}
.logo-i svg{width:22px;height:22px;stroke:#fff}
h1{font-size:22px;margin:0 0 6px;text-align:center;letter-spacing:-.01em}
p{color:var(--muted);font-size:13.5px;text-align:center;margin:0 0 24px}
.alert{background:var(--warn-soft);color:var(--warn);border:1px solid var(--warn);border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:18px}
.fld{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}
label{font-weight:600;font-size:13.5px}
.in{width:100%;height:44px;border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:10px;padding:0 14px;font-family:var(--mono);font-size:14px;letter-spacing:1px;text-transform:uppercase}
.in:focus{outline:0;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 22%,transparent)}
.btn{width:100%;height:44px;border:none;background:var(--brand);color:#fff;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{filter:brightness(1.08)}
.foot{margin-top:24px;padding-top:16px;border-top:1px solid var(--line);text-align:center;font-size:12px;color:var(--muted)}
.foot a{color:var(--brand);text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div class="logo-i"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3 3M4 20l1-4L16 5a2.1 2.1 0 0 1 3 3L8 19z"/></svg></div>
  <h1>App Builder Access</h1>
  <p>Enter your purchase license key to customize and compile your Flutter applications.</p>

  <?php if (!empty($authError)): ?>
    <div class="alert">⚠️ <?= e($authError) ?></div>
  <?php endif; ?>

  <form method="POST" action="index.php">
    <input type="hidden" name="action" value="login">
    <?= csrf_field() ?>

    <div class="fld">
      <label for="lic">Purchase License Key</label>
      <input type="text" id="lic" name="license_key" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required autofocus
             value="<?= e($queryKey ?: ($_POST['license_key'] ?? '')) ?>" class="in">
      <small style="color:var(--muted);font-size:12px;">License key received upon purchase or via receipt.</small>
    </div>

    <button type="submit" class="btn">Verify License &amp; Open Dashboard &rarr;</button>
  </form>

  <div class="foot">
    Central License Management Engine &middot; <a href="https://license.zoomnearby.com" target="_blank">License Server</a>
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
$clientEmail = Auth::email();

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
        'total_builds' => 0,
        'platforms' => ['android' => 0, 'web' => 0, 'windows' => 0, 'ios' => 0],
        'reset_date' => date('Y-m-01 00:00:00', strtotime('+1 month')),
    ];
}

// Fetch recent builds for this license via BuildManager
require_once __DIR__ . '/lib/BuildManager.php';
$recentBuilds = [];
try {
    $pdo = db();
    $recentBuilds = BuildManager::getBuildsForUser($pdo, $licenseKey, $clientEmail, [], 10);
} catch (Throwable $e) {
    $recentBuilds = [];
}

// Check if any recent build is in-progress
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
        $account = AccountManager::getLinkedDetails($licenseKey, $clientEmail);
    } catch (Throwable $e) {
        $account = AccountManager::buildFinalDefaults($licenseKey, [], $clientEmail);
    }
}

$docInfo = builder_documentation_info();
$planName = ucfirst($plan ?: 'Regular');
$maskedLicense = (strlen($licenseKey) > 10)
    ? substr($licenseKey, 0, 5) . '-•••••-' . substr($licenseKey, -4)
    : ($licenseKey ?: 'DEMO-LICENSE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · App Builder</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f7fb;--surface:#fff;--ink:#12142a;--muted:#5d6280;--line:#e4e6f0;--brand:#4f46e5;--brand-ink:#3730a3;--brand-soft:#eef0ff;--ok:#0f9d6a;--ok-soft:#e6f6ef;--warn:#b4540a;--warn-soft:#fff3e4;--danger:#dc2626;--danger-soft:#fee2e2;--r:12px;--font:'Onest',system-ui,-apple-system,'Segoe UI',sans-serif;--mono:ui-monospace,'SF Mono',Menlo,monospace}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12;--danger:#f87171;--danger-soft:#3f1418}}
:root[data-theme=dark]{--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12;--danger:#f87171;--danger-soft:#3f1418}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 var(--font);-webkit-font-smoothing:antialiased}
button,input,select{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--brand);outline-offset:2px}
.app{display:grid;grid-template-columns:232px 1fr;min-height:100vh}
aside{position:sticky;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--line);padding:20px 14px;display:flex;flex-direction:column;gap:20px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:17px;padding:0 8px;color:var(--ink);text-decoration:none}
.logo i{width:32px;height:32px;border-radius:9px;background:var(--brand);display:grid;place-items:center}
.logo svg{width:18px;height:18px;stroke:#fff}
nav a{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;color:var(--muted);text-decoration:none;font-weight:500}
nav a svg{width:18px;height:18px;flex:none}
nav a:hover{background:var(--bg)}
nav a[aria-current]{background:var(--brand-soft);color:var(--brand-ink);font-weight:600}
.lic{margin-top:auto;border:1px solid var(--line);border-radius:var(--r);padding:12px}
.lic small{color:var(--muted);display:block}
.lic b{display:block;font-family:var(--mono);font-size:13px;margin:2px 0 8px}
.tag{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px;background:var(--ok-soft);color:var(--ok)}
.tag.warn{background:var(--warn-soft);color:var(--warn)}
.tag.danger{background:var(--danger-soft);color:var(--danger)}
.tag.brand{background:var(--brand-soft);color:var(--brand)}
main{min-width:0}
.top{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:12px;padding:12px 28px;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--ok)}
.top .sp{flex:1}
.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:var(--surface);padding:8px 14px;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;color:inherit;text-decoration:none}
.btn:hover{border-color:var(--brand)}
.btn.pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn.pri:hover{filter:brightness(1.08)}
.btn svg{width:16px;height:16px}
.wrap{padding:28px;max-width:1320px}
h1{font-size:28px;line-height:1.2;margin:0 0 6px;letter-spacing:-.02em}
.lead{color:var(--muted);margin:0 0 24px;max-width:65ch}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:20px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px}
.stat{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:18px 20px}
.stat small{display:block;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
.stat b{display:block;font-size:28px;font-weight:800;color:var(--ink);margin:4px 0 2px}
.stat span{font-size:12px;color:var(--muted)}
.quota-box{background:linear-gradient(135deg,var(--brand),#3730a3);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:24px}
.quota-box h2{font-size:20px;margin:8px 0 4px}
.quota-bar{height:7px;border-radius:99px;background:rgba(255,255,255,0.25);overflow:hidden;margin-top:14px}
.quota-fill{height:100%;background:#34d399;border-radius:99px}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th{text-align:left;padding:12px;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1.5px solid var(--line)}
td{padding:14px 12px;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:none}
@media (max-width:860px){.app{grid-template-columns:1fr}.app>aside{display:none}.wrap{padding:16px}.top{padding:10px 16px}.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app">
<aside>
  <a href="index.php" class="logo"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3 3M4 20l1-4L16 5a2.1 2.1 0 0 1 3 3L8 19z"/></svg></i>App Builder</a>
  <nav aria-label="Main">
    <a href="index.php" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
    <a href="new-build.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>New build</a>
    <a href="builds.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Build history</a>
    <a href="<?= e($docInfo['url'] ?? 'https://saas.zoomnearby.com') ?>" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h6"/></svg>Documentation</a>
  </nav>
  <div class="lic"><small>Active license</small><b><?= e($maskedLicense) ?></b><span class="tag"><?= e($planName) ?> plan</span></div>
</aside>

<main>
<div class="top">
  <span class="dot"></span><span style="font-weight:500;font-size:14px">Build engine online</span>
  <span class="sp"></span>
  <button class="btn" id="theme" type="button">Theme</button>
  <a href="new-build.php" class="btn pri"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>New build</a>
</div>

<div class="wrap">
  <h1>App Builder Dashboard</h1>
  <p class="lead">Manage your white-label builds, monitor compile quotas, and dispatch multi-platform apps.</p>

  <?php if (!empty($account['company_name'])): ?>
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:16px 20px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:10px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;font-size:18px;">🏢</div>
        <div>
          <small style="color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:block;">Linked Business Account</small>
          <strong style="font-size:15px;color:var(--ink);"><?= e($account['company_name']) ?> <span style="font-weight:500;color:var(--muted);font-size:13px;">(<?= e($account['product_name'] ?? 'Zoom Sales CRM') ?>)</span></strong>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <?php if (!empty($account['server_url'])): ?>
          <span style="color:var(--muted);font-size:12.5px;">API: <code style="font-family:var(--mono);background:var(--bg);padding:3px 7px;border-radius:6px;"><?= e(parse_url($account['server_url'], PHP_URL_HOST) ?: $account['server_url']) ?></code></span>
        <?php endif; ?>
        <a href="new-build.php" class="btn" style="padding:6px 14px;font-size:13px;">Configure Build &rarr;</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="quota-box">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
      <div>
        <span style="background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;"><?= e($planName) ?> Quota</span>
        <h2>Monthly Build Quota</h2>
        <p style="margin:0;font-size:13px;opacity:0.9;max-width:55ch;text-align:left;">1 Build pack compiles all 4 platforms (Android, Web, Windows &amp; iOS) in parallel. Failed builds never consume quota. Renews on <?= date('M 1, Y', strtotime($stats['reset_date'])) ?>.</p>
      </div>
      <div style="text-align:right;">
        <div style="font-size:28px;font-weight:800;"><?= $stats['is_unlimited'] ? 'Unlimited' : ($stats['used_this_month'] . ' / ' . $stats['limit']) ?></div>
        <div style="font-size:12px;opacity:0.85;"><?= $stats['is_unlimited'] ? 'Unlimited Monthly Builds' : ($stats['remaining_this_month'] . ' builds remaining') ?></div>
      </div>
    </div>
    <?php if (!$stats['is_unlimited']): ?>
      <?php $pct = min(100, round(($stats['used_this_month'] / max(1, $stats['limit'])) * 100)); ?>
      <div class="quota-bar"><div class="quota-fill" style="width:<?= $pct ?>%;"></div></div>
    <?php endif; ?>
  </div>

  <div class="stats">
    <div class="stat"><small>Total Builds</small><b><?= (int)($stats['total_builds'] ?? 0) ?></b><span>All-time triggered</span></div>
    <div class="stat"><small>Android</small><b><?= (int)($stats['platforms']['android'] ?? 0) ?></b><span>APK &amp; Play Store</span></div>
    <div class="stat"><small>Web</small><b><?= (int)($stats['platforms']['web'] ?? 0) ?></b><span>PWA hosting package</span></div>
    <div class="stat"><small>Windows</small><b><?= (int)($stats['platforms']['windows'] ?? 0) ?></b><span>Desktop binary</span></div>
    <div class="stat"><small>iOS</small><b><?= (int)($stats['platforms']['ios'] ?? 0) ?></b><span>Xcode IPA bundle</span></div>
  </div>

  <div class="card" style="padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="font-size:17px;margin:0 0 2px;">Recent Build Activity</h2>
        <p style="margin:0;font-size:13px;color:var(--muted);text-align:left;">Monitor active builds or download finished application packages.</p>
      </div>
      <a href="builds.php" class="btn" style="font-size:13px;">View All &rarr;</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Build ID</th>
            <th>App &amp; Version</th>
            <th>Platform</th>
            <th>Status</th>
            <th>Date &amp; Time</th>
            <th>Artifact</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentBuilds)): ?>
            <tr>
              <td colspan="7" style="text-align:center;padding:36px;color:var(--muted);">
                No builds created yet. Click <a href="new-build.php" style="color:var(--brand);font-weight:600;">+ New build</a> to compile your first Flutter app!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentBuilds as $b): ?>
              <tr>
                <td>
                  <a href="view-build.php?id=<?= urlencode($b['build_uid']) ?>" style="font-family:var(--mono);font-weight:700;color:var(--brand);text-decoration:none;">
                    <?= e($b['build_uid']) ?>
                  </a>
                </td>
                <td>
                  <strong style="display:block;color:var(--ink);"><?= e($b['app_name']) ?> <span style="font-size:11px;font-family:var(--mono);font-weight:600;color:var(--muted);">v<?= e($b['build_version'] ?? '1.0.0') ?></span></strong>
                  <small style="color:var(--muted);font-family:var(--mono);font-size:11.5px;"><?= e($b['package_id'] ?? '') ?></small>
                </td>
                <td>
                  <span class="tag brand" style="text-transform:capitalize;"><?= e($b['platform']) ?></span>
                </td>
                <td>
                  <?php
                  $st = strtolower($b['status'] ?? 'unknown');
                  $tagCls = match ($st) {
                      'completed' => 'tag',
                      'building', 'preparing' => 'tag brand',
                      'failed' => 'tag danger',
                      default => 'tag warn',
                  };
                  ?>
                  <span class="<?= $tagCls ?>">● <?= ucfirst(e($st)) ?></span>
                </td>
                <td style="color:var(--muted);font-size:12.5px;">
                  <div><?= !empty($b['created_at']) ? date('M d, Y H:i', strtotime($b['created_at'])) : '—' ?></div>
                  <small style="opacity:0.8;"><?= time_ago($b['created_at']) ?></small>
                </td>
                <td style="font-size:12.5px;">
                  <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                    <span style="color:var(--ok);font-weight:600;">📦 <?= e($b['artifact_filename'] ?? 'Binary Package') ?></span>
                    <?php if (!empty($b['artifact_size_bytes'])): ?>
                      <small style="color:var(--muted);">(<?= round($b['artifact_size_bytes'] / (1024 * 1024), 1) ?> MB)</small>
                    <?php endif; ?>
                  <?php elseif ($b['status'] === 'failed'): ?>
                    <span style="color:var(--danger);max-width:180px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($b['error_message'] ?? 'Compilation failed') ?>">⚠️ <?= e($b['error_message'] ?: 'Error') ?></span>
                  <?php else: ?>
                    <span style="color:var(--brand);">⏳ In cloud pipeline...</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">
                  <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                      <a href="download.php?id=<?= urlencode($b['build_uid']) ?>" class="btn pri" style="padding:4px 10px;font-size:12px;">⬇️ Download</a>
                    <?php endif; ?>
                    <a href="view-build.php?id=<?= urlencode($b['build_uid']) ?>" class="btn" style="padding:4px 10px;font-size:12px;">Inspect</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</main>
</div>

<script>
const themeBtn = document.getElementById("theme");
if (themeBtn) {
  themeBtn.onclick = () => {
    const r = document.documentElement;
    const isDark = getComputedStyle(r).getPropertyValue("--bg").trim() == "#0e1020";
    const next = isDark ? "light" : "dark";
    r.dataset.theme = next;
    localStorage.setItem("zn_theme", next);
  };
}
const savedTheme = localStorage.getItem("zn_theme");
if (savedTheme) {
  document.documentElement.dataset.theme = savedTheme;
}

<?php if ($hasActiveBuild): ?>
setTimeout(() => {
  window.location.reload();
}, 6000);
<?php endif; ?>
</script>
</body>
</html>
