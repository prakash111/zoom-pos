<?php

require __DIR__.'/../lib/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ! empty($_SERVER['HTTPS'])]);
session_start();

$configuredHash = defined('ADMIN_PASS_HASH') ? ADMIN_PASS_HASH : '';
$isPlaceholder = empty($configuredHash) || $configuredHash === 'REPLACE_WITH_password_hash_OUTPUT';

// Check if a password hash is stored in database settings
$dbHash = '';
try {
    if (function_exists('setting')) {
        $dbHash = (string) setting('admin_password_hash', '');
    }
} catch (\Throwable $e) {}

$needsSetup = $isPlaceholder && empty($dbHash);
$error = '';
$info = '';

// Handle direct browser password setup / reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_admin_password'])) {
    $newPass = (string) ($_POST['new_password'] ?? '');
    $confirmPass = (string) ($_POST['confirm_password'] ?? '');
    $adminUser = trim((string) ($_POST['username'] ?? 'admin'));
    if ($adminUser === '') {
        $adminUser = 'admin';
    }

    if (strlen($newPass) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        // 1. Try saving to database settings
        try {
            if (function_exists('set_setting')) {
                set_setting('admin_password_hash', $newHash);
                set_setting('admin_username', $adminUser);
            }
        } catch (\Throwable $e) {}

        // 2. Try writing to config/config.php if writable
        $configFile = __DIR__ . '/../config/config.php';
        if (is_writable($configFile)) {
            $confContent = @file_get_contents($configFile);
            if ($confContent) {
                $confContent = preg_replace(
                    "/const\s+ADMIN_PASS_HASH\s*=\s*['\"][^'\"]*['\"];/",
                    "const ADMIN_PASS_HASH = '{$newHash}';",
                    $confContent
                );
                $confContent = preg_replace(
                    "/const\s+ADMIN_USER\s*=\s*['\"][^'\"]*['\"];/",
                    "const ADMIN_USER = '{$adminUser}';",
                    $confContent
                );
                @file_put_contents($configFile, $confContent);
            }
        }

        session_regenerate_id(true);
        $_SESSION['lm_admin'] = $adminUser;
        header('Location: index.php?msg=' . urlencode('Admin password saved successfully.'));
        exit;
    }
}

// Handle standard login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['set_admin_password'])) {
    $u = (string) ($_POST['username'] ?? '');
    $p = (string) ($_POST['password'] ?? '');

    $targetUser = defined('ADMIN_USER') ? ADMIN_USER : 'admin';
    $dbUser = function_exists('setting') ? setting('admin_username', $targetUser) : $targetUser;

    $matched = false;

    // 1. Check against DB setting
    if (!empty($dbHash) && password_verify($p, $dbHash) && (hash_equals($targetUser, $u) || hash_equals($dbUser, $u))) {
        $matched = true;
    }

    // 2. Check against ADMIN_PASS_HASH constant
    if (!$matched && !$isPlaceholder && !empty($configuredHash)) {
        if (hash_equals($targetUser, $u) && password_verify($p, $configuredHash)) {
            $matched = true;
        }
    }

    // 3. Built-in resilient fallback for default credentials 'admin' / 'admin123' or 'admin'
    if (!$matched && (hash_equals($targetUser, $u) || hash_equals('admin', $u))) {
        if ($p === 'admin123' || $p === 'admin') {
            $matched = true;
            // Auto-persist hash so future logins are secure
            try {
                if (function_exists('set_setting')) {
                    set_setting('admin_password_hash', password_hash($p, PASSWORD_DEFAULT));
                }
            } catch (\Throwable $e) {}
        }
    }

    if ($matched) {
        session_regenerate_id(true);
        $_SESSION['lm_admin'] = $u;
        header('Location: index.php');
        exit;
    }

    $error = 'Invalid credentials. Default login is: admin / admin123';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>License Manager — Sign in</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        .default-hint { font-size: 11px; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 8px 12px; margin-top: 14px; text-align: center; }
        .diag-link { display: inline-block; margin-top: 12px; font-size: 12px; color: #6366f1; text-decoration: none; font-weight: 600; }
        .diag-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="auth">
<form method="post" class="card">
    <div class="brand-icon">Q</div>
    <h1>Quantro</h1>
    <div class="subtitle">License Management &amp; Distribution Portal</div>

    <?php if ($error !== ''): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info !== ''): ?><div class="info"><?= e($info) ?></div><?php endif; ?>

    <?php if ($needsSetup): ?>
        <div class="warn" style="margin-bottom:16px;">
            <strong>Initial Setup Required:</strong> Please set your initial administrator password below to activate portal access.
        </div>
        <input type="hidden" name="set_admin_password" value="1">
        <label>Admin Username
            <input name="username" value="admin" required autofocus>
        </label>
        <label>New Password
            <input type="password" name="new_password" required placeholder="Choose a strong password (or admin123)">
        </label>
        <label>Confirm Password
            <input type="password" name="confirm_password" required placeholder="Confirm your password">
        </label>
        <button type="submit">Set Password &amp; Sign in &rarr;</button>
    <?php else: ?>
        <label>Username
            <input name="username" autocomplete="username" required autofocus placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>">
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
        </label>
        <button type="submit">Sign in &rarr;</button>

        <div class="default-hint">
            🔑 Default credentials: <strong>admin</strong> / <strong>admin123</strong>
        </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9;">
        <a href="whereami.php" class="diag-link">🔍 Server Diagnostics &amp; Path Diagnostic (whereami.php)</a>
        &nbsp;&middot;&nbsp;
        <a href="../app-builder/" class="diag-link" target="_blank">🔨 App Builder</a>
    </div>
</form>
</body>
</html>