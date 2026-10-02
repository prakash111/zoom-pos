<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/layout.php';

ensure_app_builder_schema();

Auth::requireAuth();

$licenseKey = Auth::key();

$fStatus = trim($_GET['status'] ?? '');
$fPlatform = trim($_GET['platform'] ?? '');
$q = trim($_GET['q'] ?? '');

require_once __DIR__ . '/lib/BuildManager.php';
$builds = [];
try {
    $pdo = db();
    $builds = BuildManager::getBuildsForUser($pdo, $licenseKey, Auth::email(), [
        'status'   => $fStatus,
        'platform' => $fPlatform,
        'q'        => $q,
    ], 200);
} catch (Throwable $e) {
    $builds = [];
}

builder_header('builds', 'Build History');
?>

<div style="max-width:1100px;margin:0 auto;">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:22px;font-weight:800;color:#0f172a;">Build History & Downloads</h2>
            <p style="margin:0;font-size:13px;color:#64748b;">Review previous compilation jobs and download your generated application packages.</p>
        </div>
        <div>
            <a href="new-build.php" class="btn" style="background:#4f46e5;color:#fff;text-decoration:none;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;">+ Start New Build</a>
        </div>
    </div>

    <?php if (!empty($_GET['batch'])): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:16px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-weight:800;font-size:15px;margin-bottom:2px;">🎉 Parallel Multi-Platform Build Dispatched!</div>
                <div style="font-size:13px;opacity:0.95;">
                    Successfully dispatched <?= (int)($_GET['count'] ?? 1) ?> target platforms in parallel to Cloud Build Engine. Compilation progress is tracked live below.
                </div>
            </div>
            <a href="new-build.php" class="btn" style="background:#166534;color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:12.5px;font-weight:700;">+ Another Build</a>
        </div>
        <script>
            // Auto-refresh while batch builds are active
            setTimeout(function() {
                window.location.reload();
            }, 7000);
        </script>
    <?php endif; ?>

    <!-- Filters Bar -->
    <div class="card" style="padding:16px 20px;margin-bottom:20px;">
        <form method="get" action="builds.php" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
            <div>
                <label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Platform</label>
                <select name="platform" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:12.5px;">
                    <option value="">All Platforms</option>
                    <option value="android" <?= $fPlatform === 'android' ? 'selected' : '' ?>>🤖 Android</option>
                    <option value="web" <?= $fPlatform === 'web' ? 'selected' : '' ?>>🌐 Web</option>
                    <option value="windows" <?= $fPlatform === 'windows' ? 'selected' : '' ?>>💻 Windows</option>
                    <option value="ios" <?= $fPlatform === 'ios' ? 'selected' : '' ?>>🍏 iOS</option>
                </select>
            </div>

            <div>
                <label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Status</label>
                <select name="status" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:12.5px;">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $fStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="building" <?= $fStatus === 'building' ? 'selected' : '' ?>>Building</option>
                    <option value="failed" <?= $fStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="queued" <?= $fStatus === 'queued' ? 'selected' : '' ?>>Queued</option>
                </select>
            </div>

            <div style="flex-grow:1;min-width:200px;">
                <label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;">Search</label>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Build ID, app name, or package..."
                       style="width:100%;padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:12.5px;">
            </div>

            <div style="margin-top:18px;">
                <button type="submit" class="btn" style="background:#1e293b;color:#fff;border:none;padding:7px 16px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Builds Table -->
    <div class="card" style="padding:20px;">
        <div class="table-wrap">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1.5px solid #e2e8f0;text-align:left;color:#64748b;font-size:11.5px;text-transform:uppercase;">
                        <th style="padding:10px 12px;">Build UID</th>
                        <th style="padding:10px 12px;">License / Order</th>
                        <th style="padding:10px 12px;">App &amp; Version</th>
                        <th style="padding:10px 12px;">Platform</th>
                        <th style="padding:10px 12px;">Status</th>
                        <th style="padding:10px 12px;">Date &amp; Time</th>
                        <th style="padding:10px 12px;">Output &amp; Diagnostics</th>
                        <th style="padding:10px 12px;text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($builds)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
                                No build records found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($builds as $b): ?>
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
                                    <div style="font-size:11px;color:#64748b;font-family:monospace;"><?= e($b['package_id']) ?></div>
                                </td>
                                <td style="padding:12px;">
                                    <?php $icons = ['android' => '🤖 Android', 'web' => '🌐 Web', 'windows' => '💻 Windows', 'ios' => '🍏 iOS']; ?>
                                    <span class="tag blue" style="font-size:11px;font-weight:700;"><?= $icons[$b['platform']] ?? ucfirst(e($b['platform'])) ?></span>
                                </td>
                                <td style="padding:12px;">
                                    <span class="status-pill <?= e($b['status']) ?>">● <?= ucfirst(e($b['status'])) ?></span>
                                </td>
                                <td style="padding:12px;color:#64748b;font-size:12px;">
                                    <div><?= !empty($b['created_at']) ? date('M d, Y H:i', strtotime($b['created_at'])) : '—' ?></div>
                                    <div style="font-size:11px;color:#94a3b8;">
                                        <?= time_ago($b['created_at']) ?>
                                        <?php if (!empty($b['build_duration_seconds'])): ?>
                                            &middot; <?= gmdate('i\m s\s', (int)$b['build_duration_seconds']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding:12px;font-size:12px;">
                                    <?php if ($b['status'] === 'completed' && !empty($b['artifact_path'])): ?>
                                        <span style="color:#059669;font-weight:600;">📦 <?= e($b['artifact_filename'] ?? 'Binary Artifact') ?></span>
                                        <?php if (!empty($b['artifact_size_bytes'])): ?>
                                            <span style="font-size:11px;color:#64748b;">(<?= round($b['artifact_size_bytes'] / (1024 * 1024), 1) ?> MB)</span>
                                        <?php endif; ?>
                                    <?php elseif ($b['status'] === 'failed'): ?>
                                        <span style="color:#b91c1c;font-size:11.5px;display:block;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($b['error_message'] ?? 'Compilation failed.') ?>">
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
</div>

<?php
builder_footer();
