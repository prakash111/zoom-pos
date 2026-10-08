<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/GitHubActions.php';
require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/BuildManager.php';
require_once __DIR__ . '/lib/layout.php';

ensure_app_builder_schema();

Auth::requireAuth();

$buildUid = trim((string)($_GET['id'] ?? ''));
if (empty($buildUid)) {
    header('Location: builds.php');
    exit;
}

$licenseKey = Auth::key();
$clientEmail = Auth::email();

$build = null;
try {
    $pdo = db();

    // 1. Try to sync build status if live
    try {
        BuildManager::syncSingleBuild($pdo, $buildUid, $licenseKey);
    } catch (Throwable $e) {}

    // 2. Query with comprehensive multi-tenant & email ownership fallback
    $stmt = $pdo->prepare('
        SELECT * FROM app_builds 
        WHERE build_uid = ? 
          AND (
            UPPER(TRIM(license_key)) = UPPER(TRIM(?)) 
            OR (client_email = ? AND client_email != "")
            OR (license_id IN (SELECT id FROM licenses WHERE client_email = ?))
          )
        LIMIT 1
    ');
    $stmt->execute([$buildUid, $licenseKey, $clientEmail, $clientEmail]);
    $build = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fallback matching
    if (!$build) {
        $stmtAll = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
        $stmtAll->execute([$buildUid]);
        $cand = $stmtAll->fetch(PDO::FETCH_ASSOC);
        if ($cand) {
            // Admin override
            if (!empty($_SESSION['lm_admin'])) {
                $build = $cand;
            } elseif (!empty($cand['client_email']) && strtolower(trim((string)$cand['client_email'])) === strtolower(trim($clientEmail))) {
                $build = $cand;
            } elseif (!empty($cand['license_key'])) {
                // Check if candidate build license belongs to the same client email
                $stLic = $pdo->prepare('SELECT client_email FROM licenses WHERE UPPER(TRIM(license_key)) = UPPER(TRIM(?)) LIMIT 1');
                $stLic->execute([$cand['license_key']]);
                $candEmail = $stLic->fetchColumn();
                if ($candEmail && strtolower(trim((string)$candEmail)) === strtolower(trim($clientEmail))) {
                    $build = $cand;
                }
            }
        }
    }
} catch (Throwable $e) {
    header('Location: builds.php?error=db_error');
    exit;
}

if (!$build) {
    header('Location: builds.php?error=not_found');
    exit;
}

$isLive = in_array($build['status'] ?? '', ['queued', 'preparing', 'building'], true);
$docInfo = builder_documentation_info();
$planName = ucfirst(Auth::plan() ?: 'Regular');
$maskedLicense = (strlen($licenseKey) > 10)
    ? substr($licenseKey, 0, 5) . '-•••••-' . substr($licenseKey, -4)
    : ($licenseKey ?: 'DEMO-LICENSE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Build <?= e($build['build_uid']) ?> · App Builder</title>
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
.wrap{padding:28px;max-width:1100px}
h1{font-size:26px;line-height:1.2;margin:0;letter-spacing:-.02em}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:24px;margin-bottom:20px}
.stepper{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px}
.step{display:flex;flex-direction:column;align-items:center;text-align:center;gap:6px;font-size:12px;font-weight:600;color:var(--muted)}
.step .num{width:30px;height:30px;border-radius:50%;border:1.5px solid var(--line);background:var(--bg);display:grid;place-items:center;font-size:12px;color:var(--muted)}
.step.done .num{background:var(--ok-soft);border-color:var(--ok);color:var(--ok)}
.step.active .num{background:var(--brand-soft);border-color:var(--brand);color:var(--brand);animation:pulse 1.8s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(79,70,229,0.3)}50%{box-shadow:0 0 0 8px rgba(79,70,229,0)}}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.spec-item small{display:block;font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
.spec-item strong{display:block;font-size:14.5px;color:var(--ink);margin-top:2px}
@media (max-width:860px){.app{grid-template-columns:1fr}.app>aside{display:none}.wrap{padding:16px}.top{padding:10px 16px}.stepper{grid-template-columns:1fr 1fr}.grid2{grid-template-columns:1fr}}
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
  <a href="builds.php" class="btn">&larr; Back to builds</a>
  <a href="new-build.php" class="btn pri">+ New build</a>
</div>

<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
      <div style="display:flex;align-items:center;gap:10px;">
        <h1 style="font-family:var(--mono);"><?= e($build['build_uid']) ?></h1>
        <?php
        $st = strtolower($build['status'] ?? 'unknown');
        $tagCls = match ($st) {
            'completed' => 'tag',
            'building', 'preparing' => 'tag brand',
            'failed' => 'tag danger',
            default => 'tag warn',
        };
        ?>
        <span class="<?= $tagCls ?>">● <?= ucfirst(e($st)) ?></span>
      </div>
      <p style="margin:4px 0 0;font-size:13.5px;color:var(--muted);">
        <?= ucfirst(e($build['platform'])) ?> compile for <strong><?= e($build['app_name']) ?></strong> &middot; Triggered <?= time_ago($build['created_at']) ?>
      </p>
    </div>

    <div>
      <?php if ($st === 'completed' && !empty($build['artifact_path'])): ?>
        <a href="download.php?id=<?= urlencode($build['build_uid']) ?>" class="btn pri" style="padding:10px 20px;font-size:14px;">
          ⬇️ Download Artifact (<?= !empty($build['artifact_size_bytes']) ? round($build['artifact_size_bytes'] / (1024 * 1024), 1) . ' MB' : 'Package' ?>)
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pipeline Stepper Card -->
  <div class="card">
    <h2 style="font-size:16px;margin:0 0 18px;">Build Pipeline Status</h2>

    <div class="stepper">
      <div class="step done"><span class="num">✓</span><span>License check</span></div>
      <div class="step done"><span class="num">✓</span><span>Configuration</span></div>
      <div class="step <?= in_array($st, ['preparing', 'building', 'completed'], true) ? ($st === 'completed' ? 'done' : 'active') : '' ?>">
        <span class="num"><?= $st === 'completed' ? '✓' : '3' ?></span><span>Cloud runner</span>
      </div>
      <div class="step <?= ($st === 'completed') ? 'done' : ($st === 'building' ? 'active' : '') ?>">
        <span class="num"><?= $st === 'completed' ? '✓' : '4' ?></span><span>Compilation</span>
      </div>
      <div class="step <?= ($st === 'completed') ? 'done' : '' ?>">
        <span class="num"><?= $st === 'completed' ? '✓' : '5' ?></span><span>Artifact ready</span>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--bg);border-radius:12px;border:1px solid var(--line);">
      <div style="font-size:24px;"><?= $isLive ? '⚙️' : ($st === 'completed' ? '✅' : '❌') ?></div>
      <div style="flex:1;">
        <strong style="display:block;font-size:14px;color:var(--ink);">
          <?php if ($st === 'completed'): ?>
            Compilation succeeded. Build artifact verified and ready for download.
          <?php elseif ($st === 'failed'): ?>
            Build pipeline encountered an error during compilation.
          <?php elseif ($st === 'building'): ?>
            Compiling Flutter <?= ucfirst(e($build['platform'])) ?> package on Cloud Runner cluster...
          <?php else: ?>
            Preparing compilation workspace and dispatching cloud runner...
          <?php endif; ?>
        </strong>
        <span style="font-size:12px;color:var(--muted);">Notification sent to <strong><?= e($build['client_email']) ?></strong> upon status change.</span>
      </div>
    </div>

    <?php if ($st === 'failed'): ?>
      <div style="margin-top:14px;padding:14px 18px;background:var(--danger-soft);border:1px solid var(--danger);color:var(--danger);border-radius:12px;font-size:13.5px;">
        ⚠️ <strong>Compilation Error:</strong> <?= e($build['error_message'] ?? 'Compilation terminated with non-zero exit code.') ?>
      </div>
    <?php endif; ?>

    <?php if ($st === 'completed' && !empty($build['artifact_path'])): ?>
      <div style="margin-top:18px;text-align:center;padding:24px;background:var(--ok-soft);border:1px solid var(--ok);border-radius:14px;">
        <div style="font-size:36px;margin-bottom:6px;">📦</div>
        <h3 style="margin:0 0 4px;font-size:18px;color:var(--ok);">Distribution Package Ready</h3>
        <p style="margin:0 0 16px;font-size:13px;color:var(--ink);">Your white-labeled <?= ucfirst(e($build['platform'])) ?> package is compiled, signed, and ready to install.</p>
        <a href="download.php?id=<?= urlencode($build['build_uid']) ?>" class="btn pri" style="padding:10px 24px;font-size:14px;">
          ⬇️ Download <?= e($build['artifact_filename'] ?? 'Binary') ?>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Specifications Card -->
  <div class="card">
    <h2 style="font-size:16px;margin:0 0 18px;">Build Specifications</h2>

    <div class="grid2">
      <div class="spec-item"><small>Application name</small><strong><?= e($build['app_name']) ?></strong></div>
      <div class="spec-item"><small>Build version</small><strong style="font-family:var(--mono);">v<?= e($build['build_version'] ?? '1.0.0') ?></strong></div>
      <div class="spec-item"><small>Target platform</small><strong style="text-transform:capitalize;"><?= e($build['platform']) ?></strong></div>
      <div class="spec-item"><small>Package / Bundle ID</small><strong style="font-family:var(--mono);"><?= e($build['package_id']) ?></strong></div>
      <div class="spec-item"><small>Backend API URL</small><strong style="font-family:var(--mono);"><?= e($build['server_url']) ?></strong></div>
      <div class="spec-item"><small>Primary color</small>
        <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
          <span style="width:16px;height:16px;border-radius:4px;background:<?= e($build['primary_color']) ?>;display:inline-block;border:1px solid var(--line);"></span>
          <span style="font-family:var(--mono);font-weight:700;"><?= e($build['primary_color']) ?></span>
        </div>
      </div>
      <div class="spec-item"><small>Source code</small><strong><?= ($build['source_type'] === 'latest_github') ? 'Official Cloud Release' : 'Custom Uploaded Source' ?></strong></div>
      <div class="spec-item"><small>Build duration</small><strong><?= !empty($build['build_duration_seconds']) ? gmdate('i\m s\s', (int)$build['build_duration_seconds']) : ($isLive ? 'In progress...' : '—') ?></strong></div>
      <div class="spec-item"><small>Date created</small><strong><?= !empty($build['created_at']) ? date('M d, Y H:i T', strtotime($build['created_at'])) : '—' ?></strong></div>
      <div class="spec-item"><small>Date completed</small><strong><?= !empty($build['completed_at']) ? date('M d, Y H:i T', strtotime($build['completed_at'])) : ($isLive ? 'In progress...' : '—') ?></strong></div>
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

<?php if ($isLive): ?>
setTimeout(() => {
  window.location.reload();
}, 5000);
<?php endif; ?>
</script>
</body>
</html>
