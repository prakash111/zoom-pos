<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$secretKeys = ['envato_token', 'razorpay_key_secret', 'stripe_secret_key', 'stripe_webhook_secret', 'smtp_pass'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test_mail') {
        $testTo = trim($_POST['test_email'] ?? '');
        if ($testTo === '') {
            header('Location: settings.php?msg=' . urlencode('Please enter a recipient email address for testing.'));
            exit;
        }
        $res = lm_send_mail(
            $testTo,
            'Test Email from License Manager',
            '<div style="font-family:sans-serif;padding:20px;border:1px solid #e2e8f0;border-radius:8px;">'
            . '<h2 style="color:#4f46e5;margin:0 0 10px;">Test Email Successful!</h2>'
            . '<p style="color:#334155;font-size:14px;">Your License Manager email system is configured properly and delivering messages successfully.</p>'
            . '<p style="color:#64748b;font-size:12px;">Sent via: <strong>' . htmlspecialchars(setting('mail_driver', 'mail')) . '</strong> on ' . date('Y-m-d H:i:s') . ' UTC</p>'
            . '</div>'
        );
        $statusMsg = $res['ok'] ? '✅ ' . $res['message'] : '❌ ' . $res['message'];
        header('Location: settings.php?msg=' . urlencode($statusMsg));
        exit;
    }

    set_setting('site_name', trim($_POST['site_name'] ?? 'ZoomNearby SaaS Platform'));
    set_setting('validation_mode', in_array($_POST['validation_mode'] ?? '', ['native', 'codecanyon'], true) ? $_POST['validation_mode'] : 'native');
    set_setting('gateway', in_array($_POST['gateway'] ?? '', ['razorpay', 'stripe'], true) ? $_POST['gateway'] : 'razorpay');
    set_setting('currency', strtoupper(substr(trim($_POST['currency'] ?? 'USD'), 0, 3)));
    set_setting('envato_item_id', trim($_POST['envato_item_id'] ?? ''));
    set_setting('razorpay_key_id', trim($_POST['razorpay_key_id'] ?? ''));
    set_setting('stripe_publishable_key', trim($_POST['stripe_publishable_key'] ?? ''));
    if (isset($_POST['license_public_url'])) {
        $publicUrl = rtrim(trim($_POST['license_public_url']), '/');
        if (filter_var($publicUrl, FILTER_VALIDATE_URL) && parse_url($publicUrl, PHP_URL_SCHEME) === 'https' && !parse_url($publicUrl, PHP_URL_QUERY) && !parse_url($publicUrl, PHP_URL_FRAGMENT)) {
            set_setting('license_public_url', $publicUrl);
        } else {
            header('Location: settings.php?msg='.urlencode('Enter a valid HTTPS License Server Public URL without a query string.'));
            exit;
        }
    }

    // Email settings
    set_setting('mail_driver', in_array($_POST['mail_driver'] ?? '', ['mail', 'smtp'], true) ? $_POST['mail_driver'] : 'mail');
    set_setting('mail_from_address', trim($_POST['mail_from_address'] ?? 'licenses@zoomnearby.com'));
    set_setting('mail_from_name', trim($_POST['mail_from_name'] ?? 'ZoomNearby License Manager'));
    set_setting('smtp_host', trim($_POST['smtp_host'] ?? ''));
    set_setting('smtp_port', (string) (int) ($_POST['smtp_port'] ?? 587));
    set_setting('smtp_encryption', in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls');
    set_setting('smtp_user', trim($_POST['smtp_user'] ?? ''));

    // Invoice settings
    set_setting('send_invoice_attachment', !empty($_POST['send_invoice_attachment']) ? '1' : '0');
    set_setting('invoice_company_name', trim($_POST['invoice_company_name'] ?? ''));
    set_setting('invoice_company_address', trim($_POST['invoice_company_address'] ?? ''));
    set_setting('invoice_tax_number', trim($_POST['invoice_tax_number'] ?? ''));
    set_setting('invoice_footer_note', trim($_POST['invoice_footer_note'] ?? ''));

    foreach ($secretKeys as $k) {
        if (trim($_POST[$k] ?? '') !== '') {
            set_setting($k, trim($_POST[$k]));
        }
    }
    header('Location: settings.php?msg=' . urlencode('Settings saved successfully.'));
    exit;
}

$token = csrf_token();
$has = fn ($k) => setting($k, '') !== '' ? 'configured — leave blank to keep' : '';
lm_header('settings', 'Settings');
?>
<form method="post" action="settings.php">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="save">

    <section class="card" style="margin-bottom:20px">
        <h2><span>⚙️</span> General &amp; Validation Settings</h2>
        <div class="grid">
            <label>Brand / Site Name
                <input name="site_name" value="<?= e(setting('site_name', 'ZoomNearby SaaS Platform')) ?>" placeholder="e.g. ZoomNearby SaaS Platform">
            </label>
            <label>Default Currency
                <input name="currency" value="<?= e(setting('currency', 'USD')) ?>" maxlength="3" placeholder="USD">
            </label>
            <label>Validation mode
                <select name="validation_mode">
                    <option value="native" <?= setting('validation_mode', 'native') === 'native' ? 'selected' : '' ?>>Native (keys issued &amp; checked directly on this server)</option>
                    <option value="codecanyon" <?= setting('validation_mode') === 'codecanyon' ? 'selected' : '' ?>>CodeCanyon (verify Envato purchase codes)</option>
                </select>
            </label>
            <label>Envato Item ID (optional)
                <input name="envato_item_id" value="<?= e(setting('envato_item_id', '')) ?>" placeholder="e.g. 12345678">
            </label>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2><span>📧</span> Email &amp; SMTP Delivery Settings</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">
            Purchased script license keys, bundle modules, and activation instructions are sent to the buyer's registered email using these settings.
        </p>
        <div class="grid" style="margin-bottom:16px">
            <label>Mail Driver
                <select name="mail_driver">
                    <option value="mail" <?= setting('mail_driver', 'mail') === 'mail' ? 'selected' : '' ?>>PHP Native mail()</option>
                    <option value="smtp" <?= setting('mail_driver') === 'smtp' ? 'selected' : '' ?>>SMTP Server (Socket TLS/SSL)</option>
                </select>
            </label>
            <label>Sender Email (From)
                <input type="email" name="mail_from_address" value="<?= e(setting('mail_from_address', 'licenses@zoomnearby.com')) ?>" placeholder="licenses@yourdomain.com">
            </label>
            <label>Sender Name
                <input name="mail_from_name" value="<?= e(setting('mail_from_name', 'ZoomNearby Licensing')) ?>" placeholder="ZoomNearby Licensing">
            </label>
            <label>SMTP Host
                <input name="smtp_host" value="<?= e(setting('smtp_host', '')) ?>" placeholder="smtp.gmail.com or mail.yourdomain.com">
            </label>
            <label>SMTP Port
                <input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>" placeholder="587 or 465">
            </label>
            <label>SMTP Encryption
                <select name="smtp_encryption">
                    <option value="tls" <?= setting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                    <option value="ssl" <?= setting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                    <option value="none" <?= setting('smtp_encryption') === 'none' ? 'selected' : '' ?>>None (Port 25)</option>
                </select>
            </label>
            <label>SMTP Username
                <input name="smtp_user" value="<?= e(setting('smtp_user', '')) ?>" placeholder="user@yourdomain.com">
            </label>
            <label>SMTP Password
                <input type="password" name="smtp_pass" placeholder="<?= e($has('smtp_pass') ?: '••••••••') ?>">
            </label>
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;font-size:12px;line-height:1.6;color:#475569">
            <strong style="color:#1e293b">💡 Sender Email (From) &amp; SMTP Configuration Notes:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <li><strong>Configured From Address:</strong> <code>licenses@zoomnearby.com</code> is enforced as the From, Reply-To, and Return-Path across all outgoing emails.</li>
                <li><strong>For Hostinger Hosting (Recommended):</strong> Use Hostinger SMTP (<code>smtp.hostinger.com</code>, Port <code>465</code>, SSL) with Username <code>licenses@zoomnearby.com</code> and its email password so emails are sent directly from your domain without third-party rewrites.</li>
                <li><strong>If using Gmail SMTP (smtp.gmail.com):</strong> Note that Google automatically replaces the outgoing From address with the authenticated Gmail account (e.g. <code>prakashks045@gmail.com</code>) unless <code>licenses@zoomnearby.com</code> is added and verified as a "Send mail as" alias in your Google Account settings.</li>
                <li><strong>For PHP Native mail():</strong> The mailer now explicitly passes the <code>-f licenses@zoomnearby.com</code> envelope sender parameter so the server's default account email does not override your From header.</li>
            </ul>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2><span>🧾</span> Invoice &amp; Purchase Receipt Settings</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">
            Configure automated PDF invoice generation and seller details included on customer receipts.
        </p>
        <div style="margin-bottom:18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;margin:0">
                <input type="checkbox" name="send_invoice_attachment" value="1" <?= setting('send_invoice_attachment', '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#4f46e5">
                <span>Attach PDF Invoice to Completed Order Email automatically</span>
            </label>
            <p class="muted" style="margin:6px 0 0 28px;font-size:12px">
                When enabled, a clean PDF tax invoice is automatically generated and attached to the purchase fulfillment email sent to the buyer.
            </p>
        </div>
        <div class="grid" style="margin-bottom:12px">
            <label>Legal Company / Seller Name
                <input name="invoice_company_name" value="<?= e(setting('invoice_company_name', '')) ?>" placeholder="e.g. ZoomNearby Technologies (defaults to Brand Name)">
            </label>
            <label>Tax ID / GSTIN / VAT Number (Optional)
                <input name="invoice_tax_number" value="<?= e(setting('invoice_tax_number', '')) ?>" placeholder="e.g. GSTIN: 29AAAAA0000A1Z5 or EU VAT: ...">
            </label>
            <label style="grid-column:span 2">Company Address &amp; Contact Info (Optional)
                <input name="invoice_company_address" value="<?= e(setting('invoice_company_address', '')) ?>" placeholder="e.g. 100 Tech Blvd, Suite 200, San Francisco, CA">
            </label>
            <label style="grid-column:span 2">Invoice Footer Note (Optional)
                <input name="invoice_footer_note" value="<?= e(setting('invoice_footer_note', '')) ?>" placeholder="e.g. Thank you for your business! All licenses are perpetual and bound to your domain.">
            </label>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2><span>💳</span> Payment Gateways (Hosted Checkout)</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">
            Configure gateway credentials used by the public checkout page at <code>/buy.php</code> and the external marketing landing page script.
        </p>
        <div class="grid" style="margin-bottom:20px">
            <label>Active Payment Gateway
                <select name="gateway">
                    <option value="razorpay" <?= setting('gateway', 'razorpay') === 'razorpay' ? 'selected' : '' ?>>Razorpay</option>
                    <option value="stripe" <?= setting('gateway') === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                </select>
            </label>
            <label>Envato Personal API Token (Optional)
                <input type="password" name="envato_token" placeholder="<?= e($has('envato_token') ?: 'Paste personal token') ?>">
            </label>
        </div>

        <h3 style="font-size:14px;margin-bottom:12px;color:#334155">Razorpay Credentials</h3>
        <div class="grid" style="margin-bottom:20px">
            <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e(setting('razorpay_key_id', '')) ?>" placeholder="rzp_live_..."></label>
            <label>Razorpay Key Secret<input type="password" name="razorpay_key_secret" placeholder="<?= e($has('razorpay_key_secret') ?: '••••••••') ?>"></label>
        </div>

        <h3 style="font-size:14px;margin-bottom:12px;color:#334155">Stripe Credentials</h3>
        <label style="margin-bottom:16px">License Server Public URL
            <input type="url" name="license_public_url" value="<?= e(setting('license_public_url', 'https://license.zoomnearby.com')) ?>" placeholder="https://license.zoomnearby.com" required>
            <span class="muted" style="font-size:12px">Used for the private product download links in customer emails. Include the installation subdirectory if applicable.</span>
        </label>
        <div class="grid" style="margin-bottom:24px">
            <label>Stripe Publishable Key<input name="stripe_publishable_key" value="<?= e(setting('stripe_publishable_key', '')) ?>" placeholder="pk_live_..."></label>
            <label>Stripe Secret Key<input type="password" name="stripe_secret_key" placeholder="<?= e($has('stripe_secret_key') ?: '••••••••') ?>"></label>
            <label>Stripe Webhook Signing Secret<input type="password" name="stripe_webhook_secret" placeholder="<?= e($has('stripe_webhook_secret') ?: 'whsec_...') ?>"></label>
            <p class="muted" style="font-size:12px">In Stripe, add this license server’s <code>/api/stripe-webhook.php</code> endpoint for <code>checkout.session.completed</code> and <code>checkout.session.async_payment_succeeded</code>. Paste its signing secret here so paid orders complete even when the buyer closes checkout.</p>
        </div>

        <button type="submit" style="padding:12px 24px">💾 Save all settings</button>
    </section>
</form>

<section class="card" style="margin-top:24px;">
    <h2><span>🧪</span> Test Email Delivery</h2>
    <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">
        Verify that your mail driver and SMTP configuration are able to deliver license notifications.
    </p>
    <form method="post" action="settings.php" style="display:flex;gap:12px;max-width:540px;align-items:flex-end">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="test_mail">
        <label style="flex:1;margin-bottom:0">Recipient Email
            <input type="email" name="test_email" placeholder="your-email@example.com" required style="margin-bottom:0">
        </label>
        <button type="submit" class="outline-secondary" style="white-space:nowrap;padding:10px 18px">✉️ Send Test Email</button>
    </form>
</section>

<section class="card" style="margin-top:24px;">
    <h2><span>🚀</span> Standalone Marketing Landing Page Script</h2>
    <p class="muted" style="margin-top:-6px;margin-bottom:14px;font-size:13px;line-height:1.6">
        A completely separate, high-converting marketing landing page script is available for this system. It can be hosted on <strong>ANY domain</strong> (cPanel, Apache, Nginx, or CloudPanel) to sell your self-hosted core script and custom bundles.
    </p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <strong style="color:#0f172a;font-size:14px">Download Landing Page Package (.zip)</strong>
            <p style="margin:4px 0 0;font-size:12px;color:#64748b">Pre-packaged, portable PHP marketing script with bundle selector and checkout integration.</p>
        </div>
        <a href="download-landing-pkg.php" class="btn" style="width:auto;padding:10px 20px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            📥 Download ZIP Package
        </a>
    </div>
</section>
<?php lm_footer();
