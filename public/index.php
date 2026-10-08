<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Universal Hosting Compatibility Checks
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>PHP Version Error</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{background:#1e293b;border:1px solid #334155;padding:2rem;border-radius:1rem;max-width:520px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.5);}h1{color:#f43f5e;margin-top:0;font-size:1.5rem;}p{color:#cbd5e1;font-size:0.95rem;line-height:1.6;}code{background:#090d16;color:#38bdf8;padding:0.2rem 0.5rem;border-radius:0.35rem;font-size:0.9rem;}</style></head><body><div><h1>PHP 8.2 or Higher Required</h1><p>Your server is currently running PHP <code>' . PHP_VERSION . '</code>.</p><p>This application requires <strong>PHP 8.2</strong> or <strong>PHP 8.3</strong>. Please switch your PHP version to 8.2 or 8.3 in your hosting control panel (cPanel &rarr; MultiPHP Manager or PHP Selector).</p></div></body></html>';
    exit;
}

// Auto-repair storage directories on fresh hosting
$storageDirs = [
    __DIR__.'/../storage',
    __DIR__.'/../storage/app',
    __DIR__.'/../storage/app/public',
    __DIR__.'/../storage/framework',
    __DIR__.'/../storage/framework/cache',
    __DIR__.'/../storage/framework/cache/data',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/logs',
    __DIR__.'/../bootstrap/cache',
];
foreach ($storageDirs as $sDir) {
    if (! is_dir($sDir)) {
        @mkdir($sDir, 0777, true);
    }
    @chmod($sDir, 0777);
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

try {
    $app->handleRequest(Request::capture());
} catch (\Throwable $e) {
    // Graceful recovery for uninstalled instances on fresh hosting
    if (! file_exists(__DIR__.'/../storage/installed') && ! file_exists(__DIR__.'/../storage/--installed')) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Installation Setup Notice</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;padding:2rem;display:flex;justify-content:center;}div{background:#1e293b;border:1px solid #334155;border-radius:1rem;padding:2rem;max-width:720px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.5);}h1{color:#38bdf8;margin-top:0;font-size:1.5rem;}p{color:#cbd5e1;line-height:1.6;font-size:0.95rem;}pre{background:#090d16;padding:1rem;border-radius:0.5rem;overflow-x:auto;color:#f43f5e;font-size:0.85rem;white-space:pre-wrap;word-break:break-word;}a{display:inline-block;margin-top:1rem;background:#0284c7;color:#fff;padding:0.6rem 1.2rem;border-radius:0.5rem;text-decoration:none;font-weight:bold;}</style></head><body><div><h1>Setup Wizard Initialization</h1><p>The system detected an unhandled exception before installation finished:</p><pre>' . htmlspecialchars($e->getMessage()) . "\n\nFile: " . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</pre><p>To configure your database and initialize the application, open the interactive web installer:</p><p><a href="/install">Launch Web Installer &rarr;</a></p></div></body></html>';
        exit;
    }
    throw $e;
}
