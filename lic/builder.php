<?php

// Separate App Builder for license holders & tenants
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($uri, '/app-builder')) {
    header('Location: index.php' . $qs);
} else {
    header('Location: /app-builder/' . $qs);
}
exit;
