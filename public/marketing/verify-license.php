<?php

require __DIR__ . '/config.php';

$data = get_landing_page_data();
$siteName = $data['branding']['site_name'] ?? 'ZoomNearby POS & Business SaaS';

$key = trim($_GET['key'] ?? $_POST['key'] ?? '');
$domain = trim($_GET['domain'] ?? $_POST['domain'] ?? '');
$product = trim($_GET['product'] ?? $_POST['product'] ?? 'core');
$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $key !== '' && $domain !== '') {
    $payload = json_encode([
        'license_key' => $key,
        'domain' => $domain,
        'product_slug' => $product,
    ]);

    $ch = curl_init(VERIFY_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = ($code === 200 && $raw) ? json_decode($raw, true) : ['status' => false, 'message' => 'Unable to connect to license verification server.'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>License Verification — <?= e_attr($siteName) ?></title>
  <link rel="stylesheet" href="<?= marketing_asset('css/marketing.css') ?>">
</head>
<body style="background:var(--slate-50);min-height:100vh;display:flex;flex-direction:column;justify-content:space-between;">

  <header class="header">
    <div class="container header-container">
      <a href="index.php" class="brand">
        <span class="brand-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
        </span>
        <span class="brand-name">Zoom POS <span class="brand-sub">&amp; Market</span></span>
      </a>
      <div class="nav-actions">
        <a href="index.php" class="btn btn-nav-demo" style="font-size:13px">Back to Home</a>
        <a href="index.php#pricing" class="btn btn-nav-buy" style="font-size:13px">Get License</a>
      </div>
    </div>
  </header>

  <main style="padding:60px 20px;">
    <div style="max-width:540px;margin:0 auto;background:#ffffff;border:1px solid var(--slate-200);border-radius:20px;padding:36px;box-shadow:var(--shadow-lg);">
      <h1 style="font-size:24px;font-weight:900;color:var(--slate-900);margin-bottom:8px;text-align:center;">
        Verify Software License
      </h1>
      <p style="font-size:14px;color:var(--slate-500);text-align:center;margin-bottom:24px;">
        Check the status, binding domain, and expiration of your issued license key.
      </p>

      <?php if ($result !== null): ?>
        <div style="padding:16px;border-radius:12px;margin-bottom:20px;font-size:14px;line-height:1.5;<?= ($result['status'] ?? false) ? 'background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;' : 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;' ?>">
          <strong><?= ($result['status'] ?? false) ? '✅ License Valid' : '❌ Verification Failed' ?></strong>
          <p style="margin:6px 0 0;"><?= e_attr($result['message'] ?? 'Unknown status') ?></p>
          <?php if (!empty($result['expires_at'])): ?>
            <p style="margin:4px 0 0;font-size:12px;">Valid Until: <?= e_attr($result['expires_at']) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="verify-license.php">
        <div class="modal-field">
          <label>Product</label>
          <select name="product" style="width:100%;padding:12px;border:1px solid var(--slate-200);border-radius:10px;font-size:14px;">
            <option value="core" <?= $product === 'core' ? 'selected' : '' ?>>Core Platform Script</option>
            <option value="leadmanagement" <?= $product === 'leadmanagement' ? 'selected' : '' ?>>Lead Management System</option>
            <option value="pharmacy" <?= $product === 'pharmacy' ? 'selected' : '' ?>>Pharmacy POS</option>
            <option value="salon" <?= $product === 'salon' ? 'selected' : '' ?>>Salon Management</option>
            <option value="repairtechnician" <?= $product === 'repairtechnician' ? 'selected' : '' ?>>Repair Service Provider</option>
          </select>
        </div>

        <div class="modal-field">
          <label>License Key</label>
          <input type="text" name="key" value="<?= e_attr($key) ?>" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required style="font-family:monospace;letter-spacing:1px;font-weight:700;">
        </div>

        <div class="modal-field">
          <label>Installed Domain</label>
          <input type="text" name="domain" value="<?= e_attr($domain) ?>" placeholder="e.g. pos.yourcompany.com" required>
        </div>

        <button type="submit" class="btn btn-hero-buy" style="width:100%;margin-top:10px;">
          🔍 Verify License Now
        </button>
      </form>
    </div>
  </main>

  <footer style="text-align:center;padding:24px;font-size:12px;color:var(--slate-400);">
    &copy; <?= date('Y') ?> <?= e_attr($siteName) ?>. All rights reserved.
  </footer>

</body>
</html>
