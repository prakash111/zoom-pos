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
    <div class="brand-icon">Q</div>
    <h1>Quantro</h1>
    <div class="subtitle">License Management &amp; Distribution Portal</div>
    <?php if ($needsSetup): ?>
        <div class="warn">No admin password set. Run
            <code>php bin/hash-password.php 'your-password'</code> and paste the result into
            <code>ADMIN_PASS_HASH</code> in <code>config/config.php</code>.</div>
    <?php else: ?>
        <?php if ($error !== ''): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
        <label>Username<input name="username" autocomplete="username" required autofocus placeholder="admin"></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required placeholder="••••••••"></label>
        <button type="submit">Sign in &rarr;</button>
    <?php endif; ?>
</form>
</body>
</html>