<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LicenseVerificationController extends Controller
{
    private const HMAC_SALT = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
    private const AES_CIPHER = 'AES-256-CBC';

    public function verifyClientApp(Request $request)
    {
        $serverUrl   = rtrim(strtolower((string)$request->input('server_url')), '/');
        $platform    = (string)$request->input('platform', 'android');
        $timestamp   = (int)$request->input('timestamp');
        $signature   = (string)$request->input('signature');

        // 1. Anti-Replay: Prevent validation requests older than 5 minutes
        if (abs(time() - $timestamp) > 300) {
            return response()->json([
                'status'  => 'error',
                'code'    => 400,
                'message' => 'Request signature expired. Check device clock.'
            ], 400);
        }

        // 2. Validate HMAC Signature
        $expectedSignature = hash_hmac('sha256', "{$serverUrl}|{$timestamp}|{$platform}", self::HMAC_SALT);
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Tampered handshake payload detected.'
            ], 401);
        }

        // 3. Extract Clean Domain / Host
        $host = parse_url($serverUrl, PHP_URL_HOST) ?? $serverUrl;

        // 4. Query Registered License Record
        $license = DB::table('licenses')
            ->where(function ($q) use ($host) {
                $q->where('registered_domain', $host)
                  ->orWhereJsonContains('allowed_domains', $host);
            })
            ->first();

        if (!$license || !$license->is_active) {
            return response()->json([
                'status'  => 'unregistered',
                'code'    => 403,
                'message' => 'Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.',
                'buy_url' => 'https://zoomnearby.com/pricing'
            ], 403);
        }

        // 5. Tier Check: Regular vs Extended
        $isExtended = ($license->license_type === 'extended');

        // 6. Generate Encrypted Verification Token (Valid for 7 Days)
        $sessionPayload = json_encode([
            'domain'       => $host,
            'license_type' => $license->license_type,
            'is_extended'  => $isExtended,
            'issued_at'    => time(),
            'expires_at'   => Carbon::now()->addDays(7)->timestamp,
        ]);

        $appKey = config('app.key') ?: 'base64:ZN_DEFAULT_SIGNING_KEY_32CHARS_LONG';
        $encKey = substr(hash('sha256', $appKey), 0, 32);
        $encIv  = substr(hash('sha256', self::HMAC_SALT), 0, 16);
        $encryptedToken = openssl_encrypt($sessionPayload, self::AES_CIPHER, $encKey, 0, $encIv);

        return response()->json([
            'status'         => 'authorized',
            'code'           => 200,
            'license_tier'   => $license->license_type,
            'license_token'  => $encryptedToken,
            'entitlements'   => [
                'app_access'           => true,
                'white_label_branding' => $isExtended,
                'custom_package_name'  => $isExtended,
                'ready_compiled_files' => $isExtended,
                'installation_support' => $isExtended,
            ],
            // Custom brand assets returned only for Extended licenses
            'branding'       => $isExtended ? json_decode($license->branding_json ?? '{}') : null,
            'upgrade_notice' => !$isExtended ? [
                'title'   => 'Regular License Active',
                'message' => 'Need your own branded APK, custom package name, and Windows EXE installer? Upgrade to Extended.',
                'url'     => 'https://zoomnearby.com/upgrade'
            ] : null,
        ]);
    }
}
