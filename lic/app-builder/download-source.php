<?php

require_once __DIR__ . '/lib/bootstrap.php';

$buildUid = trim($_GET['id'] ?? '');
$token = trim($_GET['token'] ?? '');

if (empty($buildUid) || empty($token)) {
    http_response_code(400);
    die("Build ID and security token are required.");
}

$build = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
    $stmt->execute([$buildUid]);
    $build = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    die("Database error while loading build source.");
}

if (!$build) {
    http_response_code(404);
    die("Build not found.");
}

// Verify token
$expectedToken = hash('sha256', $buildUid . $build['license_key'] . 'ZN_SOURCE_SALT_98421');
if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    die("Invalid security token.");
}

$sourceFile = __DIR__ . '/storage/uploads/source_' . $buildUid . '.zip';
if (!file_exists($sourceFile)) {
    $sourceFile = __DIR__ . '/storage/temp/' . $buildUid . '/source.zip';
}

if (!file_exists($sourceFile)) {
    http_response_code(404);
    die("Uploaded source package no longer exists on server.");
}

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="source-' . $buildUid . '.zip"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($sourceFile));

readfile($sourceFile);
exit;
