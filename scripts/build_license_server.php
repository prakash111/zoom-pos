<?php

/**
 * Builds `license-manager-standalone.zip` — a self-contained PHP micro-app that
 * implements the contract in docs/LICENSE_SERVER_CONTRACT.md:
 *
 *   POST /api/v1/license/verify   (X-Server-Secret)
 *   POST /api/v1/license/issue    (X-Server-Secret, idempotent on payment.reference)
 *
 * plus a password-protected admin panel to issue / suspend / revoke keys and
 * review the payment ledger.
 *
 * Usage:   php scripts/build_license_server.php
 * Output:  storage/app/license-dist/license-manager-standalone.zip   (git-ignored)
 *
 * Deploy the ZIP's contents to a separate subdomain (e.g. license.example.com),
 * import database/schema.sql, edit config/config.php, then point the SaaS
 * LICENSE_SERVER_URL / LICENSE_SERVER_SECRET at it.
 */

$root = dirname(__DIR__);
$outDir = $root.'/storage/app/license-dist';
@mkdir($outDir, 0775, true);
$outFile = $outDir.'/license-manager-standalone.zip';

$files = [];

/* ------------------------------------------------------------------ README */

$files['README.md'] = <<<'MD'
# Central License Manager (standalone)

A dependency-free PHP 8+ micro-app that issues and verifies license keys for the
SaaS core and its add-on modules. Runs on its own domain — e.g.
`https://license.example.com` — completely separate from the SaaS.

## What it does

| Endpoint | Auth | Purpose |
|---|---|---|
| `POST /api/v1/license/verify` | `X-Server-Secret` header | Check a key for a product + domain. |
| `POST /api/v1/license/issue`  | `X-Server-Secret` header | Issue a key after a paid purchase (idempotent on `payment.reference`). |
| `/admin/`                     | Session login | Issue / suspend / revoke keys, reset domain binding, view payments. |

The wire contract is documented in the SaaS repo at
`docs/LICENSE_SERVER_CONTRACT.md`.

## Install

1. **Upload** the contents of this ZIP to the subdomain's document root.
2. **Create a database** (e.g. `license_manager`) and a DB user.
3. **Edit `config/config.php`** — set the DB credentials, a long random
   `SERVER_SECRET`, and `ADMIN_USER`.
4. **Set the admin password:**
   ```
   php bin/hash-password.php 'your-strong-password'
   ```
   Paste the output into `ADMIN_PASS_HASH` in `config/config.php`.
5. **Import the schema:**
   ```
   php bin/install.php
   ```
   (or import `database/schema.sql` with phpMyAdmin / the `mysql` client).
6. **Point the SaaS at it.** In the SaaS `.env` (or SuperAdmin → Settings →
   Licensing):
   ```
   LICENSE_DRIVER=custom
   LICENSE_SERVER_URL=https://license.example.com
   LICENSE_SERVER_SECRET=<the same SERVER_SECRET>
   ```

## Web server routing

The two API paths must map to the PHP files.

**Apache** — `.htaccess` in this package already does it (needs `mod_rewrite`).

**nginx** — add to the server block (adjust the PHP-FPM socket):

```nginx
location ~ ^/api/v1/license/(verify|issue)$ {
    rewrite ^/api/v1/license/verify$ /api/verify.php break;
    rewrite ^/api/v1/license/issue$  /api/issue.php  break;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}

location ~ ^/(config|lib|bin|database)/ { deny all; }
```

## Security notes

- Serve **only over HTTPS** — the shared secret and admin session ride on it.
- `config/`, `lib/`, `bin/`, `database/` ship with a deny-all `.htaccess`;
  replicate that with the nginx `location` above.
- Rotate `SERVER_SECRET` by updating it here **and** in the SaaS at the same
  time (an old secret stops verifying immediately).
- The admin panel has a login gate but no rate limiting — put it behind an IP
  allowlist or server-level auth if it is internet-facing.
MD;

/* ------------------------------------------------------------------ config */

$files['config/config.php'] = <<<'PHP'
<?php

/**
 * Central License Manager — configuration.
 * EDIT EVERY VALUE MARKED "CHANGE ME" BEFORE GOING LIVE.
 */

// --- Database -------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'license_manager';
const DB_USER = 'root';
const DB_PASS = '';

// --- Shared secret with the SaaS (sent as the X-Server-Secret header) ----
// CHANGE ME — must be identical to LICENSE_SERVER_SECRET in the SaaS.
const SERVER_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';

// --- Admin panel login -------------------------------------------------- CHANGE ME
const ADMIN_USER = 'admin';
// Generate with:  php bin/hash-password.php 'your-password'
const ADMIN_PASS_HASH = 'REPLACE_WITH_password_hash_OUTPUT';

// --- Behaviour ---------------------------------------------------------------
// Default lifetime for keys minted by /api/v1/license/issue.
// 0 = perpetual (CodeCanyon style); e.g. 365 for an annual subscription.
const DEFAULT_LICENSE_TTL_DAYS = 0;
PHP;

/* ------------------------------------------------------------------ helpers */

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
PHP;

$files['lib/bootstrap.php'] = <<<'PHP'
<?php

require __DIR__.'/../config/config.php';
require __DIR__.'/helpers.php';
PHP;

/* ------------------------------------------------------------------ api: verify */

$files['api/verify.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();

$in = read_json_body();
$key = trim($in['license_key'] ?? '');
$slug = strtolower(trim($in['product_slug'] ?? ''));
$domain = strtolower(trim($in['domain'] ?? ''));

if ($key === '' || $slug === '' || $domain === '') {
    json_out(422, ['status' => false, 'message' => 'license_key, product_slug and domain are required.']);
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ? AND product_slug = ? LIMIT 1');
$st->execute([$key, $slug]);
$lic = $st->fetch();

if (! $lic) {
    json_out(200, ['status' => false, 'expires_at' => null, 'message' => 'Unknown license key for this product.', 'plan' => null]);
}

$expiresAt = iso8601_from_date($lic['valid_until']);

if ($lic['status'] !== 'active') {
    json_out(200, ['status' => false, 'expires_at' => $expiresAt, 'message' => 'License is '.$lic['status'].'.', 'plan' => $lic['plan']]);
}

if (! empty($lic['valid_until']) && strtotime($lic['valid_until'].' 23:59:59 UTC') < time()) {
    $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$lic['id']]);
    json_out(200, ['status' => false, 'expires_at' => $expiresAt, 'message' => 'License expired on '.$lic['valid_until'].'.', 'plan' => $lic['plan']]);
}

if (empty($lic['bound_domain'])) {
    $pdo->prepare('UPDATE licenses SET bound_domain = ?, bound_ip = ? WHERE id = ?')->execute([$domain, remote_ip(), $lic['id']]);
} elseif (strtolower($lic['bound_domain']) !== $domain) {
    json_out(200, ['status' => false, 'expires_at' => $expiresAt, 'message' => 'License is bound to another domain ('.$lic['bound_domain'].').', 'plan' => $lic['plan']]);
}

$pdo->prepare('UPDATE licenses SET last_verified_at = UTC_TIMESTAMP(), last_verified_ip = ? WHERE id = ?')
    ->execute([remote_ip(), $lic['id']]);

json_out(200, [
    'status' => true,
    'expires_at' => $expiresAt,
    'message' => 'License verified for '.$domain.'.',
    'plan' => $lic['plan'],
]);
PHP;

/* ------------------------------------------------------------------ api: issue */

$files['api/issue.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['status' => false, 'message' => 'Method not allowed.']);
}

require_secret();

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

$ttl = defined('DEFAULT_LICENSE_TTL_DAYS') ? (int) DEFAULT_LICENSE_TTL_DAYS : 0;
$validUntil = $ttl > 0 ? gmdate('Y-m-d', time() + $ttl * 86400) : null;

try {
    $pdo->beginTransaction();

    do {
        $key = generate_license_key();
        $chk = $pdo->prepare('SELECT 1 FROM licenses WHERE license_key = ?');
        $chk->execute([$key]);
    } while ($chk->fetch());

    $pdo->prepare(
        'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, bound_ip, status, valid_until)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$key, $slug, trim($payment['payer_email'] ?? ''), $domain, remote_ip(), 'active', $validUntil]);

    $licenseId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO payments (reference, gateway, amount, currency, product_slug, license_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $ref,
        substr((string) ($payment['gateway'] ?? ''), 0, 40),
        (float) ($payment['amount'] ?? 0),
        strtoupper(substr((string) ($payment['currency'] ?? 'USD'), 0, 8)),
        $slug,
        $licenseId,
    ]);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Lost a race for the same payment.reference — return the row that won.
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
    json_out(200, ['status' => false, 'license_key' => null, 'expires_at' => null, 'message' => 'Could not issue license: '.$ex->getMessage(), 'plan' => null]);
}

json_out(200, [
    'status' => true,
    'license_key' => $key,
    'expires_at' => iso8601_from_date($validUntil),
    'message' => 'License issued.',
    'plan' => null,
]);
PHP;

/* ------------------------------------------------------------------ admin: guard */

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

/* ------------------------------------------------------------------ admin: login */

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

/* ------------------------------------------------------------------ admin: logout */

$files['admin/logout.php'] = <<<'PHP'
<?php

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
PHP;

/* ------------------------------------------------------------------ admin: index */

$files['admin/index.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $flash = 'Done.';

    if ($action === 'issue') {
        $key = trim($_POST['license_key'] ?? '') ?: generate_license_key();
        $pdo->prepare(
            'INSERT INTO licenses (license_key, product_slug, client_email, bound_domain, plan, valid_until)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $key,
            strtolower(trim($_POST['product_slug'] ?? '')),
            trim($_POST['client_email'] ?? ''),
            strtolower(trim($_POST['bound_domain'] ?? '')) ?: null,
            trim($_POST['plan'] ?? '') ?: null,
            trim($_POST['valid_until'] ?? '') ?: null,
        ]);
        $flash = 'Issued '.$key;
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

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$licenses = $pdo->query('SELECT * FROM licenses ORDER BY id DESC LIMIT 500')->fetchAll();
$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>License Manager</title><link rel="stylesheet" href="assets/app.css"></head>
<body>
<nav><strong>License Manager</strong>
    <a href="index.php">Licenses</a><a href="payments.php">Payments</a>
    <a href="logout.php" class="right">Sign out</a></nav>
<main>
<?php if (! empty($_GET['msg'])): ?><p class="ok"><?= e($_GET['msg']) ?></p><?php endif; ?>

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
                    $btns = $l['status'] === 'active'
                        ? ['suspend' => 'Suspend', 'revoke' => 'Revoke']
                        : ['activate' => 'Activate', 'revoke' => 'Revoke'];
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
        <?php if (! $licenses): ?><tr><td colspan="8" class="muted">No licenses yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
</main>
</body>
</html>
PHP;

/* ------------------------------------------------------------------ admin: payments */

$files['admin/payments.php'] = <<<'PHP'
<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';

$rows = db()->query(
    'SELECT p.*, l.license_key, l.client_email
       FROM payments p LEFT JOIN licenses l ON l.id = p.license_id
      ORDER BY p.id DESC LIMIT 500'
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>License Manager — Payments</title><link rel="stylesheet" href="assets/app.css"></head>
<body>
<nav><strong>License Manager</strong>
    <a href="index.php">Licenses</a><a href="payments.php">Payments</a>
    <a href="logout.php" class="right">Sign out</a></nav>
<main>
<section class="card">
    <h2>Payment ledger</h2>
    <table>
        <thead><tr><th>Reference</th><th>Gateway</th><th>Amount</th><th>Product</th><th>License</th><th>Client</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= e($r['reference']) ?></code></td>
                <td><?= e($r['gateway']) ?></td>
                <td><?= e(number_format((float) $r['amount'], 2)).' '.e($r['currency']) ?></td>
                <td><span class="tag"><?= e($r['product_slug']) ?></span></td>
                <td><code><?= e($r['license_key'] ?: '—') ?></code></td>
                <td><?= e($r['client_email'] ?: '—') ?></td>
                <td class="muted"><?= e($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="7" class="muted">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
</main>
</body>
</html>
PHP;

/* ------------------------------------------------------------------ admin: css */

$files['admin/assets/app.css'] = <<<'CSS'
* { box-sizing: border-box; }
body { margin: 0; font: 14px/1.5 system-ui, sans-serif; color: #1e293b; background: #f1f5f9; }
nav { display: flex; gap: 16px; align-items: center; padding: 12px 20px; background: #0f172a; color: #fff; }
nav a { color: #cbd5e1; text-decoration: none; }
nav a:hover { color: #fff; }
nav .right { margin-left: auto; }
main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
h1, h2 { margin-top: 0; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
label { display: flex; flex-direction: column; font-weight: 600; font-size: 12px; gap: 4px; }
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

/* ------------------------------------------------------------------ database */

$files['database/schema.sql'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_license` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the SaaS core + the standalone module slugs. `core` is what the SaaS
-- sends for its own installer license (product_slug = "core").
INSERT INTO `products` (`slug`, `name`, `price`, `currency`) VALUES
  ('core',             'Main SaaS Script',                  199.00, 'USD'),
  ('pharmacy',         'Pharmacy POS Module',                49.00, 'USD'),
  ('salon',            'Salon & Bookings Module',            49.00, 'USD'),
  ('repairtechnician', 'Repair & Service Workbench Module',  49.00, 'USD')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
SQL;

/* ------------------------------------------------------------------ bin */

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
    exit("CLI only.\n");
}

require __DIR__.'/../lib/bootstrap.php';

$sql = file_get_contents(__DIR__.'/../database/schema.sql');
db()->exec($sql);

echo "Schema imported into '".DB_NAME."'.\n";
PHP;

/* ------------------------------------------------------------------ web server */

$files['.htaccess'] = <<<'HT'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^api/v1/license/verify/?$ api/verify.php [L]
    RewriteRule ^api/v1/license/issue/?$  api/issue.php  [L]
</IfModule>
HT;

foreach (['config', 'lib', 'bin', 'database'] as $dir) {
    $files[$dir.'/.htaccess'] = "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
}

$files['.gitignore'] = <<<'GI'
/config/config.php
*.log
GI;

/* ------------------------------------------------------------------ build */

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
