<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/QuotaManager.php';
require_once __DIR__ . '/../lib/Customizer.php';
require_once __DIR__ . '/../lib/GitHubActions.php';

ensure_app_builder_schema();

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$licenseKey = Auth::key();
$plan = Auth::plan();
$clientEmail = Auth::email();

$input = !empty($_POST) ? $_POST : read_json_body();

// Resolve target platforms
$rawPlatforms = $input['platforms'] ?? ($input['platform'] ?? ['android']);
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
    json_out(422, ['success' => false, 'message' => 'Please select at least one valid platform (android, web, windows, ios).']);
}

// 1. Check Quota Server-Side (1 build unit = all 4 platforms in suite pack)
try {
    $quotaCheck = QuotaManager::checkQuota($licenseKey, $plan, 1);
} catch (Throwable $e) {
    $quotaCheck = ['allowed' => true];
}
if (!$quotaCheck['allowed']) {
    json_out(403, ['success' => false, 'message' => $quotaCheck['message'] ?? 'Quota exceeded']);
}

// 2. Validate Customization & Process White-Label Branding
$validation = Customizer::validateConfiguration($input, $_FILES);
if (!$validation['valid']) {
    json_out(422, ['success' => false, 'errors' => $validation['errors'], 'message' => implode(' ', $validation['errors'])]);
}

$cfg = $validation['config'];
$sourceType = $input['source_type'] ?? 'latest_github';
$batchUid = 'suite_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
$pdo = db();
$gh = new GitHubActions();
$dispatched = [];

foreach ($selectedPlatforms as $platform) {
    $buildUid = 'bld_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
    $artifactExpectedFilename = $cfg['short_name'] . '-' . $platform . ($platform === 'android' ? '.apk' : ($platform === 'ios' ? '.ipa' : '.zip'));

    require_once __DIR__ . '/../lib/BuildManager.php';
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

    $dispatch = $gh->dispatchBuild($buildUid, $platform, $cfg);
    if ($dispatch['success']) {
        $pdo->prepare('UPDATE app_builds SET status = "preparing" WHERE build_uid = ?')->execute([$buildUid]);
        $dispatched[] = ['build_uid' => $buildUid, 'platform' => $platform];
    } else {
        $pdo->prepare('UPDATE app_builds SET status = "failed", error_message = ? WHERE build_uid = ?')
            ->execute([$dispatch['message'], $buildUid]);
    }
}

if (!empty($dispatched)) {
    $firstUid = $dispatched[0]['build_uid'];
    $redirectUrl = count($dispatched) === 1
        ? "view-build.php?id={$firstUid}&new=1"
        : "builds.php?batch=" . urlencode(implode(',', array_column($dispatched, 'build_uid'))) . "&count=" . count($dispatched);

    json_out(200, [
        'success' => true,
        'message' => count($dispatched) > 1 ? count($dispatched) . ' builds dispatched successfully.' : 'Build dispatched successfully.',
        'build_uid' => $firstUid,
        'builds' => $dispatched,
        'count' => count($dispatched),
        'redirect_url' => $redirectUrl,
    ]);
} else {
    json_out(500, ['success' => false, 'message' => 'Failed to dispatch builds to Cloud Build Engine.']);
}
