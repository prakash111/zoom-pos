<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/layout.php';

ensure_app_builder_schema();

Auth::requireAuth();

$licenseKey = Auth::key();
$plan = Auth::plan();
$clientEmail = Auth::email();

$fStatus = trim($_GET['status'] ?? '');
$fPlatform = trim($_GET['platform'] ?? '');
$q = trim($_GET['q'] ?? '');

require_once __DIR__ . '/lib/BuildManager.php';
$builds = [];
try {
    $pdo = db();
    $builds = BuildManager::getBuildsForUser($pdo, $licenseKey, $clientEmail, [
        'status'   => $fStatus,
        'platform' => $fPlatform,
        'q'        => $q,
    ], 200);
} catch (Throwable $e) {
    $builds = [];
}

$hasActiveBuild = false;
foreach ($builds as $b) {
    if (in_array($b['status'] ?? '', ['queued', 'preparing', 'building'], true)) {
        $hasActiveBuild = true;
        break;
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
<title>Build history · App Builder</title>
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
.alert{display:flex;gap:12px;align-items:flex-start;background:var(--ok-soft);color:var(--ok);border-radius:var(--r);padding:14px 18px;margin-bottom:20px}
.alert svg{width:20px;height:20px;flex:none;margin-top:2px}
.alert p{margin:0;color:var(--ink)}
.alert b{display:block;color:var(--ok)}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:20px}
.in{height:38px;border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:8px;padding:0 12px;font-size:13.5px}
.in:focus{outline:0;border-color:var(--brand)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th{text-align:left;padding:12px;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1.5px solid var(--line)}
td{padding:14px 12px;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:none}
@media (max-width:860px){.app{grid-template-columns:1fr}.app>aside{display:none}.wrap{padding:16px}.top{padding:10px 16px}}
</style>
</head>
<body>
<div class="app">
<aside>
  <a href="index.php" class="logo"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3 3M4 20l1-4L16 5a2.1 2.1 0 0 1 3 3L8 19z"/></svg></i>App Builder</a>
  <nav aria-label="Main">
    <a href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
    <a href="new-build.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>New build</a>
    <a href="builds.php" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Build history</a>
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
  <h1>Build history</h1>
  <p class="lead">Review previous compilation jobs, download signed binaries, and inspect build diagnostics.</p>

  <?php if (!empty($_GET['batch'])): ?>
    <div class="alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
      <p><b>Multi-platform build suite dispatched</b>Dispatched <?= (int)($_GET['count'] ?? 1) ?> platform builds in parallel to Cloud Build Engine. Progress is updating live below.</p>
    </div>
  <?php endif; ?>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert" role="alert" style="background:var(--warn-soft);color:var(--warn);border:1px solid var(--warn);">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p><b>Build notice:</b> <?= ($_GET['error'] === 'not_found') ? 'The requested build could not be located.' : 'Database error encountered while retrieving build.' ?></p>
    </div>
  <?php endif; ?>

  <!-- Filters Card -->
  <div class="card" style="padding:16px 20px;">
    <form method="GET" action="builds.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11.5px;font-weight:600;color:var(--muted);">Platform</label>
        <select name="platform" class="in">
          <option value="">All Platforms</option>
          <option value="android" <?= $fPlatform === 'android' ? 'selected' : '' ?>>Android</option>
          <option value="web" <?= $fPlatform === 'web' ? 'selected' : '' ?>>Web</option>
          <option value="windows" <?= $fPlatform === 'windows' ? 'selected' : '' ?>>Windows</option>
          <option value="ios" <?= $fPlatform === 'ios' ? 'selected' : '' ?>>iOS</option>
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11.5px;font-weight:600;color:var(--muted);">Status</label>
        <select name="status" class="in">
          <option value="">All Statuses</option>
          <option value="completed" <?= $fStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="building" <?= $fStatus === 'building' ? 'selected' : '' ?>>Building</option>
          <option value="failed" <?= $fStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
          <option value="queued" <?= $fStatus === 'queued' ? 'selected' : '' ?>>Queued</option>
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:200px;">
        <label style="font-size:11.5px;font-weight:600;color:var(--muted);">Search</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Build ID, app name, or package ID..." class="in">
      </div>

      <div style="margin-top:20px;display:flex;gap:8px;align-items:center;">
        <button type="submit" class="btn pri" style="padding:7px 16px;">Filter</button>
        <?php if (!empty($fPlatform) || !empty($fStatus) || !empty($q)): ?>
          <a href="builds.php" class="btn" style="padding:7px 12px;">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Builds Table Card -->
  <div class="card" style="padding:24px;">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Build UID</th>
            <th>License / Order</th>
            <th>App &amp; Version</th>
            <th>Platform</th>
            <th>Status</th>
            <th>Date &amp; Time</th>
            <th>Artifact &amp; Output</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($builds)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">
                No build records found matching your filters.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($builds as $b): ?>
              <tr>
                <td>
                  <a href="view-build.php?id=<?= urlencode($b['build_uid']) ?>" style="font-family:var(--mono);font-weight:700;color:var(--brand);text-decoration:none;">
                    <?= e($b['build_uid']) ?>
                  </a>
                </td>
                <td>
                  <div style="font-family:var(--mono);font-size:12px;font-weight:600;color:var(--ink);">
                    <?= e($b['order_reference'] ?? (!empty($b['order_id']) ? ('ORD-#' . $b['order_id']) : substr($b['license_key'] ?? '', 0, 11) . '••••')) ?>
                  </div>
                  <?php if (!empty($b['license_id'])): ?>
                    <small style="color:var(--muted);font-size:11px;">Lic #<?= (int)$b['license_id'] ?></small>
                  <?php endif; ?>
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
                    <span style="color:var(--danger);max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($b['error_message'] ?? 'Build failed.') ?>">⚠️ <?= e($b['error_message'] ?: 'Failed') ?></span>
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

<?php if ($hasActiveBuild || !empty($_GET['batch'])): ?>
setTimeout(() => {
  window.location.reload();
}, 7000);
<?php endif; ?>
</script>
</body>
</html>
