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
lm_header('settings', 'Settings');
?>
<form method="post" action="settings.php">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">

    <section class="card">
        <h2><span>⚙️</span> General &amp; Validation Settings</h2>
        <div class="grid">
            <label>Validation mode
                <select name="validation_mode">
                    <option value="native" <?= setting('validation_mode', 'native') === 'native' ? 'selected' : '' ?>>Native (keys issued &amp; checked directly on this server)</option>
                    <option value="codecanyon" <?= setting('validation_mode') === 'codecanyon' ? 'selected' : '' ?>>CodeCanyon (verify Envato purchase codes)</option>
                </select>
            </label>
            <label>Default Currency<input name="currency" value="<?= e(setting('currency', 'USD')) ?>" maxlength="3" placeholder="USD"></label>
        </div>
    </section>

    <section class="card">
        <h2><span>🎟️</span> Envato / CodeCanyon API</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">Required only if using CodeCanyon validation mode to verify buyer purchase codes.</p>
        <div class="grid">
            <label>Envato Personal API Token<input type="password" name="envato_token" placeholder="<?= e($has('envato_token') ?: 'Paste personal token') ?>"></label>
            <label>Envato Item ID (optional)<input name="envato_item_id" value="<?= e(setting('envato_item_id', '')) ?>" placeholder="e.g. 12345678"></label>
        </div>
    </section>

    <section class="card">
        <h2><span>💳</span> Payment Gateways (Hosted Checkout)</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:16px;font-size:12px">
            Configure gateway credentials used by the public checkout page at <code>/buy.php</code>.
        </p>
        <div class="grid" style="margin-bottom:20px">
            <label>Active Payment Gateway
                <select name="gateway">
                    <option value="razorpay" <?= setting('gateway', 'razorpay') === 'razorpay' ? 'selected' : '' ?>>Razorpay</option>
                    <option value="stripe" <?= setting('gateway') === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                </select>
            </label>
        </div>

        <h3 style="font-size:14px;margin-bottom:12px;color:#334155">Razorpay Credentials</h3>
        <div class="grid" style="margin-bottom:20px">
            <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e(setting('razorpay_key_id', '')) ?>" placeholder="rzp_live_..."></label>
            <label>Razorpay Key Secret<input type="password" name="razorpay_key_secret" placeholder="<?= e($has('razorpay_key_secret') ?: '••••••••') ?>"></label>
        </div>

        <h3 style="font-size:14px;margin-bottom:12px;color:#334155">Stripe Credentials</h3>
        <div class="grid" style="margin-bottom:24px">
            <label>Stripe Publishable Key<input name="stripe_publishable_key" value="<?= e(setting('stripe_publishable_key', '')) ?>" placeholder="pk_live_..."></label>
            <label>Stripe Secret Key<input type="password" name="stripe_secret_key" placeholder="<?= e($has('stripe_secret_key') ?: '••••••••') ?>"></label>
        </div>

        <button type="submit" style="padding:12px 24px">💾 Save all settings</button>
    </section>
</form>
<?php lm_footer();