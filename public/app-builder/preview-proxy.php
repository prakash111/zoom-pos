<?php

// Real-Time Live Server Preview Proxy for App Builder
// Securely proxies the target server endpoint to allow embedding in preview mockups without cross-origin X-Frame-Options blocking.

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';

Auth::requireAuth();

$targetUrl = trim((string)($_GET['url'] ?? 'https://saas.zoomnearby.com'));
if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://'))) {
    $targetUrl = 'https://saas.zoomnearby.com';
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36 AppBuilderPreview/1.0',
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html';
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $targetUrl;
$curlErr = curl_error($ch);
curl_close($ch);

// Set headers allowing iframe embedding from license server
header('Content-Type: ' . $contentType);
header('X-Frame-Options: ALLOWALL');
header("Content-Security-Policy: frame-ancestors *;");
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($html === false || ($httpCode >= 400 && empty($html))) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px;text-align:center;color:#64748b;background:#f8fafc;">';
    echo '<div style="font-size:32px;margin-bottom:8px;">⚠️</div>';
    echo '<h3 style="margin:0 0 8px;color:#0f172a;">Could not reach endpoint</h3>';
    echo '<p style="margin:0;font-size:13px;">' . htmlspecialchars($curlErr ?: "HTTP Error {$httpCode}", ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p style="margin-top:12px;font-size:12px;color:#94a3b8;">URL: ' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

$baseHref = rtrim($finalUrl, '/') . '/';
if (preg_match('~^https?://[^/]+(/[^?#]*)~', $finalUrl, $m) && !str_ends_with($m[1], '/')) {
    $baseHref = dirname($finalUrl) . '/';
}

if (str_contains($contentType, 'text/html') && is_string($html)) {
    // Inject <base href="..."> so relative paths (CSS, images, JS) resolve to target server
    if (stripos($html, '<head>') !== false) {
        $html = preg_replace('/<head>/i', '<head><base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '">', $html, 1);
    } elseif (stripos($html, '<head ') !== false) {
        $html = preg_replace('/<head([^>]*)>/i', '<head$1><base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '">', $html, 1);
    } else {
        $html = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '">' . $html;
    }
    echo $html;
} else {
    echo $html;
}
exit;
