<?php

/**
 * AccountManager
 * Automatically resolves and pre-fills white-label branding details
 * from the license-linked account (companies, users, licenses, app_builds, payments).
 */
class AccountManager
{
    /**
     * Resolve account details linked to a validated license key and/or client email.
     *
     * @param string $licenseKey
     * @param string|null $clientEmail
     * @return array
     */
    public static function getLinkedDetails(?string $licenseKey = '', ?string $clientEmail = null): array
    {
        $details = [];
        $key = strtoupper(trim((string)$licenseKey));
        $clientEmail = trim((string)$clientEmail);

        try {
            $pdo = db();
        } catch (Throwable $e) {
            $pdo = null;
        }

        if (!$pdo) {
            return self::buildFinalDefaults($key, $details, $clientEmail, null, null, null);
        }

        // -------------------------------------------------------------
        // SOURCE 1: Check existing customized builds for this license
        // -------------------------------------------------------------
        $lastBuild = null;
        try {
            $st = $pdo->prepare('SELECT * FROM app_builds WHERE UPPER(license_key) = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$key]);
            $lastBuild = $st->fetch(PDO::FETCH_ASSOC);

            if ($lastBuild) {
                if (!empty($lastBuild['app_name']) && is_scalar($lastBuild['app_name'])) $details['app_name'] = trim((string)$lastBuild['app_name']);
                if (!empty($lastBuild['server_url']) && is_scalar($lastBuild['server_url'])) $details['server_url'] = trim((string)$lastBuild['server_url']);
                if (!empty($lastBuild['package_id']) && is_scalar($lastBuild['package_id'])) $details['package_id'] = trim((string)$lastBuild['package_id']);
                if (!empty($lastBuild['primary_color']) && is_scalar($lastBuild['primary_color'])) $details['primary_color'] = trim((string)$lastBuild['primary_color']);

                if (!empty($lastBuild['branding_json'])) {
                    $bJson = is_string($lastBuild['branding_json'])
                        ? json_decode($lastBuild['branding_json'], true)
                        : $lastBuild['branding_json'];

                    if (is_array($bJson)) {
                        $fields = [
                            'company_name', 'product_name', 'app_name', 'short_name',
                            'display_name', 'org_name', 'copyright', 'support_email',
                            'support_phone', 'website_url', 'server_url', 'package_id',
                            'primary_color', 'secondary_color', 'accent_color',
                            'bg_color', 'sidebar_color', 'text_color'
                        ];
                        foreach ($fields as $f) {
                            if (!empty($bJson[$f]) && empty($details[$f]) && is_scalar($bJson[$f])) {
                                $details[$f] = trim((string)$bJson[$f]);
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently continue if app_builds query fails
        }

        // -------------------------------------------------------------
        // SOURCE 2: Check licenses table (bound_domain, client_email, branding_json)
        // -------------------------------------------------------------
        $licRecord = null;
        try {
            $st = $pdo->prepare('SELECT * FROM licenses WHERE UPPER(license_key) = ? LIMIT 1');
            $st->execute([$key]);
            $licRecord = $st->fetch(PDO::FETCH_ASSOC);

            if ($licRecord) {
                if (empty($clientEmail) && !empty($licRecord['client_email'])) {
                    $clientEmail = trim((string)$licRecord['client_email']);
                }

                if (!empty($licRecord['branding_json'])) {
                    $bJson = is_string($licRecord['branding_json'])
                        ? json_decode($licRecord['branding_json'], true)
                        : $licRecord['branding_json'];

                    if (is_array($bJson)) {
                        foreach ($bJson as $k => $v) {
                            if (!empty($v) && empty($details[$k]) && is_scalar($v)) {
                                $details[$k] = trim((string)$v);
                            }
                        }
                    }
                }

                $bound = trim((string)($licRecord['bound_domain'] ?? ''));
                if ($bound !== '') {
                    $domainUrl = 'https://' . ltrim($bound, '/');
                    if (empty($details['server_url'])) $details['server_url'] = $domainUrl;
                    if (empty($details['website_url'])) $details['website_url'] = $domainUrl;
                }
            }
        } catch (Throwable $e) {}

        // -------------------------------------------------------------
        // SOURCE 3: Check linked company & store records (SaaS Database)
        // -------------------------------------------------------------
        $company = null;
        try {
            // A. Match by activation_key = license_key
            $st = $pdo->prepare('SELECT * FROM companies WHERE activation_key = ? LIMIT 1');
            $st->execute([$key]);
            $company = $st->fetch(PDO::FETCH_ASSOC);

            // B. Match by company email = clientEmail
            if (!$company && !empty($clientEmail)) {
                $st = $pdo->prepare('SELECT * FROM companies WHERE email = ? LIMIT 1');
                $st->execute([$clientEmail]);
                $company = $st->fetch(PDO::FETCH_ASSOC);
            }

            // C. Match by user login / user email = clientEmail
            if (!$company && !empty($clientEmail)) {
                $st = $pdo->prepare('SELECT c.* FROM companies c JOIN users u ON u.company_id = c.id WHERE u.email = ? LIMIT 1');
                $st->execute([$clientEmail]);
                $company = $st->fetch(PDO::FETCH_ASSOC);
            }

            // D. Match by email prefix (e.g. "ramu" from "ramu@gmail.com" matches "ramu@zoomnearby.com")
            if (!$company && !empty($clientEmail)) {
                $emailPrefix = explode('@', $clientEmail)[0];
                if (strlen($emailPrefix) >= 3) {
                    $st = $pdo->prepare('
                        SELECT c.* FROM companies c 
                        LEFT JOIN users u ON u.company_id = c.id 
                        WHERE c.email LIKE ? OR u.email LIKE ?
                        LIMIT 1
                    ');
                    $st->execute([$emailPrefix . '@%', $emailPrefix . '@%']);
                    $company = $st->fetch(PDO::FETCH_ASSOC);
                }
            }

            // E. Match by custom domain or subdomain slug
            if (!$company && !empty($licRecord['bound_domain'])) {
                $bound = strtolower(trim($licRecord['bound_domain']));
                $subdomain = explode('.', $bound)[0];
                $st = $pdo->prepare('SELECT * FROM companies WHERE custom_domain = ? OR slug = ? LIMIT 1');
                $st->execute([$bound, $subdomain]);
                $company = $st->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {}

        // Populate fields from resolved company
        if ($company) {
            // Company Name
            if (empty($details['company_name'])) {
                $details['company_name'] = !empty($company['name']) ? $company['name'] : ($company['legal_name'] ?? '');
            }

            // Customer Support Email
            if (empty($details['support_email'])) {
                $details['support_email'] = !empty($company['email']) ? $company['email'] : ($clientEmail ?? '');
            }

            // Customer Support Phone / WhatsApp
            if (empty($details['support_phone']) && !empty($company['phone'])) {
                $rawPhone = trim($company['phone']);
                if (!str_starts_with($rawPhone, '+') && preg_match('/^[0-9]{10,14}$/', $rawPhone)) {
                    $rawPhone = '+' . $rawPhone;
                }
                $details['support_phone'] = $rawPhone;
            }

            // Official Website URL
            if (empty($details['website_url']) && !empty($company['website'])) {
                $web = trim($company['website']);
                if (!str_starts_with($web, 'http://') && !str_starts_with($web, 'https://')) {
                    $web = 'https://' . $web;
                }
                $details['website_url'] = $web;
            }

            // Application Name & App Display Name
            if (empty($details['app_name'])) {
                $details['app_name'] = !empty($company['trade_name']) ? $company['trade_name'] : ($company['name'] . ' POS');
            }
            if (empty($details['display_name'])) {
                $details['display_name'] = !empty($company['trade_name']) ? $company['trade_name'] : $company['name'];
            }

            // Product Name
            if (empty($details['product_name'])) {
                $details['product_name'] = $company['name'] . ' POS';
            }

            // Short App Name
            if (empty($details['short_name'])) {
                $base = !empty($company['trade_name']) ? $company['trade_name'] : $company['name'];
                $clean = preg_replace('/[^a-zA-Z0-9]+/', '', $base);
                if (strlen($clean) >= 2) {
                    $details['short_name'] = substr($clean, 0, 16);
                }
            }

            // Backend Server API URL
            if (empty($details['server_url'])) {
                $host = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';

                if (!empty($company['custom_domain'])) {
                    $details['server_url'] = "{$scheme}://{$company['custom_domain']}";
                } elseif (!empty($company['slug'])) {
                    $details['server_url'] = "{$scheme}://{$company['slug']}.{$host}";
                } else {
                    $details['server_url'] = "{$scheme}://{$host}";
                }
            }

            // Primary Color
            if (empty($details['primary_color']) && !empty($company['primary_color'])) {
                $details['primary_color'] = $company['primary_color'];
            }
        }

        // -------------------------------------------------------------
        // SOURCE 4: Check payments table (Standalone License Server)
        // -------------------------------------------------------------
        if ($licRecord && !empty($licRecord['payment_reference'])) {
            try {
                $st = $pdo->prepare('SELECT * FROM payments WHERE reference = ? LIMIT 1');
                $st->execute([$licRecord['payment_reference']]);
                $pay = $st->fetch(PDO::FETCH_ASSOC);

                if ($pay) {
                    if (empty($details['support_email']) && !empty($pay['email'])) {
                        $details['support_email'] = $pay['email'];
                    }

                    if (!empty($pay['target_domain'])) {
                        $tDom = 'https://' . ltrim($pay['target_domain'], '/');
                        if (empty($details['server_url'])) $details['server_url'] = $tDom;
                        if (empty($details['website_url'])) $details['website_url'] = $tDom;
                    }

                    if (!empty($pay['checkout_json'])) {
                        $chJson = json_decode($pay['checkout_json'], true);
                        if (!empty($chJson['title']) && empty($details['product_name'])) {
                            $details['product_name'] = $chJson['title'];
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        $company = is_array($company) ? $company : null;
        $lastBuild = is_array($lastBuild) ? $lastBuild : null;
        $licRecord = is_array($licRecord) ? $licRecord : null;

        return self::buildFinalDefaults($key, $details, $clientEmail, $company, $lastBuild, $licRecord);
    }

    /**
     * Build clean default branding array with strict scalar types.
     */
    public static function buildFinalDefaults(
        string $key = '',
        array $details = [],
        ?string $clientEmail = null,
        mixed $company = null,
        mixed $lastBuild = null,
        mixed $licRecord = null
    ): array {
        $company = is_array($company) ? $company : null;
        $lastBuild = is_array($lastBuild) ? $lastBuild : null;
        $licRecord = is_array($licRecord) ? $licRecord : null;
        $defaultHost = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
        $defaultScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $defaultServerUrl = "{$defaultScheme}://{$defaultHost}";

        $companyName = !empty($details['company_name']) && is_scalar($details['company_name']) ? trim((string)$details['company_name']) : 'Zoom Nearby';
        $appName     = !empty($details['app_name']) && is_scalar($details['app_name']) ? trim((string)$details['app_name']) : ($companyName . ' POS');
        $productName = !empty($details['product_name']) && is_scalar($details['product_name']) ? trim((string)$details['product_name']) : 'Zoom Sales CRM';
        $displayName = !empty($details['display_name']) && is_scalar($details['display_name']) ? trim((string)$details['display_name']) : $appName;

        $shortName = !empty($details['short_name']) && is_scalar($details['short_name']) ? trim((string)$details['short_name']) : preg_replace('/[^a-zA-Z0-9]+/', '', $appName);
        if (strlen($shortName) < 2) $shortName = 'ZoomPOS';

        $packageSlug = preg_replace('/[^a-z0-9]+/', '', strtolower($shortName));
        if (strlen($packageSlug) < 2) $packageSlug = 'zoompos';
        $defaultPackageId = 'com.zoomnearby.' . $packageSlug;

        $pkgId = !empty($details['package_id']) && is_scalar($details['package_id']) ? trim((string)$details['package_id']) : $defaultPackageId;
        $srvUrl = !empty($details['server_url']) && is_scalar($details['server_url']) ? trim((string)$details['server_url']) : $defaultServerUrl;
        $webUrl = !empty($details['website_url']) && is_scalar($details['website_url']) ? trim((string)$details['website_url']) : "https://{$defaultHost}";
        $supEmail = !empty($details['support_email']) && is_scalar($details['support_email']) ? trim((string)$details['support_email']) : ($clientEmail ?: 'support@zoomnearby.com');
        $supPhone = !empty($details['support_phone']) && is_scalar($details['support_phone']) ? trim((string)$details['support_phone']) : '+918535075196';
        $copyright = !empty($details['copyright']) && is_scalar($details['copyright']) ? trim((string)$details['copyright']) : ('Copyright (C) ' . date('Y') . ' ' . $companyName . '. All rights reserved.');
        $priColor = !empty($details['primary_color']) && is_scalar($details['primary_color']) ? trim((string)$details['primary_color']) : '#4F46E5';

        $final = [
            'license_key'    => $key,
            'company_name'   => $companyName,
            'product_name'   => $productName,
            'app_name'       => $appName,
            'short_name'     => $shortName,
            'display_name'   => $displayName,
            'package_id'     => $pkgId,
            'server_url'     => $srvUrl,
            'website_url'    => $webUrl,
            'support_email'  => $supEmail,
            'support_phone'  => $supPhone,
            'copyright'      => $copyright,
            'primary_color'  => $priColor,
            'is_auto_filled' => !empty($company) || !empty($lastBuild) || !empty($licRecord['branding_json']),
            'source_matched' => !empty($lastBuild) ? 'Previous Build' : (!empty($company) ? 'Linked Business Account' : 'License Record'),
        ];

        return $final;
    }
}
