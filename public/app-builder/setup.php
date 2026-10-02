<?php

/**
 * Zoom POS App Builder - Interactive Web Setup & Diagnostic Tool
 * Used for one-time initialization when deploying to another hosting provider.
 */

require_once __DIR__ . '/lib/bootstrap.php';

$envFile = __DIR__ . '/.env';
$envExample = __DIR__ . '/.env.example';

// Check if .env exists, if not copy from .env.example
if (!file_exists($envFile) && file_exists($envExample)) {
    @copy($envExample, $envFile);
}

$step = $_GET['step'] ?? 'check';
$error = '';
$success = '';

// Handle .env updates via form
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_config'])) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');
    $ghToken = trim($_POST['gh_token'] ?? '');
    $licUrl  = trim($_POST['license_url'] ?? 'https://license.zoomnearby.com');

    $envContent = "# Zoom POS App Builder Configuration\n";
    $envContent .= "APP_NAME=\"Zoom POS App Builder\"\n";
    $envContent .= "APP_ENV=production\n";
    $envContent .= "APP_DEBUG=false\n";
    $envContent .= "APP_URL=" . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/app-builder\n\n";
    $envContent .= "DB_HOST={$dbHost}\n";
    $envContent .= "DB_PORT={$dbPort}\n";
    $envContent .= "DB_DATABASE={$dbName}\n";
    $envContent .= "DB_USERNAME={$dbUser}\n";
    $envContent .= "DB_PASSWORD={$dbPass}\n\n";
    $envContent .= "LICENSE_SERVER_URL={$licUrl}\n";
    $envContent .= "LICENSE_SERVER_SECRET=N4jJ8R2pyZWTpp4oghrq\n\n";
    $envContent .= "GITHUB_REPO=prakash111/zoom-pos\n";
    $envContent .= "GITHUB_BRANCH=feat/windows-offline-sync\n";
    $envContent .= "GITHUB_TOKEN={$ghToken}\n\n";
    $envContent .= "BUILDER_DEFAULT_MONTHLY_LIMIT=10\n";
    $envContent .= "MAIL_FROM_ADDRESS=licenses@zoomnearby.com\n";
    $envContent .= "MAIL_FROM_NAME=\"Zoom POS App Builder\"\n";

    if (@file_put_contents($envFile, $envContent)) {
        header('Location: setup.php?saved=1');
        exit;
    } else {
        $error = "Could not write to .env file. Please check folder write permissions.";
    }
}

// 1. Diagnostics
$checks = [
    'php_version' => [
        'title' => 'PHP Version >= 8.1',
        'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'detail' => 'Current: ' . PHP_VERSION,
    ],
    'pdo_mysql' => [
        'title' => 'PDO MySQL Extension',
        'passed' => extension_loaded('pdo_mysql'),
        'detail' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing',
    ],
    'curl' => [
        'title' => 'cURL Extension',
        'passed' => extension_loaded('curl'),
        'detail' => extension_loaded('curl') ? 'Installed' : 'Missing',
    ],
    'zip' => [
        'title' => 'ZipArchive Extension',
        'passed' => class_exists('ZipArchive'),
        'detail' => class_exists('ZipArchive') ? 'Installed' : 'Missing',
    ],
    'storage_writable' => [
        'title' => 'Storage Directory Writable',
        'passed' => is_writable(__DIR__ . '/storage'),
        'detail' => is_writable(__DIR__ . '/storage') ? 'Writable' : 'Not writable (chmod 775 storage)',
    ],
];

// 2. Database Connection Test
$dbConnected = false;
$dbError = '';
$tableReady = false;

try {
    $pdo = db();
    $dbConnected = true;
    
    // Check if app_builds table exists
    $st = $pdo->query("SHOW TABLES LIKE 'app_builds'");
    $tableReady = (bool)$st->fetch();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// 3. Handle Schema Migration Action
if (isset($_POST['install_database']) && $dbConnected) {
    try {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        $success = "Database schema imported successfully! All tables and default settings are active.";
        $tableReady = true;
    } catch (Throwable $e) {
        $error = "Schema import failed: " . $e->getMessage();
    }
}

$allRequirementsPassed = true;
foreach ($checks as $c) {
    if (!$c['passed']) $allRequirementsPassed = false;
}
$isSystemReady = $allRequirementsPassed && $dbConnected && $tableReady;

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup & Diagnostic — Zoom POS App Builder</title>
  <link rel="stylesheet" href="assets/app.css">
  <style>
    body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 40px 20px; color: #1e293b; }
    .setup-card { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 36px; }
    .header { text-align: center; margin-bottom: 28px; }
    .header h1 { font-size: 24px; font-weight: 800; margin: 0 0 6px; color: #0f172a; }
    .header p { color: #64748b; font-size: 14px; margin: 0; }
    .section-title { font-size: 16px; font-weight: 700; margin: 24px 0 12px; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
    .check-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-radius: 8px; margin-bottom: 8px; background: #f8fafc; }
    .check-badge { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
    .badge-pass { background: #dcfce7; color: #166534; }
    .badge-fail { background: #fee2e2; color: #991b1b; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
    .form-control { width: 100%; box-sizing: border-box; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; }
    .form-control:focus { outline: none; border-color: #4f46e5; ring: 2px rgba(79,70,229,0.2); }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; text-decoration: none; }
    .btn-primary { background: #4f46e5; color: #fff; }
    .btn-primary:hover { background: #4338ca; }
    .btn-success { background: #10b981; color: #fff; width: 100%; }
    .alert { padding: 14px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
  </style>
</head>
<body>

<div class="setup-card">
  <div class="header">
    <div style="font-size: 40px; margin-bottom: 8px;">🚀</div>
    <h1>Zoom POS App Builder Setup</h1>
    <p>Environment diagnostics, database verification & initialization</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= e($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">✅ Configuration saved to .env file!</div>
  <?php endif; ?>

  <!-- Server Requirements -->
  <div class="section-title">1. Server Environment</div>
  <?php foreach ($checks as $c): ?>
    <div class="check-row">
      <div>
        <strong><?= e($c['title']) ?></strong>
        <div style="font-size: 12px; color: #64748b;"><?= e($c['detail']) ?></div>
      </div>
      <span class="check-badge <?= $c['passed'] ? 'badge-pass' : 'badge-fail' ?>">
        <?= $c['passed'] ? 'PASSED' : 'ACTION REQUIRED' ?>
      </span>
    </div>
  <?php endforeach; ?>

  <!-- Database Connection -->
  <div class="section-title">2. Database Status</div>
  <div class="check-row">
    <div>
      <strong>MySQL Connection</strong>
      <div style="font-size: 12px; color: #64748b;">
        <?= $dbConnected ? 'Successfully connected to database' : 'Connection failed' ?>
      </div>
    </div>
    <span class="check-badge <?= $dbConnected ? 'badge-pass' : 'badge-fail' ?>">
      <?= $dbConnected ? 'CONNECTED' : 'FAILED' ?>
    </span>
  </div>

  <?php if (!$dbConnected): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <strong>Connection Error:</strong> <?= e($dbError) ?>
    </div>
    
    <div style="background:#f1f5f9; padding:18px; border-radius:10px; margin-top:14px;">
      <h3 style="margin:0 0 12px; font-size:15px;">Configure Database Credentials (.env)</h3>
      <form method="post" action="setup.php">
        <input type="hidden" name="save_config" value="1">
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
          <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" class="form-control" value="127.0.0.1" required>
          </div>
          <div class="form-group">
            <label>Port</label>
            <input type="text" name="db_port" class="form-control" value="3306" required>
          </div>
        </div>
        <div class="form-group">
          <label>Database Name</label>
          <input type="text" name="db_name" class="form-control" placeholder="zoom_app_builder" required>
        </div>
        <div class="form-group">
          <label>Database User</label>
          <input type="text" name="db_user" class="form-control" placeholder="root" required>
        </div>
        <div class="form-group">
          <label>Database Password</label>
          <input type="password" name="db_pass" class="form-control" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>Cloud Engine Access Token</label>
          <input type="password" name="gh_token" class="form-control" value="" placeholder="Enter GitHub Personal Access Token">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Save to .env & Re-test</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Schema & Tables -->
  <?php if ($dbConnected): ?>
    <div class="section-title">3. Database Schema & Tables</div>
    <div class="check-row">
      <div>
        <strong>App Builds Table & Seed Data</strong>
        <div style="font-size: 12px; color: #64748b;">
          <?= $tableReady ? 'Table `app_builds` exists and is ready' : 'Table `app_builds` missing' ?>
        </div>
      </div>
      <span class="check-badge <?= $tableReady ? 'badge-pass' : 'badge-fail' ?>">
        <?= $tableReady ? 'INITIALIZED' : 'NOT INITIALIZED' ?>
      </span>
    </div>

    <?php if (!$tableReady): ?>
      <form method="post" action="setup.php" style="margin-top:14px;">
        <input type="hidden" name="install_database" value="1">
        <button type="submit" class="btn btn-primary" style="width:100%;">Initialize Schema & Seed Settings Now</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Ready to Use -->
  <?php if ($isSystemReady): ?>
    <div style="margin-top: 30px; text-align: center; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 24px; border-radius: 12px;">
      <h2 style="color: #166534; font-size: 20px; margin: 0 0 8px;">🎉 System is Fully Ready!</h2>
      <p style="color: #15803d; font-size: 14px; margin: 0 0 18px;">All server requirements, database tables, and storage configurations are verified.</p>
      <a href="index.php" class="btn btn-success" style="font-size: 16px; padding: 14px 28px;">Open App Builder Dashboard →</a>
      <div style="margin-top: 12px; font-size: 12px; color: #86efac;">Tip: For production security, you can delete or restrict setup.php once installed.</div>
    </div>
  <?php endif; ?>

</div>

</body>
</html>
