<?php

/**
 * License Manager Mailer
 * Zero-dependency pure PHP mailer supporting PHP mail() and SMTP sockets (TLS/SSL).
 */

function lm_send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', array $attachments = []): array
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid recipient email address.'];
    }

    $driver = setting('mail_driver', 'mail');
    $fromAddress = trim((string) setting('mail_from_address', 'licenses@zoomnearby.com'));
    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $fromAddress = 'licenses@zoomnearby.com';
    }
    $fromName = trim((string) setting('mail_from_name', 'ZoomNearby Licensing'));
    if ($fromName === '') {
        $fromName = 'ZoomNearby Licensing';
    }

    if ($textBody === '') {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    if ($driver === 'smtp' && setting('smtp_host', '') !== '') {
        $smtpRes = lm_send_smtp($to, $subject, $htmlBody, $textBody, $fromAddress, $fromName, $attachments);
        if ($smtpRes['ok']) {
            return $smtpRes;
        }
        // Fallback to mail() if SMTP fails
        error_log('LicenseManager SMTP failed: ' . $smtpRes['message'] . '. Falling back to mail().');
    }

    return lm_send_native_mail($to, $subject, $htmlBody, $fromAddress, $fromName, $textBody, $attachments);
}

function lm_send_native_mail(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, string $textBody = '', array $attachments = []): array
{
    @ini_set('sendmail_from', $fromAddress);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
        'Return-Path: <' . $fromAddress . '>',
        'Sender: ' . $fromAddress,
        'X-Mailer: LicenseManager-Mailer/2.0',
    ];

    if (!empty($attachments)) {
        $boundaryMixed = '=_mix_' . md5(microtime() . mt_rand());
        $boundaryAlt = '=_alt_' . md5(microtime() . mt_rand());
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"';

        $body = "--{$boundaryMixed}\n"
            . "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\n\n"
            . "--{$boundaryAlt}\n"
            . "Content-Type: text/plain; charset=UTF-8\n"
            . "Content-Transfer-Encoding: base64\n\n"
            . chunk_split(base64_encode($textBody)) . "\n"
            . "--{$boundaryAlt}\n"
            . "Content-Type: text/html; charset=UTF-8\n"
            . "Content-Transfer-Encoding: base64\n\n"
            . chunk_split(base64_encode($htmlBody)) . "\n"
            . "--{$boundaryAlt}--\n";

        foreach ($attachments as $att) {
            $filename = preg_replace('/[^\w\.-]/', '_', (string) ($att['filename'] ?? 'attachment.dat'));
            $mime = (string) ($att['mime'] ?? 'application/octet-stream');
            $data = (string) ($att['content'] ?? '');

            $body .= "--{$boundaryMixed}\n"
                . "Content-Type: {$mime}; name=\"{$filename}\"\n"
                . "Content-Disposition: attachment; filename=\"{$filename}\"\n"
                . "Content-Transfer-Encoding: base64\n\n"
                . chunk_split(base64_encode($data)) . "\n";
        }
        $body .= "--{$boundaryMixed}--\n";
    } elseif ($textBody !== '') {
        $boundary = '=_' . md5(microtime() . mt_rand());
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = "--{$boundary}\n"
            . "Content-Type: text/plain; charset=UTF-8\n"
            . "Content-Transfer-Encoding: base64\n\n"
            . chunk_split(base64_encode($textBody)) . "\n"
            . "--{$boundary}\n"
            . "Content-Type: text/html; charset=UTF-8\n"
            . "Content-Transfer-Encoding: base64\n\n"
            . chunk_split(base64_encode($htmlBody)) . "\n"
            . "--{$boundary}--\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $body = $htmlBody;
    }

    $headerStr = implode("\r\n", $headers);
    $extraParams = '-f' . escapeshellarg($fromAddress);

    // Pass envelope sender -f so the server MTA doesn't stamp the account email (e.g. prakashks045@gmail.com)
    $sent = @mail($to, $encodedSubject, $body, $headerStr, $extraParams);
    if (!$sent) {
        // Fallback without $extraParams if the environment restricts additional parameters
        $sent = @mail($to, $encodedSubject, $body, $headerStr);
    }

    if ($sent) {
        return ['ok' => true, 'message' => 'Email dispatched via PHP mail().'];
    }

    return ['ok' => false, 'message' => 'PHP mail() failed to dispatch. Check server sendmail/postfix configuration.'];
}

function lm_send_smtp(string $to, string $subject, string $htmlBody, string $textBody, string $fromAddress, string $fromName, array $attachments = []): array
{
    $host = (string) setting('smtp_host', '');
    $port = (int) setting('smtp_port', 587);
    $encryption = strtolower((string) setting('smtp_encryption', 'tls'));
    $user = (string) setting('smtp_user', '');
    $pass = (string) setting('smtp_pass', '');

    $prefix = '';
    if ($encryption === 'ssl' || $port === 465) {
        $prefix = 'ssl://';
    }

    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return ['ok' => false, 'message' => "Socket connection to {$host}:{$port} failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, 15);

    $read = function () use ($socket) {
        $data = '';
        while ($str = fgets($socket, 512)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $send = function (string $cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $greeting = $read();
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return ['ok' => false, 'message' => 'Invalid SMTP greeting: ' . trim($greeting)];
    }

    $clientHost = gethostname() ?: 'localhost';
    $ehlo = $send("EHLO {$clientHost}");

    if ($encryption === 'tls' && strpos($ehlo, 'STARTTLS') !== false) {
        $starttls = $send('STARTTLS');
        if (substr($starttls, 0, 3) !== '220') {
            fclose($socket);
            return ['ok' => false, 'message' => 'STARTTLS failed: ' . trim($starttls)];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['ok' => false, 'message' => 'TLS encryption negotiation failed.'];
        }
        $send("EHLO {$clientHost}");
    }

    if ($user !== '' && $pass !== '') {
        $auth = $send('AUTH LOGIN');
        if (substr($auth, 0, 3) !== '334') {
            fclose($socket);
            return ['ok' => false, 'message' => 'AUTH LOGIN error: ' . trim($auth)];
        }
        $uResp = $send(base64_encode($user));
        if (substr($uResp, 0, 3) !== '334') {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP Username rejected: ' . trim($uResp)];
        }
        $pResp = $send(base64_encode($pass));
        if (substr($pResp, 0, 3) !== '235') {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP Authentication failed: ' . trim($pResp)];
        }
    }

    $mf = $send("MAIL FROM:<{$fromAddress}>");
    if (substr($mf, 0, 3) !== '250') {
        if ($user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL) && $user !== $fromAddress) {
            $mf = $send("MAIL FROM:<{$user}>");
        }
    }
    if (substr($mf, 0, 3) !== '250') {
        fclose($socket);
        return ['ok' => false, 'message' => 'MAIL FROM error: ' . trim($mf)];
    }

    $rcpt = $send("RCPT TO:<{$to}>");
    if (substr($rcpt, 0, 3) !== '250') {
        fclose($socket);
        return ['ok' => false, 'message' => 'RCPT TO error: ' . trim($rcpt)];
    }

    $data = $send('DATA');
    if (substr($data, 0, 3) !== '354') {
        fclose($socket);
        return ['ok' => false, 'message' => 'DATA initiation error: ' . trim($data)];
    }

    $domain = (string) (parse_url((string) setting('license_public_url', 'https://license.zoomnearby.com'), PHP_URL_HOST) ?: 'zoomnearby.com');
    $msgId = '<' . md5(uniqid((string) mt_rand(), true)) . '@' . $domain . '>';

    $msg = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromAddress}>\r\n";
    $msg .= "Reply-To: <{$fromAddress}>\r\n";
    $msg .= "Return-Path: <{$fromAddress}>\r\n";
    $msg .= "Sender: <{$fromAddress}>\r\n";
    $msg .= "To: <{$to}>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: {$msgId}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";

    if (!empty($attachments)) {
        $boundaryMixed = '=_mix_' . md5(microtime() . mt_rand());
        $boundaryAlt = '=_alt_' . md5(microtime() . mt_rand());

        $msg .= "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"\r\n";
        $msg .= "X-Mailer: LicenseManager-SMTP/2.0\r\n\r\n";

        $msg .= "--{$boundaryMixed}\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";

        $msg .= "--{$boundaryAlt}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($textBody)) . "\r\n";

        $msg .= "--{$boundaryAlt}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $msg .= "--{$boundaryAlt}--\r\n";

        foreach ($attachments as $att) {
            $filename = preg_replace('/[^\w\.-]/', '_', (string) ($att['filename'] ?? 'attachment.dat'));
            $mime = (string) ($att['mime'] ?? 'application/octet-stream');
            $data = (string) ($att['content'] ?? '');

            $msg .= "--{$boundaryMixed}\r\n";
            $msg .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
            $msg .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode($data)) . "\r\n";
        }
        $msg .= "--{$boundaryMixed}--\r\n";
    } else {
        $boundary = '=_' . md5(microtime() . mt_rand());
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $msg .= "X-Mailer: LicenseManager-SMTP/2.0\r\n\r\n";

        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($textBody)) . "\r\n";

        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $msg .= "--{$boundary}--\r\n";
    }

    $msg .= ".";

    $sendResult = $send($msg);
    $send('QUIT');
    fclose($socket);

    if (substr($sendResult, 0, 3) === '250') {
        return ['ok' => true, 'message' => 'Email sent via SMTP successfully.'];
    }

    return ['ok' => false, 'message' => 'SMTP body send error: ' . trim($sendResult)];
}

/**
 * Format and send the customer purchase delivery email containing all license keys, instructions,
 * and optional invoice attachment.
 */
function lm_send_license_delivery_email(array $order, array $licenses, array $attachments = []): array
{
    $content = lm_license_delivery_content($order, $licenses, (string) setting('site_name', 'ZoomNearby SaaS Platform'), !empty($attachments));
    return lm_send_mail((string) ($order['email'] ?? ''), $content['subject'], $content['html'], $content['text'], $attachments);
}

function lm_license_delivery_content(array $order, array $licenses, string $siteName, bool $hasAttachment = false): array
{
    $reference = (string) ($order['reference'] ?? '');
    $domain = (string) ($order['domain'] ?? $order['target_domain'] ?? '');
    $amount = number_format((float) ($order['amount'] ?? 0), 2).' '.strtoupper($order['currency'] ?? 'USD');
    $payment = match ($order['status'] ?? '') {
        'paid' => 'Payment verified',
        'redeemed' => 'Purchase code redeemed',
        default => 'Completed by administrator (payment not verified by gateway)',
    };
    $subject = preg_replace('/[\r\n]+/', ' ', 'Your Licenses & Downloads — '.$siteName.' [Order '.$reference.']');
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'.e($subject).'</title></head>'
        .'<body style="margin:0;padding:24px 12px;background:#f8fafc;font-family:Arial,sans-serif;color:#334155;line-height:1.6">'
        .'<div style="max-width:680px;margin:auto;background:white;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">'
        .'<div style="background:#312e81;color:white;padding:24px"><h1 style="margin:0;font-size:22px">'.e($siteName).'</h1><p style="margin:6px 0 0">Your licenses and product downloads</p></div>'
        .'<div style="padding:24px"><h2 style="margin-top:0">Order completed</h2><p>Your order has been fulfilled. Each ordered product has its own license key and private download link below.</p>'
        .'<table style="width:100%;text-align:left;font-size:14px">';
    foreach (['Order reference' => $reference, 'Order total' => $amount, 'Payment' => $payment, 'Installation domain' => $domain ?: 'Not specified', 'Customer email' => (string) ($order['email'] ?? '')] as $label => $value) {
        $html .= '<tr><th style="padding:6px 8px 6px 0;vertical-align:top">'.e($label).'</th><td style="overflow-wrap:anywhere">'.e($value).'</td></tr>';
    }
    $html .= '</table>';
    if ($hasAttachment) {
        $html .= '<div style="margin:16px 0;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534"><strong>📎 Invoice Attached:</strong> A formal PDF tax invoice (<code>Invoice-'.e($reference ?: 'Order').'.pdf</code>) is attached to this email for your accounting records.</div>';
    }
    $html .= '<h2 style="font-size:18px">Ordered products, licenses &amp; downloads</h2>';
    $text = $siteName."\nOrder completed\nOrder reference: ".$reference."\nOrder total: ".$amount."\nPayment: ".$payment."\nInstallation domain: ".$domain."\n";
    if ($hasAttachment) {
        $text .= "Invoice: A formal PDF tax invoice (Invoice-".$reference.".pdf) is attached to this email.\n";
    }
    $text .= "\n";
    $hasCore = false;
    $hasModules = false;
    foreach ($licenses as $item) {
        $name = (string) ($item['name'] ?? $item['slug'] ?? 'Software license');
        $key = (string) ($item['license_key'] ?? '');
        $validity = $item['valid_until'] ?: 'Unlimited';
        $url = (string) ($item['download_url'] ?? '');
        $hasCore = $hasCore || ($item['slug'] ?? '') === 'core';
        $hasModules = $hasModules || ($item['slug'] ?? '') !== 'core';
        $html .= '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:14px"><h3 style="margin:0 0 10px;font-size:16px">'.e($name).'</h3>'
            .'<p style="margin:0 0 8px">License key: <strong style="font-family:monospace;overflow-wrap:anywhere">'.e($key).'</strong><br>Validity: '.e($validity).'</p>';
        $text .= $name."\nLicense key: ".$key."\nValidity: ".$validity."\n";
        if ($url !== '') {
            $html .= '<a href="'.e($url).'" style="display:inline-block;background:#4f46e5;color:white;text-decoration:none;padding:10px 16px;border-radius:6px">Download '.e($name).'</a>';
            $text .= 'Download: '.$url."\n";
        }
        if (($item['package_available'] ?? true) === false) {
            $html .= '<p style="font-size:12px;color:#92400e;margin-bottom:0">The package is being prepared. This same link will work when the file is available. Contact support if you need help.</p>';
            $text .= "Package is being prepared. The same link will work when the file is available.\n";
        }
        $html .= '</div>';
        $text .= "\n";
    }
    $steps = [];
    if ($hasCore) $steps[] = 'Download and install the Core SaaS script on '.$domain.', then enter its Core license key in the installer.';
    if ($hasModules) $steps[] = 'Download each add-on package and use its matching license key in SuperAdmin → Modules.';
    $steps[] = 'Keep these keys and download links private. Downloads require a completed order and an active, unexpired license.';
    $html .= '<h3>Installation instructions</h3><ol>';
    $text .= "Installation instructions:\n";
    foreach ($steps as $step) {
        $html .= '<li>'.e($step).'</li>';
        $text .= '- '.$step."\n";
    }
    $html .= '</ol></div></div></body></html>';
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/**
 * Dynamically determine the App Builder features list based on the purchased plan / bundle / limits.
 */
function lm_get_builder_features_for_plan(?string $plan, ?int $builderLimit, ?array $customFeatures = null, ?string $bundleSlug = null): array
{
    $p = strtolower(trim((string)$plan));
    $limit = $builderLimit !== null ? (int)$builderLimit : 10;
    $features = [];

    // Base White-Label Customization (all plans with builder)
    $features[] = 'Custom App Name & Application Identifier';
    $features[] = 'Custom Logo, App Icons & Splash Screen';
    $features[] = 'Custom Colors & White-Label Brand Palette';
    $features[] = 'Package & App Configuration (Backend API Endpoint)';

    // Platform Binaries
    $features[] = 'Android Build (Production APK & Play Store AAB)';
    $features[] = 'Web Build (Progressive Web App - PWA distribution)';
    $features[] = 'Windows Build (Native Desktop .exe installer)';

    if ($p === 'extended' || $p === 'enterprise' || $p === 'unlimited' || $limit === -1) {
        $features[] = 'Apple iOS Build (Enterprise Runner archive)';
        $features[] = 'Custom Flutter Source Code Upload (ZIP)';
        $features[] = 'Unlimited Monthly Cloud Build Packs (No build counter)';
        $features[] = 'Dedicated High-Speed Parallel Cloud Runner Queues';
        $features[] = '100% White-Label Application with Zero Developer Watermarks';
    } elseif ($p === 'pro' || $p === 'professional' || $limit >= 30) {
        $features[] = '30 Monthly Cloud Build Packs with Automatic Monthly Renewal';
        $features[] = 'Automated Cloud Compilation via GitHub Actions';
        $features[] = 'Instant Downloadable Binary Artifacts & Email Delivery';
    } elseif (!empty($bundleSlug)) {
        $features[] = ($limit > 0 ? "{$limit} Monthly Cloud Build Packs with Monthly Renewal" : 'Cloud Build Packs included with your Bundle');
        $features[] = 'Multi-Vertical Module Integration (Core, Lead CRM & Business Verticals)';
        $features[] = 'Automated Cloud Compilation Pipeline & Instant Email Delivery';
    } else {
        $features[] = ($limit > 0 ? "{$limit} Monthly Cloud Build Packs with Monthly Renewal" : '10 Monthly Cloud Build Packs');
        $features[] = 'Automated Cloud Compilation Pipeline & Instant Email Delivery';
    }

    // Merge any custom features defined on the product or bundle
    if (!empty($customFeatures) && is_array($customFeatures)) {
        foreach ($customFeatures as $cf) {
            $cfStr = trim((string)$cf);
            if ($cfStr !== '' && !in_array($cfStr, $features, true) && (stripos($cfStr, 'app') !== false || stripos($cfStr, 'build') !== false || stripos($cfStr, 'white-label') !== false || stripos($cfStr, 'apk') !== false)) {
                $features[] = $cfStr;
            }
        }
    }

    return array_values(array_unique($features));
}

/**
 * Check if an order includes App Builder access and send the App Builder Access Email.
 * Tracks delivery in payments.builder_email_sent to prevent duplicates.
 */
function lm_send_app_builder_welcome_email(PDO $pdo, array $order, array $licenses, bool $force = false): array
{
    $orderId = (int)($order['id'] ?? 0);
    $orderRef = (string)($order['reference'] ?? ('ORD-' . $orderId));
    $recipientEmail = trim((string)($order['email'] ?? ''));

    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'No valid customer email found for App Builder access.'];
    }

    // Prevent duplicate emails for the same completed order unless explicitly forced by admin
    if (!empty($order['builder_email_sent']) && !$force) {
        return ['ok' => true, 'already_sent' => true, 'message' => 'App Builder access email already sent for this order.'];
    }

    // 1. Resolve Core License and check if plan includes App Builder
    $coreLicense = null;
    $primaryLicenseKey = '';
    $plan = 'regular';
    $builderLimit = null;
    $bundleSlug = !empty($order['bundle_slug']) ? (string)$order['bundle_slug'] : null;

    // Check licenses list
    foreach ($licenses as $lic) {
        $slug = (string)($lic['slug'] ?? '');
        if ($slug === 'core' || $slug === 'main' || $slug === 'pos' || $slug === 'zoom-pos') {
            $coreLicense = $lic;
            $primaryLicenseKey = (string)($lic['license_key'] ?? '');
            break;
        }
    }
    if (!$coreLicense) {
        return ['ok' => true, 'included' => false, 'message' => 'Order does not include Core SaaS Platform. App Builder is applicable for the core script only, not for modules or extensions.'];
    }

    // Query license record from DB for plan and limit details
    if (!empty($coreLicense['id'])) {
        try {
            $stLic = $pdo->prepare('SELECT plan, app_builder_monthly_limit, license_type FROM licenses WHERE id = ? LIMIT 1');
            $stLic->execute([(int)$coreLicense['id']]);
            $dbLic = $stLic->fetch(PDO::FETCH_ASSOC);
            if ($dbLic) {
                $plan = strtolower((string)($dbLic['plan'] ?? $dbLic['license_type'] ?? 'regular'));
                if ($dbLic['app_builder_monthly_limit'] !== null && $dbLic['app_builder_monthly_limit'] !== '') {
                    $builderLimit = (int)$dbLic['app_builder_monthly_limit'];
                }
            }
        } catch (Throwable $e) {}
    }

    // Check bundle limits if applicable
    $customFeatures = null;
    $planDisplayName = 'Core SaaS Platform (' . ucfirst($plan) . ' Plan)';

    if (!empty($bundleSlug)) {
        try {
            $stB = $pdo->prepare('SELECT name, app_builder_limit, custom_features FROM bundles WHERE slug = ? LIMIT 1');
            $stB->execute([$bundleSlug]);
            $bundleRow = $stB->fetch(PDO::FETCH_ASSOC);
            if ($bundleRow) {
                $planDisplayName = (string)$bundleRow['name'];
                if ($builderLimit === null && isset($bundleRow['app_builder_limit'])) {
                    $builderLimit = (int)$bundleRow['app_builder_limit'];
                }
                $customFeatures = json_decode($bundleRow['custom_features'] ?? 'null', true);
            }
        } catch (Throwable $e) {}
    } else {
        try {
            $stP = $pdo->prepare('SELECT name, app_builder_limit, custom_features FROM products WHERE slug = ? LIMIT 1');
            $stP->execute(['core']);
            $prodRow = $stP->fetch(PDO::FETCH_ASSOC);
            if ($prodRow) {
                if ($builderLimit === null && isset($prodRow['app_builder_limit'])) {
                    $builderLimit = (int)$prodRow['app_builder_limit'];
                }
                $customFeatures = json_decode($prodRow['custom_features'] ?? 'null', true);
            }
        } catch (Throwable $e) {}
    }

    // Default builderLimit based on plan tier if not explicitly set
    if ($builderLimit === null) {
        $builderLimit = ($plan === 'extended' || $plan === 'unlimited' || $plan === 'enterprise') ? -1 : 10;
    }

    // If builderLimit is explicitly 0, plan does NOT include builder
    if ($builderLimit === 0) {
        return ['ok' => true, 'included' => false, 'message' => 'Purchased plan does not include App Builder access (limit is 0).'];
    }

    // 2. Generate Builder Access URL with automatic 1-click license authentication
    $siteName = (string) setting('site_name', 'ZoomNearby SaaS Platform');
    $builderBaseUrl = rtrim((string) setting('app_builder_url', setting('license_public_url', 'https://license.zoomnearby.com') . '/app-builder/'), '/');
    if ($builderBaseUrl === '' || $builderBaseUrl === '/app-builder') {
        $builderBaseUrl = 'https://license.zoomnearby.com/app-builder';
    }
    $builderUrl = $builderBaseUrl . '/?key=' . urlencode($primaryLicenseKey);

    // 3. Resolve Dynamic Feature List for this specific plan
    $featuresList = lm_get_builder_features_for_plan($plan, $builderLimit, $customFeatures, $bundleSlug);

    // 4. Construct Email Subject & Body
    $subject = "🔨 Your plan includes App Builder access — White-Label App Creation [Order {$orderRef}]";

    $featuresHtml = '';
    $featuresText = '';
    foreach ($featuresList as $feat) {
        $featuresHtml .= '<li style="margin-bottom:8px;color:#1e293b;font-weight:600;"><span style="color:#10b981;margin-right:6px;">✔</span>' . e($feat) . '</li>';
        $featuresText .= "- " . $feat . "\n";
    }

    $maskedLicense = strlen($primaryLicenseKey) > 10 ? (substr($primaryLicenseKey, 0, 11) . '•••••') : $primaryLicenseKey;

    $html = "
    <!doctype html>
    <html lang='en'>
    <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width,initial-scale=1'>
      <title>" . e($subject) . "</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 30px 10px; color: #1e293b; line-height: 1.6; }
        .card { max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #312e81 100%); padding: 36px 28px; text-align: center; color: #ffffff; }
        .content { padding: 32px 28px; }
        .badge { display: inline-block; padding: 4px 14px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,0.2); color: #ffffff; margin-bottom: 12px; letter-spacing: 0.5px; text-transform: uppercase; }
        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 14px 34px; border-radius: 12px; font-weight: 700; font-size: 15px; margin: 24px 0 12px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .plan-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin: 20px 0; }
        .plan-box table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .plan-box td { padding: 6px 0; }
        .plan-box td:first-child { color: #64748b; font-weight: 600; width: 40%; }
        .plan-box td:last-child { color: #0f172a; font-weight: 700; text-align: right; }
        .features-card { background: #fdfdfd; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .features-card h3 { margin: 0 0 14px; font-size: 15px; font-weight: 800; color: #0f172a; }
        .features-card ul { list-style: none; padding: 0; margin: 0; }
        .note { font-size: 12.5px; color: #64748b; margin-top: 24px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 18px; }
        .footer { padding: 22px; text-align: center; font-size: 12px; color: #94a3b8; background: #f8fafc; border-top: 1px solid #f1f5f9; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='header'>
          <div class='badge'>White-Label App Access</div>
          <h1 style='margin:0;font-size:23px;font-weight:800;'>Your Plan Includes App Builder Access</h1>
          <p style='margin:8px 0 0;font-size:14px;opacity:0.9;'>Create and compile your custom branded Flutter apps</p>
        </div>
        <div class='content'>
          <p style='margin-top:0;font-size:15px;'>Hello,</p>
          <p style='font-size:14.5px;color:#334155;'>
            Your plan includes App Builder access. You can now use our App Builder to create your own white-label application with your branding.
          </p>
          <p style='font-size:14px;color:#475569;'>
            The builder allows you to configure your app branding and settings according to the features included in your purchased plan:
          </p>

          <div class='plan-box'>
            <table>
              <tr><td>Purchased Plan</td><td>" . e($planDisplayName) . "</td></tr>
              <tr><td>Order Reference</td><td><code style='color:#4f46e5;'>" . e($orderRef) . "</code></td></tr>
              <tr><td>Authorized License</td><td><code style='color:#0f172a;'>" . e($maskedLicense) . "</code></td></tr>
              <tr><td>App Builder Quota</td><td>" . ($builderLimit === -1 ? '<strong>Unlimited Monthly Builds</strong>' : (e($builderLimit) . ' Monthly Build Packs')) . "</td></tr>
            </table>
          </div>

          <div class='features-card'>
            <h3>Your plan includes:</h3>
            <ul>
              {$featuresHtml}
            </ul>
          </div>

          <div style='text-align:center;'>
            <a href='" . e($builderUrl) . "' class='btn'>🔨 Open App Builder Dashboard &rarr;</a>
            <div style='font-size:12px;color:#64748b;margin-top:6px;'>
              Direct 1-Click Builder URL:<br>
              <a href='" . e($builderUrl) . "' style='color:#4f46e5;word-break:break-all;'>" . e($builderUrl) . "</a>
            </div>
          </div>

          <p class='note'>
            Your builder access is linked to your license and registered account. You can log in at any time using your purchase license key.
          </p>
        </div>
        <div class='footer'>
          &copy; " . date('Y') . " " . e($siteName) . ". All rights reserved.
        </div>
      </div>
    </body>
    </html>
    ";

    $text = "Your plan includes App Builder access. You can now use our App Builder to create your own white-label application with your branding.\n\n"
          . "App Builder: {$builderUrl}\n\n"
          . "Plan: {$planDisplayName}\n"
          . "Order: {$orderRef}\n"
          . "License: {$primaryLicenseKey}\n\n"
          . "Your plan includes:\n"
          . $featuresText . "\n"
          . "Your builder access is linked to your license and registered account.\n";

    $res = lm_send_mail($recipientEmail, $subject, $html, $text);

    if (!empty($res['ok'])) {
        try {
            $pdo->prepare('UPDATE payments SET builder_email_sent = 1 WHERE id = ?')->execute([$orderId]);
        } catch (Throwable $e) {}
        return ['ok' => true, 'message' => 'App Builder access email successfully sent to ' . $recipientEmail . '.'];
    }

    return ['ok' => false, 'message' => 'Failed to send App Builder access email: ' . ($res['message'] ?? 'Unknown mail error')];
}

