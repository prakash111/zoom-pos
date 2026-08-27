<?php

/**
 * Physical-file stub for the legacy sync-compat URL /api/mysql.php.
 *
 * Some nginx configs only forward a *.php request to PHP-FPM when it maps to
 * a real file on disk (a common hardening rule: `location ~ \.php$ { try_files
 * $uri =404; ... }`). Laravel routes registered at this exact path (see
 * routes/sync.php) aren't real files, so those configs 404 the request before
 * PHP ever runs. This stub IS a real file, so it passes that check — then it
 * overrides SCRIPT_NAME/SCRIPT_FILENAME to look like index.php before booting
 * Laravel, so the framework resolves the route from the original REQUEST_URI
 * (/api/mysql.php) exactly as if index.php had been hit directly.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../index.php';
