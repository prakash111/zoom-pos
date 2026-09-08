<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;
use App\Services\Modular\ModulePackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Receives a signed "license issued" push from the vendor's License Manager
 * after a hosted-checkout purchase (docs/LICENSE_SERVER_CONTRACT.md). Verifies
 * the HMAC, then activates the core script or the module in place.
 *
 * POST /api/license/activate
 * Header: X-License-Signature: hex hmac-sha256(raw_body, LICENSE_SERVER_SECRET)
 * Body:   { product_slug, license_key, domain, expires_at?, plan? }
 */
class LicenseActivationController extends Controller
{
    public function activate(Request $request, ModulePackageService $packages): JsonResponse
    {
        $secret = (string) config('services.license_server.secret');
        if ($secret === '') {
            return response()->json(['status' => false, 'message' => 'License server secret not configured.'], 503);
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);
        if (! hash_equals($expected, (string) $request->header('X-License-Signature'))) {
            return response()->json(['status' => false, 'message' => 'Bad signature.'], 401);
        }

        $data = json_decode($raw, true);
        $slug = strtolower(trim($data['product_slug'] ?? ''));
        $key = trim($data['license_key'] ?? '');
        $domain = strtolower(trim($data['domain'] ?? ''));
        $expiresAt = $data['expires_at'] ?? null;

        if ($slug === '' || $key === '') {
            return response()->json(['status' => false, 'message' => 'product_slug and license_key are required.'], 422);
        }

        if ($domain !== '' && $domain !== LicenseService::currentDomain()) {
            return response()->json(['status' => false, 'message' => 'Domain mismatch.'], 422);
        }

        if ($slug === 'core') {
            return $this->activateCore($key, $expiresAt);
        }

        $module = SduiModule::query()->where('slug', $slug)->where('source_type', 'package')->first();

        if (! $module) {
            // The ZIP is not uploaded yet — stash the key; install() applies it.
            PlatformSystem::set('license_pending_'.$slug, encrypt($key));
            AuditLog::record('module.license_received', null, null, ['key' => $slug, 'pending' => true]);

            return response()->json(['status' => true, 'pending' => true, 'message' => 'License stored. Upload the module ZIP to finish installing.']);
        }

        $result = $packages->verifyAndRecordLicense($module, $key, null);
        if (! $result['status']) {
            return response()->json(['status' => false, 'message' => $result['message']], 422);
        }

        $module->refresh();
        $activated = false;
        if (! $module->is_active && File::exists(base_path('modules/'.$module->package_path.'/module.json'))) {
            try {
                $packages->activate($module, null);
                $activated = true;
            } catch (Throwable $e) {
                // licensed but activation failed (e.g. migration) — surfaced in the panel
            }
        }

        return response()->json(['status' => true, 'activated' => $activated, 'message' => 'Module licensed.']);
    }

    private function activateCore(string $key, ?string $expiresAt): JsonResponse
    {
        $path = storage_path('installed');
        $blob = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $blob['license'] = array_merge($blob['license'] ?? [], [
            'purchase_code' => $key,
            'verified_at' => now()->toIso8601String(),
        ]);
        @file_put_contents($path, json_encode($blob, JSON_PRETTY_PRINT));

        PlatformSystem::set('core_license_status', 'ok');
        PlatformSystem::set('core_license_last_checked', now()->toIso8601String());
        PlatformSystem::set('core_license_message', 'Core license activated via purchase.');
        PlatformSystem::set('core_license_expires_at', (string) ($expiresAt ?? ''));

        AuditLog::record('core.license_activated', null, null, ['via' => 'license_server_callback']);

        return response()->json(['status' => true, 'message' => 'Core license activated.']);
    }
}
