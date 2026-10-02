<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/BuildManager.php';

Auth::requireAuth();

$buildUid = trim($_GET['id'] ?? '');
if (empty($buildUid)) {
    json_out(400, ['success' => false, 'message' => 'Build UID is required.']);
}

$licenseKey = Auth::key();
$pdo = db();

$syncResult = BuildManager::syncSingleBuild($pdo, $buildUid, $licenseKey);
if (!$syncResult['success'] || empty($syncResult['build'])) {
    json_out(404, ['success' => false, 'message' => $syncResult['message'] ?? 'Build record not found.']);
}

$build = $syncResult['build'];

// Step descriptions
$descriptions = [
    'queued'    => 'Build request queued in cloud dispatcher...',
    'preparing' => 'Setting up source code workspace and environment parameters...',
    'building'  => 'Compiling Flutter application binary on Cloud Compilation Cluster...',
    'completed' => 'Compilation succeeded! Build artifact stored and ready for download.',
    'failed'    => 'Build failed during compilation. Check parameters or asset files.',
    'cancelled' => 'Build was cancelled.',
    'expired'   => 'Build artifact has expired.',
];

json_out(200, [
    'success' => true,
    'build' => [
        'build_uid' => $build['build_uid'],
        'license_id' => $build['license_id'] ?? null,
        'order_id' => $build['order_id'] ?? null,
        'order_reference' => $build['order_reference'] ?? null,
        'platform' => $build['platform'],
        'app_name' => $build['app_name'],
        'package_id' => $build['package_id'],
        'build_version' => $build['build_version'] ?? '1.0.0',
        'server_url' => $build['server_url'],
        'primary_color' => $build['primary_color'],
        'status' => $build['status'],
        'artifact_filename' => $build['artifact_filename'],
        'artifact_size_bytes' => $build['artifact_size_bytes'],
        'build_duration_seconds' => $build['build_duration_seconds'],
        'error_message' => $build['error_message'],
        'started_at' => $build['started_at'],
        'completed_at' => $build['completed_at'],
    ],
    'step_description' => $descriptions[$build['status']] ?? 'Processing build...',
]);
