<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_schema_api();

$HMAC_SALT = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
$AES_CIPHER = 'AES-256-CBC';

$in = read_json_body();
$serverUrl   = rtrim(strtolower((string)($in['server_url'] ?? '')), '/');
$platform    = (string)($in['platform'] ?? 'android');
$timestamp   = (int)($in['timestamp'] ?? 0);
$signature   = (string)($in['signature'] ?? '');

// 1. Anti-Replay: Prevent validation requests older than 5 minutes
if (abs(time() - $timestamp) > 300) {
    json_out(400, [
        'status'  => 'error',
        'code'    => 400,
        'message' => 'Request signature expired. Check device clock.'
    ]);
}

// 2. Validate HMAC Signature
$expectedSignature = hash_hmac('sha256', "{$serverUrl}|{$timestamp}|{$platform}", $HMAC_SALT);
if (!hash_equals($expectedSignature, $signature)) {
    json_out(401, [
        'status'  => 'error',
        'code'    => 401,
        'message' => 'Tampered handshake payload detected.'
    ]);
}

// 3. Extract Clean Domain / Host
$host = parse_url($serverUrl, PHP_URL_HOST) ?? $serverUrl;

// 4. Query Registered License Record
$pdo = db();
$license = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM licenses WHERE registered_domain = ? OR bound_domain = ? OR JSON_CONTAINS(allowed_domains, ?) LIMIT 1');
    $stmt->execute([$host, $host, json_encode($host)]);
    $license = $stmt->fetch();
} catch (Throwable $e) {
    try {
        $stmt2 = $pdo->prepare('SELECT * FROM licenses WHERE registered_domain = ? OR bound_domain = ? LIMIT 1');
        $stmt2->execute([$host, $host]);
        $license = $stmt2->fetch();
    } catch (Throwable $e2) {
        $stmt3 = $pdo->prepare('SELECT * FROM licenses WHERE bound_domain = ? LIMIT 1');
        $stmt3->execute([$host]);
        $license = $stmt3->fetch();
    }
}

$isActive = $license && (in_array($license['status'] ?? '', ['active', '1', 1], true) || !empty($license['is_active']));

if (!$license || !$isActive) {
    json_out(403, [
        'status'  => 'unregistered',
        'code'    => 403,
        'message' => 'Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.',
        'buy_url' => 'https://zoomnearby.com/pricing'
    ]);
}

// 5. Tier Check: Regular vs Extended
$tier = strtolower((string)($license['license_type'] ?? $license['plan'] ?? 'regular'));
$isExtended = ($tier === 'extended');

// 6. Generate Encrypted Verification Token (Valid for 7 Days)
$sessionPayload = json_encode([
    'domain'       => $host,
    'license_type' => $tier,
    'is_extended'  => $isExtended,
    'issued_at'    => time(),
    'expires_at'   => time() + 7 * 86400,
]);

$appKey = defined('SERVER_SECRET') && SERVER_SECRET !== '' ? SERVER_SECRET : 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
$encKey = substr(hash('sha256', $appKey), 0, 32);
$encIv  = substr(hash('sha256', $HMAC_SALT), 0, 16);
$encryptedToken = openssl_encrypt($sessionPayload, $AES_CIPHER, $encKey, 0, $encIv);

json_out(200, [
    'status'         => 'authorized',
    'code'           => 200,
    'license_tier'   => $tier,
    'license_token'  => $encryptedToken,
    'entitlements'   => [
        'app_access'           => true,
        'white_label_branding' => $isExtended,
        'custom_package_name'  => $isExtended,
        'ready_compiled_files' => $isExtended,
        'installation_support' => $isExtended,
    ],
    'branding'       => $isExtended ? json_decode($license['branding_json'] ?? '{}') : null,
    'upgrade_notice' => !$isExtended ? [
        'title'   => 'Regular License Active',
        'message' => 'Need your own branded APK, custom package name, and Windows EXE installer? Upgrade to Extended.',
        'url'     => 'https://zoomnearby.com/upgrade'
    ] : null,
]);
