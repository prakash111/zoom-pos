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

        return response()->json([
            'success' => true,
            'push' => PushNotificationSetting::current()->publicConfig($company?->id),
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
