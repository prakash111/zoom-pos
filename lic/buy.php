<?php

/**
 * Central License Manager — Hosted Checkout & Payment Engine
 * 
 * Supports:
 *   - Single product: /buy.php?product=core&domain=...&email=...
 *   - Predefined bundle: /buy.php?bundle=core-lead&domain=...&email=...
 *   - Custom multi-module bundle: /buy.php?modules=leadmanagement,pharmacy&include_core=1&domain=...&email=...
 * 
 * Integrated with Razorpay & Stripe gateways configured in Admin Settings.
 * Issues licenses for Core + every selected module.
 * Automatically delivers license keys and activation guides to the buyer's email.
 * Saves complete order history in the payments and licenses tables.
 */

require __DIR__.'/lib/bootstrap.php';

if (! schema_ready()) {
    http_response_code(503);
    exit('License store is not set up yet.');
}

session_name('lm_checkout');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$pdo = db();
try {
    $pdo->query('SELECT order_token FROM payments LIMIT 0');
} catch (Throwable $ex) {
    http_response_code(503);
    exit('Checkout is being updated. Please contact support. Administrator: run php bin/install.php in the license directory.');
}
$gateway = setting('gateway', 'razorpay');

$bundleSlug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', $_GET['bundle'] ?? $_POST['bundle'] ?? ''));
$productSlug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', $_GET['product'] ?? $_POST['product'] ?? ''));
$modulesParam = trim($_GET['modules'] ?? $_POST['modules'] ?? '');
$includeCore = filter_var($_POST['include_core'] ?? $_GET['include_core'] ?? false, FILTER_VALIDATE_BOOLEAN);

$domain = strtolower(preg_replace('#^https?://#', '', trim($_POST['domain'] ?? $_GET['domain'] ?? '')));
$domain = preg_replace('#[/?].*$#', '', $domain);
$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$return = $_GET['return'] ?? $_POST['return'] ?? '';
$return = preg_match('#^https?://#', $return) ? $return : '';

// Resolve purchase items and pricing
$productsList = [];
$orderTitle = '';
$orderDesc = '';
$price = 0.00;
$currency = setting('currency', 'USD');

if ($bundleSlug !== '') {
    $bStmt = $pdo->prepare('SELECT * FROM bundles WHERE slug = ? AND is_active = 1 LIMIT 1');
    $bStmt->execute([$bundleSlug]);
    $bundle = $bStmt->fetch();

    if ($bundle) {
        $orderTitle = $bundle['name'];
        $orderDesc = $bundle['description'] ?: 'Complete software bundle with core script and add-on modules.';
        $price = (float) $bundle['price'];
        $currency = strtoupper($bundle['currency'] ?: $currency);
        $incSlugs = json_decode($bundle['included_modules'] ?? '[]', true) ?: [];

        if (!empty($incSlugs)) {
            $inQ = implode(',', array_fill(0, count($incSlugs), '?'));
            $pStmt = $pdo->prepare("SELECT * FROM products WHERE slug IN ($inQ)");
            $pStmt->execute($incSlugs);
            $foundProds = $pStmt->fetchAll();
            $pBySlug = [];
            foreach ($foundProds as $fp) {
                $pBySlug[$fp['slug']] = $fp;
            }
            foreach ($incSlugs as $is) {
                if (isset($pBySlug[$is])) {
                    $productsList[] = $pBySlug[$is];
                }
            }
        }
    }
} elseif ($modulesParam !== '' || $includeCore) {
    $slugs = array_filter(array_map('trim', explode(',', strtolower($modulesParam))));
    if ($includeCore && !in_array('core', $slugs, true)) {
        array_unshift($slugs, 'core');
    }

    if (!empty($slugs)) {
        $inQ = implode(',', array_fill(0, count($slugs), '?'));
        $pStmt = $pdo->prepare("SELECT * FROM products WHERE slug IN ($inQ) AND is_active = 1");
        $pStmt->execute($slugs);
        $productsList = $pStmt->fetchAll();

        $calcPrice = 0.00;
        $names = [];
        foreach ($productsList as $p) {
            $calcPrice += (float) $p['price'];
            $names[] = $p['name'];
        }
        $price = $calcPrice;
        $orderTitle = 'Custom Software Package (' . count($productsList) . ' Items)';
        $orderDesc = implode(' + ', $names);
        if (count($productsList) === 1) {
            $orderTitle = $productsList[0]['name'];
            $orderDesc = $productsList[0]['description'] ?: $orderDesc;
        }
    }
} elseif ($productSlug !== '') {
    $pStmt = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
    $pStmt->execute([$productSlug]);
    $prod = $pStmt->fetch();

    if ($prod) {
        $productsList = [$prod];
        $orderTitle = $prod['name'];
        $orderDesc = $prod['description'] ?: '';
        $price = (float) $prod['price'];
        $currency = strtoupper($prod['currency'] ?: $currency);
    }
}

function render_checkout_page(string $title, string $html): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' — License Checkout</title>';
    echo '<style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: radial-gradient(circle at 50% 15%, #1e1b4b 0%, #0f172a 100%); color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; -webkit-font-smoothing: antialiased; }
        .box { width: 100%; max-width: 520px; background: #ffffff; border-radius: 20px; padding: 36px 30px; box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.45), 0 10px 15px -6px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.15); }
        .brand-hdr { text-align: center; margin-bottom: 20px; font-size: 13px; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; justify-content: center; gap: 6px; }
        h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 6px; letter-spacing: -0.02em; text-align: center; }
        .muted { color: #64748b; font-size: 13px; text-align: center; line-height: 1.5; margin-bottom: 18px; }
        .item-list { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin: 16px 0 20px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13px; }
        .item-row:last-child { border-bottom: 0; }
        .item-total { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; margin-top: 8px; border-top: 2px solid #cbd5e1; font-weight: 800; font-size: 15px; color: #0f172a; }
        .key-card { font: 14px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 14px; border-radius: 10px; margin: 10px 0; color: #0f172a; font-weight: 700; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .copy-pill { background: #4f46e5; color: #fff; border: 0; padding: 4px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: 600; }
        label { display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px; text-align: left; }
        input { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 9px; font: inherit; font-size: 14px; color: #0f172a; margin-bottom: 16px; outline: none; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
        input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        button, .btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; padding: 13px 20px; border: 0; border-radius: 10px; background: #4f46e5; color: #ffffff; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3); transition: all 0.15s ease; }
        button:hover, .btn:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); }
        button:active, .btn:active { transform: translateY(0); }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; text-align: left; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 14px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; line-height: 1.5; text-align: left; }
        .trust-badge { margin-top: 20px; text-align: center; font-size: 11px; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .tag-core { background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-right: 6px; }
        .tag-mod { background: #ecfdf5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-right: 6px; }
    </style></head><body>';
    echo '<div class="box"><div class="brand-hdr"><span>🛡️</span> Secure Self-Hosted License Checkout</div>'.$html.'<div class="trust-badge">🔒 Instant activation &bull; Key delivered via email &bull; 256-bit encryption</div></div></body></html>';
    exit;
}

// Restore the immutable order for gateway returns before consulting the live catalog.
$orderToken = (string) ($_POST['order_token'] ?? $_GET['order_token'] ?? '');
$savedOrder = $orderToken !== '' ? lm_order_by_token($pdo, $orderToken) : null;
$isGatewayReturn = !empty($_GET['stripe_session']) || ($_POST['rzp_verify'] ?? '') === '1';
if ($isGatewayReturn && !$savedOrder) {
    render_checkout_page('Order unavailable', '<h1>Order unavailable</h1><p class="err">Contact support with your payment reference. This checkout has no saved order.</p>');
}
if ($savedOrder && $isGatewayReturn) {
    $saved = json_decode($savedOrder['checkout_json'], true);
    $productsList = $saved['products'];
    $orderTitle = $saved['title'];
    $orderDesc = $saved['description'];
    $price = (float) $savedOrder['amount'];
    $currency = $savedOrder['currency'];
    $bundleSlug = $saved['bundle'];
    $domain = $savedOrder['target_domain'];
    $email = $savedOrder['email'];
    $gateway = $savedOrder['gateway'];
    $return = $saved['return'];
}

// Fallback if no item matched
if (empty($productsList)) {
    // Show available packages
    $allBundles = $pdo->query('SELECT * FROM bundles WHERE is_active = 1')->fetchAll();
    $allProducts = $pdo->query('SELECT * FROM products WHERE is_active = 1')->fetchAll();
    
    $html = '<h1>Choose a Package or License</h1><p class="muted">Select a self-hosted platform package or add-on module to proceed.</p>';
    $html .= '<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">';
    foreach ($allBundles as $ab) {
        $html .= '<a href="buy.php?bundle='.e($ab['slug']).'" class="btn" style="background:#312e81;justify-content:space-between;text-align:left;padding:14px 18px"><span>🎁 '.e($ab['name']).'</span><strong>'.e(number_format((float)$ab['price'], 2)).' '.e($ab['currency']).'</strong></a>';
    }
    foreach ($allProducts as $ap) {
        $html .= '<a href="buy.php?product='.e($ap['slug']).'" class="btn" style="background:#1e293b;justify-content:space-between;text-align:left;padding:12px 18px"><span>📦 '.e($ap['name']).'</span><span>'.e(number_format((float)$ap['price'], 2)).' '.e($ap['currency']).'</span></a>';
    }
    $html .= '</div>';
    render_checkout_page('Select Product', $html);
}

// Save a registration as soon as valid buyer details reach checkout, before payment.
$order = $savedOrder;
if (!$isGatewayReturn && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 191 && $domain !== '' && strlen($domain) <= 191) {
    $snapshot = [
        'title' => $orderTitle, 'description' => $orderDesc, 'products' => $productsList,
        'amount' => $price, 'currency' => $currency, 'bundle' => $bundleSlug,
        'domain' => $domain, 'email' => $email, 'gateway' => $gateway, 'return' => $return,
    ];
    $fingerprint = hash('sha256', json_encode($snapshot));
    $order = lm_register_order($pdo, $snapshot, $orderToken ?: ($_SESSION['orders'][$fingerprint] ?? ''));
    $orderToken = $order['order_token'];
    $_SESSION['orders'][$fingerprint] = $orderToken;
}
session_write_close();

/**
 * Handle successful payment completion, multi-license generation, email delivery, and receipt
 */
function finish_checkout(
    PDO $pdo,
    string $gateway,
    string $reference,
    float $amount,
    string $currency,
    string $orderTitle,
    string $bundleSlug,
    array $productsList,
    string $domain,
    string $email,
    string $return
): void {
    global $orderToken;
    try {
        $result = lm_fulfill_order($pdo, $orderToken, $reference, (int) round($amount * 100), $currency);
        $issuedLicenses = $result['licenses'];
        lm_deliver_order($result);
    } catch (Throwable $ex) {
        $pdo->prepare("UPDATE payments SET order_status = 'processing', order_error = ? WHERE order_token = ? AND status <> 'paid'")
            ->execute(['Payment received; license fulfillment needs review.', $orderToken]);
        render_checkout_page('Order processing', '<h1>Order processing</h1><p class="err">Your order needs review. Please contact support with reference '.e($reference).'.</p>');
    }

    // Build receipt HTML
    $licenseCardsHtml = '';
    foreach ($issuedLicenses as $licItem) {
        $pName = e($licItem['name'] ?? ucfirst($licItem['slug']));
        $key = e($licItem['license_key']);
        $isCore = ($licItem['slug'] === 'core');
        $tag = $isCore ? '<span class="tag-core">Core Platform</span>' : '<span class="tag-mod">Module</span>';

        $licenseCardsHtml .= '
        <div style="text-align:left;margin-bottom:12px">
            <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:4px">'.$tag.' '.$pName.'</div>
            <div class="key-card">
                <span id="key-'.e($licItem['slug']).'">'.$key.'</span>
                <button type="button" class="copy-pill" onclick="copyKeyText(\''.$key.'\', this)">Copy</button>
            </div>
        </div>';
    }

    $back = $return !== '' ? '<p style="margin-top:20px"><a class="btn" href="'.e($return).'">Return to Website</a></p>' : '';

    $receiptHtml = '
    <h1>Payment Complete! 🎉</h1>
    <p class="muted">Order status: <strong>Completed</strong></p>
    <p class="ok">
        Thank you for your order! Your payment was verified and <strong>'.count($issuedLicenses).' license(s)</strong> have been issued. Your delivery email is <strong>'.e($email).'</strong>.
    </p>

    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:12px;color:#475569;text-align:left">
        <div><strong>Order Reference:</strong> '.e($reference).'</div>
        <div><strong>Registered Domain:</strong> '.e($domain ?: 'Not specified (binds on first activation)').'</div>
        <div><strong>Total Paid:</strong> '.e(number_format($amount, 2)).' '.e($currency).' via '.strtoupper(e($gateway)).'</div>
    </div>

    <div style="margin-bottom:18px">
        '.$licenseCardsHtml.'
    </div>

    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;font-size:12px;color:#1e40af;text-align:left;line-height:1.6">
        <strong>⚡ Next Steps:</strong>
        <ol style="margin-left:16px;margin-top:6px">
            <li>Run the script installer on your domain and paste your <strong>Core Platform</strong> license key.</li>
            <li>In SaaS SuperAdmin &rarr; <em>Modules</em>, activate each add-on module using the corresponding license key.</li>
            <li>Keep these keys safe. If the delivery email does not arrive, contact support to resend it.</li>
        </ol>
    </div>
    '.$back.'
    <script>
    function copyKeyText(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            var old = btn.innerText;
            btn.innerText = "Copied!";
            setTimeout(function() { btn.innerText = old; }, 2000);
        });
    }
    </script>';

    render_checkout_page('Payment Complete', $receiptHtml);
}

/* -------------------------------------------------------------
 * Payment Gateway 1: Stripe Checkout
 * ------------------------------------------------------------- */
if ($gateway === 'stripe') {
    $sk = (string) setting('stripe_secret_key', '');
    if ($sk === '') {
        render_checkout_page('Unavailable', '<h1>Checkout unavailable</h1><p class="err">Stripe secret key is not configured in settings.</p>');
    }

    if (! empty($_GET['stripe_session'])) {
        $r = http_request('GET', 'https://api.stripe.com/v1/checkout/sessions/'.urlencode($_GET['stripe_session']), null, ['Authorization: Bearer '.$sk]);
        if ($r['code'] === 200 && ($r['json']['payment_status'] ?? '') === 'paid') {
            $s = $r['json'];
            if ($savedOrder['gateway_reference'] !== ($s['id'] ?? '') || ($s['metadata']['order_token'] ?? '') !== $orderToken) {
                render_checkout_page('Invalid payment', '<h1>Payment mismatch</h1><p class="err">This payment session does not belong to the saved order.</p>');
            }
            $paidDomain = $domain;
            $paidEmail = $email;
            finish_checkout(
                $pdo,
                'stripe',
                $s['id'],
                ($s['amount_total'] ?? 0) / 100,
                strtoupper($s['currency'] ?? $currency),
                $orderTitle,
                $bundleSlug,
                $productsList,
                $paidDomain,
                $paidEmail,
                $return
            );
        }
        render_checkout_page('Not paid', '<h1>Payment not completed</h1><p class="err">Stripe did not confirm this payment session.</p>');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$order || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191 || $domain === '' || strlen($domain) > 191) {
            $validationErr = 'Please enter a valid email address and target domain (up to 191 characters each).';
        } else {
            if ($order['status'] === 'paid') {
                render_checkout_page('Already paid', '<h1>Order already paid</h1><p class="ok">Your license keys were issued. Contact support if you need the email resent.</p>');
            }
            if (!empty($order['gateway_url'])) {
                $existingSession = http_request('GET', 'https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($order['gateway_reference']), null, ['Authorization: Bearer '.$sk]);
                if ($existingSession['code'] !== 200) {
                    render_checkout_page('Order processing', '<h1>Order processing</h1><p class="err">Unable to check the previous payment attempt. Please try again shortly.</p>');
                }
                $existing = $existingSession['json'];
                if (($existing['payment_status'] ?? '') === 'paid') {
                    finish_checkout($pdo, 'stripe', $existing['id'], ($existing['amount_total'] ?? 0) / 100, strtoupper($existing['currency'] ?? ''), $orderTitle, $bundleSlug, $productsList, $domain, $email, $return);
                }
                if (($existing['status'] ?? '') !== 'expired') {
                    if (($existing['status'] ?? '') === 'complete') {
                        render_checkout_page('Order processing', '<h1>Order processing</h1><p class="ok">Your payment is still being confirmed. Please allow time for processing.</p>');
                    }
                    header('Location: '.$order['gateway_url']);
                    exit;
                }
            }
            $base = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?');
            $qsParams = ['domain' => $domain, 'email' => $email, 'return' => $return, 'order_token' => $orderToken];
            if ($bundleSlug !== '') $qsParams['bundle'] = $bundleSlug;
            elseif ($productSlug !== '') $qsParams['product'] = $productSlug;
            if ($modulesParam !== '') $qsParams['modules'] = $modulesParam;
            if ($includeCore) $qsParams['include_core'] = '1';

            $qs = http_build_query($qsParams);

            $r = http_request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $base.'?'.$qs.'&stripe_session={CHECKOUT_SESSION_ID}',
                'cancel_url' => $base.'?'.$qs,
                'customer_email' => $email ?: null,
                'client_reference_id' => $orderToken,
                'metadata[order_token]' => $orderToken,
                'metadata[bundle]' => $bundleSlug,
                'metadata[product]' => $productSlug,
                'metadata[domain]' => $domain,
            ] + lm_stripe_details($snapshot), ['Authorization: Bearer '.$sk, 'Idempotency-Key: checkout-'.$orderToken.'-'.($order['gateway_reference'] ?: 'initial')]);

            if ($r['code'] === 200 && ! empty($r['json']['url'])) {
                $pdo->prepare("UPDATE payments SET gateway_reference = ?, gateway_url = ?, order_status = 'processing', order_error = NULL WHERE id = ?")
                    ->execute([$r['json']['id'], $r['json']['url'], $order['id']]);
                header('Location: '.$r['json']['url']);
                exit;
            }
            $validationErr = $r['json']['error']['message'] ?? 'Unable to initialize Stripe session.';
            $pdo->prepare("UPDATE payments SET order_status = 'processing', order_error = ? WHERE id = ?")->execute([$validationErr, $order['id']]);
        }
    }
}

/* -------------------------------------------------------------
 * Payment Gateway 2: Razorpay Checkout
 * ------------------------------------------------------------- */
$keyId = (string) setting('razorpay_key_id', '');
$keySecret = (string) setting('razorpay_key_secret', '');

if ($gateway === 'razorpay') {
    if ($keyId === '' || $keySecret === '') {
        render_checkout_page('Unavailable', '<h1>Checkout unavailable</h1><p class="err">Razorpay gateway credentials are not configured in settings.</p>');
    }

    if (($_POST['rzp_verify'] ?? '') === '1') {
        $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
        $orderId = trim($_POST['razorpay_order_id'] ?? '');
        $sig = trim($_POST['razorpay_signature'] ?? '');

        $verified = http_request('GET', 'https://api.razorpay.com/v1/payments/'.rawurlencode($paymentId), null, ['Authorization: Basic '.base64_encode($keyId.':'.$keySecret)]);
        $payment = $verified['json'] ?? [];
        if ($savedOrder['gateway_reference'] === $orderId && hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $keySecret), $sig)
            && $verified['code'] === 200 && ($payment['order_id'] ?? '') === $orderId && ($payment['status'] ?? '') === 'captured'
            && (int) ($payment['amount'] ?? -1) === (int) round($price * 100) && strtoupper($payment['currency'] ?? '') === $currency) {
            finish_checkout(
                $pdo,
                'razorpay',
                $paymentId,
                $price,
                $currency,
                $orderTitle,
                $bundleSlug,
                $productsList,
                $domain,
                $email,
                $return
            );
        }
        render_checkout_page('Verification failed', '<h1>Payment verification failed</h1><p class="err">Razorpay has not confirmed a captured payment matching this order. If your account was charged, please contact support with payment ID: '.e($paymentId).'</p>');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$order || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191 || $domain === '' || strlen($domain) > 191) {
            $validationErr = 'Please enter a valid email address and target domain (up to 191 characters each).';
        } else {
            if ($order['status'] === 'paid') {
                render_checkout_page('Already paid', '<h1>Order already paid</h1><p class="ok">Your license keys were issued. Contact support if you need the email resent.</p>');
            }
            $receiptId = 'ord_'.substr($orderToken, 0, 32);
            $r = !empty($order['gateway_reference']) ? ['code' => 200, 'json' => ['id' => $order['gateway_reference']]] : http_request('POST', 'https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($price * 100),
                'currency' => $currency,
                'receipt' => $receiptId,
                'notes[title]' => $orderTitle,
                'notes[bundle]' => $bundleSlug,
                'notes[domain]' => $domain,
                'notes[email]' => $email,
            ], ['Authorization: Basic '.base64_encode($keyId.':'.$keySecret)]);

            if ($r['code'] < 200 || $r['code'] >= 300 || empty($r['json']['id'])) {
                $validationErr = $r['json']['error']['description'] ?? 'Razorpay order creation failed.';
                $pdo->prepare("UPDATE payments SET order_status = 'processing', order_error = ? WHERE id = ?")->execute([$validationErr, $order['id']]);
            } else {
                $orderId = $r['json']['id'];
                $pdo->prepare("UPDATE payments SET gateway_reference = ?, order_status = 'processing', order_error = NULL WHERE id = ?")
                    ->execute([$orderId, $order['id']]);
                $selfBase = strtok($_SERVER['REQUEST_URI'], '?');
                ?>
                <!doctype html><meta charset="utf-8"><title>Opening Secure Payment…</title>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <body style="font:15px -apple-system,BlinkMacSystemFont,sans-serif;text-align:center;padding:20vh;background:#0f172a;color:#ffffff">
                <div style="font-size:18px;font-weight:700;margin-bottom:8px">Connecting to Secure Payment Gateway…</div>
                <div style="font-size:13px;color:#94a3b8">Do not refresh or close this window.</div>
                <form id="rzp_form" method="post" action="<?= e($selfBase) ?>">
                    <input type="hidden" name="rzp_verify" value="1">
                    <input type="hidden" name="order_token" value="<?= e($orderToken) ?>">
                    <input type="hidden" name="bundle" value="<?= e($bundleSlug) ?>">
                    <input type="hidden" name="product" value="<?= e($productSlug) ?>">
                    <input type="hidden" name="modules" value="<?= e($modulesParam) ?>">
                    <input type="hidden" name="include_core" value="<?= $includeCore ? '1' : '0' ?>">
                    <input type="hidden" name="domain" value="<?= e($domain) ?>">
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <input type="hidden" name="return" value="<?= e($return) ?>">
                    <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id">
                    <input type="hidden" name="razorpay_order_id" id="rzp_order_id">
                    <input type="hidden" name="razorpay_signature" id="rzp_signature">
                </form>
                <script>
                var f = document.getElementById('rzp_form');
                new Razorpay({
                    key: <?= json_encode($keyId) ?>,
                    order_id: <?= json_encode($orderId) ?>,
                    amount: <?= (int) round($price * 100) ?>,
                    currency: <?= json_encode($currency) ?>,
                    name: <?= json_encode(setting('site_name', 'ZoomNearby Platform')) ?>,
                    description: <?= json_encode($orderTitle . ' for ' . $domain) ?>,
                    prefill: { email: <?= json_encode($email) ?> },
                    handler: function (res) {
                        document.getElementById('rzp_payment_id').value = res.razorpay_payment_id;
                        document.getElementById('rzp_order_id').value = res.razorpay_order_id;
                        document.getElementById('rzp_signature').value = res.razorpay_signature;
                        f.submit();
                    },
                    modal: {
                        ondismiss: function () {
                            window.location.href = window.location.href;
                        }
                    }
                }).open();
                </script>
                </body>
                <?php
                exit;
            }
        }
    }
}

/* -------------------------------------------------------------
 * Render Hosted Checkout Form
 * ------------------------------------------------------------- */
$itemRowsHtml = '';
$regularTotal = 0.00;
foreach ($productsList as $p) {
    $pPrice = (float) $p['price'];
    $regularTotal += $pPrice;
    $isCore = ($p['slug'] === 'core');
    $tag = $isCore ? '<span class="tag-core">Core</span>' : '<span class="tag-mod">Module</span>';
    $itemRowsHtml .= '
    <div class="item-row">
        <span>'.$tag.' <strong>'.e($p['name']).'</strong></span>
        <span style="color:#64748b;font-size:12px">'.e(number_format($pPrice, 2)).' '.e($p['currency'] ?: $currency).'</span>
    </div>';
}

$discountAmount = max(0.0, $regularTotal - $price);
$discountPercent = ($regularTotal > 0 && $discountAmount > 0) ? round(($discountAmount / $regularTotal) * 100) : 0;

$errHtml = !empty($validationErr) ? '<div class="err">'.e($validationErr).'</div>' : '';

$order = $orderToken !== '' ? lm_order_by_token($pdo, $orderToken) : null;
$statusHtml = $order ? '<p class="muted">Order status: <strong>'.e(ucfirst(lm_order_status($order))).'</strong></p>' : '';

$formHtml = '
<h1>'.e($orderTitle).'</h1>
<p class="muted">'.e($orderDesc ?: 'Self-hosted software license with instant email delivery.').'</p>

'.$errHtml.$statusHtml.'

<div class="item-list">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px">Included Products ('.count($productsList).')</div>
    '.$itemRowsHtml.'
    '.($discountAmount > 0 ? '
    <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #cbd5e1;">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#64748b;margin-bottom:6px;">
            <span>Total Value (Original Price)</span>
            <span style="text-decoration:line-through;font-weight:600;">'.e(number_format($regularTotal, 2)).' '.e($currency).'</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#059669;font-weight:700;margin-bottom:6px;">
            <span>Bundle Discount ('.$discountPercent.'% OFF)</span>
            <span>-'.e(number_format($discountAmount, 2)).' '.e($currency).'</span>
        </div>
    </div>
    ' : '').'
    <div class="item-total" style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;margin-top:6px;border-top:2px solid #cbd5e1;font-weight:800;font-size:15px;color:#0f172a;">
        <span>Total Payable (Discounted)</span>
        <span style="color:#4f46e5;font-size:20px;font-weight:900;">'.e(number_format($price, 2)).' '.e($currency).'</span>
    </div>
</div>

<p class="muted" style="text-align:left">'.e(lm_license_terms()).'</p>

<form method="post" action="'.e($_SERVER['REQUEST_URI']).'">
    <input type="hidden" name="order_token" value="'.e($orderToken).'">
    <input type="hidden" name="bundle" value="'.e($bundleSlug).'">
    <input type="hidden" name="product" value="'.e($productSlug).'">
    <input type="hidden" name="modules" value="'.e($modulesParam).'">
    <input type="hidden" name="include_core" value="'.($includeCore ? '1' : '0').'">
    <input type="hidden" name="return" value="'.e($return).'">

    <label>Buyer Registered Email Address
        <input type="email" name="email" value="'.e($email).'" placeholder="your-email@example.com" required>
        <span class="muted" style="display:block;text-align:left;font-size:11px;margin-top:-12px;margin-bottom:14px">
            Your license key(s) will be automatically sent to this email upon payment.
        </span>
    </label>

    <label>Target Domain / Installation Host
        <input type="text" name="domain" value="'.e($domain).'" placeholder="e.g. pos.yourcompany.com" required>
        <span class="muted" style="display:block;text-align:left;font-size:11px;margin-top:-12px;margin-bottom:16px">
            Domain where the script will be hosted. License locks to this domain.
        </span>
    </label>

    <button type="submit">
        Pay '.e(number_format($price, 2)).' '.e($currency).' ('.strtoupper(e($gateway)).') &rarr;
    </button>
</form>';

render_checkout_page('Checkout: ' . $orderTitle, $formHtml);