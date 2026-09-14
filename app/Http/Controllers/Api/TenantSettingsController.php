<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\SmsGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantSettingsController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * POST /api/tenant/settings/sms-gateway
     * POST /api/v1/tenant/settings/sms-gateway
     *
     * Persist tenant custom SMS gateway credentials.
     */
    public function saveSmsCredentials(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $company = $this->resolveCompany($request);
            $tenantId = $company->id;
        } catch (\Throwable $e) {
            $tenantId = Auth::user()?->company_id ?? Auth::user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : 1);
        }

        $validated = $request->validate([
            'gateway_url'      => ['nullable', 'string'],
            'generic_sms_url'  => ['nullable', 'string'],
            'url'              => ['nullable', 'string'],
            'method'           => ['nullable', 'string', 'in:GET,POST,get,post'],
            'generic_sms_method' => ['nullable', 'string', 'in:GET,POST,get,post'],
            'api_token'        => ['nullable', 'string'],
            'generic_sms_api_key' => ['nullable', 'string'],
            'api_key'          => ['nullable', 'string'],
        ]);

        $gatewayUrl = $validated['gateway_url'] ?? $validated['generic_sms_url'] ?? $validated['url'] ?? null;
        if (empty($gatewayUrl)) {
            $gatewayUrl = 'https://sms.zoomnearby.com/api/v1/messages/send?phone={phone}&message={message}';
        }

        $method = strtoupper($validated['method'] ?? $validated['generic_sms_method'] ?? 'GET');
        $apiToken = $validated['api_token'] ?? $validated['generic_sms_api_key'] ?? $validated['api_key'] ?? '4HIXpW0OPsnPpzzebeA5KI7rI4fnAi7utMu5jwYl8dada339';

        tenant_set_setting($tenantId, 'sms_gateway', [
            'gateway_url' => $gatewayUrl,
            'method'      => $method,
            'api_token'   => $apiToken,
        ]);

        AuditLog::record('gateway.configured', $tenantId, Auth::id(), [
            'channel'  => 'sms',
            'provider' => 'generic_http',
            'endpoint' => $gatewayUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SMS Gateway credentials updated successfully.',
            'settings' => [
                'gateway_url' => $gatewayUrl,
                'method'      => $method,
                'api_token'   => $apiToken ? '••••••••' : null,
            ],
        ]);
    }

    /**
     * POST /api/tenant/settings/sms-gateway/test
     * POST /api/v1/tenant/settings/sms-gateway/test
     *
     * Dispatch a live test SMS to verify provider credentials and Android connectivity.
     */
    public function sendTestSms(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $company = $this->resolveCompany($request);
            $tenantId = $company->id;
        } catch (\Throwable $e) {
            $tenantId = Auth::user()?->company_id ?? Auth::user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : 1);
        }

        $request->validate([
            'phone'          => ['nullable', 'string'],
            'sms_test_phone' => ['nullable', 'string'],
            'recipient'      => ['nullable', 'string'],
        ]);

        $recipient = $request->input('phone')
            ?? $request->input('sms_test_phone')
            ?? $request->input('recipient')
            ?? '+918041118080';

        $testMessage = 'Test message from ZoomNearby POS CRM. Custom Android SMS Gateway connected successfully! Time: ' . now()->format('Y-m-d H:i:s');

        $result = SmsGatewayService::send($recipient, $testMessage, $tenantId);

        if ($result['success']) {
            return response()->json([
                'success'  => true,
                'message'  => "Test SMS dispatched to {$recipient} successfully.",
                'response' => $result['body'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to dispatch test SMS. Please verify Android gateway device connectivity.',
            'details' => $result,
        ], 422);
    }
}
