<?php

require __DIR__.'/lib/bootstrap.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Method not allowed.');
}
try {
    $license = lm_download_license(db(), (int) ($_GET['order'] ?? 0), (string) ($_GET['token'] ?? ''));
} catch (PDOException $ex) {
    http_response_code(503);
    exit('Download temporarily unavailable. Please contact support.');
} catch (RuntimeException $ex) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit($ex->getMessage());
}
$path = package_path($license['product_slug']);
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Your product package is not available yet. Please contact support. This link will work once the package is uploaded.');
}
$file = fopen($path, 'rb');
if ($file === false) { http_response_code(503); exit('Download temporarily unavailable. Please try again.'); }
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.package_slug($license['product_slug']).'.zip"');
header('Content-Length: '.fstat($file)['size']);
if ($_SERVER['REQUEST_METHOD'] === 'GET') fpassthru($file);
fclose($file);
exit;
