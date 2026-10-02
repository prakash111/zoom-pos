<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Storage.php';

$queryKey = trim($_GET['key'] ?? $_GET['license_key'] ?? '');
if (!Auth::check() && !empty($queryKey)) {
    Auth::validateAndLogin($queryKey);
}

Auth::requireAuth();

$buildUid = trim($_GET['id'] ?? '');
if (empty($buildUid)) {
    http_response_code(400);
    die("Build ID is required.");
}

$licenseKey = Auth::key();
$build = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
    $stmt->execute([$buildUid]);
    $build = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    die("Database error while loading build artifact.");
}

if (!$build) {
    http_response_code(404);
    die("Build record not found.");
}

Storage::streamArtifact($build, $licenseKey);
