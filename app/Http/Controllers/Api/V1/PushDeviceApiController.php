<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function config(Request $request): JsonResponse
    {
        $company = rescue(fn () => $this->resolveCompany($request));
        $pushConfig = PushNotificationSetting::current()->publicConfig($company?->id);

        $response = [
            'success' => true,
            'push'    => $pushConfig,
        ];

        if ($company) {
            $activeCount = PushDevice::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereNull('revoked_at')
                ->count();
            $response['active_devices_count'] = $activeCount;
        }

        return response()->json($response);
    }

    public function test(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $activeDevices = PushDevice::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereNull('revoked_at')
            ->get();

        if ($activeDevices->isEmpty()) {
            return response()->json([
                'success'              => false,
                'message'              => 'No active push devices registered for this store. Open the mobile POS app and log in to register your device.',
                'active_devices_count' => 0,
                'delivered_count'      => 0,
            ], 404);
        }

        $delivered = app(\App\Services\Push\FirebasePushService::class)->sendToCompany($company->id, [
            'type'            => 'test_push',
            'action'          => 'test',
            'title'           => 'POS Push Notification Test',
            'body'            => 'Push alerts are connected and working for ' . ($company->name ?? 'your store') . '!',
            'timestamp'       => now()->toIso8601String(),
            'notification_id' => 'test_' . time(),
        ]);

        return response()->json([
            'success'              => $delivered > 0,
            'message'              => $delivered > 0
                ? "Test push notification successfully sent to {$delivered} device(s)."
                : "Push notification failed delivery. Please verify device connectivity or re-open the POS app.",
            'active_devices_count' => $activeDevices->count(),
            'delivered_count'      => $delivered,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = PushDevice::withoutGlobalScope('company')->updateOrCreate(
            ['token' => $validated['token']],
            [
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json(['success' => true, 'device_id' => $device->id]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $validated = $request->validate(['token' => ['required', 'string', 'max:512']]);

        PushDevice::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('token', $validated['token'])
            ->update(['revoked_at' => now()]);

        return response()->json(['success' => true]);
    }
}
