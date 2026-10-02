<?php

class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['builder_license']) 
            && is_array($_SESSION['builder_license']) 
            && !empty($_SESSION['builder_license']['license_key']) 
            && is_string($_SESSION['builder_license']['license_key']);
    }

    public static function license(): ?array
    {
        return $_SESSION['builder_license'] ?? null;
    }

    public static function key(): string
    {
        return (string)($_SESSION['builder_license']['license_key'] ?? '');
    }

    public static function email(): string
    {
        $e = trim((string)($_SESSION['builder_license']['client_email'] ?? ''));
        return $e !== '' ? $e : 'licensee@zoomnearby.com';
    }

    public static function plan(): string
    {
        $p = strtolower(trim((string)($_SESSION['builder_license']['plan'] ?? 'regular')));
        return $p !== '' ? $p : 'regular';
    }

    public static function account(): ?array
    {
        return $_SESSION['builder_license']['account_details'] ?? null;
    }

    public static function autoLoginAdmin(): void
    {
        try {
            $pdo = db();
            $st = $pdo->query('
                SELECT * FROM licenses 
                WHERE (status = "active" OR status = "1")
                ORDER BY (CASE WHEN plan = "extended" OR license_type = "extended" THEN 1 ELSE 2 END) ASC, id ASC
                LIMIT 1
            ');
            $licRecord = $st ? $st->fetch() : null;
            if ($licRecord && !empty($licRecord['license_key'])) {
                self::validateAndLogin((string)$licRecord['license_key']);
                return;
            }
        } catch (Throwable $e) {}

        // Fallback default admin license
        self::validateAndLogin('6E295-65EA5-0F968-BA390');
    }

    public static function requireAuth(): void
    {
        // 1. Try GET key if passed in URL
        if (!self::check()) {
            $queryKey = trim((string)($_GET['key'] ?? $_GET['license_key'] ?? ''));
            if ($queryKey !== '') {
                $res = self::validateAndLogin($queryKey);
                if ($res['success']) {
                    return;
                }
            }
        }

        // 2. Auto-authenticate if License Manager Admin is logged in
        if (!self::check() && !empty($_SESSION['lm_admin'])) {
            self::autoLoginAdmin();
            if (self::check()) {
                return;
            }
        }

        // 3. Still unauthenticated: redirect to login
        if (!self::check()) {
            if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                json_out(401, ['success' => false, 'message' => 'Valid license authentication required.']);
            }
            $target = 'index.php?auth=required';
            if (!empty($_SERVER['QUERY_STRING'])) {
                $target .= '&' . $_SERVER['QUERY_STRING'];
            }
            header('Location: ' . $target);
            exit;
        }
    }

    /**
     * Validate license key against existing license system.
     * Reuses the existing license database and verification endpoints.
     */
    public static function validateAndLogin(string $licenseKey): array
    {
        $key = strtoupper(trim($licenseKey));
        if ($key === '') {
            return ['success' => false, 'message' => 'Please enter your license key.'];
        }

        $pdo = db();

        // 1. Check local licenses table (standalone license server DB or SaaS DB)
        $licenseRecord = null;
        try {
            $stmt = $pdo->prepare('SELECT * FROM licenses WHERE UPPER(license_key) = ? LIMIT 1');
            $stmt->execute([$key]);
            $licenseRecord = $stmt->fetch();
        } catch (Throwable $e) {
            // licenses table check fallback
        }

        // 2. If not found locally, verify via Central License Server API
        if (!$licenseRecord) {
            $serverUrl = setting('license_server_url', 'https://license.zoomnearby.com');
            $secret = setting('license_server_secret', 'N4jJ8R2pyZWTpp4oghrq');

            $ch = curl_init(rtrim($serverUrl, '/') . '/api/verify.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Server-Secret: ' . $secret,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'license_key' => $key,
                    'product_slug' => 'core',
                    'domain' => strtolower($_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com'),
                ]),
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $raw) {
                $res = json_decode($raw, true);
                if (!empty($res['status'])) {
                    $licenseRecord = [
                        'license_key' => $key,
                        'product_slug' => 'core',
                        'client_email' => $res['client_email'] ?? ($res['email'] ?? 'licensee@zoomnearby.com'),
                        'plan' => $res['plan'] ?? 'extended',
                        'status' => 'active',
                        'valid_until' => $res['expires_at'] ?? null,
                    ];
                }
            }
        }

        if (!$licenseRecord) {
            return ['success' => false, 'message' => 'License key not recognized. Please check your purchase code.'];
        }

        // Enforce Main Core Script License authorization
        // Add-on module keys (e.g. salon, pharmacy, leadmanagement) are NOT authorized for App Builder
        $productSlug = strtolower(trim((string)($licenseRecord['product_slug'] ?? '')));
        if ($productSlug !== '' && !in_array($productSlug, ['core', 'main', 'pos', 'zoom-pos'], true)) {
            return [
                'success' => false,
                'message' => 'App Builder access is exclusively authorized for Main Core Script Licenses. This license key belongs to an add-on module (' . htmlspecialchars($productSlug) . ').'
            ];
        }

        // Status check
        $status = strtolower((string)($licenseRecord['status'] ?? 'active'));
        if (!in_array($status, ['active', '1', 1], true) && empty($licenseRecord['is_active'])) {
            return ['success' => false, 'message' => 'License is currently ' . $status . '. Please contact support.'];
        }

        // Expiry check
        if (!empty($licenseRecord['valid_until'])) {
            if (strtotime($licenseRecord['valid_until'] . ' 23:59:59 UTC') < time()) {
                return ['success' => false, 'message' => 'License expired on ' . $licenseRecord['valid_until'] . '.'];
            }
        }

        // Resolve Email & Customer info
        $email = trim((string)($licenseRecord['client_email'] ?? ''));
        if ($email === '') {
            // Check associated payment reference
            if (!empty($licenseRecord['payment_reference'])) {
                try {
                    $stPay = $pdo->prepare('SELECT email FROM payments WHERE reference = ? LIMIT 1');
                    $stPay->execute([$licenseRecord['payment_reference']]);
                    $email = (string)$stPay->fetchColumn();
                } catch (Throwable $e) {}
            }
        }

        if ($email === '') {
            $email = 'licensee@zoomnearby.com';
        }

        // Auto-fetch details from license linked account
        require_once __DIR__ . '/AccountManager.php';
        $accountDetails = AccountManager::getLinkedDetails($licenseRecord['license_key'], $email);

        // Store session
        $_SESSION['builder_license'] = [
            'id' => $licenseRecord['id'] ?? 0,
            'license_key' => $licenseRecord['license_key'],
            'product_slug' => $licenseRecord['product_slug'] ?? 'core',
            'client_email' => $email,
            'plan' => strtolower((string)($licenseRecord['plan'] ?? $licenseRecord['license_type'] ?? 'regular')),
            'valid_until' => $licenseRecord['valid_until'] ?? null,
            'authenticated_at' => time(),
            'account_details' => $accountDetails,
        ];

        return [
            'success' => true,
            'message' => 'License verified successfully.',
            'license' => $_SESSION['builder_license'],
            'account_details' => $accountDetails,
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['builder_license']);
    }
}
