<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/QuotaManager.php';
require_once __DIR__ . '/lib/SourceManager.php';
require_once __DIR__ . '/lib/Customizer.php';
require_once __DIR__ . '/lib/GitHubActions.php';
require_once __DIR__ . '/lib/AccountManager.php';
require_once __DIR__ . '/lib/layout.php';

// Ensure database schema is ready
ensure_app_builder_schema();

Auth::requireAuth();

$licenseKey = Auth::key();
$plan = Auth::plan();
$clientEmail = Auth::email();

// Exception-safe Quota Retrieval
try {
    $quotaCheck = QuotaManager::checkQuota($licenseKey, $plan);
    $stats = $quotaCheck['stats'] ?? QuotaManager::getStatsForLicense($licenseKey, $plan);
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
    $quotaCheck = ['allowed' => true, 'stats' => $stats];
}

// Auto-fetch details from license-linked account
try {
    $prefill = AccountManager::getLinkedDetails($licenseKey, $clientEmail);
} catch (Throwable $e) {
    $prefill = AccountManager::buildFinalDefaults($licenseKey, [], $clientEmail);
}

$formError = '';
$formSuccess = '';

// Handle Build Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger_build') {
    csrf_check();

    // 1. Resolve selected platforms
    $rawPlatforms = $_POST['platforms'] ?? ($_POST['platform'] ?? ['android']);
    if (!is_array($rawPlatforms)) {
        $rawPlatforms = [$rawPlatforms];
    }
    $selectedPlatforms = [];
    foreach ($rawPlatforms as $p) {
        $p = strtolower(trim((string)$p));
        if (in_array($p, ['android', 'web', 'windows', 'ios'], true) && !in_array($p, $selectedPlatforms, true)) {
            $selectedPlatforms[] = $p;
        }
    }

    if (empty($selectedPlatforms)) {
        $formError = 'Please select at least one target platform to compile (Android, Web, Windows, or iOS).';
    } else {
        // 1 Build Limit covers the full multi-platform suite pack (Android, Web, Windows, iOS)
        $neededQuota = 1;
        try {
            $quotaCheck = QuotaManager::checkQuota($licenseKey, $plan, $neededQuota);
        } catch (Throwable $e) {
            $quotaCheck = ['allowed' => true, 'stats' => $stats];
        }

        if (!$quotaCheck['allowed']) {
            $formError = $quotaCheck['message'];
        } else {
            $sourceType = $_POST['source_type'] ?? 'latest_github';
            $batchUid = 'suite_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);

            // Process source
            $customSourcePath = null;
            if ($sourceType === 'uploaded_zip') {
                $zipRes = SourceManager::processUploadedZip($_FILES['source_zip'] ?? [], $batchUid);
                if (!$zipRes['success']) {
                    $formError = $zipRes['message'];
                } else {
                    $customSourcePath = $zipRes['path'] ?? null;
                }
            }

            // Process complete white-label branding customization
            if (empty($formError)) {
                $custRes = Customizer::validateConfiguration($_POST, $_FILES, $batchUid);
                if (!$custRes['valid']) {
                    $formError = implode(' ', $custRes['errors']);
                } else {
                    try {
                        $cfg = $custRes['config'];
                        $pdo = db();
                        $gh = new GitHubActions();
                        $dispatchedBuilds = [];
                        $host = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';

                        foreach ($selectedPlatforms as $platform) {
                            $buildUid = 'bld_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
                            $artifactExpectedFilename = $cfg['short_name'] . '-' . $platform . ($platform === 'android' ? '.apk' : ($platform === 'ios' ? '.ipa' : '.zip'));

                            // If custom source zip was uploaded, link it for this build
                            if ($sourceType === 'uploaded_zip' && !empty($customSourcePath)) {
                                $targetSourcePath = __DIR__ . '/storage/uploads/source_' . $buildUid . '.zip';
                                @copy($customSourcePath, $targetSourcePath);
                                $token = hash('sha256', $buildUid . $licenseKey . 'ZN_SOURCE_SALT_98421');
                                $cfg['custom_source_url'] = "{$scheme}://{$host}/app-builder/download-source.php?id=" . urlencode($buildUid) . "&token=" . urlencode($token);
                            }

                            // 1. Create database record in app_builds via BuildManager
                            require_once __DIR__ . '/lib/BuildManager.php';
                            BuildManager::recordBuild($pdo, [
                                'build_uid'         => $buildUid,
                                'batch_id'          => $batchUid,
                                'license_key'       => $licenseKey,
                                'client_email'      => $clientEmail,
                                'platform'          => $platform,
                                'source_type'       => $sourceType,
                                'app_name'          => $cfg['app_name'],
                                'package_id'        => $cfg['package_id'],
                                'build_version'     => $cfg['build_version'] ?? '1.0.0',
                                'server_url'        => $cfg['server_url'],
                                'primary_color'     => $cfg['primary_color'],
                                'custom_logo_path'  => $cfg['custom_logo_path'],
                                'branding_json'     => $cfg['branding_json'],
                                'artifact_filename' => $artifactExpectedFilename,
                            ]);

                            // 2. Dispatch build job via Cloud Build Engine
                            $dispatch = $gh->dispatchBuild($buildUid, $platform, $cfg);
                            if ($dispatch['success']) {
                                try {
                                    $pdo->prepare('UPDATE app_builds SET status = "preparing" WHERE build_uid = ?')->execute([$buildUid]);
                                } catch (Throwable $e) {}
                                $dispatchedBuilds[] = $buildUid;
                            } else {
                                try {
                                    $pdo->prepare('UPDATE app_builds SET status = "failed", error_message = ? WHERE build_uid = ?')
                                        ->execute([$dispatch['message'], $buildUid]);
                                } catch (Throwable $e) {}
                            }
                        }

                        if (!empty($dispatchedBuilds)) {
                            $firstUid = $dispatchedBuilds[0];
                            $targetUrl = count($dispatchedBuilds) === 1
                                ? "view-build.php?id={$firstUid}&new=1"
                                : "builds.php?batch=" . urlencode(implode(',', $dispatchedBuilds)) . "&count=" . count($dispatchedBuilds);
                            header("Location: {$targetUrl}");
                            exit;
                        } else {
                            $formError = 'Failed to dispatch builds. Please verify Cloud Build Engine settings.';
                        }
                    } catch (Throwable $e) {
                        $formError = 'Build initiation error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

builder_header('new-build', 'New Application Build');
?>

<div class="builder-split-wrapper">
    <div class="content-header" style="margin-bottom:20px;">
        <div>
            <h1 class="page-title">Create White-Labeled Application Build</h1>
            <p class="page-subtitle">Configure complete brand identity, visual assets, and multi-platform compilation settings with real-time preview.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn" onclick="WhitelabelPreview.resetToDefaults()" style="background:#fff;border:1px solid #cbd5e1;color:#64748b;border-radius:8px;padding:8px 14px;font-weight:700;font-size:12.5px;">
                ↺ Reset Draft
            </button>
            <a href="builds.php" class="btn" style="background:#fff;border:1px solid #cbd5e1;color:#334155;border-radius:8px;padding:8px 16px;text-decoration:none;font-weight:700;font-size:13px;">
                &larr; View All Builds
            </a>
        </div>
    </div>

    <!-- Mobile Screen Switcher (Toggle between Form and Live Device Preview) -->
    <div class="mobile-view-switcher">
        <button type="button" class="mobile-view-btn active" id="btn_view_form" onclick="switchMobileView('form')">
            ✏️ Configuration Form
        </button>
        <button type="button" class="mobile-view-btn" id="btn_view_preview" onclick="switchMobileView('preview')">
            👁️ Live Device Preview <span class="status-pill active" style="font-size:9.5px;padding:2px 6px;">Live</span>
        </button>
    </div>

    <div class="builder-split-grid">
        <!-- LEFT COLUMN: White-Label Configuration Form -->
        <div class="builder-form-column" id="col_form_view">
            <?php if (!$stats['is_unlimited'] && $stats['remaining_this_month'] <= 2 && $stats['remaining_this_month'] > 0): ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:20px;">⚠️</span>
                    <div>
                        <strong>Quota Notice:</strong> You have <strong><?= $stats['remaining_this_month'] ?> build pack<?= $stats['remaining_this_month'] > 1 ? 's' : '' ?> remaining</strong> this month out of your <?= $stats['limit'] ?> limit. (Each pack includes Android, Web, Windows &amp; iOS. Failed builds do not consume quota).
                    </div>
                </div>
            <?php elseif (!$stats['can_build']): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:16px 20px;border-radius:12px;margin-bottom:20px;font-size:13px;">
                    <h4 style="margin:0 0 4px;font-size:15px;font-weight:800;">Monthly Build Limit Reached</h4>
                    <p style="margin:0;line-height:1.5;">
                        You have reached your limit of <?= $stats['limit'] ?> successful build packs for this calendar month. Your quota will automatically renew on <strong><?= date('M 01, Y', strtotime($stats['reset_date'])) ?></strong>.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($prefill['is_auto_filled'])): ?>
                <div class="autofill-notice" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:12.5px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:18px;">✨</span>
                    <div>
                        <strong>Auto-Fetched Account Details:</strong> White-label branding has been automatically loaded from your <strong><?= e($prefill['source_matched']) ?></strong> (<?= e($prefill['company_name']) ?>). You can modify any fields below before compiling.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($formError)): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:13.5px;">
                    ⚠️ <?= e($formError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="new-build.php" enctype="multipart/form-data" id="build_form">
                <input type="hidden" name="action" value="trigger_build">
                <?= csrf_field() ?>

        <!-- =================================================================== -->
        <!-- STEP 1: TARGET PLATFORMS (MULTI-SELECT) -->
        <!-- =================================================================== -->
        <div class="card builder-step-card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="step-number" style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">1</span>
                    <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Target Platforms (Multi-Select Supported)</h3>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="selectAllPlatforms(true)" class="platform-pill-btn">✓ Select All Platforms</button>
                    <button type="button" onclick="selectAllPlatforms(false)" class="platform-pill-btn">✕ Deselect All</button>
                </div>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">
                Choose one or multiple platforms. The Cloud Build Engine compiles them in parallel using isolated cloud runners. All 4 platforms in a single submission count as 1 build pack, and failed builds never count against your quota.
            </p>

            <div class="platform-grid">
                <!-- Android -->
                <div class="platform-card selected" data-platform="android" onclick="togglePlatform('android', this)">
                    <div class="platform-checkbox">✓</div>
                    <input type="checkbox" name="platforms[]" value="android" id="cb_platform_android" checked style="display:none;">
                    <div class="platform-icon">🤖</div>
                    <div class="platform-title">Flutter Android</div>
                    <div class="platform-desc">Signed Release APK &amp; Google Play Store App Bundle (AAB).</div>
                </div>

                <!-- Web -->
                <div class="platform-card" data-platform="web" onclick="togglePlatform('web', this)">
                    <div class="platform-checkbox">✓</div>
                    <input type="checkbox" name="platforms[]" value="web" id="cb_platform_web" style="display:none;">
                    <div class="platform-icon">🌐</div>
                    <div class="platform-title">Flutter Web</div>
                    <div class="platform-desc">Complete PWA distribution package ready for web hosting.</div>
                </div>

                <!-- Windows -->
                <div class="platform-card" data-platform="windows" onclick="togglePlatform('windows', this)">
                    <div class="platform-checkbox">✓</div>
                    <input type="checkbox" name="platforms[]" value="windows" id="cb_platform_windows" style="display:none;">
                    <div class="platform-icon">💻</div>
                    <div class="platform-title">Flutter Windows</div>
                    <div class="platform-desc">Windows 64-bit desktop executable &amp; application runtime bundle.</div>
                </div>

                <!-- iOS -->
                <div class="platform-card" data-platform="ios" onclick="togglePlatform('ios', this)">
                    <div class="platform-checkbox">✓</div>
                    <input type="checkbox" name="platforms[]" value="ios" id="cb_platform_ios" style="display:none;">
                    <div class="platform-icon">🍏</div>
                    <div class="platform-title">Flutter iOS</div>
                    <div class="platform-desc">iOS Runner distribution archive (Xcode IPA bundle).</div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 2: SOURCE CODE SELECTION -->
        <!-- =================================================================== -->
        <div class="card builder-step-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span class="step-number" style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">2</span>
                <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Select Source Code</h3>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">Choose whether to compile from the official cloud repository or upload your custom source archive.</p>

            <input type="hidden" name="source_type" id="selected_source_type" value="latest_github">

            <div class="builder-source-grid">
                <!-- Option A: Latest Source -->
                <div class="platform-card source-card selected" id="source-card-latest_github" onclick="selectSourceType('latest_github')">
                    <div style="font-size:32px;margin-bottom:8px;">☁️</div>
                    <div class="platform-title">Official Cloud Release</div>
                    <div class="platform-desc" style="margin-top:6px;line-height:1.4;">
                        Uses latest validated source code with offline synchronization, Bluetooth/USB printing, and multi-store capabilities.
                    </div>
                    <span class="tag green" style="margin-top:10px;display:inline-block;font-size:10px;font-weight:800;">Recommended</span>
                </div>

                <!-- Option B: Upload ZIP -->
                <div class="platform-card source-card" id="source-card-uploaded_zip" onclick="selectSourceType('uploaded_zip')">
                    <div style="font-size:32px;margin-bottom:8px;">📦</div>
                    <div class="platform-title">Upload Custom Source ZIP</div>
                    <div class="platform-desc" style="margin-top:6px;line-height:1.4;">
                        Upload your customized Flutter project archive (.zip up to 100MB containing <code>pubspec.yaml</code>).
                    </div>
                </div>
            </div>

            <!-- Upload Area -->
            <div id="upload-zip-container" style="display:none;margin-top:20px;padding:20px;background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;text-align:center;">
                <input type="file" name="source_zip" accept=".zip" id="source_zip_file" style="display:none;" onchange="document.getElementById('zip_filename_label').innerText = this.files[0] ? this.files[0].name : '';">
                <div style="font-size:28px;margin-bottom:6px;">📁</div>
                <button type="button" onclick="document.getElementById('source_zip_file').click();" class="btn" style="background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;padding:8px 18px;font-weight:700;font-size:13px;border-radius:8px;">Browse .ZIP File</button>
                <div id="zip_filename_label" style="font-size:12px;font-weight:700;color:#0f172a;margin-top:8px;"></div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Maximum 100MB. Dangerous script extensions (.php, .sh, .bat) will be automatically sanitized.</div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 3: BASIC WHITE-LABEL BRAND IDENTITY -->
        <!-- =================================================================== -->
        <div class="card builder-step-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span class="step-number" style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">3</span>
                <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Basic White-Label Branding</h3>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">
                Every reference to original vendor branding will be completely replaced across Android, Web, Windows, iOS, and Flutter source files.
            </p>

            <div class="builder-form-grid-2col">
                <!-- Company Name -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Company Name</label>
                    <input type="text" name="company_name" id="inp_company_name" value="<?= e($_POST['company_name'] ?? ($prefill['company_name'] ?? 'Zoom Nearby')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. Acme Technologies"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Legal business or parent company name.</span>
                </div>

                <!-- Product Name -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Product Name</label>
                    <input type="text" name="product_name" id="inp_product_name" value="<?= e($_POST['product_name'] ?? ($prefill['product_name'] ?? 'Zoom Sales CRM')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. Acme POS Suite"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Software product title (metadata &amp; descriptions).</span>
                </div>

                <!-- App Name -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Application Name</label>
                    <input type="text" name="app_name" id="inp_app_name" value="<?= e($_POST['app_name'] ?? ($prefill['app_name'] ?? 'Zoom Sales POS')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. Acme POS"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Primary name displayed on app screens.</span>
                </div>

                <!-- Short App Name -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Short App Name (Executable / Output Slug)</label>
                    <input type="text" name="short_name" id="inp_short_name" value="<?= e($_POST['short_name'] ?? ($prefill['short_name'] ?? 'ZoomPOS')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. AcmePOS"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Alphanumeric slug for output binaries (e.g. <code>AcmePOS-android.apk</code>).</span>
                </div>

                <!-- App Display Name -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">App Display Name</label>
                    <input type="text" name="display_name" id="inp_display_name" value="<?= e($_POST['display_name'] ?? ($prefill['display_name'] ?? 'Zoom Sales POS')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. Acme POS - Retail & Restaurant"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Mobile launcher label and Windows/Web browser window title.</span>
                </div>

                <!-- Package / Application ID -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Package Name / Application ID</label>
                    <input type="text" name="package_id" id="inp_package_id" value="<?= e($_POST['package_id'] ?? ($prefill['package_id'] ?? 'com.zoomnearby.zoompos')) ?>" required
                           oninput="updateLivePreview()" placeholder="e.g. com.acme.pos"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Reverse-domain ID for Google Play &amp; Apple App Store bundle.</span>
                </div>

                <!-- Build / App Version -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">App Version / Release Tag</label>
                    <input type="text" name="build_version" id="inp_build_version" value="<?= e($_POST['build_version'] ?? '1.0.0') ?>" required
                           placeholder="e.g. 1.0.0"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:monospace;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Application release version (displayed in build history and receipt notifications).</span>
                </div>

                <!-- Backend Server URL -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Backend API Server URL</label>
                    <input type="url" name="server_url" value="<?= e($_POST['server_url'] ?? ($prefill['server_url'] ?? 'https://saas.zoomnearby.com')) ?>" required
                           placeholder="https://saas.yourdomain.com"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Server URL where the client POS application communicates.</span>
                </div>

                <!-- Website URL -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Official Website URL</label>
                    <input type="url" name="website_url" value="<?= e($_POST['website_url'] ?? ($prefill['website_url'] ?? 'https://zoomnearby.com')) ?>"
                           placeholder="https://acme.com"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Shown in app About section &amp; Windows installer properties.</span>
                </div>

                <!-- Support Email -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Customer Support Email</label>
                    <input type="email" name="support_email" value="<?= e($_POST['support_email'] ?? ($prefill['support_email'] ?? $clientEmail)) ?>" required
                           placeholder="support@acme.com"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Injected into app support contact &amp; crash dialogues.</span>
                </div>

                <!-- Support Phone -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Customer Support Phone / WhatsApp</label>
                    <input type="text" name="support_phone" value="<?= e($_POST['support_phone'] ?? ($prefill['support_phone'] ?? '+918535075196')) ?>"
                           placeholder="+1 555-123-4567"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Support contact number displayed on login screen.</span>
                </div>

                <!-- Copyright Text -->
                <div class="builder-form-col-full">
                    <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:5px;">Copyright Notice</label>
                    <input type="text" name="copyright" value="<?= e($_POST['copyright'] ?? ($prefill['copyright'] ?? 'Copyright (C) ' . date('Y') . ' Zoom Nearby. All rights reserved.')) ?>"
                           style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;">
                    <span style="font-size:11px;color:#94a3b8;margin-top:3px;display:block;">Injected into Windows Runner.rc, macOS/iOS info, and footer credits.</span>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 4: COLOR BRANDING PALETTE -->
        <!-- =================================================================== -->
        <div class="card builder-step-card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="step-number" style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">4</span>
                    <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Color Palette Branding</h3>
                </div>
                <div class="theme-preset-bar">
                    <span style="font-size:11px;color:#64748b;font-weight:700;">Theme Presets:</span>
                    <button type="button" class="platform-pill-btn" onclick="applyPresetPalette('#4F46E5', '#06B6D4', '#10B981', '#0F172A', '#1E293B', '#F8FAFC')">Indigo Modern</button>
                    <button type="button" class="platform-pill-btn" onclick="applyPresetPalette('#10B981', '#059669', '#34D399', '#064E3B', '#022C22', '#FFFFFF')">Emerald POS</button>
                    <button type="button" class="platform-pill-btn" onclick="applyPresetPalette('#E11D48', '#FB7185', '#F43F5E', '#18181B', '#27272A', '#FFFFFF')">Crimson Store</button>
                    <button type="button" class="platform-pill-btn" onclick="applyPresetPalette('#2563EB', '#38BDF8', '#60A5FA', '#0F172A', '#1E293B', '#FFFFFF')">Sapphire Blue</button>
                </div>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">
                All color references in Flutter themes, CSS variables, Android splash backgrounds, and web manifests are automatically re-compiled.
            </p>

            <div class="builder-color-grid">
                <!-- Primary Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Primary Brand Color</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_primary_color" value="<?= e($_POST['primary_color'] ?? ($prefill['primary_color'] ?? '#4F46E5')) ?>" onchange="syncColorInput('primary_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="primary_color" id="inp_primary_color" value="<?= e($_POST['primary_color'] ?? ($prefill['primary_color'] ?? '#4F46E5')) ?>" oninput="syncColorNative('primary_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>

                <!-- Secondary Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Secondary Color</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_secondary_color" value="<?= e($_POST['secondary_color'] ?? '#06B6D4') ?>" onchange="syncColorInput('secondary_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="secondary_color" id="inp_secondary_color" value="<?= e($_POST['secondary_color'] ?? '#06B6D4') ?>" oninput="syncColorNative('secondary_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>

                <!-- Accent Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Accent Highlight</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_accent_color" value="<?= e($_POST['accent_color'] ?? '#10B981') ?>" onchange="syncColorInput('accent_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="accent_color" id="inp_accent_color" value="<?= e($_POST['accent_color'] ?? '#10B981') ?>" oninput="syncColorNative('accent_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>

                <!-- Background Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Background Color</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_bg_color" value="<?= e($_POST['bg_color'] ?? '#0F172A') ?>" onchange="syncColorInput('bg_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="bg_color" id="inp_bg_color" value="<?= e($_POST['bg_color'] ?? '#0F172A') ?>" oninput="syncColorNative('bg_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>

                <!-- Sidebar Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Sidebar / Card Color</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_sidebar_color" value="<?= e($_POST['sidebar_color'] ?? '#1E293B') ?>" onchange="syncColorInput('sidebar_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="sidebar_color" id="inp_sidebar_color" value="<?= e($_POST['sidebar_color'] ?? '#1E293B') ?>" oninput="syncColorNative('sidebar_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>

                <!-- Text Color -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Heading Text Color</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="color" id="native_text_color" value="<?= e($_POST['text_color'] ?? '#F8FAFC') ?>" onchange="syncColorInput('text_color', this.value)" style="width:36px;height:36px;border:none;border-radius:6px;cursor:pointer;">
                        <input type="text" name="text_color" id="inp_text_color" value="<?= e($_POST['text_color'] ?? '#F8FAFC') ?>" oninput="syncColorNative('text_color', this.value)" style="width:85px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:monospace;font-weight:700;">
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 5: VISUAL BRANDING & MULTI-PLATFORM ICONS -->
        <!-- =================================================================== -->
        <div class="card builder-step-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span class="step-number" style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">5</span>
                <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Visual Branding &amp; Multi-Platform Icons</h3>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">
                Upload your master <strong>Main Logo</strong>. Our automated icon pipeline will automatically generate all required resolutions for Android mipmaps, Web PWA icons, Windows ICO, and iOS icons. You can also optionally upload platform-specific assets below.
            </p>

            <!-- Primary Master Logo Upload -->
            <div style="background:#f8fafc;padding:20px;border-radius:12px;border:1.5px dashed #cbd5e1;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:20px;">
                    <div style="width:72px;height:72px;border-radius:16px;background:#ffffff;border:1px solid #cbd5e1;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                        <span id="main_logo_placeholder" style="font-size:32px;">🖼️</span>
                        <img id="main_logo_preview" src="" style="width:100%;height:100%;object-fit:contain;display:none;">
                    </div>
                    <div style="flex-grow:1;">
                        <div style="font-weight:800;font-size:14px;color:#0f172a;margin-bottom:3px;">
                            Main Application Logo / Icon (Master Asset)
                        </div>
                        <div style="font-size:12px;color:#64748b;margin-bottom:8px;">
                            High-resolution PNG or WEBP (512x512 recommended). Used across all platforms and automatically scaled.
                        </div>
                        <input type="file" name="main_logo" accept="image/png,image/jpeg,image/webp" id="main_logo_input" style="display:none;" onchange="previewUploadImage(this, 'main_logo_preview', 'main_logo_placeholder'); updateLivePreview();">
                        <button type="button" onclick="document.getElementById('main_logo_input').click();" class="btn" style="background:#4f46e5;color:#fff;padding:6px 14px;font-size:12px;font-weight:700;border-radius:7px;">
                            📁 Choose Master Logo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Collapsible Specific Asset Uploads -->
            <details style="background:#fdfdfd;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;">
                <summary style="font-weight:700;font-size:13px;color:#334155;cursor:pointer;user-select:none;">
                    ⚙️ Advanced Specific Platform Icons &amp; Logos (Optional Overrides)
                </summary>
                <div class="builder-icon-grid" style="margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9;">
                    <!-- Light Logo -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Light Logo (for Dark Backgrounds)</label>
                        <input type="file" name="light_logo" id="file_light_logo" accept="image/*" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Dark Logo -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Dark Logo (for Light Backgrounds)</label>
                        <input type="file" name="dark_logo" id="file_dark_logo" accept="image/*" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Splash Screen Logo -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Splash Screen Logo</label>
                        <input type="file" name="splash_logo" id="file_splash_logo" accept="image/*" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Login Logo -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Login Screen Header Logo</label>
                        <input type="file" name="login_logo" id="file_login_logo" accept="image/*" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Web Favicon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Web Favicon (.ico or 32x32 PNG)</label>
                        <input type="file" name="favicon" id="file_favicon" accept="image/png,image/x-icon" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Windows Icon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Windows Icon (.ico or 256x256 PNG)</label>
                        <input type="file" name="windows_icon" id="file_windows_icon" accept="image/x-icon,image/png" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Android Launcher Icon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Android Launcher Icon (512x512 PNG)</label>
                        <input type="file" name="android_icon" id="file_android_icon" accept="image/png" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Android Notification Icon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Notification Status Bar Icon (Monochrome)</label>
                        <input type="file" name="notification_icon" id="file_notification_icon" accept="image/png" style="font-size:12px;width:100%;">
                    </div>

                    <!-- iOS App Icon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">iOS App Store Icon (1024x1024 PNG)</label>
                        <input type="file" name="ios_icon" id="file_ios_icon" accept="image/png" style="font-size:12px;width:100%;">
                    </div>

                    <!-- Web PWA Icon -->
                    <div class="sub-upload-card">
                        <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px;">Web PWA Icon (512x512 PNG)</label>
                        <input type="file" name="web_icon" id="file_web_icon" accept="image/png" style="font-size:12px;width:100%;">
                    </div>
                </div>
            </details>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 6: TRIGGER CLOUD BUILD -->
        <!-- =================================================================== -->
        <div class="card builder-step-card" style="text-align:center;background:#fafafa;">
            <div style="font-size:13px;color:#64748b;margin-bottom:14px;">
                When triggered, all code files, assets, manifests, and package namespaces will be fully white-labeled before compilation starts.
                Completed artifacts will be available on your dashboard and download links emailed to <strong><?= e($clientEmail) ?></strong>.
            </div>

            <button type="submit" id="btn_submit_build" <?= !$stats['can_build'] ? 'disabled' : '' ?>
                    style="background:#4f46e5;color:#fff;border:none;padding:14px 40px;border-radius:12px;font-size:15.5px;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(79, 70, 229, 0.35);opacity:<?= !$stats['can_build'] ? '0.6' : '1' ?>;">
                🚀 Start White-Labeled Cloud Build (1 Platform)
            </button>
        </div>

    </form>
    </div>
    <!-- END LEFT COLUMN -->

    <!-- =================================================================== -->
    <!-- RIGHT COLUMN: STICKY REAL-TIME LIVE PREVIEW & VALIDATION CHECKLIST -->
    <!-- =================================================================== -->
    <div class="builder-preview-column" id="col_preview_view">
        <div class="preview-sticky-container">
            <!-- Main Live Device Preview Card -->
            <div class="preview-card">
                <!-- Preview Header -->
                <div class="preview-header">
                    <div class="preview-header-title">
                        <span>📱 Live White-Label Preview</span>
                        <span class="preview-live-indicator">
                            <span class="preview-live-dot"></span> Live
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <button type="button" class="tool-btn" id="preview_theme_toggle_btn" onclick="WhitelabelPreview.toggleTheme()" title="Toggle Dark/Light Mode">🌙</button>
                        <button type="button" class="tool-btn" onclick="WhitelabelPreview.setZoom(-0.1)" title="Zoom Out">🔍-</button>
                        <span id="preview_zoom_label" style="font-size:11px;font-family:monospace;color:#94a3b8;font-weight:700;min-width:34px;text-align:center;">100%</span>
                        <button type="button" class="tool-btn" onclick="WhitelabelPreview.setZoom(0.1)" title="Zoom In">🔍+</button>
                        <button type="button" class="tool-btn" onclick="WhitelabelPreview.setZoom(0)" title="Reset Zoom">1:1</button>
                        <button type="button" class="tool-btn" onclick="WhitelabelPreview.resetToDefaults()" title="Reset to Defaults">↺</button>
                        <button type="button" class="tool-btn" onclick="WhitelabelPreview.toggleFullscreen()" title="Fullscreen Theater Mode">⛶</button>
                    </div>
                </div>

                <!-- Preview Toolbar -->
                <div class="preview-toolbar">
                    <!-- Row 1: Platforms and Screen Modes -->
                    <div class="preview-nav-row">
                        <!-- Platform Pills -->
                        <div class="platform-pill-group">
                            <button type="button" class="platform-pill-btn-item active" data-platform="android" onclick="WhitelabelPreview.setPlatform('android')">
                                🤖 Android
                            </button>
                            <button type="button" class="platform-pill-btn-item" data-platform="ios" onclick="WhitelabelPreview.setPlatform('ios')">
                                🍎 iOS
                            </button>
                            <button type="button" class="platform-pill-btn-item" data-platform="windows" onclick="WhitelabelPreview.setPlatform('windows')">
                                💻 Windows
                            </button>
                            <button type="button" class="platform-pill-btn-item" data-platform="web" onclick="WhitelabelPreview.setPlatform('web')">
                                🌐 Web
                            </button>
                        </div>

                        <!-- Screen Mode Pills -->
                        <div class="screen-mode-group">
                            <button type="button" class="screen-mode-btn active" data-screen="dashboard" onclick="WhitelabelPreview.setScreen('dashboard')" title="Metro Dashboard & Store KPI Analytics">
                                📊 Dashboard
                            </button>
                            <button type="button" class="screen-mode-btn" data-screen="pos" onclick="WhitelabelPreview.setScreen('pos')" title="Retail POS Register & Product Catalog">
                                🛒 Retail POS
                            </button>
                            <button type="button" class="screen-mode-btn" data-screen="cart" onclick="WhitelabelPreview.setScreen('cart')" title="Order Cart & Cashier Checkout">
                                💳 Order Cart
                            </button>
                            <button type="button" class="screen-mode-btn" data-screen="login" onclick="WhitelabelPreview.setScreen('login')" title="Sign In & Authentication">
                                🔐 Sign In
                            </button>
                            <button type="button" class="screen-mode-btn" data-screen="splash" onclick="WhitelabelPreview.setScreen('splash')" title="Branded Launch Splash">
                                🚀 Splash
                            </button>
                        </div>
                    </div>

                    <!-- Row 2: Platform-Adaptive Controls (Device Dropdown, Orientation Switcher, Custom Dimensions) -->
                    <div class="preview-nav-row" id="platform_adaptive_controls"></div>
                </div>

                <!-- Canvas Workspace -->
                <div class="preview-canvas" id="regular_canvas_container">
                    <div class="preview-viewport-wrapper" id="preview_viewport_wrapper">
                        <!-- ACTIVE DEVICE FRAME -->
                        <div id="active_device_frame" class="device-chassis type-android orientation-portrait">
                            <!-- Android Status Bar -->
                            <div class="android-status-bar">
                                <span>09:41</span>
                                <div class="android-punch-hole"></div>
                                <div class="android-status-icons">
                                    <span>5G</span>
                                    <span>📶</span>
                                    <span>100%</span>
                                </div>
                            </div>

                            <!-- iOS SE Classic Top Bar -->
                            <div class="ios-se-topbar" style="display:none;">
                                <div class="ios-se-speaker"></div>
                            </div>

                            <!-- iOS Dynamic Island Status Bar -->
                            <div class="ios-status-bar" style="display:none;">
                                <span>9:41</span>
                                <div class="ios-dynamic-island">
                                    <div class="ios-island-camera"></div>
                                    <div class="ios-island-sensor"></div>
                                </div>
                                <div style="display:flex;gap:4px;font-size:11px;">
                                    <span>📶</span>
                                    <span>🔋</span>
                                </div>
                            </div>

                            <!-- Windows 11 Chrome Titlebar -->
                            <div class="windows-titlebar" style="display:none;">
                                <div class="windows-titlebar-left">
                                    <img src="" class="windows-titlebar-icon" style="display:none;">
                                    <span class="windows-title-text">Zoom Sales POS - Point of Sale (POS Desktop)</span>
                                </div>
                                <div class="windows-controls">
                                    <button type="button" class="win-btn">─</button>
                                    <button type="button" class="win-btn">□</button>
                                    <button type="button" class="win-btn close">✕</button>
                                </div>
                            </div>
                            <div class="windows-menubar" style="display:none;">
                                <span>File</span>
                                <span>Edit</span>
                                <span>POS Register</span>
                                <span>Catalog</span>
                                <span>Reports</span>
                                <span>Settings</span>
                            </div>

                            <!-- Web Browser Chrome -->
                            <div class="web-tabstrip" style="display:none;">
                                <div class="web-tab">
                                    <img src="" class="web-tab-favicon" style="display:none;">
                                    <span class="web-tab-title-text">Zoom Sales POS</span>
                                    <span class="web-tab-close">✕</span>
                                </div>
                                <span style="font-size:14px;color:#64748b;margin-left:4px;cursor:pointer;">+</span>
                            </div>
                            <div class="web-omnibox" style="display:none;">
                                <div class="web-nav-icons">
                                    <span>←</span>
                                    <span>→</span>
                                    <span>↻</span>
                                </div>
                                <div class="web-address-bar">
                                    <span>🔒</span>
                                    <span class="web-address-text">https://saas.zoomnearby.com/pos/register</span>
                                </div>
                            </div>

                            <!-- Interactive Screen Body -->
                            <div class="device-screen-body">
                                <!-- Populated dynamically by WhitelabelPreview.render() -->
                            </div>

                            <!-- Android Bottom Bar -->
                            <div class="android-nav-bar">
                                <div class="android-gesture-pill"></div>
                            </div>

                            <!-- iOS Bottom Home Indicator -->
                            <div class="ios-home-indicator" style="display:none;">
                                <div class="ios-home-bar"></div>
                            </div>

                            <!-- iOS SE Classic Bottom Bar (Touch ID) -->
                            <div class="ios-se-bottombar" style="display:none;">
                                <div class="ios-touch-id"></div>
                            </div>

                            <!-- Windows Status Bar -->
                            <div class="windows-statusbar" style="display:none;">
                                <span class="windows-status-server">Connected: https://saas.zoomnearby.com</span>
                                <span>v1.0.0 • Production Build</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Status Strip -->
                <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div style="font-size:11.5px;color:#64748b;">
                        ⚡ Updates instantly as you type &bull; No build quota consumed
                    </div>
                    <div id="preview_completeness_badge" class="status-pill active" style="font-size:11px;">
                        ✓ White Label Configuration Ready
                    </div>
                </div>
            </div>

            <!-- Completeness Checklist & Validation Card -->
            <div class="completeness-card">
                <div class="completeness-header">
                    <div class="completeness-title">
                        <span>🛡️ White Label Validation Checklist</span>
                    </div>
                    <span id="completeness_meter_label" style="font-size:11.5px;font-weight:700;color:#059669;">
                        100% White Label Ready (12/12 criteria met)
                    </span>
                </div>

                <div class="completeness-meter-wrap">
                    <div id="completeness_meter_fill" class="completeness-meter-fill" style="width: 100%;"></div>
                </div>

                <div class="checklist-grid">
                    <div id="chk_app_name" class="check-item ready"><span class="check-icon">✓</span> Application Name</div>
                    <div id="chk_company_name" class="check-item ready"><span class="check-icon">✓</span> Company Name</div>
                    <div id="chk_logo" class="check-item ready"><span class="check-icon">✓</span> Brand Logo &amp; Assets</div>
                    <div id="chk_app_icon" class="check-item ready"><span class="check-icon">✓</span> App Launcher Icon</div>
                    <div id="chk_favicon" class="check-item ready"><span class="check-icon">✓</span> Favicon &amp; Manifest</div>
                    <div id="chk_primary_color" class="check-item ready"><span class="check-icon">✓</span> Primary Brand Color</div>
                    <div id="chk_secondary_color" class="check-item ready"><span class="check-icon">✓</span> Secondary Color</div>
                    <div id="chk_package_name" class="check-item ready"><span class="check-icon">✓</span> Package Name / App ID</div>
                    <div id="chk_server_url" class="check-item ready"><span class="check-icon">✓</span> Backend Server URL</div>
                    <div id="chk_android" class="check-item ready"><span class="check-icon">✓</span> Android White Label</div>
                    <div id="chk_ios" class="check-item ready"><span class="check-icon">✓</span> iOS White Label</div>
                    <div id="chk_windows_web" class="check-item ready"><span class="check-icon">✓</span> Windows &amp; Web Manifest</div>
                </div>
            </div>

            <!-- Expected Artifact Output Chips -->
            <div class="card" style="padding:16px 20px;">
                <div style="font-size:12px;font-weight:800;color:#334155;margin-bottom:8px;">
                    📦 Output Binaries to be Generated:
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <span class="tag blue" style="font-size:11px;" id="chip_apk">AcmePOS-android.apk</span>
                    <span class="tag purple" style="font-size:11px;" id="chip_web">AcmePOS-web.zip</span>
                    <span class="tag green" style="font-size:11px;" id="chip_win">AcmePOS-windows.zip</span>
                    <span class="tag orange" style="font-size:11px;" id="chip_ios">AcmePOS-ios.ipa</span>
                </div>
            </div>
        </div>
    </div>
    <!-- END RIGHT COLUMN -->
</div>
</div>

<!-- FULLSCREEN PREVIEW THEATER MODAL -->
<div id="preview_fullscreen_modal" class="preview-modal-overlay">
    <div class="preview-modal-content">
        <div style="background:#0f172a;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;">
            <div style="font-weight:800;font-size:15px;display:flex;align-items:center;gap:8px;">
                <span>⛶ White Label Live Preview (Theater Mode)</span>
            </div>
            <button type="button" class="tool-btn" onclick="WhitelabelPreview.toggleFullscreen()" style="color:#fff;font-size:16px;">✕</button>
        </div>
        <div class="preview-canvas" id="modal_canvas_container" style="flex:1;min-height:540px;"></div>
    </div>
</div>

<script>
function switchMobileView(view) {
    const colForm = document.getElementById('col_form_view');
    const colPrev = document.getElementById('col_preview_view');
    const btnForm = document.getElementById('btn_view_form');
    const btnPrev = document.getElementById('btn_view_preview');

    if (view === 'form') {
        if (colForm) colForm.style.display = 'block';
        if (colPrev) colPrev.style.display = 'none';
        if (btnForm) btnForm.classList.add('active');
        if (btnPrev) btnPrev.classList.remove('active');
    } else {
        if (colForm) colForm.style.display = 'none';
        if (colPrev) colPrev.style.display = 'block';
        if (btnForm) btnForm.classList.remove('active');
        if (btnPrev) btnPrev.classList.add('active');
        if (typeof WhitelabelPreview !== 'undefined') {
            WhitelabelPreview.render();
        }
    }
}

function syncColorInput(id, val) {
    const inp = document.getElementById('inp_' + id);
    if (inp) inp.value = val.toUpperCase();
    if (typeof WhitelabelPreview !== 'undefined') {
        WhitelabelPreview.syncStateFromForm();
        WhitelabelPreview.render();
        WhitelabelPreview.validate();
        WhitelabelPreview.saveDraft();
    }
}

function syncColorNative(id, val) {
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        const nat = document.getElementById('native_' + id);
        if (nat) nat.value = val;
        if (typeof WhitelabelPreview !== 'undefined') {
            WhitelabelPreview.syncStateFromForm();
            WhitelabelPreview.render();
            WhitelabelPreview.validate();
            WhitelabelPreview.saveDraft();
        }
    }
}

function applyPresetPalette(primary, secondary, accent, bg, sidebar, text) {
    syncColorInput('primary_color', primary);
    syncColorNative('primary_color', primary);
    syncColorInput('secondary_color', secondary);
    syncColorNative('secondary_color', secondary);
    syncColorInput('accent_color', accent);
    syncColorNative('accent_color', accent);
    syncColorInput('bg_color', bg);
    syncColorNative('bg_color', bg);
    syncColorInput('sidebar_color', sidebar);
    syncColorNative('sidebar_color', sidebar);
    syncColorInput('text_color', text);
    syncColorNative('text_color', text);
}

function previewUploadImage(input, previewId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (typeof WhitelabelPreview !== 'undefined') {
                WhitelabelPreview.state.logoUrl = e.target.result;
                if (!WhitelabelPreview.state.androidIconUrl) WhitelabelPreview.state.androidIconUrl = e.target.result;
                if (!WhitelabelPreview.state.faviconUrl) WhitelabelPreview.state.faviconUrl = e.target.result;
                if (!WhitelabelPreview.state.windowsIconUrl) WhitelabelPreview.state.windowsIconUrl = e.target.result;
                WhitelabelPreview.render();
                WhitelabelPreview.validate();
                WhitelabelPreview.saveDraft();
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateLivePreview() {
    if (typeof WhitelabelPreview !== 'undefined') {
        WhitelabelPreview.syncStateFromForm();
        WhitelabelPreview.render();
        WhitelabelPreview.validate();
        WhitelabelPreview.saveDraft();
    }
}

window.PREFILLED_BRANDING = <?= json_encode($prefill ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', function() {
    updateSelectedPlatformsSummary();
    if (typeof WhitelabelPreview !== 'undefined') {
        WhitelabelPreview.init();
    }
});
</script>

<?php
builder_footer();
