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

$account = Auth::account();
if (empty($account)) {
    $account = $prefill;
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
                if (empty($_POST['short_name']) && !empty($_POST['slug'])) {
                    $_POST['short_name'] = $_POST['slug'];
                }
                if (empty($_POST['server_url']) && !empty($_POST['api'])) {
                    $_POST['server_url'] = $_POST['api'];
                }
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

// Initial defaults
$defaultAppName = $_POST['app_name'] ?? ($prefill['app_name'] ?? 'Zoom Nearby POS');
$defaultCompanyName = $_POST['company_name'] ?? ($prefill['company_name'] ?? 'Zoom Nearby');
$defaultProductName = $_POST['product_name'] ?? ($prefill['product_name'] ?? 'Zoom Sales CRM');
$defaultShortName = $_POST['short_name'] ?? ($_POST['slug'] ?? ($prefill['short_name'] ?? 'zoomnearbypos'));
$defaultPackageId = $_POST['package_id'] ?? ($prefill['package_id'] ?? 'com.zoomnearby.zoomnearbypos');
$defaultBuildVersion = $_POST['build_version'] ?? '1.0.0';
$defaultServerUrl = $_POST['server_url'] ?? ($prefill['server_url'] ?? 'https://saas.zoomnearby.com');
$rawWebsite = trim((string)($prefill['website_url'] ?? ''));
if (empty($rawWebsite) || in_array(rtrim($rawWebsite, '/'), ['https://zoomnearby.com', 'https://saas.zoomnearby.com', 'http://zoomnearby.com', 'http://saas.zoomnearby.com'], true)) {
    $defaultWebsiteUrl = 'https://saas.zoomnearby.com/pos-web/';
} else {
    $defaultWebsiteUrl = $rawWebsite;
}
if (!empty($_POST['website_url'])) {
    $defaultWebsiteUrl = trim((string)$_POST['website_url']);
}
$defaultSupportEmail = $_POST['support_email'] ?? ($prefill['support_email'] ?? ($clientEmail ?: 'licensee@zoomnearby.com'));
$defaultSupportPhone = $_POST['support_phone'] ?? ($prefill['support_phone'] ?? '+918535075196');
$defaultCopyright = $_POST['copyright'] ?? ($prefill['copyright'] ?? ('Copyright (C) ' . date('Y') . ' Zoom Nearby. All rights reserved.'));
$defaultPrimaryColor = $_POST['primary_color'] ?? ($prefill['primary_color'] ?? '#4F46E5');

$docInfo = builder_documentation_info();
$planName = ucfirst($plan ?: 'Regular');
$maskedLicense = (strlen($licenseKey) > 10)
    ? substr($licenseKey, 0, 5) . '-•••••-' . substr($licenseKey, -4)
    : ($licenseKey ?: 'DEMO-LICENSE');
$canBuild = !empty($stats['can_build']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New build · App Builder</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f7fb;--surface:#fff;--ink:#12142a;--muted:#5d6280;--line:#e4e6f0;--brand:#4f46e5;--brand-ink:#3730a3;--brand-soft:#eef0ff;--ok:#0f9d6a;--ok-soft:#e6f6ef;--warn:#b4540a;--warn-soft:#fff3e4;--r:12px;--font:'Onest',system-ui,-apple-system,'Segoe UI',sans-serif;--mono:ui-monospace,'SF Mono',Menlo,monospace}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12}}
:root[data-theme=dark]{--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
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
main{min-width:0}
.top{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:12px;padding:12px 28px;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--ok)}
.top .sp{flex:1}
.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:var(--surface);padding:8px 14px;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;color:inherit;text-decoration:none}
.btn:hover{border-color:var(--brand)}
.btn.pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn.pri:hover{filter:brightness(1.08)}
.btn[disabled]{opacity:.5;cursor:not-allowed}
.btn svg{width:16px;height:16px}
.wrap{padding:28px;max-width:1540px;margin:0 auto}
h1{font-size:28px;line-height:1.2;margin:0 0 6px;letter-spacing:-.02em}
.lead{color:var(--muted);margin:0 0 20px;max-width:60ch}
.alert{display:flex;gap:12px;align-items:flex-start;background:var(--warn-soft);color:var(--warn);border-radius:var(--r);padding:12px 16px;margin-bottom:20px}
.alert svg{width:20px;height:20px;flex:none;margin-top:2px}
.alert p{margin:0;color:var(--ink)}
.alert b{display:block;color:var(--warn)}
.grid{display:grid;grid-template-columns:minmax(0,1fr) 510px;gap:28px;align-items:start;transition:grid-template-columns .28s cubic-bezier(0.4,0,0.2,1)}
.grid.mode-web{grid-template-columns:minmax(0,1fr) 680px}
.steps{display:flex;gap:6px;overflow-x:auto;margin-bottom:16px;padding-bottom:2px}
.steps a{white-space:nowrap;text-decoration:none;color:var(--muted);font-weight:500;font-size:14px;padding:6px 12px;border-radius:99px;border:1px solid var(--line);background:var(--surface)}
.steps a.on{background:var(--brand);border-color:var(--brand);color:#fff}
section.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:24px;margin-bottom:16px}
section.card>h2{font-size:18px;margin:0 0 4px;letter-spacing:-.01em}
section.card>p.sub{margin:0 0 18px;color:var(--muted);font-size:14px}
.head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.links{display:flex;gap:14px}
.links button{background:none;border:0;color:var(--brand-ink);font-weight:600;cursor:pointer;padding:0;font-size:14px}
.plats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.plat{position:relative;display:flex;gap:12px;align-items:center;border:1.5px solid var(--line);border-radius:var(--r);padding:14px;cursor:pointer;transition:border-color .15s,background .15s}
.plat:hover{border-color:var(--brand)}
.plat input{position:absolute;opacity:0}
.plat:has(input:checked){border-color:var(--brand);background:var(--brand-soft)}
.plat:has(input:focus-visible){outline:2px solid var(--brand);outline-offset:2px}
.plat .ic{width:40px;height:40px;border-radius:10px;background:var(--bg);display:grid;place-items:center;flex:none}
.plat:has(input:checked) .ic{background:var(--brand);color:#fff}
.plat .ic svg{width:20px;height:20px}
.plat b{display:block;line-height:1.25}
.plat span{font-size:13px;color:var(--muted);line-height:1.3;display:block}
.plat .ck{margin-left:auto;width:20px;height:20px;border-radius:50%;border:1.5px solid var(--line);flex:none;display:grid;place-items:center}
.plat:has(input:checked) .ck{background:var(--brand);border-color:var(--brand)}
.plat .ck svg{width:12px;height:12px;stroke:#fff;opacity:0}
.plat:has(input:checked) .ck svg{opacity:1}
.src{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.src .plat{align-items:flex-start}
.badge{font-size:12px;font-weight:600;color:var(--ok);background:var(--ok-soft);padding:1px 8px;border-radius:99px;margin-left:6px;vertical-align:1px}
.f2{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px}
.fld{display:flex;flex-direction:column;gap:5px;min-width:0}
.fld.full{grid-column:1/-1}
.fld label{font-weight:600;font-size:14px}
.fld small{color:var(--muted);font-size:13px;line-height:1.35}
.in{width:100%;height:42px;border:1px solid var(--line);background:var(--surface);border-radius:10px;padding:0 12px;transition:border-color .15s,box-shadow .15s}
.in:hover{border-color:#b9bdd6}
.in:focus{outline:0;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 22%,transparent)}
.in.mono{font-family:var(--mono);font-size:13.5px}
.sub-h{font-size:13px;font-weight:600;color:var(--muted);margin:22px 0 10px;padding-top:18px;border-top:1px solid var(--line)}
.sub-h:first-of-type{border:0;padding:0;margin-top:0}
.pals{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.pal{display:flex;align-items:center;gap:8px;padding:6px 12px 6px 8px;border:1px solid var(--line);border-radius:99px;background:var(--surface);cursor:pointer;font-weight:500;font-size:14px}
.pal:hover,.pal.on{border-color:var(--brand)}
.pal.on{background:var(--brand-soft)}
.pal i{display:flex}
.pal i s{width:14px;height:14px;border-radius:50%;margin-left:-4px;border:2px solid var(--surface)}
.pal i s:first-child{margin:0}
.colors{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.col{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:10px;padding:6px;background:var(--surface)}
.col input[type=color]{appearance:none;-webkit-appearance:none;border:0;padding:0;width:34px;height:34px;border-radius:8px;cursor:pointer;background:none;flex:none}
.col input[type=color]::-webkit-color-swatch-wrapper{padding:0}
.col input[type=color]::-webkit-color-swatch{border:1px solid rgba(0,0,0,.12);border-radius:8px}
.col div{min-width:0;line-height:1.2}
.col small{display:block;color:var(--muted);font-size:12.5px}
.col code{font-family:var(--mono);font-size:13px}
.drop{display:flex;align-items:center;gap:16px;border:1.5px dashed var(--line);border-radius:var(--r);padding:16px;background:var(--bg)}
.drop:hover{border-color:var(--brand)}
.drop .thumb{width:68px;height:68px;border-radius:16px;background:linear-gradient(135deg,#2dd4bf,#4f46e5);display:grid;place-items:center;flex:none;overflow:hidden}
.drop .thumb img{width:100%;height:100%;object-fit:cover}
.drop .thumb svg{width:30px;height:30px;stroke:#fff}
.drop b{display:block}
.drop small{color:var(--muted)}
details{margin-top:14px;border:1px solid var(--line);border-radius:var(--r)}
summary{cursor:pointer;padding:12px 16px;font-weight:600;list-style:none;display:flex;justify-content:space-between;align-items:center}
summary::-webkit-details-marker{display:none}
summary::after{content:'';width:8px;height:8px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg);transition:transform .2s;margin-right:4px}
details[open] summary::after{transform:rotate(-135deg)}
.ovr{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px 16px}
.file{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:10px;padding:8px 12px;cursor:pointer;font-size:13.5px;min-width:0}
.file:hover{border-color:var(--brand)}
.file input{display:none}
.file svg{width:16px;height:16px;color:var(--muted);flex:none}
.file span{min-width:0}
.file em{display:block;font-style:normal;color:var(--muted);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.file b{font-weight:600;display:block;line-height:1.25}
aside.side{position:sticky;top:84px;display:flex;flex-direction:column;gap:14px;background:none;border:0;padding:0;height:auto}
.prev{background:#0b0e1f;color:#eef0ff;border-radius:18px;overflow:hidden;border:1px solid #23274a;transition:all .28s}
.pv-h{display:flex;align-items:center;gap:8px;padding:12px 14px;font-weight:600;font-size:14px}
.pv-h .tag{margin-left:auto}
.tabs{display:flex;gap:4px;padding:0 12px 10px;flex-wrap:wrap}
.tabs button{border:0;background:transparent;color:#a0a5c6;font-weight:500;font-size:13px;padding:5px 12px;border-radius:99px;cursor:pointer;transition:background .15s,color .15s}
.tabs button:hover:not([aria-pressed=true]){background:rgba(255,255,255,.06);color:#fff}
.tabs button[aria-pressed=true]{background:#262a55;color:#fff}
.stage{display:flex;justify-content:center;align-items:center;padding:10px 14px 22px;min-height:480px;overflow:hidden}

.phone{width:350px;height:670px;border:8px solid #1c2040;border-radius:40px;background:var(--p-bg,#0f172a);position:relative;overflow:hidden;box-shadow:0 24px 55px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.06);transition:width .28s cubic-bezier(0.4,0,0.2,1),height .28s cubic-bezier(0.4,0,0.2,1),border-radius .28s ease,border-width .28s ease,background .25s}
.phone.web{width:620px;max-width:100%;height:410px;border-radius:14px;border:4px solid #1c2040;box-shadow:0 20px 48px rgba(0,0,0,.55)}

.phone .bar{height:24px;display:flex;justify-content:space-between;align-items:center;padding:0 14px;font-size:10px;opacity:.85;position:relative;z-index:20;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);user-select:none;transition:all .2s}
.phone .phone-notch{position:absolute;left:50%;top:4px;transform:translateX(-50%);width:64px;height:12px;background:#000;border-radius:99px;pointer-events:none;z-index:21}
.phone .win-ctrls{display:none;align-items:center;gap:6px}
.phone .w-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
.phone .w-dot.red{background:#ff5f56}
.phone .w-dot.yel{background:#ffbd2e}
.phone .w-dot.grn{background:#27c93f}

.phone.web .phone-notch{display:none}
.phone.web .win-ctrls{display:flex}
.phone.web #pv_bar_status{display:none}
.phone.web #pv_bar_title{font-size:11.5px;font-weight:600;color:#cbd5e1;letter-spacing:.02em;margin:0 auto 0 10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.phone.web .bar{height:28px;padding:0 12px;background:#14172a;border-bottom:1px solid rgba(255,255,255,.08)}

#pv_url_bar{height:24px;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:space-between;padding:0 10px;font-size:10px;color:#cbd5e1;font-family:var(--mono);border-bottom:1px solid rgba(255,255,255,.08);position:relative;z-index:10;transition:all .2s}
.phone.web #pv_url_bar{height:28px;background:#0e1122;padding:0 12px}
#pv_url_display{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:250px;color:#94a3b8;font-size:9.5px}
.phone.web #pv_url_display{max-width:480px;background:rgba(255,255,255,.05);border-radius:5px;padding:2px 8px;color:#cbd5e1}
.pv-acts{display:flex;gap:6px;align-items:center}
.pv-acts button,.pv-acts a{background:none;border:none;cursor:pointer;color:#818cf8;font-size:11px;padding:0;line-height:1;text-decoration:none}
.pv-acts a{color:#a0a5c6}

#pv_iframe{position:absolute;top:48px;left:0;right:0;bottom:0;width:100%;height:calc(100% - 48px);border:none;background:#fff;display:block}
.phone.web #pv_iframe{top:56px;height:calc(100% - 56px)}

.splash{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:linear-gradient(160deg,var(--p-pri,#4f46e5),var(--p-bg,#0f172a) 70%);z-index:1}
.splash .ico{width:62px;height:62px;border-radius:16px;background:linear-gradient(135deg,var(--p-sec,#06b6d4),var(--p-pri,#4f46e5));display:grid;place-items:center;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.35)}
.splash .ico img{width:100%;height:100%;object-fit:cover}
.splash .ico svg{width:30px;height:30px;stroke:#fff}
.splash h4{margin:4px 0 0;font-size:15px;text-align:center;padding:0 10px}
.splash p{margin:0;font-size:11px;opacity:.75}
.splash .ld{width:70px;height:3px;border-radius:3px;background:rgba(255,255,255,.2);overflow:hidden;margin-top:6px}
.splash .ld::after{content:'';display:block;width:45%;height:100%;background:var(--p-acc,#10b981)}
.splash small{position:absolute;bottom:12px;font-size:8.5px;opacity:.55;text-align:center;padding:0 12px}
.card2{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:18px}
.ring-row{display:flex;gap:16px;align-items:center;margin-bottom:12px}
.ring{--v:100;width:56px;height:56px;border-radius:50%;background:conic-gradient(var(--ok) calc(var(--v)*1%),var(--line) 0);display:grid;place-items:center;flex:none}
.ring b{width:44px;height:44px;border-radius:50%;background:var(--surface);display:grid;place-items:center;font-size:13px}
.ring-row strong{display:block}
.ring-row small{color:var(--muted)}
.chk{display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;margin:0;padding:0;list-style:none;font-size:13.5px}
.chk li{display:flex;gap:7px;align-items:center}
.chk li::before{content:'';width:14px;height:14px;border-radius:50%;background:var(--ok-soft) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 6.2 5 8l4-4.5' fill='none' stroke='%230f9d6a' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/10px no-repeat;flex:none}
.chk li.no{color:var(--muted)}
.chk li.no::before{background:var(--line)}
.outs{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.outs code{font-family:var(--mono);font-size:12.5px;background:var(--bg);border:1px solid var(--line);border-radius:7px;padding:2px 8px}
.bar-act{position:sticky;bottom:0;z-index:15;display:flex;align-items:center;gap:16px;background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:14px 18px;box-shadow:0 -8px 30px rgba(18,20,42,.08)}
.bar-act p{margin:0;flex:1;font-size:13.5px;color:var(--muted)}
.bar-act p b{color:var(--ink)}
.bar-act .btn{height:44px;padding:0 22px}
@media (max-width:1440px){.grid.mode-web{grid-template-columns:minmax(0,1fr) 580px}.phone.web{width:540px}}
@media (max-width:1200px){.grid,.grid.mode-web{grid-template-columns:1fr}aside.side{position:static}.phone.web{width:100%;max-width:620px}}
@media (max-width:860px){.app{grid-template-columns:1fr}.app>aside{display:none}.wrap{padding:16px}.top{padding:10px 16px}.plats,.src,.f2,.ovr,.colors,.chk{grid-template-columns:1fr}.bar-act{flex-direction:column;align-items:stretch}}
@media (prefers-reduced-motion:reduce){*{transition:none!important;scroll-behavior:auto!important}}
</style>
</head>
<body>
<div class="app">
<aside>
  <a href="index.php" class="logo"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3 3M4 20l1-4L16 5a2.1 2.1 0 0 1 3 3L8 19z"/></svg></i>App Builder</a>
  <nav aria-label="Main">
    <a href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
    <a href="new-build.php" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>New build</a>
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
  <button class="btn" type="button" id="reset"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/></svg>Reset draft</button>
</div>

<div class="wrap">
  <h1>Create a white-label build</h1>
  <p class="lead">Set your brand, pick platforms, and watch the preview update as you type. Previewing never uses build quota.</p>

  <?php if (!empty($formError)): ?>
    <div class="alert" role="alert" style="background:var(--warn-soft);color:var(--warn);border:1px solid var(--warn);">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p><b>Configuration notice</b><?= e($formError) ?></p>
    </div>
  <?php endif; ?>

  <?php if (!$canBuild): ?>
    <div class="alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 20h20zM12 10v5M12 18h.01"/></svg>
      <p><b>Monthly build limit reached</b>You've used all build packs for this month. Your quota renews on <?= date('M j, Y', strtotime($stats['reset_date'] ?? '+1 month')) ?>. You can still configure and save a draft.</p>
    </div>
  <?php endif; ?>

  <div class="grid" id="main_grid">
  <form id="f" method="POST" action="new-build.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="trigger_build">

    <div class="steps" aria-label="Sections">
      <a href="#s1" class="on">Platforms</a><a href="#s2">Source</a><a href="#s3">Identity</a><a href="#s4">Colors</a><a href="#s5">Icons</a>
    </div>

    <section class="card" id="s1">
      <div class="head"><div><h2>Platforms</h2><p class="sub">Selected platforms compile in parallel. Building all four counts as one build pack, and failed builds never use quota.</p></div>
      <div class="links"><button type="button" data-all="1">Select all</button><button type="button" data-all="0">Clear</button></div></div>
      <div class="plats">
        <label class="plat"><input type="checkbox" name="platforms[]" value="android" checked><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="3"/><path d="M11 18h2"/></svg></span><span><b>Android</b><span>Signed APK and Play Store bundle</span></span><span class="ck"><svg viewBox="0 0 12 12" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6.2 2.4 2.3 4.6-5"/></svg></span></label>
        <label class="plat"><input type="checkbox" name="platforms[]" value="web" checked><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg></span><span><b>Web</b><span>PWA package ready for hosting</span></span><span class="ck"><svg viewBox="0 0 12 12" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6.2 2.4 2.3 4.6-5"/></svg></span></label>
        <label class="plat"><input type="checkbox" name="platforms[]" value="windows"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></span><span><b>Windows</b><span>64-bit desktop app and runtime</span></span><span class="ck"><svg viewBox="0 0 12 12" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6.2 2.4 2.3 4.6-5"/></svg></span></label>
        <label class="plat"><input type="checkbox" name="platforms[]" value="ios"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 19h2"/></svg></span><span><b>iOS</b><span>Xcode IPA bundle</span></span><span class="ck"><svg viewBox="0 0 12 12" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6.2 2.4 2.3 4.6-5"/></svg></span></label>
      </div>
    </section>

    <section class="card" id="s2">
      <h2>Source code</h2><p class="sub">Compile from the official release, or bring your own Flutter project.</p>
      <div class="src">
        <label class="plat"><input type="radio" name="source_type" value="latest_github" checked><span><b>Official cloud release<em class="badge">Recommended</em></b><span>Latest validated source with offline sync, Bluetooth/USB printing and multi-store support.</span></span></label>
        <label class="plat"><input type="radio" name="source_type" value="uploaded_zip"><span><b>Upload custom source</b><span>A .zip up to 100 MB containing your <span style="font-family:var(--mono)">pubspec.yaml</span>.</span></span></label>
      </div>
      <div id="zip_upload_section" style="display:none;margin-top:14px;">
        <label class="file" style="border-style:dashed;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>
          <span><b>Custom Flutter source archive (.zip)</b><em id="zip_filename_display">No file chosen (maximum 100 MB)</em></span>
          <input type="file" name="source_zip" id="source_zip" accept=".zip" style="display:none;">
        </label>
      </div>
    </section>

    <section class="card" id="s3">
      <h2>Brand identity</h2><p class="sub">Every reference to the original vendor is replaced across all platforms.</p>
      <div class="sub-h">Company</div>
      <div class="f2">
        <div class="fld"><label for="company">Company name</label><input class="in" id="company" name="company_name" value="<?= e($defaultCompanyName) ?>"><small>Legal business or parent company</small></div>
        <div class="fld"><label for="product">Product name</label><input class="in" id="product" name="product_name" value="<?= e($defaultProductName) ?>"><small>Shown in metadata and descriptions</small></div>
      </div>
      <div class="sub-h">App</div>
      <div class="f2">
        <div class="fld"><label for="appname">Application name</label><input class="in" id="appname" name="app_name" value="<?= e($defaultAppName) ?>"><small>Primary name on app screens and the launcher</small></div>
        <div class="fld"><label for="slug">Output file name</label><input class="in mono" id="slug" name="short_name" value="<?= e($defaultShortName) ?>"><small>Lowercase, no spaces. Used for binaries like <span style="font-family:var(--mono)">name.apk</span></small></div>
        <div class="fld"><label for="pkg">Package name / app ID</label><input class="in mono" id="pkg" name="package_id" value="<?= e($defaultPackageId) ?>"><small>Reverse-domain ID for Google Play and the App Store</small></div>
        <div class="fld"><label for="ver">Version</label><input class="in mono" id="ver" name="build_version" value="<?= e($defaultBuildVersion) ?>"><small>Shown in build history and notifications</small></div>
        <div class="fld full"><label for="api">Backend API server URL</label><input class="in mono" id="api" name="server_url" value="<?= e($defaultServerUrl) ?>"><small>Where the POS client connects</small></div>
      </div>
      <div class="sub-h">Contact and legal</div>
      <div class="f2">
        <div class="fld"><label for="site">Flutter Web URL <span style="font-size:12px;color:var(--brand);font-weight:600;">Example : https://saas.zoomnearby.com/pos-web/</span></label><input class="in mono" id="site" name="website_url" value="<?= e($defaultWebsiteUrl) ?>" placeholder="https://saas.zoomnearby.com/pos-web/"><small>Web app preview URL loaded in the live phone & desktop frame</small></div>
        <div class="fld"><label for="mail">Support email</label><input class="in" id="mail" name="support_email" type="email" value="<?= e($defaultSupportEmail) ?>"><small>Used in contact and crash dialogs</small></div>
        <div class="fld"><label for="tel">Support phone / WhatsApp</label><input class="in" id="tel" name="support_phone" value="<?= e($defaultSupportPhone) ?>"><small>Shown on the login screen</small></div>
        <div class="fld"><label for="copy">Copyright notice</label><input class="in" id="copy" name="copyright" value="<?= e($defaultCopyright) ?>"><small>Used in app footers and credits</small></div>
      </div>
    </section>

    <section class="card" id="s4">
      <h2>Colors</h2><p class="sub">Start from a theme, then fine-tune. Changes apply to Flutter themes, splash screens and web manifests.</p>
      <div class="pals" id="pals"></div>
      <div class="colors" id="colors"></div>
    </section>

    <section class="card" id="s5">
      <h2>Logo and icons</h2><p class="sub">Upload one master logo. We generate every size for Android, Web, Windows and iOS.</p>
      <label class="drop" style="cursor:pointer"><span class="thumb" id="thumb"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M9 8h6M9 12h6"/></svg></span>
        <span style="flex:1"><b>Master logo</b><small>PNG or WEBP, 512 × 512 or larger</small></span>
        <span class="btn" role="button">Choose file</span><input type="file" id="logo" name="app_logo" accept="image/png,image/webp" hidden></label>
      <details><summary>Platform-specific overrides (optional)</summary>
        <div class="ovr" id="ovr"></div>
      </details>
    </section>

    <div class="bar-act">
      <p><b id="cnt">2 platforms</b> selected. <?php if (!$stats['is_unlimited']): ?>Quota: <?= (int)$stats['remaining_this_month'] ?> builds remaining.<?php else: ?>Unlimited quota.<?php endif; ?></p>
      <button class="btn" type="button" id="save_draft_btn" onclick="saveDraft()">Save draft</button>
      <button class="btn pri" type="submit" id="go" <?= !$canBuild ? 'disabled' : '' ?>>Start cloud build</button>
    </div>
  </form>

  <aside class="side" aria-label="Live preview">
    <div class="prev">
      <div class="pv-h">
        <span>Live preview</span>
        <span class="tag" id="pv_device_badge">Android</span>
      </div>
      <div class="tabs" id="tabs">
        <button type="button" aria-pressed="true" data-m="phone" data-p="android">Android</button>
        <button type="button" aria-pressed="false" data-m="phone" data-p="ios">iOS</button>
        <button type="button" aria-pressed="false" data-m="web" data-p="windows">Windows</button>
        <button type="button" aria-pressed="false" data-m="web" data-p="web">Web</button>
      </div>

      <div style="display:flex;gap:6px;padding:0 12px 10px;justify-content:space-between;align-items:center;">
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn" id="btn_view_splash" style="padding:3px 10px;font-size:12px;border-radius:99px;border-color:#2a2e4d;background:#171a30;color:#eef0ff;">Splash</button>
          <button type="button" class="btn pri" id="btn_view_web" style="padding:3px 10px;font-size:12px;border-radius:99px;">Flutter Web</button>
        </div>
        <button type="button" class="btn" id="btn_modal_live" style="padding:3px 8px;font-size:11px;border-radius:6px;border-color:#2a2e4d;background:#171a30;color:#818cf8;" title="Open in Interactive Simulator Modal">📱 Simulator</button>
      </div>

      <div class="stage"><div class="phone" id="phone">
        <div class="bar" id="pv_bar">
          <div class="win-ctrls">
            <span class="w-dot red"></span>
            <span class="w-dot yel"></span>
            <span class="w-dot grn"></span>
          </div>
          <div class="phone-notch"></div>
          <span id="pv_bar_title">09:41</span>
          <span id="pv_bar_status">5G 100%</span>
        </div>
        
        <div id="pv_url_bar">
          <span id="pv_url_display" title="<?= e($defaultWebsiteUrl) ?>"><?= e($defaultWebsiteUrl) ?></span>
          <div class="pv-acts">
            <button type="button" id="pv_reload_btn" onclick="reloadWebIframe()" title="Reload Web View">↻</button>
            <a href="<?= e($defaultWebsiteUrl) ?>" id="pv_ext_btn" target="_blank" title="Open in new window">↗</a>
          </div>
        </div>

        <div class="splash" id="pv_splash" style="display:none;">
          <div class="ico" id="pico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="3"/><path d="M9 8h6M9 12h6"/></svg></div>
          <h4 id="pname"><?= e($defaultAppName) ?></h4>
          <p id="pprod"><?= e($defaultProductName) ?></p>
          <div class="ld"></div>
          <small id="pcopy"><?= e($defaultCopyright) ?></small>
        </div>

        <iframe id="pv_iframe" src="<?= e($defaultWebsiteUrl) ?>" allow="clipboard-write"></iframe>
      </div></div>
    </div>

    <div class="card2">
      <div class="ring-row"><div class="ring" id="ring"><b id="rt">12/12</b></div><div><strong id="rs">Ready to white-label</strong><small id="rm">All 12 checks pass</small></div></div>
      <ul class="chk" id="chk"></ul>
      <div class="outs" id="outs"></div>
    </div>
  </aside>
  </div>
</div>
</main>
</div>

<!-- Interactive Full Simulator Modal -->
<div id="live_modal" style="display:none;position:fixed;inset:0;background:rgba(10,12,24,0.88);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div id="modal_box" style="background:var(--surface);border:1px solid var(--line);border-radius:24px;overflow:hidden;box-shadow:0 30px 70px rgba(0,0,0,0.6);display:flex;flex-direction:column;width:440px;max-width:96vw;height:90vh;max-height:880px;position:relative;transition:width .26s cubic-bezier(0.4,0,0.2,1),max-width .26s ease,height .26s ease,border-radius .26s ease;">
    <div style="background:var(--surface);border-bottom:1px solid var(--line);padding:10px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-weight:700;font-size:14px;" id="modal_title"><?= e($defaultAppName) ?> Simulator</span>
        <span class="tag" id="modal_badge" style="font-size:11px;">Android</span>
      </div>

      <!-- Simulator Layout Switcher -->
      <div style="display:flex;gap:4px;background:var(--bg);padding:3px;border-radius:10px;border:1px solid var(--line);">
        <button type="button" id="modal_tab_phone" onclick="switchModalLayout('phone')" style="border:none;background:var(--brand);color:#fff;font-weight:600;font-size:12px;padding:4px 12px;border-radius:7px;cursor:pointer;transition:all .15s;">📱 Mobile</button>
        <button type="button" id="modal_tab_web" onclick="switchModalLayout('web')" style="border:none;background:transparent;color:var(--muted);font-weight:600;font-size:12px;padding:4px 12px;border-radius:7px;cursor:pointer;transition:all .15s;">💻 Desktop / Web</button>
      </div>

      <div style="display:flex;align-items:center;gap:8px;">
        <button type="button" onclick="reloadModalIframe()" class="btn" style="padding:4px 8px;font-size:12px;" title="Reload Web View">↻</button>
        <button type="button" onclick="closeLiveModal()" class="btn" style="padding:4px 10px;font-size:12px;">✕ Close</button>
      </div>
    </div>
    <div style="flex:1;position:relative;background:#000;">
      <iframe id="modal_iframe" src="about:blank" style="width:100%;height:100%;border:none;background:#fff;" allow="clipboard-write"></iframe>
    </div>
  </div>
</div>

<script>
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const canBuild=<?= $canBuild ? 'true' : 'false' ?>;
const pals=[
  ["Sapphire",["#4F46E5","#06B6D4","#10B981","#0F172A","#1E293B","#F8FAFC"]],
  ["Emerald",["#059669","#0EA5E9","#F59E0B","#07130E","#10241B","#ECFDF5"]],
  ["Sunset",["#EA580C","#DB2777","#FACC15","#1A0F0A","#2A1812","#FFF7ED"]],
  ["Graphite",["#475569","#94A3B8","#22C55E","#0B0D12","#171A22","#F1F5F9"]]
];
const names=["Primary","Secondary","Accent","Background","Surface","Heading text"],
      vars=["--p-pri","--p-sec","--p-acc","--p-bg","--p-surf","--p-txt"],
      fldNames=["primary_color","secondary_color","accent_color","bg_color","sidebar_color","text_color"];

const ph=$("#phone");

$("#colors").innerHTML=names.map((n,i)=>`<label class="col"><input type="color" data-i="${i}" name="${fldNames[i]}" aria-label="${n}"><div><small>${n}</small><code></code></div></label>`).join("");

function setPal(k){
  $$("#pals .pal").forEach((b,i)=>b.classList.toggle("on",i==k));
  pals[k][1].forEach((c,i)=>{
    const el=$$("#colors input")[i];
    el.value=c;
    paint(el);
  });
}

function paint(el){
  const i=+el.dataset.i;
  el.nextElementSibling.querySelector("code").textContent=el.value.toUpperCase();
  ph.style.setProperty(vars[i],el.value);
  ph.style.color=getComputedStyle(ph).getPropertyValue("--p-txt")||"#fff";
}

$("#pals").innerHTML=pals.map((p,i)=>`<button type="button" class="pal" data-k="${i}"><i>${p[1].slice(0,3).map(c=>`<s style="background:${c}"></s>`).join("")}</i>${p[0]}</button>`).join("");
$("#pals").onclick=e=>{
  const b=e.target.closest(".pal");
  if(b) setPal(+b.dataset.k);
};

$("#colors").oninput=e=>{
  if(e.target.type=="color"){
    paint(e.target);
    $$("#pals .pal").forEach(b=>b.classList.remove("on"));
  }
};
setPal(0);

const ov=[
  {label: "Light logo (for dark backgrounds)", name: "light_logo"},
  {label: "Dark logo (for light backgrounds)", name: "dark_logo"},
  {label: "Splash screen logo", name: "splash_logo"},
  {label: "Login header logo", name: "login_logo"},
  {label: "Web favicon (.ico or 32×32)", name: "favicon"},
  {label: "Windows icon (.ico or 256×256)", name: "windows_icon"},
  {label: "Android launcher (512×512)", name: "android_icon"},
  {label: "Notification icon (monochrome)", name: "notification_icon"},
  {label: "iOS App Store (1024×1024)", name: "ios_icon"},
  {label: "Web PWA icon (512×512)", name: "web_icon"}
];

$("#ovr").innerHTML=ov.map(t=>`<label class="file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg><span><b>${t.label}</b><em>No file chosen</em></span><input type="file" name="${t.name}" accept="image/png,image/webp,image/x-icon,image/vnd.microsoft.icon"></label>`).join("");

$("#ovr").onchange=e=>{
  const em=e.target.closest(".file").querySelector("em");
  em.textContent=e.target.files[0]?.name||"No file chosen";
};

$("#logo").onchange=e=>{
  const f=e.target.files[0];
  if(!f) return;
  const u=URL.createObjectURL(f);
  $("#thumb").innerHTML=`<img src="${u}" alt="">`;
  $("#pico").innerHTML=`<img src="${u}" alt="">`;
};

// Text fields live binding to splash screen & window title
const bind=(a,b)=>$(a)&&$(a).addEventListener("input",e=>{
  if($(b)) $(b).textContent=e.target.value;
  if(a==="#appname"){
    const activeTab=$('#tabs button[aria-pressed=true]');
    if(activeTab&&activeTab.dataset.m==='web'){
      const p=activeTab.dataset.p;
      const barTitle=$("#pv_bar_title");
      if(barTitle) barTitle.textContent=(e.target.value.trim()||'ZooM-POS')+(p==='windows'?' — Windows App':' — Flutter Web');
    }
  }
});
bind("#appname","#pname");
bind("#product","#pprod");
bind("#copy","#pcopy");

// Source Type Selection toggle
$$('input[name=source_type]').forEach(r => r.addEventListener('change', () => {
  const isZip = $('input[name=source_type]:checked').value === 'uploaded_zip';
  $('#zip_upload_section').style.display = isZip ? 'block' : 'none';
}));
if ($('#source_zip')) {
  $('#source_zip').addEventListener('change', e => {
    $('#zip_filename_display').textContent = e.target.files[0]?.name || 'No file chosen (maximum 100 MB)';
  });
}

// Flutter Web URL live preview binding
function updateWebPreviewUrl(raw) {
  let val = (raw || '').trim();
  if (!val) val = 'https://saas.zoomnearby.com/pos-web/';
  if (!val.startsWith('http://') && !val.startsWith('https://')) {
    val = 'https://' + val;
  }
  const display = $('#pv_url_display');
  const extBtn = $('#pv_ext_btn');
  const iframe = $('#pv_iframe');
  if (display) {
    display.textContent = val;
    display.title = val;
  }
  if (extBtn) extBtn.href = val;
  if (iframe && val.length > 8) {
    iframe.src = val;
  }
}

if ($('#site')) {
  $('#site').addEventListener('input', e => updateWebPreviewUrl(e.target.value));
}

// Toggle View: Splash vs Live Flutter Web
function setPreviewView(mode) {
  const splash = $('#pv_splash');
  const iframe = $('#pv_iframe');
  const urlBar = $('#pv_url_bar');
  const btnSplash = $('#btn_view_splash');
  const btnLive = $('#btn_view_web');
  if (mode === 'splash') {
    splash.style.display = 'flex';
    iframe.style.display = 'none';
    urlBar.style.display = 'none';
    btnSplash.className = 'btn pri';
    btnLive.className = 'btn';
    btnLive.style.background = '#171a30';
    btnLive.style.borderColor = '#2a2e4d';
    btnLive.style.color = '#eef0ff';
  } else {
    splash.style.display = 'none';
    iframe.style.display = 'block';
    urlBar.style.display = 'flex';
    btnLive.className = 'btn pri';
    btnSplash.className = 'btn';
    btnSplash.style.background = '#171a30';
    btnSplash.style.borderColor = '#2a2e4d';
    btnSplash.style.color = '#eef0ff';
    const currentVal = $('#site').value.trim() || 'https://saas.zoomnearby.com/pos-web/';
    if (!iframe.src || iframe.src === 'about:blank' || iframe.src === '') {
      iframe.src = currentVal;
    }
  }
}

$('#btn_view_splash').onclick = () => setPreviewView('splash');
$('#btn_view_web').onclick = () => setPreviewView('live');

function reloadWebIframe() {
  const val = $('#site').value.trim() || 'https://saas.zoomnearby.com/pos-web/';
  $('#pv_iframe').src = val;
}

// Simulator Modal Layout Switcher
function switchModalLayout(mode, explicitPlatform) {
  const box = $('#modal_box');
  const btnPhone = $('#modal_tab_phone');
  const btnWeb = $('#modal_tab_web');
  const badge = $('#modal_badge');
  const title = $('#modal_title');
  const appName = ($('#appname')?.value.trim()) || 'ZooM-POS';

  let p = explicitPlatform;
  if (!p) {
    const activeTab = $('#tabs button[aria-pressed=true]');
    p = activeTab ? activeTab.dataset.p : (mode === 'web' ? 'web' : 'android');
  }

  if (mode === 'web') {
    if (box) {
      box.style.width = '94vw';
      box.style.maxWidth = '1140px';
      box.style.height = '86vh';
      box.style.maxHeight = '780px';
      box.style.borderRadius = '16px';
    }
    if (btnWeb) {
      btnWeb.style.background = 'var(--brand)';
      btnWeb.style.color = '#fff';
    }
    if (btnPhone) {
      btnPhone.style.background = 'transparent';
      btnPhone.style.color = 'var(--muted)';
    }
    const pLabel = (p === 'windows') ? 'Windows App' : 'Flutter Web';
    if (badge) badge.textContent = pLabel;
    if (title) title.textContent = appName + ' — ' + pLabel + ' Simulator';
  } else {
    if (box) {
      box.style.width = '440px';
      box.style.maxWidth = '95vw';
      box.style.height = '90vh';
      box.style.maxHeight = '870px';
      box.style.borderRadius = '24px';
    }
    if (btnPhone) {
      btnPhone.style.background = 'var(--brand)';
      btnPhone.style.color = '#fff';
    }
    if (btnWeb) {
      btnWeb.style.background = 'transparent';
      btnWeb.style.color = 'var(--muted)';
    }
    const pLabel = (p === 'ios') ? 'iOS' : 'Android';
    if (badge) badge.textContent = pLabel;
    if (title) title.textContent = appName + ' — ' + pLabel + ' Simulator';
  }
}

// Simulator Modal
$('#btn_modal_live').onclick = () => {
  const val = $('#site').value.trim() || 'https://saas.zoomnearby.com/pos-web/';
  const activeTab = $('#tabs button[aria-pressed=true]');
  const isWeb = activeTab && activeTab.dataset.m === 'web';
  const platform = activeTab ? activeTab.dataset.p : 'android';

  // Automatically adapt simulator layout to currently selected platform!
  switchModalLayout(isWeb ? 'web' : 'phone', platform);

  $('#modal_iframe').src = val;
  $('#live_modal').style.display = 'flex';
};

function closeLiveModal() {
  $('#live_modal').style.display = 'none';
  $('#modal_iframe').src = 'about:blank';
}

function reloadModalIframe() {
  const val = $('#site').value.trim() || 'https://saas.zoomnearby.com/pos-web/';
  $('#modal_iframe').src = val;
}

// Platform Tabs Switcher
$("#tabs").onclick=e=>{
  const b=e.target.closest("button");
  if(!b) return;
  $$("#tabs button").forEach(x=>x.setAttribute("aria-pressed",x==b));
  const isWeb=(b.dataset.m=="web");
  const platform=b.dataset.p;

  ph.classList.toggle("web",isWeb);
  const grid=$("#main_grid");
  if(grid) grid.classList.toggle("mode-web",isWeb);

  const badge=$("#pv_device_badge");
  if(badge){
    const labels={android:'Android',ios:'iOS',windows:'Windows',web:'Flutter Web'};
    badge.textContent=labels[platform]||'Live';
  }

  const barTitle=$("#pv_bar_title");
  if(barTitle){
    if(isWeb){
      const appName=($('#appname')?.value.trim())||'ZooM-POS';
      barTitle.textContent=appName+(platform==='windows'?' — Windows App':' — Flutter Web');
    } else {
      barTitle.textContent='09:41';
    }
  }

  const btnSim=$('#btn_modal_live');
  if(btnSim){
    btnSim.innerHTML=isWeb ? (platform==='windows' ? '💻 Windows Simulator' : '🌐 Web Simulator') : '📱 Simulator';
  }

  if(isWeb) {
    setPreviewView("live");
  }
};

const checks=[
  ["Application name","appname"],
  ["Company name","company"],
  ["Logo","logo"],
  ["Launcher icon","logo"],
  ["Favicon and manifest","logo"],
  ["Primary color",1],
  ["Secondary color",1],
  ["Package ID","pkg"],
  ["Backend URL","api"],
  ["Android","p:android"],
  ["iOS","p:ios"],
  ["Windows and Web","p:win"]
];

function state(){
  const sel=$$('[name="platforms[]"]:checked').map(x=>x.value);
  $("#cnt").textContent=sel.length+(sel.length==1?" platform":" platforms");
  const ext={android:["android.apk"],web:["web.zip"],windows:["windows.zip"],ios:["ios.ipa"]};
  $("#outs").innerHTML=sel.length?sel.flatMap(s=>ext[s]).map(x=>`<code>${$("#slug").value||"app"}-${x}</code>`).join(""):'<small style="color:var(--muted)">Select a platform to see output files.</small>';
  let ok=0;
  const items=checks.map(([t,k])=>{
    let v=true;
    if(typeof k=="string"&&k.startsWith("p:")) {
      v=sel.some(s=>s.startsWith(k.slice(2)))||k=="p:win"&&sel.some(s=>s=="web"||s=="windows");
    } else if(k=="logo") {
      v=true;
    } else if(typeof k=="string") {
      v=$("#"+k).value.trim().length>0;
    }
    if(v) ok++;
    return `<li class="${v?"":"no"}">${t}</li>`;
  });
  $("#chk").innerHTML=items.join("");
  $("#ring").style.setProperty("--v",Math.round(ok/12*100));
  $("#rt").textContent=ok+"/12";
  $("#rs").textContent=ok==12?"Ready to white-label":"Needs a few more details";
  $("#rm").textContent=ok==12?"All 12 checks pass":(12-ok)+" checks to go";
  const goBtn=$("#go");
  if(goBtn && canBuild) {
    goBtn.disabled = (sel.length === 0);
  }
}

$("#f").addEventListener("input",state);
$("#f").addEventListener("change",state);

$$("[data-all]").forEach(b=>b.onclick=()=>{
  $$('[name="platforms[]"]').forEach(c=>c.checked=b.dataset.all=="1");
  state();
});

function saveDraft() {
  const data = {
    company: $("#company").value,
    product: $("#product").value,
    appname: $("#appname").value,
    slug: $("#slug").value,
    pkg: $("#pkg").value,
    ver: $("#ver").value,
    api: $("#api").value,
    site: $("#site").value,
    mail: $("#mail").value,
    tel: $("#tel").value,
    copy: $("#copy").value
  };
  localStorage.setItem("zn_builder_draft", JSON.stringify(data));
  const btn = $("#save_draft_btn");
  if (btn) {
    const orig = btn.textContent;
    btn.textContent = "Saved ✓";
    setTimeout(() => btn.textContent = orig, 2000);
  }
}

function loadDraft() {
  try {
    const raw = localStorage.getItem("zn_builder_draft");
    if (raw) {
      const data = JSON.parse(raw);
      if (data.site && (data.site === "https://saas.zoomnearby.com" || data.site === "https://saas.zoomnearby.com/")) {
        data.site = "https://saas.zoomnearby.com/pos-web/";
      }
      for (const k in data) {
        if ($("#" + k) && data[k]) $("#" + k).value = data[k];
      }
      if ($("#pname")) $("#pname").textContent = $("#appname").value;
      if ($("#pprod")) $("#pprod").textContent = $("#product").value;
      if ($("#pcopy")) $("#pcopy").textContent = $("#copy").value;
      const currentSite = $("#site") ? $("#site").value : (data.site || "https://saas.zoomnearby.com/pos-web/");
      updateWebPreviewUrl(currentSite);
    }
  } catch (e) {}
}

$("#reset").onclick=()=>{
  localStorage.removeItem("zn_builder_draft");
  $("#f").reset();
  setPal(0);
  $("#pname").textContent=$("#appname").value;
  $("#pprod").textContent=$("#product").value;
  $("#pcopy").textContent=$("#copy").value;
  updateWebPreviewUrl($("#site").value);
  state();
};

const themeBtn = $("#theme");
if (themeBtn) {
  themeBtn.onclick=()=>{
    const r=document.documentElement;
    const isDark=getComputedStyle(r).getPropertyValue("--bg").trim()=="#0e1020";
    const next=isDark?"light":"dark";
    r.dataset.theme=next;
    localStorage.setItem("zn_theme",next);
  };
}
const savedTheme=localStorage.getItem("zn_theme");
if(savedTheme){
  document.documentElement.dataset.theme=savedTheme;
}

const secs=$$("section.card"),links=$$(".steps a");
if(window.IntersectionObserver){
  const obs=new IntersectionObserver(es=>es.forEach(e=>{
    if(e.isIntersecting)links.forEach(a=>a.classList.toggle("on",a.hash=="#"+e.target.id));
  }),{rootMargin:"-25% 0px -65% 0px"});
  secs.forEach(s=>obs.observe(s));
}

loadDraft();
state();
</script>
</body>
</html>
