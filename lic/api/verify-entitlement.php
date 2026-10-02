<?php

// Handle CORS preflight & cross-origin API headers immediately
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-Server-Secret, X-Client-Platform, DNT, User-Agent, If-Modified-Since, Cache-Control, Range');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// 1. Anti-Replay: Prevent validation requests older than 10 minutes (allow tolerance for clock drift)
if (abs(time() - $timestamp) > 600) {
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

// 3. Extract Clean Domain / Host & Candidates
$host = parse_url($serverUrl, PHP_URL_HOST) ?? $serverUrl;
$host = strtolower(trim((string)$host));

// Handle root domain extraction (e.g. pos.zoomnearby.com -> zoomnearby.com)
$hostParts = explode('.', $host);
$rootDomain = count($hostParts) >= 2 ? implode('.', array_slice($hostParts, -2)) : $host;
$wildcardDomain = '*.' . $rootDomain;

// Candidate domains to match in database
$candidates = array_values(array_unique([
    $host,
    $rootDomain,
    $wildcardDomain,
    'saas.' . $rootDomain,
    'crm.' . $rootDomain,
    'pos.' . $rootDomain,
]));

// 4. Query Registered License Record
$pdo = db();
$license = null;

foreach ($candidates as $cand) {
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM licenses 
            WHERE (registered_domain = ? OR bound_domain = ? OR JSON_CONTAINS(allowed_domains, ?))
              AND (status = "active" OR status = "1")
            ORDER BY (CASE WHEN plan = "extended" OR license_type = "extended" THEN 1 ELSE 2 END) ASC
            LIMIT 1
        ');
        $stmt->execute([$cand, $cand, json_encode($cand)]);
        $res = $stmt->fetch();
        if ($res) {
            $license = $res;
            break;
        }
    } catch (Throwable $e) {
        try {
            $stmt2 = $pdo->prepare('
                SELECT * FROM licenses 
                WHERE (bound_domain = ?)
                  AND (status = "active" OR status = "1")
                ORDER BY (CASE WHEN plan = "extended" THEN 1 ELSE 2 END) ASC
                LIMIT 1
            ');
            $stmt2->execute([$cand]);
            $res = $stmt2->fetch();
            if ($res) {
                $license = $res;
                break;
            }
        } catch (Throwable $e2) {
            // Column fallback
        }
    }
}


$isActive = $license && (in_array($license['status'] ?? '', ['active', '1', 1], true) || !empty($license['is_active']));

if (!$license || !$isActive) {
    json_out(403, [
        'status'  => 'unregistered',
        'code'    => 403,
        'message' => 'Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.',
        'buy_url' => 'https://pos.zoomnearby.com/public/marketing'
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
