<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const HMAC_SALT = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
    private const AES_CIPHER = 'AES-256-CBC';

    private function generateSignature(string $serverUrl, int $timestamp, string $platform = 'android'): string
    {
        $cleanUrl = rtrim(strtolower($serverUrl), '/');
        return hash_hmac('sha256', "{$cleanUrl}|{$timestamp}|{$platform}", self::HMAC_SALT);
    }

    public function test_rejects_expired_handshake_timestamps(): void
    {
        $serverUrl = 'https://demo.example.com';
        $timestamp = time() - 400; // 400 seconds ago (> 300s limit)
        $signature = $this->generateSignature($serverUrl, $timestamp);

        $response = $this->postJson('/api/v2/verify-entitlement', [
            'server_url' => $serverUrl,
            'platform' => 'android',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'code' => 400,
                'message' => 'Request signature expired. Check device clock.',
            ]);
    }

    public function test_rejects_tampered_hmac_signature(): void
    {
        $serverUrl = 'https://demo.example.com';
        $timestamp = time();

        $response = $this->postJson('/api/v2/verify-entitlement', [
            'server_url' => $serverUrl,
            'platform' => 'android',
            'timestamp' => $timestamp,
            'signature' => 'invalid-hmac-signature-abc-123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'code' => 401,
                'message' => 'Tampered handshake payload detected.',
            ]);
    }

    public function test_unregistered_domain_returns_403_blocking_modal(): void
    {
        $serverUrl = 'https://unregistered-domain.com';
        $timestamp = time();
        $signature = $this->generateSignature($serverUrl, $timestamp);

        $response = $this->postJson('/api/v2/verify-entitlement', [
            'server_url' => $serverUrl,
            'platform' => 'android',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'unregistered',
                'code' => 403,
                'buy_url' => 'https://zoomnearby.com/pricing',
            ]);
    }

    public function test_regular_license_returns_authorized_with_standard_entitlements(): void
    {
        $host = 'regular-client.com';
        DB::table('licenses')->updateOrInsert(
            ['registered_domain' => $host],
            [
                'license_key' => 'ZN-REG-TEST-0001',
                'product_slug' => 'core',
                'client_email' => 'client@regular.com',
                'registered_domain' => $host,
                'license_type' => 'regular',
                'is_active' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $serverUrl = "https://{$host}";
        $timestamp = time();
        $signature = $this->generateSignature($serverUrl, $timestamp);

        $response = $this->postJson('/api/v2/verify-entitlement', [
            'server_url' => $serverUrl,
            'platform' => 'android',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authorized',
                'code' => 200,
                'license_tier' => 'regular',
                'entitlements' => [
                    'app_access' => true,
                    'white_label_branding' => false,
                    'custom_package_name' => false,
                    'ready_compiled_files' => false,
                    'installation_support' => false,
                ],
                'branding' => null,
            ])
            ->assertJsonStructure([
                'license_token',
                'upgrade_notice' => ['title', 'message', 'url'],
            ]);
    }

    public function test_extended_license_returns_whitelabel_assets_and_extended_entitlements(): void
    {
        $host = 'extended-enterprise.com';
        $brandingData = [
            'app_name' => 'Metro Retail Enterprise',
            'primary_color' => '#10B981',
            'logo_url' => 'https://extended-enterprise.com/logo.png',
        ];

        DB::table('licenses')->updateOrInsert(
            ['registered_domain' => $host],
            [
                'license_key' => 'ZN-EXT-TEST-9999',
                'product_slug' => 'core',
                'client_email' => 'owner@extended-enterprise.com',
                'registered_domain' => $host,
                'license_type' => 'extended',
                'is_active' => true,
                'status' => 'active',
                'branding_json' => json_encode($brandingData),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $serverUrl = "https://{$host}";
        $timestamp = time();
        $signature = $this->generateSignature($serverUrl, $timestamp, 'windows');

        $response = $this->postJson('/api/v2/verify-entitlement', [
            'server_url' => $serverUrl,
            'platform' => 'windows',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authorized',
                'code' => 200,
                'license_tier' => 'extended',
                'entitlements' => [
                    'app_access' => true,
                    'white_label_branding' => true,
                    'custom_package_name' => true,
                    'ready_compiled_files' => true,
                    'installation_support' => true,
                ],
                'branding' => $brandingData,
                'upgrade_notice' => null,
            ]);

        // Verify that the AES-256-CBC token can be decrypted properly
        $token = $response->json('license_token');
        $this->assertNotEmpty($token);

        $appKey = config('app.key') ?: 'base64:ZN_DEFAULT_SIGNING_KEY_32CHARS_LONG';
        $encKey = substr(hash('sha256', $appKey), 0, 32);
        $encIv = substr(hash('sha256', self::HMAC_SALT), 0, 16);
        $decrypted = openssl_decrypt($token, self::AES_CIPHER, $encKey, 0, $encIv);
        $this->assertNotFalse($decrypted);

        $payload = json_decode($decrypted, true);
        $this->assertEquals($host, $payload['domain']);
        $this->assertEquals('extended', $payload['license_type']);
        $this->assertTrue($payload['is_extended']);
    }
}
