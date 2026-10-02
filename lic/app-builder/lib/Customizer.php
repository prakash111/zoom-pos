<?php

require_once __DIR__ . '/IconGenerator.php';

class Customizer
{
    /**
     * Validate, sanitize, and process the complete white-label branding configuration.
     * Generates all required icons, manifests, and the branding bundle.
     */
    public static function validateConfiguration(array $input, array $files = [], string $buildUid = ''): array
    {
        $errors = [];
        $buildUid = $buildUid ?: ('bld_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12));

        // 1. Basic Branding Fields
        $companyName = trim((string)($input['company_name'] ?? 'Zoom Nearby'));
        if (strlen($companyName) < 2 || strlen($companyName) > 100) {
            $errors[] = 'Company Name must be between 2 and 100 characters.';
        }

        $productName = trim((string)($input['product_name'] ?? 'Zoom Sales CRM'));
        if (strlen($productName) < 2 || strlen($productName) > 100) {
            $errors[] = 'Product Name must be between 2 and 100 characters.';
        }

        $appName = trim((string)($input['app_name'] ?? 'Zoom Sales POS'));
        if (strlen($appName) < 2 || strlen($appName) > 60) {
            $errors[] = 'Application Name must be between 2 and 60 characters.';
        }

        $shortName = trim((string)($input['short_name'] ?? ''));
        if ($shortName === '') {
            $shortName = preg_replace('/[^a-zA-Z0-9]+/', '', $appName) ?: 'App';
        }
        if (!preg_match('/^[a-zA-Z0-9_]{2,30}$/', $shortName)) {
            $errors[] = 'Short App Name must be 2-30 alphanumeric characters (no spaces or special symbols).';
        }

        $displayName = trim((string)($input['display_name'] ?? $appName));
        if ($displayName === '') {
            $displayName = $appName;
        }

        $orgName = trim((string)($input['org_name'] ?? $companyName));
        if ($orgName === '') {
            $orgName = $companyName;
        }

        $copyright = trim((string)($input['copyright'] ?? ''));
        if ($copyright === '') {
            $copyright = 'Copyright (C) ' . date('Y') . ' ' . $companyName . '. All rights reserved.';
        }

        $supportEmail = trim((string)($input['support_email'] ?? 'support@zoomnearby.com'));
        if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Support Email must be a valid email address.';
        }

        $supportPhone = trim((string)($input['support_phone'] ?? '+918535075196'));

        $websiteUrl = rtrim(trim((string)($input['website_url'] ?? 'https://zoomnearby.com')), '/');
        if (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            $websiteUrl = 'https://zoomnearby.com';
        }

        $serverUrl = rtrim(trim((string)($input['server_url'] ?? 'https://saas.zoomnearby.com')), '/');
        if (!filter_var($serverUrl, FILTER_VALIDATE_URL) || (!str_starts_with($serverUrl, 'http://') && !str_starts_with($serverUrl, 'https://'))) {
            $errors[] = 'Server URL must be a valid HTTP/HTTPS URL.';
        }

        // Package / Application ID (e.g. com.company.pos)
        $packageId = strtolower(trim((string)($input['package_id'] ?? 'com.zoomnearby.zoompos')));
        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $packageId)) {
            $errors[] = 'Invalid Package/Application ID. Format should be like "com.company.app" with at least two dot-separated segments.';
        }

        // App / Build Version (e.g. 1.0.0 or 1.2.0)
        $buildVersion = trim((string)($input['build_version'] ?? $input['app_version'] ?? '1.0.0'));
        if ($buildVersion === '') {
            $buildVersion = '1.0.0';
        }
        $buildVersion = preg_replace('/[^0-9a-zA-Z\.\+\-_]/', '', $buildVersion) ?: '1.0.0';

        // 2. Color Branding Fields (Hex validation)
        $validateColor = function (string $key, string $default) use ($input, &$errors): string {
            $raw = strtoupper(trim((string)($input[$key] ?? $default)));
            if (!str_starts_with($raw, '#')) {
                $raw = '#' . $raw;
            }
            if (!preg_match('/^#([A-F0-9]{6}|[A-F0-9]{3})$/', $raw)) {
                $errors[] = "Invalid color format for {$key}. Use standard 6-character Hex like #4F46E5.";
                return $default;
            }
            return strlen($raw) === 4 ? '#' . $raw[1].$raw[1].$raw[2].$raw[2].$raw[3].$raw[3] : $raw;
        };

        $primaryColor   = $validateColor('primary_color', '#4F46E5');
        $secondaryColor = $validateColor('secondary_color', '#06B6D4');
        $accentColor    = $validateColor('accent_color', '#10B981');
        $bgColor        = $validateColor('bg_color', '#0F172A');
        $sidebarColor   = $validateColor('sidebar_color', '#1E293B');
        $textColor      = $validateColor('text_color', '#F8FAFC');

        // 3. File Uploads Handling & Validation
        $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
        $uploadedTmpFiles = [];
        $logoBase64 = '';
        $mainLogoPath = null;

        $uploadBaseDir = __DIR__ . '/../storage/uploads/' . $buildUid;
        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0755, true);
        }

        $imageRoles = [
            'main_logo'         => 'app_logo',
            'light_logo'        => 'app_logo_light',
            'dark_logo'         => 'app_logo_dark',
            'splash_logo'       => 'splash_logo',
            'login_logo'        => 'login_logo',
            'favicon'           => 'favicon',
            'windows_icon'      => 'windows_icon',
            'android_icon'      => 'android_icon',
            'notification_icon' => 'notification_icon',
            'ios_icon'          => 'ios_icon',
            'web_icon'          => 'web_icon',
            'app_logo'          => 'app_logo', // Backwards compatibility
        ];

        foreach ($imageRoles as $fieldKey => $assetName) {
            $fileObj = $files[$fieldKey] ?? null;
            if (!empty($fileObj['tmp_name']) && is_uploaded_file($fileObj['tmp_name'])) {
                if ($fileObj['size'] > 6291456) { // 6MB
                    $errors[] = "Uploaded image for '{$fieldKey}' exceeds the maximum allowed size of 6MB.";
                    continue;
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $fileObj['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowedMimes, true) && !str_ends_with(strtolower($fileObj['name']), '.ico')) {
                    $errors[] = "Uploaded image for '{$fieldKey}' must be a PNG, JPEG, WEBP, or ICO image.";
                    continue;
                }

                $ext = pathinfo($fileObj['name'], PATHINFO_EXTENSION) ?: 'png';
                $storedPath = $uploadBaseDir . '/' . $assetName . '.' . strtolower($ext);
                copy($fileObj['tmp_name'], $storedPath);
                $uploadedTmpFiles[$fieldKey] = $storedPath;

                if ($fieldKey === 'main_logo' || $fieldKey === 'app_logo') {
                    $mainLogoPath = 'storage/uploads/' . $buildUid . '/' . $assetName . '.' . strtolower($ext);
                    $logoBase64 = base64_encode(file_get_contents($storedPath));
                }
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // 4. Generate All Icons & Visual Assets
        $assetsOutputDir = $uploadBaseDir . '/branding_assets';
        $manifest = IconGenerator::generateAllAssets($uploadedTmpFiles, $assetsOutputDir, $primaryColor, $shortName ?: $appName);

        // Ensure logoBase64 is populated (from master logo, uploaded icon, or generated master asset)
        if (empty($logoBase64)) {
            $candidatePath = $uploadedTmpFiles['main_logo'] ?? $uploadedTmpFiles['app_icon'] ?? $uploadedTmpFiles['android_icon'] ?? null;
            if (!$candidatePath || !file_exists($candidatePath)) {
                $candidatePath = $assetsOutputDir . '/assets/images/app_logo.png';
            }
            if ($candidatePath && file_exists($candidatePath)) {
                $im = IconGenerator::loadImage($candidatePath);
                if ($im) {
                    $compact = IconGenerator::resizeSquare($im, 256);
                    $logoBase64 = base64_encode(IconGenerator::toPngBytes($compact));
                }
            }
        }

        // 5. Build Comprehensive White-Label Config JSON
        $brandingConfig = [
            'company_name'       => $companyName,
            'product_name'       => $productName,
            'app_name'           => $appName,
            'short_name'         => $shortName,
            'display_name'       => $displayName,
            'org_name'           => $orgName,
            'copyright'          => $copyright,
            'support_email'      => $supportEmail,
            'support_phone'      => $supportPhone,
            'website_url'        => $websiteUrl,
            'server_url'         => $serverUrl,
            'package_id'         => $packageId,
            'build_version'      => $buildVersion,
            'primary_color'      => $primaryColor,
            'secondary_color'    => $secondaryColor,
            'accent_color'       => $accentColor,
            'bg_color'           => $bgColor,
            'sidebar_color'      => $sidebarColor,
            'text_color'         => $textColor,
            'custom_logo_base64' => $logoBase64,
            'build_uid'          => $buildUid,
            'created_at'         => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Save JSON config into upload directory
        $configJsonPath = $uploadBaseDir . '/branding_config.json';
        file_put_contents($configJsonPath, json_encode($brandingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 6. Create Branding ZIP Bundle for GitHub Actions & offline builds
        $tempDir = __DIR__ . '/../storage/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $zipBundlePath = $tempDir . '/branding_' . $buildUid . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipBundlePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($configJsonPath, 'branding_config.json');

            // Add master logo if available directly at root of branding bundle
            $masterLogoCandidate = null;
            if (!empty($mainLogoPath) && file_exists(__DIR__ . '/../' . $mainLogoPath)) {
                $masterLogoCandidate = __DIR__ . '/../' . $mainLogoPath;
            } elseif (!empty($candidatePath) && file_exists($candidatePath)) {
                $masterLogoCandidate = $candidatePath;
            }
            if ($masterLogoCandidate) {
                $zip->addFile($masterLogoCandidate, 'master_logo.png');
            }

            // Add all files from branding_assets
            $filesIter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($assetsOutputDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($filesIter as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'assets/' . substr($filePath, strlen($assetsOutputDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        // Public download URL for the branding bundle (dynamically resolves current server host)
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/app-builder'), '/\\');
            if ($scriptDir === '' || $scriptDir === '.') {
                $scriptDir = '/app-builder';
            }
            $appUrl = "{$scheme}://{$_SERVER['HTTP_HOST']}{$scriptDir}";
        } else {
            $appUrl = rtrim((string)setting('app_url', 'https://saas.zoomnearby.com/app-builder'), '/');
        }
        $brandingBundleUrl = $appUrl . '/download-branding.php?id=' . urlencode($buildUid);

        // Sanitize branding_json to ensure it never embeds massive base64 strings
        $cleanBrandingConfig = $brandingConfig;
        unset($cleanBrandingConfig['custom_logo_base64']);

        return [
            'valid' => true,
            'config' => [
                'build_uid'            => $buildUid,
                'app_name'             => $appName,
                'short_name'           => $shortName,
                'display_name'         => $displayName,
                'company_name'         => $companyName,
                'product_name'         => $productName,
                'package_id'           => $packageId,
                'build_version'        => $buildVersion,
                'server_url'           => $serverUrl,
                'primary_color'        => $primaryColor,
                'secondary_color'      => $secondaryColor,
                'accent_color'         => $accentColor,
                'bg_color'             => $bgColor,
                'sidebar_color'        => $sidebarColor,
                'text_color'           => $textColor,
                'custom_logo_base64'   => $logoBase64,
                'custom_logo_path'     => $mainLogoPath,
                'branding_bundle_url'  => $brandingBundleUrl,
                'branding_bundle_path' => $zipBundlePath,
                'branding_json'        => json_encode($cleanBrandingConfig, JSON_UNESCAPED_SLASHES),
                'branding_array'       => $brandingConfig,
            ],
        ];
    }
}
