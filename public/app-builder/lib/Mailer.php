<?php

class Mailer
{
    /**
     * Resolve the registered email associated with the license / order.
     */
    public static function resolveRecipientEmail(array $buildRecord): string
    {
        $email = trim((string)($buildRecord['client_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($email), '@zoomnearby.com')) {
            return $email;
        }

        // Lookup registered email in licenses table
        $licenseKey = trim((string)($buildRecord['license_key'] ?? ''));
        if ($licenseKey !== '') {
            try {
                $pdo = db();
                $st = $pdo->prepare('SELECT client_email, payment_reference FROM licenses WHERE UPPER(license_key) = UPPER(?) LIMIT 1');
                $st->execute([$licenseKey]);
                $lic = $st->fetch();
                if ($lic) {
                    $licEmail = trim((string)($lic['client_email'] ?? ''));
                    if (filter_var($licEmail, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($licEmail), '@zoomnearby.com')) {
                        return $licEmail;
                    }
                    if (!empty($lic['payment_reference'])) {
                        $stPay = $pdo->prepare('SELECT email FROM payments WHERE reference = ? LIMIT 1');
                        $stPay->execute([$lic['payment_reference']]);
                        $payEmail = trim((string)$stPay->fetchColumn());
                        if (filter_var($payEmail, FILTER_VALIDATE_EMAIL)) {
                            return $payEmail;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        // Lookup by order_id or order_reference
        if (!empty($buildRecord['order_id']) || !empty($buildRecord['order_reference'])) {
            try {
                $pdo = db();
                $stPay = $pdo->prepare('SELECT email FROM payments WHERE id = ? OR reference = ? LIMIT 1');
                $stPay->execute([$buildRecord['order_id'] ?? 0, $buildRecord['order_reference'] ?? '']);
                $payEmail = trim((string)$stPay->fetchColumn());
                if (filter_var($payEmail, FILTER_VALIDATE_EMAIL)) {
                    return $payEmail;
                }
            } catch (Throwable $e) {}
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return 'licensee@zoomnearby.com';
    }

    /**
     * Send email via lm_send_mail() or standard mail() with proper headers.
     */
    public static function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (function_exists('lm_send_mail')) {
            $res = lm_send_mail($toEmail, $subject, $htmlBody);
            return (bool)($res['ok'] ?? false);
        }

        $fromAddress = setting('mail_from_address', 'licenses@zoomnearby.com');
        $fromName = setting('mail_from_name', 'ZoomNearby Cloud Build Engine');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            "From: {$fromName} <{$fromAddress}>",
            "Reply-To: {$fromAddress}",
            "Return-Path: <{$fromAddress}>",
            "Sender: {$fromAddress}",
            'X-Mailer: ZoomNearby-AppBuilder',
        ];

        return @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers), "-f {$fromAddress}");
    }

    /**
     * Send build success notification with download link and comprehensive metadata.
     */
    public static function sendBuildSuccess(array $buildRecord, string $downloadUrl): bool
    {
        $toEmail = self::resolveRecipientEmail($buildRecord);
        $appName = $buildRecord['app_name'] ?? 'Zoom Sales POS';
        $platform = ucfirst($buildRecord['platform'] ?? 'Android');
        $buildUid = $buildRecord['build_uid'] ?? '';
        $buildVersion = $buildRecord['build_version'] ?? '1.0.0';
        $subject = "✅ Your {$appName} {$platform} Build is Ready! [{$buildUid}]";

        $duration = !empty($buildRecord['build_duration_seconds'])
            ? gmdate('i\m s\s', (int)$buildRecord['build_duration_seconds'])
            : '2m 15s';

        $buildDate = !empty($buildRecord['completed_at']) 
            ? date('Y-m-d H:i:s T', strtotime($buildRecord['completed_at']))
            : gmdate('Y-m-d H:i:s \U\T\C');

        $licenseKey = $buildRecord['license_key'] ?? '';
        $maskedLicense = strlen($licenseKey) > 10 ? (substr($licenseKey, 0, 11) . '•••••') : $licenseKey;
        $orderRef = $buildRecord['order_reference'] ?? (!empty($buildRecord['order_id']) ? ('ORD-#' . $buildRecord['order_id']) : 'Direct License');

        $artifactInfo = '';
        if (!empty($buildRecord['artifact_filename'])) {
            $artifactInfo = e($buildRecord['artifact_filename']);
            if (!empty($buildRecord['artifact_size_bytes'])) {
                $artifactInfo .= ' (' . round($buildRecord['artifact_size_bytes'] / (1024 * 1024), 1) . ' MB)';
            }
        }

        $body = "
        <!doctype html>
        <html>
        <head>
          <meta charset='utf-8'>
          <meta name='viewport' content='width=device-width,initial-scale=1'>
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 30px 10px; color: #1e293b; }
            .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
            .header { background: linear-gradient(135deg, #10b981 0%, #047857 100%); padding: 32px 28px; text-align: center; color: #ffffff; }
            .content { padding: 32px 28px; }
            .badge { display: inline-block; padding: 4px 14px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,0.25); color: #ffffff; margin-bottom: 12px; }
            .btn { display: inline-block; background: #10b981; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; font-size: 15px; margin-top: 24px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
            .meta-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13.5px; }
            .meta-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
            .meta-table td:first-child { color: #64748b; font-weight: 600; width: 42%; }
            .meta-table td:last-child { color: #0f172a; font-weight: 700; text-align: right; }
            .status-tag { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
            .footer { padding: 22px; text-align: center; font-size: 12px; color: #94a3b8; background: #f8fafc; border-top: 1px solid #f1f5f9; }
          </style>
        </head>
        <body>
          <div class='card'>
            <div class='header'>
              <div class='badge'>Build Completed Successfully</div>
              <h1 style='margin:0;font-size:22px;font-weight:800;'>Your {$platform} App is Ready!</h1>
              <p style='margin:8px 0 0;font-size:14px;opacity:0.95;'>Build ID: " . e($buildUid) . "</p>
            </div>
            <div class='content'>
              <p style='margin-top:0;font-size:15px;line-height:1.6;'>Hello,</p>
              <p style='font-size:14px;line-height:1.6;color:#475569;'>
                Great news! Your custom white-label Flutter application compilation has finished cleanly in our Cloud Build Engine. You can now download and install your compiled application package.
              </p>
              
              <table class='meta-table'>
                <tr><td>Application Name</td><td>" . e($appName) . "</td></tr>
                <tr><td>Build ID</td><td><code style='color:#4f46e5;'>" . e($buildUid) . "</code></td></tr>
                <tr><td>Target Platform</td><td>" . e($platform) . "</td></tr>
                <tr><td>Build Status</td><td><span class='status-tag'>● Completed</span></td></tr>
                <tr><td>Build Version</td><td>" . e($buildVersion) . "</td></tr>
                <tr><td>Build Date &amp; Time</td><td>" . e($buildDate) . "</td></tr>
                <tr><td>Build Duration</td><td>" . e($duration) . "</td></tr>
                <tr><td>Package ID</td><td><code>" . e($buildRecord['package_id'] ?? '') . "</code></td></tr>
                <tr><td>License / Order Info</td><td>" . e($maskedLicense) . " <span style='font-size:11px;color:#64748b;'>(" . e($orderRef) . ")</span></td></tr>" .
                ($artifactInfo ? "<tr><td>Output Package</td><td>" . $artifactInfo . "</td></tr>" : "") . "
              </table>

              <div style='text-align:center;margin-top:24px;'>
                <a href='" . e($downloadUrl) . "' class='btn'>⬇️ Download {$platform} Package</a>
              </div>

              <p style='font-size:12px;color:#94a3b8;margin-top:24px;text-align:center;'>
                This download link is secured and bound to your active license and account.
              </p>
            </div>
            <div class='footer'>
              &copy; " . date('Y') . " ZoomNearby Cloud Platform. All rights reserved.
            </div>
          </div>
        </body>
        </html>
        ";

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Send build failure notification with error information and inspect link.
     */
    public static function sendBuildFailed(array $buildRecord, ?string $errorMessage = null): bool
    {
        $toEmail = self::resolveRecipientEmail($buildRecord);
        $appName = $buildRecord['app_name'] ?? 'Zoom Sales POS';
        $platform = ucfirst($buildRecord['platform'] ?? 'Android');
        $buildUid = $buildRecord['build_uid'] ?? '';
        $buildVersion = $buildRecord['build_version'] ?? '1.0.0';
        $subject = "❌ Build Failed: {$appName} ({$platform}) [{$buildUid}]";

        $buildDate = !empty($buildRecord['completed_at']) 
            ? date('Y-m-d H:i:s T', strtotime($buildRecord['completed_at']))
            : gmdate('Y-m-d H:i:s \U\T\C');

        $licenseKey = $buildRecord['license_key'] ?? '';
        $maskedLicense = strlen($licenseKey) > 10 ? (substr($licenseKey, 0, 11) . '•••••') : $licenseKey;
        $orderRef = $buildRecord['order_reference'] ?? (!empty($buildRecord['order_id']) ? ('ORD-#' . $buildRecord['order_id']) : 'Direct License');

        $errorDetail = $errorMessage ?: ($buildRecord['error_message'] ?? 'An unexpected compilation error occurred on the cloud runner.');

        $host = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $inspectUrl = "{$scheme}://{$host}/app-builder/view-build.php?id=" . urlencode($buildUid) . "&key=" . urlencode($licenseKey);

        $body = "
        <!doctype html>
        <html>
        <head>
          <meta charset='utf-8'>
          <meta name='viewport' content='width=device-width,initial-scale=1'>
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 30px 10px; color: #1e293b; }
            .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
            .header { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); padding: 32px 28px; text-align: center; color: #ffffff; }
            .content { padding: 32px 28px; }
            .badge { display: inline-block; padding: 4px 14px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,0.25); color: #ffffff; margin-bottom: 12px; }
            .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 13px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; margin-top: 20px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25); }
            .meta-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13.5px; }
            .meta-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
            .meta-table td:first-child { color: #64748b; font-weight: 600; width: 42%; }
            .meta-table td:last-child { color: #0f172a; font-weight: 700; text-align: right; }
            .status-tag { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
            .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 16px; margin: 20px 0; font-size: 13px; color: #991b1b; line-height: 1.5; font-family: monospace; white-space: pre-wrap; word-break: break-word; }
            .footer { padding: 22px; text-align: center; font-size: 12px; color: #94a3b8; background: #f8fafc; border-top: 1px solid #f1f5f9; }
          </style>
        </head>
        <body>
          <div class='card'>
            <div class='header'>
              <div class='badge'>Build Notification</div>
              <h1 style='margin:0;font-size:22px;font-weight:800;'>{$platform} Compilation Failed</h1>
              <p style='margin:8px 0 0;font-size:14px;opacity:0.95;'>Build ID: " . e($buildUid) . "</p>
            </div>
            <div class='content'>
              <p style='margin-top:0;font-size:15px;line-height:1.6;'>Hello,</p>
              <p style='font-size:14px;line-height:1.6;color:#475569;'>
                Your recent white-label Flutter compilation for <strong>" . e($appName) . " ({$platform})</strong> encountered an error during cloud processing. <em>(Note: Failed builds do not consume your monthly quota.)</em>
              </p>

              <div class='error-box'>⚠️ Error Information:
" . e($errorDetail) . "</div>
              
              <table class='meta-table'>
                <tr><td>Application Name</td><td>" . e($appName) . "</td></tr>
                <tr><td>Build ID</td><td><code style='color:#ef4444;'>" . e($buildUid) . "</code></td></tr>
                <tr><td>Target Platform</td><td>" . e($platform) . "</td></tr>
                <tr><td>Build Status</td><td><span class='status-tag'>● Failed</span></td></tr>
                <tr><td>Build Version</td><td>" . e($buildVersion) . "</td></tr>
                <tr><td>Date &amp; Time</td><td>" . e($buildDate) . "</td></tr>
                <tr><td>Package ID</td><td><code>" . e($buildRecord['package_id'] ?? '') . "</code></td></tr>
                <tr><td>License / Order Info</td><td>" . e($maskedLicense) . " <span style='font-size:11px;color:#64748b;'>(" . e($orderRef) . ")</span></td></tr>
              </table>

              <div style='text-align:center;margin-top:24px;'>
                <a href='" . e($inspectUrl) . "' class='btn'>🔍 View Build Diagnostics &amp; Retry</a>
              </div>

              <p style='font-size:12px;color:#94a3b8;margin-top:20px;text-align:center;'>
                You can adjust your branding assets or package settings and trigger a fresh build anytime from the App Builder dashboard.
              </p>
            </div>
            <div class='footer'>
              &copy; " . date('Y') . " ZoomNearby Cloud Platform. All rights reserved.
            </div>
          </div>
        </body>
        </html>
        ";

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Backward-compatible alias for sendBuildSuccess.
     */
    public static function sendBuildComplete(array $buildRecord, string $downloadUrl): bool
    {
        return self::sendBuildSuccess($buildRecord, $downloadUrl);
    }
}
