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
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' — License Checkout</title>';
    echo '<style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: radial-gradient(circle at 50% 15%, #1e1b4b 0%, #0f172a 100%); color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; -webkit-font-smoothing: antialiased; }
        .box { width: 100%; max-width: 440px; background: #ffffff; border-radius: 18px; padding: 32px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.12); }
        .brand-hdr { text-align: center; margin-bottom: 20px; font-size: 13px; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; justify-content: center; gap: 6px; }
        h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 6px; letter-spacing: -0.02em; text-align: center; }
        .muted { color: #64748b; font-size: 13px; text-align: center; line-height: 1.5; margin-bottom: 18px; }
        .price-badge { display: inline-block; background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-weight: 700; padding: 4px 12px; border-radius: 999px; font-size: 13px; margin-top: 6px; }
        .key { font: 16px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px; border-radius: 10px; word-break: break-all; margin: 16px 0; text-align: center; color: #0f172a; font-weight: 700; letter-spacing: 0.03em; }
        label { display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px; text-align: left; }
        input { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 9px; font: inherit; font-size: 14px; color: #0f172a; margin-bottom: 16px; outline: none; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
        input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        button, .btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border: 0; border-radius: 10px; background: #4f46e5; color: #ffffff; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3); transition: all 0.15s ease; }
        button:hover, .btn:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); }
        button:active, .btn:active { transform: translateY(0); }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 14px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; line-height: 1.5; }
        .trust-badge { margin-top: 18px; text-align: center; font-size: 11px; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 6px; }
    </style></head><body>';
    echo '<div class="box"><div class="brand-hdr"><span>🛡️</span> Secure License Checkout</div>'.$html.'<div class="trust-badge">🔒 Instant activation &amp; encrypted connection</div></div></body></html>';
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