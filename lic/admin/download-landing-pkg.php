<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';

$storedZip = __DIR__.'/../storage/marketing-landing-script.zip';
if (!file_exists($storedZip)) {
    $storedZip = __DIR__.'/../storage/marketing-landing-page.zip';
}
if (file_exists($storedZip)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="marketing-landing-page.zip"');
    header('Content-Length: ' . filesize($storedZip));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($storedZip);
    exit;
}

$candidates = [
    realpath(__DIR__.'/../../public/marketing'),
    realpath(__DIR__.'/../public/marketing'),
    realpath(__DIR__.'/../../landing-marketing'),
    realpath(__DIR__.'/../marketing'),
];

$sourceDir = null;
foreach ($candidates as $candidate) {
    if ($candidate && is_dir($candidate)) {
        $sourceDir = $candidate;
        break;
    }
}

if (!$sourceDir) {
    http_response_code(404);
    exit('Landing marketing script package not found.');
}

$zipFile = sys_get_temp_dir() . '/marketing-landing-script-' . date('Ymd-His') . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Failed to create ZIP archive.');
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

if (!file_exists($zipFile)) {
    http_response_code(500);
    exit('ZIP file could not be generated.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="marketing-landing-script.zip"');
header('Content-Length: ' . filesize($zipFile));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFile);
@unlink($zipFile);
exit;
