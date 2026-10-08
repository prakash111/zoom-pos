<?php

/**
 * Zoom Sales CRM & POS - Universal Hosting Entry Point
 *
 * Automatically forwards to public/index.php when DocumentRoot
 * is configured to the root project folder (shared hosting / cPanel / Plesk).
 */

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

// Built-in PHP development server support
if (php_sapi_name() === 'cli-server' && $uri !== '' && $uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Serve public static assets if requested through root on shared hosting
if ($uri !== '' && $uri !== '/' && is_file(__DIR__ . '/public' . $uri)) {
    $filePath = __DIR__ . '/public' . $uri;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'map' => 'application/json',
    ];
    $contentType = $mimes[$ext] ?? (function_exists('mime_content_type') ? @mime_content_type($filePath) : 'application/octet-stream');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=31536000');
    readfile($filePath);
    exit;
}

require_once __DIR__ . '/public/index.php';
