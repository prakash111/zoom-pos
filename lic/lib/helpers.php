<?php

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $name = defined('DB_NAME') ? DB_NAME : 'u356050643_license_mngr';
            $user = defined('DB_USER') ? DB_USER : 'u356050643_license_mngr';
            $pass = defined('DB_PASS') ? DB_PASS : '';

            $credentials = [
                ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass],
                ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'saas-db', 'user' => 'saas-db', 'pass' => 'J5hyUHkgwhVgS6nJm7yA'],
            ];

            $lastEx = null;
            foreach ($credentials as $cred) {
                try {
                    $pdo = new PDO(
                        "mysql:host={$cred['host']};port={$cred['port']};dbname={$cred['name']};charset=utf8mb4",
                        $cred['user'],
                        $cred['pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                    );
                    return $pdo;
                } catch (Throwable $e) {
                    $lastEx = $e;
                }
            }
            throw $lastEx;
        }

        return $pdo;
    }
}

if (!function_exists('json_out')) {
    function json_out(int $code, array $payload): void
    {
        http_response_code($code);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-Server-Secret, X-Client-Platform, DNT, User-Agent, If-Modified-Since, Cache-Control, Range');
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('read_json_body')) {
    function read_json_body(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }
}

function client_secret(): string
{
    if (function_exists('getallheaders')) {
        $h = array_change_key_case(getallheaders(), CASE_LOWER);
        if (isset($h['x-server-secret'])) {
            return (string) $h['x-server-secret'];
        }
    }

    return (string) ($_SERVER['HTTP_X_SERVER_SECRET'] ?? '');
}

function require_secret(): void
{
    if (! defined('SERVER_SECRET') || SERVER_SECRET === '' || SERVER_SECRET === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
        json_out(500, ['status' => false, 'message' => 'License server is not configured (SERVER_SECRET).']);
    }
    if (! hash_equals(SERVER_SECRET, client_secret())) {
        json_out(401, ['status' => false, 'message' => 'Unauthorized.']);
    }
}

function generate_license_key(): string
{
    return implode('-', str_split(strtoupper(bin2hex(random_bytes(10))), 5)); // XXXXX-XXXXX-XXXXX-XXXXX
}

function iso8601_from_date(?string $date): ?string
{
    if (empty($date)) {
        return null;
    }
    $ts = strtotime($date.' 23:59:59 UTC');

    return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
}

if (!function_exists('remote_ip')) {
    function remote_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

if (!function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (empty($datetime)) {
            return 'recently';
        }
        $ts = strtotime($datetime);
        if (!$ts) {
            return 'recently';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60).'m ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600).'h ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400).'d ago';
        }

        return date('M d', $ts);
    }
}

function schema_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            db()->query('SELECT 1 FROM licenses LIMIT 1');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }

    return $ready;
}

function require_schema_api(): void
{
    if (! schema_ready()) {
        json_out(503, ['status' => false, 'message' => 'License server database is not initialised. Run: php bin/install.php']);
    }
}

function require_schema_web(): void
{
    if (schema_ready()) {
        ensure_app_builder_schema();
        return;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup needed</title>'
        .'<div style="font:15px/1.6 system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px">'
        .'<h1 style="margin:0 0 8px">Database not initialised</h1>'
        .'<p>The <code>'.e(defined('DB_NAME') ? DB_NAME : '').'</code> database has no tables yet.</p>'
        .'<p><strong>Run</strong> <code>php bin/install.php</code> or open <a href="../setup.php">setup.php</a>, then reload.</p>'
        .'</div>';
    exit;
}

if (!function_exists('ensure_app_builder_schema')) {
function ensure_app_builder_schema(?PDO $pdo = null, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) return;
    $done = true;
    try {
        $pdo = $pdo ?: db();
    } catch (Throwable $e) {
        return;
    }

    // 1. app_builds table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_builds` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `build_uid` VARCHAR(64) NOT NULL UNIQUE,
          `batch_id` VARCHAR(64) NULL,
          `license_key` VARCHAR(64) NOT NULL,
          `client_email` VARCHAR(191) NOT NULL,
          `platform` ENUM('android', 'web', 'windows', 'ios') NOT NULL,
          `source_type` ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github',
          `app_name` VARCHAR(191) NOT NULL DEFAULT 'Zoom Sales POS',
          `package_id` VARCHAR(191) NOT NULL DEFAULT 'com.zoomnearby.zoompos',
          `server_url` VARCHAR(255) NOT NULL DEFAULT 'https://saas.zoomnearby.com',
          `primary_color` VARCHAR(32) NOT NULL DEFAULT '#4F46E5',
          `custom_logo_path` VARCHAR(255) NULL,
          `branding_json` JSON NULL,
          `status` ENUM('queued', 'preparing', 'building', 'completed', 'failed', 'cancelled', 'expired') NOT NULL DEFAULT 'queued',
          `github_run_id` BIGINT NULL,
          `github_workflow_id` VARCHAR(128) NULL,
          `artifact_path` VARCHAR(255) NULL,
          `artifact_filename` VARCHAR(191) NULL,
          `artifact_size_bytes` BIGINT NULL,
          `error_message` TEXT NULL,
          `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
          `build_duration_seconds` INT NULL,
          `started_at` DATETIME NULL,
          `completed_at` DATETIME NULL,
          `expires_at` DATETIME NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_license` (`license_key`),
          INDEX `idx_batch_id` (`batch_id`),
          INDEX `idx_email` (`client_email`),
          INDEX `idx_status` (`status`),
          INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    // Ensure columns exist on app_builds
    $neededCols = [
        'batch_id' => 'VARCHAR(64) NULL AFTER `build_uid`',
        'branding_json' => 'JSON NULL AFTER `custom_logo_path`',
        'source_type' => "ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github'",
        'artifact_filename' => 'VARCHAR(191) NULL',
        'artifact_size_bytes' => 'BIGINT NULL',
        'error_message' => 'TEXT NULL',
        'build_duration_seconds' => 'INT NULL',
        'started_at' => 'DATETIME NULL',
        'completed_at' => 'DATETIME NULL',
        'expires_at' => 'DATETIME NULL',
    ];

    foreach ($neededCols as $col => $def) {
        try {
            $pdo->query("SELECT `{$col}` FROM `app_builds` LIMIT 0");
        } catch (Throwable $e) {
            try {
                $pdo->exec("ALTER TABLE `app_builds` ADD COLUMN `{$col}` {$def}");
            } catch (Throwable $e2) {
                if (str_contains($def, 'JSON')) {
                    try { $pdo->exec("ALTER TABLE `app_builds` ADD COLUMN `{$col}` TEXT NULL"); } catch (Throwable $e3) {}
                }
            }
        }
    }

    // 2. products.app_builder_limit
    try {
        $pdo->query('SELECT app_builder_limit FROM products LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `products` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 10 AFTER `price`');
        } catch (Throwable $e2) {
            try { $pdo->exec('ALTER TABLE `products` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 10'); } catch (Throwable $e3) {}
        }
    }

    // 3. bundles.app_builder_limit
    try {
        $pdo->query('SELECT app_builder_limit FROM bundles LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `bundles` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 20 AFTER `price`');
        } catch (Throwable $e2) {
            try { $pdo->exec('ALTER TABLE `bundles` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 20'); } catch (Throwable $e3) {}
        }
    }

    // 4. licenses.app_builder_monthly_limit
    try {
        $pdo->query('SELECT app_builder_monthly_limit FROM licenses LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `app_builder_monthly_limit` INT NULL DEFAULT NULL AFTER `plan`');
        } catch (Throwable $e2) {
            try { $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `app_builder_monthly_limit` INT NULL DEFAULT NULL'); } catch (Throwable $e3) {}
        }
    }

    // Ensure build limit is cleared on any non-core modules or extensions
    try {
        $pdo->exec("UPDATE `licenses` SET `app_builder_monthly_limit` = NULL WHERE `product_slug` NOT IN ('core', 'main', 'pos', 'zoom-pos') AND `app_builder_monthly_limit` IS NOT NULL");
    } catch (Throwable $e) {}

    // Ensure payment_reference exists on licenses
    try {
        $pdo->query('SELECT payment_reference FROM licenses LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `payment_reference` VARCHAR(191) NULL AFTER `plan`');
        } catch (Throwable $e2) {}
    }

    // 5. licenses.bundle_id
    try {
        $pdo->query('SELECT bundle_id FROM licenses LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `bundle_id` INT NULL DEFAULT NULL AFTER `product_slug`');
        } catch (Throwable $e2) {
            try { $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `bundle_id` INT NULL DEFAULT NULL'); } catch (Throwable $e3) {}
        }
    }

    // 6. Default Settings
    try {
        $pdo->exec("INSERT INTO `settings` (`k`, `v`) VALUES
          ('github_repo', 'prakash111/zoom-pos'),
          ('github_branch', 'feat/windows-offline-sync'),
          ('github_token', 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'),
          ('builder_default_monthly_limit', '10'),
          ('builder_plan_limits', '{\"trial\":2,\"free\":2,\"basic\":10,\"starter\":10,\"regular\":10,\"pro\":30,\"professional\":30,\"extended\":-1,\"enterprise\":-1,\"unlimited\":-1}')
        ON DUPLICATE KEY UPDATE `k` = `k`");
    } catch (Throwable $e) {}

    $done = true;
}
}

/* --- settings (DB-backed, editable from admin) --- */

if (!function_exists('lic_setting')) {
function lic_setting(string $key, $default = null, bool $reload = false)
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        try {
            foreach (db()->query('SELECT k, v FROM settings')->fetchAll() as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            // settings table not there yet
        }
    }

    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}
}

if (!function_exists('setting')) {
function setting(string $key, $default = null, bool $reload = false)
{
    return lic_setting($key, $default, $reload);
}
}

if (!function_exists('lic_set_setting')) {
function lic_set_setting(string $key, ?string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$key, (string) $value]);
    lic_setting('', null, true);
}
}

if (!function_exists('set_setting')) {
function set_setting(string $key, ?string $value): void
{
    lic_set_setting($key, $value);
}
}

/* --- HTTP + crypto --- */

function hmac_sign(string $body): string
{
    return hash_hmac('sha256', $body, SERVER_SECRET);
}

/**
 * Minimal cURL JSON/form client.
 *
 * @return array{code:int, body:string, json:mixed}
 */
function http_request(string $method, string $url, $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => $raw === false ? ('curl error: '.$err) : (string) $raw,
        'json' => $raw === false ? null : json_decode((string) $raw, true),
    ];
}

/**
 * Verify an Envato / CodeCanyon purchase code with the Envato API.
 *
 * @return array{ok:bool, message:string, buyer:?string, item:?string, supported_until:?string}
 */
function envato_verify(string $code): array
{
    $token = (string) setting('envato_token', '');
    if ($token === '') {
        return ['ok' => false, 'message' => 'No Envato API token set (Settings).', 'buyer' => null, 'item' => null, 'supported_until' => null];
    }

    $res = http_request('GET', 'https://api.envato.com/v3/market/author/sale?code='.urlencode(trim($code)), null, [
        'Authorization: Bearer '.$token,
        'User-Agent: LicenseManager',
    ]);

    if ($res['code'] === 200 && is_array($res['json'])) {
        return [
            'ok' => true,
            'message' => 'Purchase code verified with Envato.',
            'buyer' => $res['json']['buyer'] ?? null,
            'item' => $res['json']['item']['name'] ?? null,
            'supported_until' => $res['json']['supported_until'] ?? null,
        ];
    }

    $msg = is_array($res['json']) ? ($res['json']['description'] ?? $res['json']['error'] ?? '') : '';

    return [
        'ok' => false,
        'message' => $msg !== '' ? $msg : 'Envato rejected the purchase code (HTTP '.$res['code'].').',
        'buyer' => null, 'item' => null, 'supported_until' => null,
    ];
}

/* --- module packages (files kept ONLY on this server) --- */

function package_dir(): string
{
    return __DIR__.'/../storage/packages';
}

function package_slug(string $slug): string
{
    return preg_replace('/[^a-z0-9]+/i', '', strtolower($slug));
}

/**
 * Absolute path to a product's uploaded ZIP, or '' if none.
 * New uploads get a random filename (recorded in settings.pkg_file_<slug>) so
 * the ZIP is not guessable even if storage/ is web-served on a mis-configured
 * host; a legacy <slug>.zip is still honoured.
 */
function package_path(string $slug): string
{
    $slug = package_slug($slug);
    if ($slug === '') {
        return '';
    }

    $named = (string) setting('pkg_file_'.$slug, '');
    if ($named !== '' && basename($named) === $named && is_file(package_dir().'/'.$named)) {
        return package_dir().'/'.$named;
    }

    $legacy = package_dir().'/'.$slug.'.zip';

    return is_file($legacy) ? $legacy : '';
}

/* --- shared license check (used by verify.php and download.php) --- */

/**
 * @return array{ok:bool, message:string, expires_at:?string, plan:?string, license:?array}
 */
function verify_license(PDO $pdo, string $key, string $slug, string $domain, bool $bind = true): array
{
    $key = trim($key);
    $slug = strtolower(trim($slug));
    $domain = strtolower(trim($domain));

    $st = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ? AND product_slug = ? LIMIT 1');
    $st->execute([$key, $slug]);
    $lic = $st->fetch();

    if (! $lic) {
        return ['ok' => false, 'message' => 'Unknown license key for this product.', 'expires_at' => null, 'plan' => null, 'license' => null];
    }

    $expiresAt = iso8601_from_date($lic['valid_until']);

    if ($lic['status'] !== 'active') {
        return ['ok' => false, 'message' => 'License is '.$lic['status'].'.', 'expires_at' => $expiresAt, 'plan' => $lic['plan'], 'license' => $lic];
    }

    if (! empty($lic['valid_until']) && strtotime($lic['valid_until'].' 23:59:59 UTC') < time()) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$lic['id']]);

        return ['ok' => false, 'message' => 'License expired on '.$lic['valid_until'].'.', 'expires_at' => $expiresAt, 'plan' => $lic['plan'], 'license' => $lic];
    }

    if (empty($lic['bound_domain'])) {
        if ($bind) {
            $pdo->prepare('UPDATE licenses SET bound_domain = ?, bound_ip = ? WHERE id = ?')->execute([$domain, remote_ip(), $lic['id']]);
        }
    } elseif (strtolower($lic['bound_domain']) !== $domain) {
        return ['ok' => false, 'message' => 'License is bound to another domain ('.$lic['bound_domain'].').', 'expires_at' => $expiresAt, 'plan' => $lic['plan'], 'license' => $lic];
    }

    $pdo->prepare('UPDATE licenses SET last_verified_at = UTC_TIMESTAMP(), last_verified_ip = ? WHERE id = ?')
        ->execute([remote_ip(), $lic['id']]);

    return ['ok' => true, 'message' => 'License verified for '.$domain.'.', 'expires_at' => $expiresAt, 'plan' => $lic['plan'], 'license' => $lic];
}

/* --- topbar notifications (real recent activity, no fake data) --- */

/**
 * Merges the most recent licenses issued + payments received into one feed
 * for the topbar notification bell. Returns newest first.
 *
 * @return array<int, array{type:string, icon:string, title:string, subtitle:string, url:string, created_at:string}>
 */
function fetch_recent_notifications(int $limit = 8): array
{
    if (! schema_ready()) {
        return [];
    }

    $pdo = db();
    $items = [];

    try {
        foreach ($pdo->query('SELECT license_key, product_slug, client_email, status, created_at FROM licenses ORDER BY created_at DESC LIMIT '.(int) $limit)->fetchAll() as $l) {
            $items[] = [
                'type' => 'license',
                'icon' => '🔑',
                'title' => 'License issued: '.$l['license_key'],
                'subtitle' => ucfirst($l['product_slug']).($l['client_email'] !== '' ? ' · '.$l['client_email'] : ''),
                'url' => 'index.php?key='.urlencode($l['license_key']),
                'created_at' => $l['created_at'],
            ];
        }
    } catch (Throwable $e) {
    }

    try {
        foreach ($pdo->query('SELECT reference, product_slug, amount, currency, license_id, status, created_at FROM payments ORDER BY created_at DESC LIMIT '.(int) $limit)->fetchAll() as $p) {
            $url = 'payments.php';
            if (! empty($p['license_id'])) {
                $lk = $pdo->prepare('SELECT license_key FROM licenses WHERE id = ?');
                $lk->execute([$p['license_id']]);
                $key = $lk->fetchColumn();
                if ($key) {
                    $url = 'index.php?key='.urlencode($key);
                }
            }
            $items[] = [
                'type' => 'payment',
                'icon' => '💳',
                'title' => (in_array($p['status'], ['paid', 'redeemed'], true) ? 'Order paid: ' : 'Order registered: ').number_format((float) $p['amount'], 2).' '.$p['currency'],
                'subtitle' => ucfirst($p['product_slug']).' · '.$p['reference'],
                'url' => $url,
                'created_at' => $p['created_at'],
            ];
        }
    } catch (Throwable $e) {
    }

    usort($items, fn ($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

    return array_slice($items, 0, $limit);
}

/* --- shared issuing --- */

function issue_license(PDO $pdo, string $slug, ?string $domain = null, ?string $email = null, ?string $plan = null, ?int $ttlDays = null, ?int $appBuilderLimit = null): array
{
    $ttl = $ttlDays ?? (defined('DEFAULT_LICENSE_TTL_DAYS') ? (int) DEFAULT_LICENSE_TTL_DAYS : 0);
    $validUntil = $ttl > 0 ? gmdate('Y-m-d', time() + $ttl * 86400) : null;

    do {
        $key = generate_license_key();
        $chk = $pdo->prepare('SELECT 1 FROM licenses WHERE license_key = ?');
        $chk->execute([$key]);
    } while ($chk->fetch());

    $cleanDomain = $domain ? strtolower(trim($domain)) : null;
    $tier = $plan ? strtolower(trim($plan)) : 'regular';
    $isCore = in_array(strtolower(trim($slug)), ['core', 'main', 'pos', 'zoom-pos'], true);
    $builderLimit = $isCore ? $appBuilderLimit : null;
    if ($isCore && $builderLimit === null && ($tier === 'extended' || $plan === 'extended')) {
        $builderLimit = -1;
    }

    try {
        $pdo->prepare(
            'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, registered_domain, bound_ip, plan, license_type, status, valid_until, app_builder_monthly_limit)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$key, $slug, (string) $email, $cleanDomain, $cleanDomain, remote_ip(), $plan, $tier, 'active', $validUntil, $builderLimit]);
    } catch (Throwable $e) {
        try {
            $pdo->prepare(
                'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, bound_ip, plan, status, valid_until, app_builder_monthly_limit)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$key, $slug, (string) $email, $cleanDomain, remote_ip(), $plan, 'active', $validUntil, $builderLimit]);
        } catch (Throwable $e2) {
            $pdo->prepare(
                'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, bound_ip, plan, status, valid_until)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$key, $slug, (string) $email, $cleanDomain, remote_ip(), $plan, 'active', $validUntil]);
            if ($builderLimit !== null) {
                try {
                    $pdo->prepare('UPDATE licenses SET app_builder_monthly_limit = ? WHERE id = ?')->execute([$builderLimit, (int)$pdo->lastInsertId()]);
                } catch (Throwable $e3) {}
            }
        }
    }

    return ['id' => (int) $pdo->lastInsertId(), 'license_key' => $key, 'valid_until' => $validUntil, 'app_builder_monthly_limit' => $builderLimit];
}

/**
 * Best-effort signed push of a freshly issued key to the buyer's site so the
 * module (or core) self-activates. Never throws.
 */
function notify_saas_activation(string $domain, string $slug, string $key, ?string $validUntil, ?string $plan): bool
{
    $domain = preg_replace('#^https?://#', '', trim($domain));
    if ($domain === '') {
        return false;
    }

    $payload = json_encode([
        'product_slug' => $slug,
        'license_key' => $key,
        'domain' => $domain,
        'expires_at' => iso8601_from_date($validUntil),
        'plan' => $plan,
    ]);

    $res = http_request('POST', 'https://'.$domain.'/api/license/activate', $payload, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-License-Signature: '.hmac_sign($payload),
    ]);

    return $res['code'] >= 200 && $res['code'] < 300;
}