<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/GitHubActions.php';
require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/layout.php';

ensure_app_builder_schema();

Auth::requireAuth();

$buildUid = trim($_GET['id'] ?? '');
if (empty($buildUid)) {
    header('Location: builds.php');
    exit;
}

$licenseKey = Auth::key();
$build = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? AND license_key = ? LIMIT 1');
    $stmt->execute([$buildUid, $licenseKey]);
    $build = $stmt->fetch();
} catch (Throwable $e) {
    header('Location: builds.php?error=db_error');
    exit;
}

if (!$build) {
    header('Location: builds.php?error=not_found');
    exit;
}

$isLive = in_array($build['status'], ['queued', 'preparing', 'building'], true);

builder_header('builds', 'Build ' . $build['build_uid']);
?>

<div style="max-width:880px;margin:0 auto;">
    
    <!-- Top Action Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="display:flex;align-items:center;gap:10px;">
                <h2 style="margin:0;font-size:22px;font-weight:800;color:#0f172a;font-family:monospace;"><?= e($build['build_uid']) ?></h2>
                <span id="build-status-badge" class="status-pill <?= e($build['status']) ?>">● <?= ucfirst(e($build['status'])) ?></span>
            </div>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                <?= ucfirst(e($build['platform'])) ?> build for <strong><?= e($build['app_name']) ?></strong> &middot; Triggered <?= time_ago($build['created_at']) ?>
            </p>
        </div>
        <div>
            <a href="builds.php" class="btn" style="background:#f3f4f6;color:#1e293b;border:1px solid #e5e7eb;text-decoration:none;padding:8px 14px;border-radius:8px;font-size:12.5px;font-weight:600;">
                &larr; Back to Build History
            </a>
        </div>
    </div>

    <!-- Stepper Progress Tracker -->
    <div class="card" style="padding:24px;margin-bottom:24px;">
        <h4 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#0f172a;">Build Pipeline Status</h4>

        <div class="builder-stepper">
            <div class="step-item done">
                <span class="step-number">✓</span>
                <span>License & Quota</span>
            </div>
            <div class="step-item done">
                <span class="step-number">✓</span>
                <span>Source Config</span>
            </div>
            <div class="step-item <?= in_array($build['status'], ['preparing', 'building', 'completed'], true) ? ($build['status'] === 'completed' ? 'done' : 'active') : '' ?>">
                <span class="step-number"><?= $build['status'] === 'completed' ? '✓' : '3' ?></span>
                <span>Cloud Runner</span>
            </div>
            <div class="step-item <?= ($build['status'] === 'completed') ? 'done' : ($build['status'] === 'building' ? 'active' : '') ?>">
                <span class="step-number"><?= $build['status'] === 'completed' ? '✓' : '4' ?></span>
                <span>Compilation</span>
            </div>
            <div class="step-item <?= ($build['status'] === 'completed') ? 'done' : '' ?>">
                <span class="step-number"><?= $build['status'] === 'completed' ? '✓' : '5' ?></span>
                <span>Artifact Ready</span>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:12px;padding:14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
            <div style="font-size:22px;"><?= $isLive ? '⚙️' : ($build['status'] === 'completed' ? '✅' : '❌') ?></div>
            <div>
                <div id="build-step-text" style="font-size:13.5px;font-weight:700;color:#1e293b;">
                    <?php if ($build['status'] === 'completed'): ?>
                        Compilation succeeded! Build artifact stored and verified.
                    <?php elseif ($build['status'] === 'failed'): ?>
                        Build process encountered an error during compilation.
                    <?php elseif ($build['status'] === 'building'): ?>
                        Compiling Flutter <?= ucfirst(e($build['platform'])) ?> binary on Cloud Compilation Cluster...
                    <?php else: ?>
                        Preparing workspace and dispatching cloud runner...
                    <?php endif; ?>
                </div>
                <div style="font-size:11.5px;color:#64748b;margin-top:2px;">
                    Notification email automatically sent to <strong><?= e($build['client_email']) ?></strong>.
                </div>
            </div>
        </div>

        <!-- Error Box (if failed) -->
        <div id="build-error-box" style="display:<?= ($build['status'] === 'failed') ? 'block' : 'none' ?>;margin-top:14px;padding:14px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;font-size:13px;">
            ⚠️ <?= e($build['error_message'] ?? 'Compilation failed. Please inspect build configuration or project assets.') ?>
        </div>

        <!-- Download Action Container (if completed) -->
        <div id="download-action-container" style="display:<?= ($build['status'] === 'completed') ? 'block' : 'none' ?>;margin-top:20px;text-align:center;padding:24px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;">
            <div style="font-size:36px;margin-bottom:8px;">🎁</div>
            <h3 style="margin:0 0 6px;font-size:18px;font-weight:800;color:#065f46;">Distribution Package Ready</h3>
            <p style="margin:0 0 16px;font-size:13px;color:#047857;">
                Your customized <?= ucfirst(e($build['platform'])) ?> package is compiled, signed, and ready for deployment.
            </p>
            <a href="download.php?id=<?= urlencode($build['build_uid']) ?>" class="btn" style="background:#059669;color:#fff;text-decoration:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700;display:inline-block;box-shadow:0 4px 12px rgba(5, 150, 105, 0.3);">
                ⬇️ Download Artifact (<?= !empty($build['artifact_size_bytes']) ? format_bytes($build['artifact_size_bytes']) : 'Package' ?>)
            </a>
        </div>
    </div>

    <!-- Build Details & Configuration Card -->
    <div class="card" style="padding:24px;">
        <h4 style="margin:0 0 16px;font-size:14px;font-weight:800;color:#0f172a;">Build Specifications</h4>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:13px;">
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Application Name</span>
                <span style="font-weight:700;color:#0f172a;font-size:14px;"><?= e($build['app_name']) ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Build Version</span>
                <span style="font-family:monospace;font-weight:700;color:#4f46e5;font-size:14px;">v<?= e($build['build_version'] ?? '1.0.0') ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">License / Order Info</span>
                <span style="font-family:monospace;font-weight:700;color:#0f172a;">
                    <?= e($build['order_reference'] ?? (!empty($build['order_id']) ? ('ORD-#' . $build['order_id']) : substr($build['license_key'] ?? '', 0, 11) . '••••')) ?>
                </span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Package / Bundle ID</span>
                <span style="font-family:monospace;font-weight:700;color:#0f172a;"><?= e($build['package_id']) ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Backend Server URL</span>
                <span style="font-weight:600;color:#0f172a;"><?= e($build['server_url']) ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Primary Theme Color</span>
                <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
                    <span style="width:16px;height:16px;border-radius:4px;background:<?= e($build['primary_color']) ?>;display:inline-block;border:1px solid #cbd5e1;"></span>
                    <span style="font-family:monospace;font-weight:700;"><?= e($build['primary_color']) ?></span>
                </div>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Source Type</span>
                <span style="font-weight:600;color:#0f172a;"><?= ($build['source_type'] === 'latest_github') ? 'Official Cloud Release' : 'Custom Uploaded ZIP' ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Build Duration</span>
                <span style="font-weight:600;color:#0f172a;">
                    <?= !empty($build['build_duration_seconds']) ? gmdate('i\m s\s', (int)$build['build_duration_seconds']) : ($isLive ? 'In progress...' : '—') ?>
                </span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Created Date &amp; Time</span>
                <span style="font-weight:600;color:#0f172a;"><?= !empty($build['created_at']) ? date('Y-m-d H:i:s T', strtotime($build['created_at'])) : '—' ?></span>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:11px;font-weight:700;text-transform:uppercase;">Completed Date &amp; Time</span>
                <span style="font-weight:600;color:#0f172a;"><?= !empty($build['completed_at']) ? date('Y-m-d H:i:s T', strtotime($build['completed_at'])) : ($isLive ? 'In progress...' : '—') ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($isLive): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        initBuildStatusPoller('<?= e($build['build_uid']) ?>', false);
    });
</script>
<?php endif; ?>

<?php
builder_footer();
