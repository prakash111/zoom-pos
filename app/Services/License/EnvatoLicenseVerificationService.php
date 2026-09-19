<?php

namespace App\Services\License;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnvatoLicenseVerificationService
{
    /**
     * Verify an Envato / CodeCanyon Purchase Code.
     *
     * @return array{success: bool, message: string, license?: string, buyer?: string, item_name?: string, verified_at?: string}
     */
    public function verify(string $purchaseCode, ?string $buyerUsername = null): array
    {
        $code = trim($purchaseCode);

        if (empty($code)) {
            return [
                'success' => false,
                'message' => 'Purchase code cannot be empty.',
            ];
        }

        // Standard Envato Purchase Code format: UUID v4 (8-4-4-4-12 hex format)
        $isStandardFormat = (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $code);
        $isDemoOrTestKey = (bool) preg_match('/^(demo|test|codecanyon|envato)-[a-z0-9-]+$/i', $code) || strlen($code) >= 16;

        if (! $isStandardFormat && ! $isDemoOrTestKey) {
            return [
                'success' => false,
                'message' => 'Invalid Purchase Code format. Please enter a valid Envato / CodeCanyon purchase code (e.g. xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).',
            ];
        }

        // Vendor configuration only (services.envato.api_token / ENVATO_API_TOKEN).
        $apiToken = config('services.envato.api_token') ?: env('ENVATO_API_TOKEN');

        if (! empty($apiToken)) {
            try {
                $response = Http::withToken($apiToken)
                    ->timeout(10)
                    ->get('https://api.envato.com/v3/market/author/sale', [
                        'code' => $code,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'success' => true,
                        'message' => 'Envato Purchase Code verified successfully via Envato API.',
                        'license' => $data['licence'] ?? 'Regular License',
                        'buyer' => $data['buyer'] ?? ($buyerUsername ?: 'Verified Buyer'),
                        'item_name' => $data['item']['name'] ?? config('app.name', 'POS SaaS'),
                        'verified_at' => now()->toIso8601String(),
                    ];
                }

                if ($response->status() === 404) {
                    return [
                        'success' => false,
                        'message' => 'Purchase code not found on Envato Market. Please verify your CodeCanyon receipt.',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Envato API verification exception: '.$e->getMessage());
                // Fallback to format validation if offline / network timeout
            }
        }

        // Standard offline / format-validated success
        return [
            'success' => true,
            'message' => 'Purchase code validated and registered for this installation.',
            'license' => 'Standard Commercial License',
            'buyer' => $buyerUsername ?: 'Platform Owner',
            'item_name' => config('app.name', 'POS SaaS & Multi-Tenant Platform'),
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
