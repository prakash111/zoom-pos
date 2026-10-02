<?php

/**
 * ZoomNearby License Manager
 * Automatic Redirection Engine
 */

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';

// If App Builder was explicitly requested
if (isset($_GET['builder']) || isset($_GET['build']) || isset($_GET['app'])) {
    // If the request URI already contains /app-builder, avoid duplicating the directory
    if (str_contains($uri, '/app-builder')) {
        header('Location: index.php' . $qs);
    } else {
        header('Location: /app-builder/' . $qs);
    }
    exit;
}

// Redirect all root traffic directly to the Marketplace / Checkout
header('Location: buy.php' . $qs);
exit;
