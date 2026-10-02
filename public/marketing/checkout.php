<?php

require __DIR__ . '/config.php';

$domain = strtolower(preg_replace('#^https?://#', '', trim($_POST['domain'] ?? '')));
$domain = preg_replace('#[/?].*$#', '', $domain);
$email = trim($_POST['email'] ?? '');
$bundle = strtolower(trim($_POST['bundle'] ?? ''));
$product = strtolower(trim($_POST['product'] ?? ''));
$modules = strtolower(trim($_POST['modules'] ?? ''));
$includeCore = !empty($_POST['include_core']) ? '1' : '0';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?err=' . urlencode('Please enter a valid email address.'));
    exit;
}

if ($domain === '') {
    header('Location: index.php?err=' . urlencode('Please enter the target domain where you will host the script.'));
    exit;
}

// Build return URL back to this landing site
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$landingReturnUrl = $scheme . '://' . $host . dirname($_SERVER['REQUEST_URI']) . '/index.php?order=complete';

$params = [
    'domain' => $domain,
    'email' => $email,
    'return' => $landingReturnUrl,
];

if ($bundle !== '') {
    $params['bundle'] = $bundle;
} elseif ($product !== '') {
    $params['product'] = $product;
} else {
    $params['modules'] = $modules;
    $params['include_core'] = $includeCore;
}

$checkoutRedirectUrl = CHECKOUT_URL . '?' . http_build_query($params);

// Redirect buyer directly to the License Manager payment engine
header('Location: ' . $checkoutRedirectUrl);
exit;
