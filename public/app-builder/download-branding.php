<?php

require_once __DIR__ . '/lib/bootstrap.php';

$buildUid = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_GET['id'] ?? ''));
if ($buildUid === '') {
    http_response_code(400);
    exit('Missing build ID.');
}

$zipPath = __DIR__ . '/storage/temp/branding_' . $buildUid . '.zip';
if (!file_exists($zipPath)) {
    http_response_code(404);
    exit('Branding bundle not found or expired.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="branding_' . $buildUid . '.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, no-store, must-revalidate');

readfile($zipPath);
exit;
