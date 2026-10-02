<?php

// App Builder is separate for license holders / tenants using their license key,
// not for the license manager admin. Redirect directly to the standalone builder.
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($uri, '/app-builder')) {
    header('Location: index.php' . $qs);
} else {
    $dest = file_exists(__DIR__ . '/../app-builder') ? '../app-builder/' : '/app-builder/';
    header('Location: ' . $dest . $qs);
}
exit;
