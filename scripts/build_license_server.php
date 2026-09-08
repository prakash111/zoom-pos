<?php

/**
 * Builds `license-manager-standalone.zip` — a self-contained PHP 8+ micro-app,
 * the vendor's control centre for selling and validating the SaaS script and
 * its add-on modules. Runs on its own domain (e.g. license.example.com).
 *
 * Endpoints (all POST unless noted, header `X-Server-Secret`):
 *   /api/v1/license/verify    check a key for product + domain
 *   /api/v1/license/issue     issue a key after a paid purchase (idempotent)
 *   /api/v1/module/download   verify a key, then stream that module's ZIP
 *   /api/v1/catalog    (GET)  active products for the SaaS "Buy module" screen
 *   /buy.php           (GET)  public hosted checkout (Razorpay / Stripe)
 *
 * Admin panel (session login): Licenses, Products, Payments, Redeem (CodeCanyon),
 * Settings (validation mode, Envato token, gateway credentials).
 *
 * Usage:   php scripts/build_license_server.php
 * Output:  storage/app/license-dist/license-manager-standalone.zip   (git-ignored)
 */
$root = dirname(__DIR__);
$outDir = $root.'/storage/app/license-dist';
@mkdir($outDir, 0775, true);
$outFile = $outDir.'/license-manager-standalone.zip';

$files = [];

/* ==================================================================== README */

$files['README.md'] = <<<'MD'
# Central License Manager (standalone)

The vendor's control centre for selling and validating the SaaS script + its
add-on modules. Dependency-free PHP 8+. Runs on its own domain
(`https://license.example.com`), separate from any SaaS install.

## Pieces

| Path | Auth | Purpose |
|---|---|---|
| `POST /api/v1/license/verify`   | `X-Server-Secret` | Check a key for a product + domain. |
| `POST /api/v1/license/issue`    | `X-Server-Secret` | Issue a key after a paid purchase (idempotent on `payment.reference`). |
| `POST /api/v1/module/download`  | `X-Server-Secret` | Verify a key, then stream that product's package ZIP. **Module source files live only here.** |
| `GET  /api/v1/catalog`          | `X-Server-Secret` | Active products for the SaaS "Buy module" list. |
| `GET  /buy.php`                 | public | Hosted checkout — operator pays, a key is issued and pushed to their site. |
| `/admin/`                       | session login | Licenses, Products (+ package upload), Payments, Redeem CodeCanyon codes, Settings. |

## Module packages

Each module's source is a ZIP (`module.json` at its root, exactly as the SaaS
`ModulePackageService` expects). Upload it on **Products** → per-product
*Module package (.zip)*. It is stored under `storage/packages/<slug>.zip`,
**denied to the web**, and only ever sent through `POST /api/v1/module/download`
after the caller's license key verifies for that product + domain. The SaaS
extracts and installs it on the client server. `core` needs no package.

Wire contract: `docs/LICENSE_SERVER_CONTRACT.md` in the SaaS repo.

## Install

1. Upload the ZIP contents to the subdomain's document root.
2. Create a database + user.
3. Edit `config/config.php` — DB creds, a long random `SERVER_SECRET`, `ADMIN_USER`.
4. Admin password: `php bin/hash-password.php 'pw'` → paste into `ADMIN_PASS_HASH`.
5. Create the tables — `php bin/install.php`, **or** open `/setup.php` in a
   browser and click **Create tables** (then delete `setup.php`), **or** import
   `database/schema.sql` manually.
6. Sign in at `/admin/`, open **Settings**, choose the validation mode and enter
   your payment gateway credentials. Add your **Products** (core + modules) with
   prices.
7. In each SaaS install's `.env` — the server URL is hardcoded in the SaaS
   (`config/services.php`); only the shared secret is set:
   ```
   LICENSE_SERVER_SECRET=<the same SERVER_SECRET as this config/config.php>
   ```
   then `php artisan config:clear` on the SaaS.

## Validation modes (Settings)

- **native** — keys are issued and checked entirely here.
- **codecanyon** — the **Redeem** screen (and `issue`) verify an Envato purchase
  code via the Envato API, then mint a native key bound to the buyer's domain.
  Needs an Envato API personal token in Settings.

## Web server routing

The API paths must map to the PHP files.

**Apache** — the bundled `.htaccess` does it (needs `mod_rewrite`).

**nginx** — in the server block (adjust the PHP-FPM socket):

```nginx
location ~ ^/api/v1/license/(verify|issue)$ {
    rewrite ^/api/v1/license/verify$ /api/verify.php break;
    rewrite ^/api/v1/license/issue$  /api/issue.php  break;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
location = /api/v1/catalog { rewrite ^ /api/catalog.php break; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/api/catalog.php; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ~ ^/(config|lib|bin|database)/ { deny all; }
```

## Security notes

- HTTPS only — the shared secret, admin session and gateway keys ride on it.
- `config/`, `lib/`, `bin/`, `database/` ship with deny-all `.htaccess`; mirror
  that on nginx.
- Rotate `SERVER_SECRET` here and in every SaaS `.env` together.
- The admin panel has a login gate but no rate limiting — add an IP allowlist if
  it is internet-facing.
- **Delete `setup.php`** after setup.
MD;

/* ==================================================================== config */

$files['config/config.php'] = <<<'PHP'
<?php

/**
 * Central License Manager — bootstrap configuration.
 * Everything else (validation mode, Envato token, gateway keys, currency) is
 * edited from the admin panel → Settings and stored in the `settings` table.
 * EDIT EVERY VALUE MARKED "CHANGE ME" BEFORE GOING LIVE.
 */

// --- Database ---------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'license_manager';
const DB_USER = 'root';
const DB_PASS = '';

// --- Shared secret with every SaaS install -------------------------------- CHANGE ME
// Sent as the X-Server-Secret header; also signs the auto-activation callback.
// Must equal LICENSE_SERVER_SECRET in each SaaS .env.
const SERVER_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';

// --- Admin panel login -------------------------------------------------- CHANGE ME
const ADMIN_USER = 'admin';
// Generate with:  php bin/hash-password.php 'your-password'
const ADMIN_PASS_HASH = 'REPLACE_WITH_password_hash_OUTPUT';

// --- Behaviour -----------------------------------------------------------------
// Default lifetime (days) for issued keys. 0 = perpetual; e.g. 365 = annual.
const DEFAULT_LICENSE_TTL_DAYS = 0;
PHP;

/* =================================================================== helpers */

$files['lib/helpers.php'] = <<<'PHP'
<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    return $pdo;
}

function json_out(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function read_json_body(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
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

function remote_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
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

/* --- settings (DB-backed, editable from admin) --- */

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
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

function set_setting(string $key, ?string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$key, (string) $value]);
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

function package_path(string $slug): string
{
    return package_dir().'/'.preg_replace('/[^a-z0-9]+/i', '', strtolower($slug)).'.zip';
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

/* --- shared issuing --- */

function issue_license(PDO $pdo, string $slug, ?string $domain, ?string $email, ?string $plan = null, ?int $ttlDays = null): array
{
    $ttl = $ttlDays ?? (defined('DEFAULT_LICENSE_TTL_DAYS') ? (int) DEFAULT_LICENSE_TTL_DAYS : 0);
    $validUntil = $ttl > 0 ? gmdate('Y-m-d', time() + $ttl * 86400) : null;

    do {
        $key = generate_license_key();
        $chk = $pdo->prepare('SELECT 1 FROM licenses WHERE license_key = ?');
        $chk->execute([$key]);
    } while ($chk->fetch());

    $pdo->prepare(
        'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, bound_ip, plan, status, valid_until)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$key, $slug, (string) $email, $domain ?: null, remote_ip(), $plan, 'active', $validUntil]);

    return ['id' => (int) $pdo->lastInsertId(), 'license_key' => $key, 'valid_until' => $validUntil];
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
PHP;

$files['lib/bootstrap.php'] = <<<'PHP'
<?php

require __DIR__.'/../config/config.php';
require __DIR__.'/helpers.php';
PHP;

/* ================================================================ api: verify */

$files['api/verify.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();
require_schema_api();

$in = read_json_body();
$key = trim($in['license_key'] ?? '');
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));

if ($key === '' || $slug === '' || $domain === '') {
    json_out(422, ['status' => false, 'message' => 'license_key, product_slug and domain are required.']);
}

$r = verify_license(db(), $key, $slug, $domain);

json_out(200, [
    'status' => $r['ok'],
    'expires_at' => $r['expires_at'],
    'message' => $r['message'],
    'plan' => $r['plan'],
]);
PHP;

/* ============================================================== api: download */

$files['api/download.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();
require_schema_api();

$in = read_json_body();
$key = trim($in['license_key'] ?? '');
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));

if ($key === '' || $slug === '' || $domain === '') {
    json_out(422, ['status' => false, 'message' => 'license_key, product_slug and domain are required.']);
}

$r = verify_license(db(), $key, $slug, $domain);
if (! $r['ok']) {
    json_out(403, ['status' => false, 'message' => $r['message']]);
}

$zip = package_path($slug);
if (! is_file($zip)) {
    json_out(404, ['status' => false, 'message' => 'No package uploaded for "'.$slug.'" yet.']);
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$slug.'.zip"');
header('Content-Length: '.filesize($zip));
header('X-License-Expires-At: '.($r['expires_at'] ?? ''));
readfile($zip);
exit;
PHP;

/* ================================================================= api: issue */

$files['api/issue.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();
require_schema_api();

$in = read_json_body();
$payment = is_array($in['payment'] ?? null) ? $in['payment'] : [];
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));
$ref = trim($payment['reference'] ?? '');

if ($slug === '' || $domain === '' || $ref === '') {
    json_out(422, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'product_slug, domain and payment.reference are required.', 'plan' => null]);
}

$pdo = db();

$prod = $pdo->prepare('SELECT 1 FROM products WHERE slug = ? LIMIT 1');
$prod->execute([$slug]);
if (! $prod->fetch()) {
    json_out(200, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'Unknown product "'.$slug.'".', 'plan' => null]);
}

$seen = $pdo->prepare(
    'SELECT l.license_key, l.valid_until, l.plan
       FROM payments p JOIN licenses l ON l.id = p.license_id
      WHERE p.reference = ? LIMIT 1'
);
$seen->execute([$ref]);
if ($row = $seen->fetch()) {
    json_out(200, [
        'status' => true,
        'license_key' => $row['license_key'],
        'expires_at' => iso8601_from_date($row['valid_until']),
        'message' => 'License already issued for this payment.',
        'plan' => $row['plan'],
    ]);
}

try {
    $pdo->beginTransaction();
    $lic = issue_license($pdo, $slug, $domain, trim($payment['payer_email'] ?? ''));
    $pdo->prepare(
        'INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id, email, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ref,
        substr((string) ($payment['gateway'] ?? 'api'), 0, 40),
        (float) ($payment['amount'] ?? 0),
        strtoupper(substr((string) ($payment['currency'] ?? 'USD'), 0, 8)),
        $slug,
        $lic['id'],
        trim($payment['payer_email'] ?? ''),
        'paid',
    ]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $seen->execute([$ref]);
    if ($row = $seen->fetch()) {
        json_out(200, ['status' => true, 'license_key' => $row['license_key'], 'expires_at' => iso8601_from_date($row['valid_until']), 'message' => 'License already issued for this payment.', 'plan' => $row['plan']]);
    }
    json_out(200, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'Could not issue license: '.$ex->getMessage(), 'plan' => null]);
}

json_out(200, [
    'status' => true,
    'license_key' => $lic['license_key'],
    'expires_at' => iso8601_from_date($lic['valid_until']),
    'message' => 'License issued.',
    'plan' => null,
]);
PHP;

/* =============================================================== api: catalog */

$files['api/catalog.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

require_secret();
require_schema_api();

$rows = db()->query('SELECT slug, name, description, price, currency FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();

json_out(200, [
    'status' => true,
    'products' => array_map(fn ($r) => [
        'slug' => $r['slug'],
        'name' => $r['name'],
        'description' => $r['description'],
        'price' => (float) $r['price'],
        'currency' => $r['currency'],
    ], $rows),
]);
PHP;

/* =========================================================== admin: guard/layout */

$files['admin/_guard.php'] = <<<'PHP'
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
    session_start();
}

if (empty($_SESSION['lm_admin'])) {
    header('Location: login.php');
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    if (! hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}
PHP;

$files['admin/_layout.php'] = <<<'PHP'
<?php

function lm_header(string $active, string $title = 'License Manager'): void
{
    $tabs = ['index' => 'Licenses', 'products' => 'Products', 'payments' => 'Payments', 'redeem' => 'Redeem', 'settings' => 'Settings'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).'</title><link rel="stylesheet" href="assets/app.css"></head><body>';
    echo '<nav><strong>License Manager</strong>';
    foreach ($tabs as $file => $label) {
        $cls = $file === $active ? ' class="on"' : '';
        echo '<a href="'.$file.'.php"'.$cls.'>'.e($label).'</a>';
    }
    echo '<a href="logout.php" class="right">Sign out</a></nav><main>';
    if (! empty($_GET['msg'])) {
        echo '<p class="ok">'.e($_GET['msg']).'</p>';
    }
    if (! empty($_GET['err'])) {
        echo '<p class="err">'.e($_GET['err']).'</p>';
    }
}

function lm_footer(): void
{
    echo '</main></body></html>';
}
PHP;

/* ================================================================ admin: login */

$files['admin/login.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
session_start();

$needsSetup = ! defined('ADMIN_PASS_HASH') || ADMIN_PASS_HASH === '' || ADMIN_PASS_HASH === 'REPLACE_WITH_password_hash_OUTPUT';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $needsSetup) {
    $u = (string) ($_POST['username'] ?? '');
    $p = (string) ($_POST['password'] ?? '');
    if (hash_equals(ADMIN_USER, $u) && password_verify($p, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['lm_admin'] = $u;
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid credentials.';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>License Manager — Sign in</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth">
<form method="post" class="card">
    <h1>License Manager</h1>
    <?php if ($needsSetup): ?>
        <p class="warn">No admin password set. Run
            <code>php bin/hash-password.php 'your-password'</code> and paste the result into
            <code>ADMIN_PASS_HASH</code> in <code>config/config.php</code>.</p>
    <?php else: ?>
        <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
        <label>Username<input name="username" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit">Sign in</button>
    <?php endif; ?>
</form>
</body>
</html>
PHP;

$files['admin/logout.php'] = <<<'PHP'
<?php

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
PHP;

/* ================================================================ admin: index */

$files['admin/index.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $flash = 'Done.';

    if ($action === 'issue') {
        $slug = strtolower(trim($_POST['product_slug'] ?? ''));
        $lic = issue_license(
            $pdo,
            $slug,
            strtolower(trim($_POST['bound_domain'] ?? '')) ?: null,
            trim($_POST['client_email'] ?? ''),
            trim($_POST['plan'] ?? '') ?: null,
            trim($_POST['valid_until'] ?? '') !== '' ? null : null
        );
        if (trim($_POST['valid_until'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET valid_until = ? WHERE id = ?')->execute([$_POST['valid_until'], $lic['id']]);
        }
        if (trim($_POST['license_key'] ?? '') !== '') {
            $pdo->prepare('UPDATE licenses SET license_key = ? WHERE id = ?')->execute([trim($_POST['license_key']), $lic['id']]);
            $lic['license_key'] = trim($_POST['license_key']);
        }
        $flash = 'Issued '.$lic['license_key'];
    } elseif (in_array($action, ['suspend', 'activate', 'revoke', 'expire'], true) && $id) {
        $map = ['suspend' => 'suspended', 'activate' => 'active', 'revoke' => 'revoked', 'expire' => 'expired'];
        $pdo->prepare('UPDATE licenses SET status = ? WHERE id = ?')->execute([$map[$action], $id]);
        $flash = 'License '.$map[$action].'.';
    } elseif ($action === 'reset_domain' && $id) {
        $pdo->prepare('UPDATE licenses SET bound_domain = NULL, bound_ip = NULL WHERE id = ?')->execute([$id]);
        $flash = 'Domain binding cleared.';
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM licenses WHERE id = ?')->execute([$id]);
        $flash = 'License deleted.';
    }

    header('Location: index.php?msg='.urlencode($flash));
    exit;
}

$fProduct = strtolower(trim($_GET['product'] ?? ''));
$fStatus = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

$where = [];
$args = [];
if ($fProduct !== '') { $where[] = 'product_slug = ?'; $args[] = $fProduct; }
if (in_array($fStatus, ['active', 'suspended', 'revoked', 'expired'], true)) { $where[] = 'status = ?'; $args[] = $fStatus; }
if ($q !== '') {
    $where[] = '(license_key LIKE ? OR client_email LIKE ? OR bound_domain LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT * FROM licenses'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY id DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($args);
$licenses = $st->fetchAll();

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$token = csrf_token();

lm_header('index');
?>
<section class="card">
    <h2>Issue a license</h2>
    <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="issue">
        <label>Product
            <select name="product_slug" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Client email<input type="email" name="client_email" required></label>
        <label>Bind domain (optional)<input name="bound_domain" placeholder="acme.example.com"></label>
        <label>Valid until (optional)<input type="date" name="valid_until"></label>
        <label>Plan (optional)<input name="plan" placeholder="regular"></label>
        <label>Key (optional)<input name="license_key" placeholder="auto-generated"></label>
        <button type="submit">Issue license</button>
    </form>
</section>

<section class="card">
    <h2>Licenses</h2>
    <form method="get" class="grid" style="margin-bottom:14px">
        <label>Product
            <select name="product"><option value="">All</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= $fProduct === $p['slug'] ? 'selected' : '' ?>><?= e($p['slug']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status"><option value="">Any</option>
                <?php foreach (['active', 'suspended', 'revoked', 'expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Search key / email / domain<input name="q" value="<?= e($q) ?>"></label>
        <button type="submit">Filter</button>
    </form>
    <table>
        <thead><tr><th>Key</th><th>Product</th><th>Client</th><th>Domain</th><th>Status</th><th>Valid until</th><th>Last seen</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($licenses as $l): ?>
            <tr>
                <td><code><?= e($l['license_key']) ?></code></td>
                <td><span class="tag"><?= e($l['product_slug']) ?></span></td>
                <td><?= e($l['client_email']) ?></td>
                <td><?= $l['bound_domain'] ? e($l['bound_domain']) : '<span class="muted">unbound</span>' ?></td>
                <td><span class="tag <?= $l['status'] === 'active' ? 'green' : 'red' ?>"><?= e($l['status']) ?></span></td>
                <td><?= e($l['valid_until'] ?: '—') ?></td>
                <td class="muted"><?= e($l['last_verified_at'] ?: '—') ?></td>
                <td class="acts">
                    <?php
                    $btns = $l['status'] === 'active' ? ['suspend' => 'Suspend', 'revoke' => 'Revoke'] : ['activate' => 'Activate', 'revoke' => 'Revoke'];
                    foreach ($btns as $a => $label): ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <button type="submit"><?= $label ?></button></form>
                    <?php endforeach; ?>
                    <form method="post"><input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="reset_domain"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <button type="submit">Reset domain</button></form>
                    <form method="post" onsubmit="return confirm('Delete this license permanently?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <button type="submit" class="danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $licenses): ?><tr><td colspan="8" class="muted">No licenses match.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php lm_footer();
PHP;

/* ============================================================= admin: products */

$files['admin/products.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $msg = 'Saved.';
    if ($action === 'save') {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', trim($_POST['slug'] ?? '')));
        if ($slug !== '') {
            $pdo->prepare(
                'INSERT INTO products (slug, name, description, price, currency, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                   price = VALUES(price), currency = VALUES(currency), is_active = VALUES(is_active)'
            )->execute([
                $slug,
                trim($_POST['name'] ?? $slug),
                trim($_POST['description'] ?? '') ?: null,
                (float) ($_POST['price'] ?? 0),
                strtoupper(substr(trim($_POST['currency'] ?? 'USD'), 0, 3)),
                isset($_POST['is_active']) ? 1 : 0,
            ]);

            // Module package upload — the ZIP the client server downloads once
            // its license key checks out. `core` needs no package.
            if ($slug !== 'core' && ! empty($_FILES['package']['tmp_name']) && is_uploaded_file($_FILES['package']['tmp_name'])) {
                if (strtolower(pathinfo($_FILES['package']['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    $msg = 'Product saved, but the package must be a .zip.';
                } else {
                    @mkdir(package_dir(), 0770, true);
                    move_uploaded_file($_FILES['package']['tmp_name'], package_path($slug));
                    $pdo->prepare('UPDATE products SET package_uploaded_at = NOW() WHERE slug = ?')->execute([$slug]);
                    $msg = 'Product + package saved.';
                }
            }
        }
    } elseif ($action === 'delete' && ! empty($_POST['slug'])) {
        $pdo->prepare('DELETE FROM products WHERE slug = ?')->execute([$_POST['slug']]);
        @unlink(package_path($_POST['slug']));
    }
    header('Location: products.php?msg='.urlencode($msg));
    exit;
}

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$token = csrf_token();
lm_header('products');
?>
<section class="card">
    <h2>Add / edit product</h2>
    <form method="post" class="grid" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <label>Slug (immutable id)<input name="slug" placeholder="pharmacy" required></label>
        <label>Name<input name="name" placeholder="Pharmacy POS Module" required></label>
        <label>Price<input type="number" name="price" step="0.01" min="0" value="0"></label>
        <label>Currency<input name="currency" value="USD" maxlength="3"></label>
        <label style="grid-column:1/-1">Description<input name="description" placeholder="Batches, expiry, prescriptions"></label>
        <label>Module package (.zip)<input type="file" name="package" accept=".zip"></label>
        <label class="row"><input type="checkbox" name="is_active" checked> Active (listed for sale)</label>
        <button type="submit">Save product</button>
    </form>
    <p class="muted">Use slug <code>core</code> for the main script (no package). Module slugs must match the SaaS module keys (e.g. <code>pharmacy</code>, <code>salon</code>, <code>repairtechnician</code>). The <b>package ZIP is the module's source</b> — it is sent to a client server only after its license key verifies, then extracted and installed there. Re-uploading replaces it.</p>
</section>

<section class="card">
    <h2>Products</h2>
    <table>
        <thead><tr><th>Slug</th><th>Name</th><th>Price</th><th>Active</th><th>Package</th><th>Description</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><code><?= e($p['slug']) ?></code></td>
                <td><?= e($p['name']) ?></td>
                <td><?= e(number_format((float) $p['price'], 2)).' '.e($p['currency']) ?></td>
                <td><?= $p['is_active'] ? 'yes' : '<span class="muted">no</span>' ?></td>
                <td>
                    <?php if ($p['slug'] === 'core'): ?><span class="muted">n/a</span>
                    <?php elseif (is_file(package_path($p['slug']))): ?>
                        <span class="tag green">uploaded</span>
                        <span class="muted"><?= e(number_format(filesize(package_path($p['slug'])) / 1024, 0)) ?> KB</span>
                    <?php else: ?><span class="tag red">missing</span><?php endif; ?>
                </td>
                <td class="muted"><?= e($p['description']) ?></td>
                <td class="acts">
                    <form method="post" onsubmit="return confirm('Delete product <?= e($p['slug']) ?>?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                        <button class="danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php lm_footer();
PHP;

/* ============================================================= admin: settings */

$files['admin/settings.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$secretKeys = ['envato_token', 'razorpay_key_secret', 'stripe_secret_key'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_setting('validation_mode', in_array($_POST['validation_mode'] ?? '', ['native', 'codecanyon'], true) ? $_POST['validation_mode'] : 'native');
    set_setting('gateway', in_array($_POST['gateway'] ?? '', ['razorpay', 'stripe'], true) ? $_POST['gateway'] : 'razorpay');
    set_setting('currency', strtoupper(substr(trim($_POST['currency'] ?? 'USD'), 0, 3)));
    set_setting('envato_item_id', trim($_POST['envato_item_id'] ?? ''));
    set_setting('razorpay_key_id', trim($_POST['razorpay_key_id'] ?? ''));
    set_setting('stripe_publishable_key', trim($_POST['stripe_publishable_key'] ?? ''));
    foreach ($secretKeys as $k) {
        if (trim($_POST[$k] ?? '') !== '') {
            set_setting($k, trim($_POST[$k]));
        }
    }
    header('Location: settings.php?msg='.urlencode('Settings saved.'));
    exit;
}

$token = csrf_token();
$has = fn ($k) => setting($k, '') !== '' ? 'configured — leave blank to keep' : '';
lm_header('settings');
?>
<section class="card">
    <h2>Settings</h2>
    <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">

        <label>Validation mode
            <select name="validation_mode">
                <option value="native" <?= setting('validation_mode', 'native') === 'native' ? 'selected' : '' ?>>native (keys issued + checked here)</option>
                <option value="codecanyon" <?= setting('validation_mode') === 'codecanyon' ? 'selected' : '' ?>>codecanyon (verify Envato purchase codes)</option>
            </select>
        </label>
        <label>Default currency<input name="currency" value="<?= e(setting('currency', 'USD')) ?>" maxlength="3"></label>

        <label>Envato API token<input type="password" name="envato_token" placeholder="<?= e($has('envato_token') ?: 'personal token') ?>"></label>
        <label>Envato item id (optional)<input name="envato_item_id" value="<?= e(setting('envato_item_id', '')) ?>"></label>

        <label>Payment gateway
            <select name="gateway">
                <option value="razorpay" <?= setting('gateway', 'razorpay') === 'razorpay' ? 'selected' : '' ?>>Razorpay</option>
                <option value="stripe" <?= setting('gateway') === 'stripe' ? 'selected' : '' ?>>Stripe</option>
            </select>
        </label>
        <span></span>

        <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e(setting('razorpay_key_id', '')) ?>"></label>
        <label>Razorpay Key Secret<input type="password" name="razorpay_key_secret" placeholder="<?= e($has('razorpay_key_secret')) ?>"></label>

        <label>Stripe Publishable Key<input name="stripe_publishable_key" value="<?= e(setting('stripe_publishable_key', '')) ?>"></label>
        <label>Stripe Secret Key<input type="password" name="stripe_secret_key" placeholder="<?= e($has('stripe_secret_key')) ?>"></label>

        <button type="submit">Save settings</button>
    </form>
    <p class="muted">The hosted checkout at <code>/buy.php</code> uses the selected gateway. On success it issues a key and pushes it (signed with <code>SERVER_SECRET</code>) to <code>https://&lt;domain&gt;/api/license/activate</code> on the buyer's site.</p>
</section>
<?php lm_footer();
PHP;

/* =============================================================== admin: redeem */

$files['admin/redeem.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = trim($_POST['purchase_code'] ?? '');
    $slug = strtolower(trim($_POST['product_slug'] ?? ''));
    $email = trim($_POST['client_email'] ?? '');
    $domain = strtolower(trim($_POST['bound_domain'] ?? '')) ?: null;

    $v = envato_verify($code);
    if (! $v['ok']) {
        $result = ['ok' => false, 'message' => $v['message']];
    } else {
        $ttl = $v['supported_until'] ? max(1, (int) ceil((strtotime($v['supported_until']) - time()) / 86400)) : null;
        $lic = issue_license($pdo, $slug, $domain, $email ?: (string) $v['buyer'], 'codecanyon', $ttl);
        $pdo->prepare('INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id, email, status) VALUES (?, ?, 0, ?, ?, ?, ?, ?)')
            ->execute(['envato:'.substr($code, 0, 24), 'envato', setting('currency', 'USD'), $slug, $lic['id'], $email, 'redeemed']);
        $result = ['ok' => true, 'message' => 'Verified for buyer "'.$v['buyer'].'" ('.$v['item'].').', 'key' => $lic['license_key']];
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$token = csrf_token();
lm_header('redeem');
?>
<section class="card">
    <h2>Redeem a CodeCanyon purchase code</h2>
    <p class="muted">Verifies the code with the Envato API (token in Settings) and mints a native license key bound to the buyer's domain.</p>
    <?php if ($result): ?>
        <p class="<?= $result['ok'] ? 'ok' : 'err' ?>"><?= e($result['message']) ?></p>
        <?php if ($result['ok']): ?><p>New key: <code style="font-size:16px"><?= e($result['key']) ?></code></p><?php endif; ?>
    <?php endif; ?>
    <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>Purchase code<input name="purchase_code" required placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
        <label>Product
            <select name="product_slug" required>
                <?php foreach ($products as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Client email (optional)<input type="email" name="client_email"></label>
        <label>Bind domain (optional)<input name="bound_domain" placeholder="acme.example.com"></label>
        <button type="submit">Verify &amp; issue key</button>
    </form>
</section>
<?php lm_footer();
PHP;

/* ============================================================= admin: payments */

$files['admin/payments.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$rows = db()->query(
    'SELECT p.*, l.license_key, l.bound_domain
       FROM payments p LEFT JOIN licenses l ON l.id = p.license_id
      ORDER BY p.id DESC LIMIT 500'
)->fetchAll();

lm_header('payments');
?>
<section class="card">
    <h2>Payment ledger</h2>
    <table>
        <thead><tr><th>Reference</th><th>Gateway</th><th>Amount</th><th>Product</th><th>Domain</th><th>Email</th><th>Key</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= e($r['reference']) ?></code></td>
                <td><?= e($r['gateway']) ?></td>
                <td><?= e(number_format((float) $r['amount'], 2)).' '.e($r['currency']) ?></td>
                <td><span class="tag"><?= e($r['product_slug']) ?></span></td>
                <td><?= e($r['bound_domain'] ?: '—') ?></td>
                <td><?= e($r['email'] ?: '—') ?></td>
                <td><code><?= e($r['license_key'] ?: '—') ?></code></td>
                <td><span class="tag"><?= e($r['status']) ?></span></td>
                <td class="muted"><?= e($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="9" class="muted">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php lm_footer();
PHP;

/* ============================================================ public: checkout */

$files['buy.php'] = <<<'PHP'
<?php

/**
 * Public hosted checkout. The SaaS "Buy module" button links here:
 *   /buy.php?product=<slug>&domain=<buyer-site-host>&email=<optional>&return=<url>
 * On success a license key is issued, bound to <domain>, pushed to the buyer's
 * site (signed), and shown on a receipt.
 */

require __DIR__.'/lib/bootstrap.php';

if (! schema_ready()) {
    http_response_code(503);
    exit('License store is not set up yet.');
}

$pdo = db();
$gateway = setting('gateway', 'razorpay');

$slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', $_GET['product'] ?? $_POST['product'] ?? ''));
$domain = strtolower(preg_replace('#^https?://#', '', trim($_GET['domain'] ?? $_POST['domain'] ?? '')));
$domain = preg_replace('#[/?].*$#', '', $domain);
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$return = $_GET['return'] ?? $_POST['return'] ?? '';
$return = preg_match('#^https?://#', $return) ? $return : '';

$product = null;
if ($slug !== '') {
    $st = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
    $st->execute([$slug]);
    $product = $st->fetch();
}

function page(string $title, string $html): void
{
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).'</title>';
    echo '<style>body{font:15px/1.6 system-ui,sans-serif;background:#f1f5f9;margin:0}'
        .'.box{max-width:460px;margin:8vh auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px}'
        .'h1{font-size:20px;margin:0 0 4px}.muted{color:#64748b;font-size:13px}'
        .'.key{font:16px/1.4 ui-monospace,monospace;background:#f1f5f9;padding:10px;border-radius:8px;word-break:break-all;margin:12px 0}'
        .'button,.btn{display:inline-block;padding:11px 18px;border:0;border-radius:9px;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer;text-decoration:none}'
        .'input{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;margin:6px 0 12px}'
        .'.err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px}'
        .'.ok{background:#dcfce7;color:#166534;padding:10px;border-radius:8px}</style>';
    echo '<div class="box">'.$html.'</div>';
    exit;
}

if (! $product || $domain === '') {
    page('Unavailable', '<h1>Checkout unavailable</h1><p class="err">Unknown product or missing site domain.</p>');
}

$price = (float) $product['price'];
$currency = strtoupper($product['currency'] ?: setting('currency', 'USD'));

function finish(PDO $pdo, string $gateway, string $reference, float $amount, string $currency, array $product, string $domain, string $email, string $return): void
{
    // Idempotency on the gateway reference.
    $seen = $pdo->prepare('SELECT l.license_key, l.valid_until, l.plan FROM payments p JOIN licenses l ON l.id = p.license_id WHERE p.reference = ? LIMIT 1');
    $seen->execute([$reference]);
    $row = $seen->fetch();

    if (! $row) {
        $pdo->beginTransaction();
        $lic = issue_license($pdo, $product['slug'], $domain, $email);
        $pdo->prepare('INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$reference, $gateway, $amount, $currency, $product['slug'], $lic['id'], $email, 'paid']);
        $pdo->commit();
        $row = ['license_key' => $lic['license_key'], 'valid_until' => $lic['valid_until'], 'plan' => null];
    }

    $pushed = notify_saas_activation($domain, $product['slug'], $row['license_key'], $row['valid_until'], $row['plan']);

    $back = $return !== '' ? '<p><a class="btn" href="'.e($return).'">Return to your site</a></p>' : '';
    page('Payment complete',
        '<h1>Payment complete</h1>'
        .'<p class="ok">'.($pushed ? 'Your '.e($product['name']).' license was activated on '.e($domain).' automatically.' : 'License issued. Paste the key below into SuperAdmin &rarr; Modules on '.e($domain).'.').'</p>'
        .'<p class="muted">License key</p><div class="key">'.e($row['license_key']).'</div>'
        .$back
    );
}

/* ---- Stripe: redirect to a Checkout Session, verify on return ---- */
if ($gateway === 'stripe') {
    $sk = (string) setting('stripe_secret_key', '');
    if ($sk === '') {
        page('Unavailable', '<h1>Checkout unavailable</h1><p class="err">Stripe is not configured.</p>');
    }

    if (! empty($_GET['stripe_session'])) {
        $r = http_request('GET', 'https://api.stripe.com/v1/checkout/sessions/'.urlencode($_GET['stripe_session']), null, ['Authorization: Bearer '.$sk]);
        if ($r['code'] === 200 && ($r['json']['payment_status'] ?? '') === 'paid') {
            $s = $r['json'];
            finish($pdo, 'stripe', $s['id'], ($s['amount_total'] ?? 0) / 100, strtoupper($s['currency'] ?? $currency), $product, $domain, (string) ($s['customer_details']['email'] ?? $email), $return);
        }
        page('Not paid', '<h1>Payment not completed</h1><p class="err">Stripe did not confirm this payment.</p>');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $base = (! empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?');
        $qs = http_build_query(['product' => $slug, 'domain' => $domain, 'email' => $email, 'return' => $return]);
        $r = http_request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $base.'?'.$qs.'&stripe_session={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base.'?'.$qs,
            'customer_email' => $email ?: null,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => (int) round($price * 100),
            'line_items[0][price_data][product_data][name]' => $product['name'],
            'metadata[product]' => $slug,
            'metadata[domain]' => $domain,
        ], ['Authorization: Bearer '.$sk]);
        if ($r['code'] === 200 && ! empty($r['json']['url'])) {
            header('Location: '.$r['json']['url']);
            exit;
        }
        page('Error', '<h1>Could not start checkout</h1><p class="err">'.e($r['json']['error']['message'] ?? 'Stripe error.').'</p>');
    }

    page('Buy '.$product['name'],
        '<h1>'.e($product['name']).'</h1><p class="muted">For '.e($domain).' — '.e($currency).' '.number_format($price, 2).'</p>'
        .'<form method="post"><input type="hidden" name="product" value="'.e($slug).'"><input type="hidden" name="domain" value="'.e($domain).'">'
        .'<input type="hidden" name="return" value="'.e($return).'">'
        .'<label>Email<input type="email" name="email" value="'.e($email).'" required></label>'
        .'<button type="submit">Pay with card</button></form>'
    );
}

/* ---- Razorpay: create an order, verify the signature on callback ---- */
$keyId = (string) setting('razorpay_key_id', '');
$keySecret = (string) setting('razorpay_key_secret', '');
if ($keyId === '' || $keySecret === '') {
    page('Unavailable', '<h1>Checkout unavailable</h1><p class="err">Razorpay is not configured.</p>');
}

if (($_POST['rzp_verify'] ?? '') === '1') {
    $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $orderId = trim($_POST['razorpay_order_id'] ?? '');
    $sig = trim($_POST['razorpay_signature'] ?? '');
    if (hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $keySecret), $sig)) {
        finish($pdo, 'razorpay', $paymentId, $price, $currency, $product, $domain, $email, $return);
    }
    page('Verification failed', '<h1>Payment verification failed</h1><p class="err">The Razorpay signature did not match. If money was taken, contact support with id '.e($paymentId).'.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = http_request('POST', 'https://api.razorpay.com/v1/orders', [
        'amount' => (int) round($price * 100),
        'currency' => $currency,
        'receipt' => 'mod_'.substr(md5($slug.$domain.microtime()), 0, 16),
        'notes[product]' => $slug,
        'notes[domain]' => $domain,
    ], ['Authorization: Basic '.base64_encode($keyId.':'.$keySecret)]);

    if ($r['code'] < 200 || $r['code'] >= 300 || empty($r['json']['id'])) {
        page('Error', '<h1>Could not start checkout</h1><p class="err">'.e($r['json']['error']['description'] ?? 'Razorpay error.').'</p>');
    }
    $orderId = $r['json']['id'];
    $selfBase = strtok($_SERVER['REQUEST_URI'], '?');
    ?>
    <!doctype html><meta charset="utf-8"><title>Redirecting to payment…</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <body style="font:15px system-ui,sans-serif;text-align:center;padding:20vh">Opening secure payment…
    <form id="f" method="post" action="<?= e($selfBase) ?>">
        <input type="hidden" name="rzp_verify" value="1">
        <input type="hidden" name="product" value="<?= e($slug) ?>">
        <input type="hidden" name="domain" value="<?= e($domain) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_signature">
    </form>
    <script>
    var f = document.getElementById('f');
    new Razorpay({
        key: <?= json_encode($keyId) ?>,
        order_id: <?= json_encode($orderId) ?>,
        amount: <?= (int) round($price * 100) ?>,
        currency: <?= json_encode($currency) ?>,
        name: <?= json_encode($product['name']) ?>,
        description: <?= json_encode('License for '.$domain) ?>,
        prefill: { email: <?= json_encode($email) ?> },
        handler: function (res) {
            f.razorpay_payment_id.value = res.razorpay_payment_id;
            f.razorpay_order_id.value = res.razorpay_order_id;
            f.razorpay_signature.value = res.razorpay_signature;
            f.submit();
        },
        modal: { ondismiss: function () { document.body.innerHTML = 'Payment cancelled. You can close this tab.'; } }
    }).open();
    </script>
    </body>
    <?php
    exit;
}

page('Buy '.$product['name'],
    '<h1>'.e($product['name']).'</h1><p class="muted">For '.e($domain).' — '.e($currency).' '.number_format($price, 2).'</p>'
    .($product['description'] ? '<p class="muted">'.e($product['description']).'</p>' : '')
    .'<form method="post"><input type="hidden" name="product" value="'.e($slug).'"><input type="hidden" name="domain" value="'.e($domain).'">'
    .'<input type="hidden" name="return" value="'.e($return).'">'
    .'<label>Email<input type="email" name="email" value="'.e($email).'" required></label>'
    .'<button type="submit">Pay '.e($currency).' '.number_format($price, 2).'</button></form>'
);
PHP;

/* ================================================================= admin: css */

$files['admin/assets/app.css'] = <<<'CSS'
* { box-sizing: border-box; }
body { margin: 0; font: 14px/1.5 system-ui, sans-serif; color: #1e293b; background: #f1f5f9; }
nav { display: flex; gap: 14px; align-items: center; padding: 12px 20px; background: #0f172a; color: #fff; flex-wrap: wrap; }
nav a { color: #cbd5e1; text-decoration: none; }
nav a:hover, nav a.on { color: #fff; font-weight: 700; }
nav .right { margin-left: auto; }
main { max-width: 1120px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
h1, h2 { margin-top: 0; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; align-items: end; }
label { display: flex; flex-direction: column; font-weight: 600; font-size: 12px; gap: 4px; }
label.row { flex-direction: row; align-items: center; gap: 8px; }
input, select { padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
button { padding: 8px 12px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font-weight: 700; cursor: pointer; }
button:hover { background: #4338ca; }
button.danger { background: #dc2626; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; }
.tag { display: inline-block; padding: 1px 8px; border-radius: 999px; background: #e2e8f0; font-size: 11px; font-weight: 700; }
.tag.green { background: #dcfce7; color: #166534; }
.tag.red { background: #fee2e2; color: #991b1b; }
.muted { color: #94a3b8; }
.acts { display: flex; flex-wrap: wrap; gap: 4px; }
.acts form { margin: 0; }
.acts button { padding: 4px 8px; font-size: 11px; background: #64748b; }
.acts button.danger { background: #dc2626; }
.ok { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 8px; }
.err { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px; }
.warn { background: #fef9c3; color: #854d0e; padding: 10px 14px; border-radius: 8px; }
body.auth { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
body.auth .card { width: 320px; }
body.auth label { margin-bottom: 12px; }
body.auth button { width: 100%; }
CSS;

/* ================================================================== database */

$files['database/schema.sql'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) PRIMARY KEY,
  `v` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `package_uploaded_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `licenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_key` VARCHAR(64) NOT NULL UNIQUE,
  `product_slug` VARCHAR(64) NOT NULL,
  `client_email` VARCHAR(191) NOT NULL DEFAULT '',
  `bound_domain` VARCHAR(191) NULL,
  `bound_ip` VARCHAR(64) NULL,
  `plan` VARCHAR(64) NULL,
  `status` ENUM('active','suspended','revoked','expired') NOT NULL DEFAULT 'active',
  `valid_until` DATE NULL,
  `last_verified_at` TIMESTAMP NULL,
  `last_verified_ip` VARCHAR(64) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_lookup` (`license_key`, `product_slug`),
  INDEX `idx_product` (`product_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(191) NOT NULL UNIQUE,
  `gateway` VARCHAR(40) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `product_slug` VARCHAR(64) NOT NULL,
  `license_id` INT NULL,
  `email` VARCHAR(191) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_license` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade path for installs created before packages existed. A "duplicate
-- column" error here on a fresh DB is expected and ignored by the runners.
ALTER TABLE `products` ADD COLUMN `package_uploaded_at` DATETIME NULL;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('validation_mode', 'native'),
  ('gateway', 'razorpay'),
  ('currency', 'USD')
ON DUPLICATE KEY UPDATE `k` = `k`;

INSERT INTO `products` (`slug`, `name`, `description`, `price`, `currency`) VALUES
  ('core',             'Main SaaS Script',                  'The full multi-tenant POS SaaS platform.', 199.00, 'USD'),
  ('pharmacy',         'Pharmacy POS Module',               'Drug batches, expiry tracking, prescriptions.', 49.00, 'USD'),
  ('salon',            'Salon & Bookings Module',           'Services, stylists, appointment lifecycle.', 49.00, 'USD'),
  ('repairtechnician', 'Repair & Service Workbench Module', 'Device intake tickets, checklist, parts & labour.', 49.00, 'USD')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
SQL;

/* ======================================================================= bin */

$files['bin/hash-password.php'] = <<<'PHP'
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDERR, "Usage: php bin/hash-password.php 'your-password'\n");
    exit(1);
}

echo password_hash($pw, PASSWORD_DEFAULT), "\n";
PHP;

$files['bin/install.php'] = <<<'PHP'
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only. Use setup.php from a browser if you have no shell access.\n");
}

require __DIR__.'/../lib/bootstrap.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to database '".DB_NAME."': ".$e->getMessage()."\n");
    fwrite(STDERR, "Create the database and check config/config.php, then re-run.\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__.'/../database/schema.sql')))) as $stmt) {
    try {
        $pdo->exec($stmt);
    } catch (Throwable $e) {
        // Idempotent upgrade statements (e.g. "duplicate column") are expected.
        if (! preg_match('/duplicate|exists/i', $e->getMessage())) {
            throw $e;
        }
    }
}

@mkdir(__DIR__.'/../storage/packages', 0770, true);

echo "Schema imported into '".DB_NAME."'. Tables: ".implode(', ', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN))."\n";
PHP;

$files['setup.php'] = <<<'PHP'
<?php

/**
 * One-time web installer for hosts without shell access. Creates the schema,
 * then self-disables. DELETE THIS FILE once setup is done.
 */

require __DIR__.'/lib/bootstrap.php';

$placeholder = ! defined('SERVER_SECRET') || SERVER_SECRET === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';
$done = false;
$error = '';

try {
    $connected = (bool) db();
} catch (Throwable $ex) {
    $connected = false;
    $error = 'Cannot connect to database "'.(defined('DB_NAME') ? DB_NAME : '?').'": '.$ex->getMessage();
}

if ($connected && schema_ready()) {
    $done = true;
}

if (! $done && ! $placeholder && $connected && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__.'/database/schema.sql')))) as $stmt) {
            try {
                db()->exec($stmt);
            } catch (Throwable $e) {
                if (! preg_match('/duplicate|exists/i', $e->getMessage())) {
                    throw $e;
                }
            }
        }
        @mkdir(__DIR__.'/storage/packages', 0770, true);
        $done = true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!doctype html><meta charset="utf-8"><title>License Manager — Setup</title>
<div style="font:15px/1.6 system-ui,sans-serif;max-width:620px;margin:10vh auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px">
<h1 style="margin:0 0 12px">License Manager setup</h1>
<?php if ($error): ?><p style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px"><?= e($error) ?></p><?php endif; ?>
<?php if ($done): ?>
    <p style="background:#dcfce7;color:#166534;padding:10px;border-radius:8px">Database is ready.</p>
    <p><strong>Now delete <code>setup.php</code></strong>, then open <a href="admin/">the admin panel</a> and fill in <em>Settings</em>.</p>
<?php elseif ($placeholder): ?>
    <p>Edit <code>config/config.php</code> first — set <code>DB_*</code>, a real
    <code>SERVER_SECRET</code>, and <code>ADMIN_PASS_HASH</code>
    (<code>php bin/hash-password.php 'pw'</code>). Reload when done.</p>
<?php elseif (! $connected): ?>
    <p>Fix the database settings in <code>config/config.php</code> and reload.</p>
<?php else: ?>
    <p>This creates the <code>settings</code>, <code>products</code>,
    <code>licenses</code> and <code>payments</code> tables in
    <code><?= e(DB_NAME) ?></code>.</p>
    <form method="post"><button style="padding:10px 16px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-weight:700;cursor:pointer">Create tables</button></form>
<?php endif; ?>
</div>
PHP;

/* ================================================================ web server */

$files['.htaccess'] = <<<'HT'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^api/v1/license/verify/?$   api/verify.php   [L]
    RewriteRule ^api/v1/license/issue/?$    api/issue.php    [L]
    RewriteRule ^api/v1/catalog/?$          api/catalog.php  [L]
    RewriteRule ^api/v1/module/download/?$  api/download.php [L]
</IfModule>
HT;

foreach (['config', 'lib', 'bin', 'database', 'storage', 'storage/packages'] as $dir) {
    $files[$dir.'/.htaccess'] = "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
}
// Keep the storage dir in the archive so it exists on extract.
$files['storage/packages/.gitkeep'] = '';

$files['.gitignore'] = <<<'GI'
/config/config.php
/setup.php
/storage/
*.log
GI;

/* =================================================================== build */

if (! class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ext-zip is required.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot open $outFile for writing.\n");
    exit(1);
}

ksort($files);
foreach ($files as $path => $contents) {
    $zip->addFromString($path, $contents);
}
$zip->close();

printf("Built %s (%d files, %s)\n", $outFile, count($files), number_format(filesize($outFile) / 1024, 1).' KB');
foreach (array_keys($files) as $path) {
    echo "  - $path\n";
}
