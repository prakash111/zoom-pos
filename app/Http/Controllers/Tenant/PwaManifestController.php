<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PwaManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $company = auth('web')->user()?->company;
        $name = trim((string) ($company?->trade_name ?: $company?->name ?: config('app.name', 'Zoom POS')));
        $themeColor = $this->validColor($company?->primary_color) ?: '#2563eb';

        return response()->json([
            'id' => '/tenant/',
            'name' => $name.' POS',
            'short_name' => mb_strimwidth($name, 0, 18, ''),
            'description' => 'Point of sale and store management for '.$name.'.',
            'start_url' => '/tenant/?source=pwa',
            'scope' => '/tenant/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
            'orientation' => 'any',
            'background_color' => '#0f172a',
            'theme_color' => $themeColor,
            'icons' => [
                ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'New Sale',
                    'short_name' => 'POS',
                    'url' => '/tenant/sales/create?source=pwa-shortcut',
                    'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Dashboard',
                    'short_name' => 'Dashboard',
                    'url' => '/tenant/?source=pwa-shortcut',
                    'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function validColor(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtolower($color) : null;
    }
}
