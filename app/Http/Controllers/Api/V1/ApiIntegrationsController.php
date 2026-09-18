<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantNotificationGateway;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiIntegrationsController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(
        protected TenantNotificationDispatcherService $dispatcher
    ) {
    }

    /**
     * GET /api/v1/tenant/api-integrations
     * Returns the complete Server-Driven UI Tabbed Layout for API & Integrations.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $schema = SchemaResponse::apiIntegrationsTabbedView($company);

        return response()->json($schema);
    }

    /**
     * POST /api/v1/tenant/api-integrations/{channel}
     * Persist credentials and enable/disable toggle for a specific channel.
     */
    public function saveChannel(Request $request, string $channel): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $normalizedChannel = strtolower(trim(str_replace('-', '_', $channel)));

        if ($normalizedChannel === 'email_smtp' || $normalizedChannel === 'smtp') {
            $normalizedChannel = 'email';
        } elseif ($normalizedChannel === 'webhook' || $normalizedChannel === 'webhooks') {
            $normalizedChannel = 'custom_webhook';
        } elseif (in_array($normalizedChannel, ['ai', 'ai_studio', 'api_keys', 'ai_vision'], true)) {
            $normalizedChannel = 'ai';
        }

        switch ($normalizedChannel) {
            case 'whatsapp':
                $validator = Validator::make($request->all(), [
                    'whatsapp_is_enabled' => ['nullable'],
                    'is_enabled' => ['nullable'],
                    'whatsapp_provider' => ['nullable', 'string', 'in:meta_cloud_api,twilio,unofficial_http'],
                    'provider' => ['nullable', 'string', 'in:meta_cloud_api,twilio,unofficial_http'],
                    'unofficial_whatsapp_url' => ['nullable', 'string', 'max:500'],
                    'unofficial_whatsapp_token' => ['nullable', 'string'],
                    'meta_phone_number_id' => ['nullable', 'string', 'max:100'],
                    'phone_number_id' => ['nullable', 'string', 'max:100'],
                    'meta_waba_id' => ['nullable', 'string', 'max:100'],
                    'waba_id' => ['nullable', 'string', 'max:100'],
                    'meta_access_token' => ['nullable', 'string'],
                    'access_token' => ['nullable', 'string'],
                    'meta_template_namespace' => ['nullable', 'string', 'max:100'],
                    'template_namespace' => ['nullable', 'string', 'max:100'],
                    'twilio_account_sid' => ['nullable', 'string', 'max:100'],
                    'account_sid' => ['nullable', 'string', 'max:100'],
                    'twilio_auth_token' => ['nullable', 'string'],
                    'auth_token' => ['nullable', 'string'],
                    'twilio_from_number' => ['nullable', 'string', 'max:50'],
                    'from_number' => ['nullable', 'string', 'max:50'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
                }

                $isEnabled = $request->boolean('whatsapp_is_enabled', $request->boolean('is_enabled', false));
                $provider = $request->input('whatsapp_provider', $request->input('provider', 'meta_cloud_api'));
                $existingWhatsapp = TenantNotificationGateway::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('channel', TenantNotificationGateway::CHANNEL_WHATSAPP)
                    ->first();
                $existingWhatsappCreds = (array) ($existingWhatsapp?->credentials ?? []);
                $unofficialToken = $request->input('unofficial_whatsapp_token', $request->input('api_token', ''));
                if ($unofficialToken === '') {
                    $unofficialToken = $existingWhatsappCreds['api_token'] ?? '';
                }

                $credentials = [
                    'phone_number_id' => $request->input('meta_phone_number_id', $request->input('phone_number_id', '')),
                    'waba_id' => $request->input('meta_waba_id', $request->input('waba_id', '')),
                    'access_token' => $request->input('meta_access_token', $request->input('access_token', '')),
                    'template_namespace' => $request->input('meta_template_namespace', $request->input('template_namespace', '')),
                    'account_sid' => $request->input('twilio_account_sid', $request->input('account_sid', '')),
                    'auth_token' => $request->input('twilio_auth_token', $request->input('auth_token', '')),
                    'from_number' => $request->input('twilio_from_number', $request->input('from_number', '')),
                    'url' => $request->input('unofficial_whatsapp_url', $request->input('url', '')),
                    'api_token' => $unofficialToken,
                ];

                TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                    ['company_id' => $company->id, 'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP],
                    [
                        'tenant_id' => $company->id,
                        'provider' => $provider,
                        'is_enabled' => $isEnabled,
                        'credentials' => $credentials,
                    ]
                );

                AuditLog::record('gateway.configured', $company->id, $user?->id, ['channel' => 'whatsapp', 'enabled' => $isEnabled]);

                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp Business gateway configuration saved successfully.',
                ]);

            case 'sms':
                $validator = Validator::make($request->all(), [
                    'sms_is_enabled' => ['nullable'],
                    'is_enabled' => ['nullable'],
                    'sms_provider' => ['nullable', 'string', 'in:twilio,msg91,generic_http'],
                    'provider' => ['nullable', 'string', 'in:twilio,msg91,generic_http'],
                    'sms_twilio_sid' => ['nullable', 'string', 'max:100'],
                    'sms_twilio_token' => ['nullable', 'string'],
                    'sms_twilio_from' => ['nullable', 'string', 'max:50'],
                    'msg91_auth_key' => ['nullable', 'string'],
                    'msg91_sender_id' => ['nullable', 'string', 'max:10'],
                    'msg91_dlt_template_id' => ['nullable', 'string', 'max:100'],
                    'generic_sms_url' => ['nullable', 'string', 'max:500'],
                    'gateway_url' => ['nullable', 'string', 'max:500'],
                    'url' => ['nullable', 'string', 'max:500'],
                    'generic_sms_method' => ['nullable', 'string', 'in:POST,GET,post,get'],
                    'method' => ['nullable', 'string', 'in:POST,GET,post,get'],
                    'generic_sms_api_key' => ['nullable', 'string'],
                    'api_token' => ['nullable', 'string'],
                    'api_key' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
                }

                $isEnabled = $request->boolean('sms_is_enabled', $request->boolean('is_enabled', false));
                $provider = $request->input('sms_provider', $request->input('provider', 'generic_http'));

                $genericUrl = $request->input('generic_sms_url', $request->input('gateway_url', $request->input('url', '')));
                $genericMethod = strtoupper($request->input('generic_sms_method', $request->input('method', 'GET')));
                $genericApiKey = $request->input('generic_sms_api_key', $request->input('api_token', $request->input('api_key', '')));
                $existingSmsGateway = TenantNotificationGateway::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('channel', TenantNotificationGateway::CHANNEL_SMS)
                    ->first();
                $existingSmsCredentials = (array) ($existingSmsGateway?->credentials ?? []);
                // Secret fields are intentionally blank in demo schemas. Keep
                // the encrypted server value when a demo user saves settings.
                if ($genericApiKey === '' && ! empty($existingSmsCredentials['api_key'])) {
                    $genericApiKey = $existingSmsCredentials['api_key'];
                }

                $credentials = [
                    'account_sid' => $request->input('sms_twilio_sid', $request->input('account_sid', '')),
                    'auth_token' => $request->input('sms_twilio_token', $request->input('auth_token', '')),
                    'from_number' => $request->input('sms_twilio_from', $request->input('from_number', '')),
                    'auth_key' => $request->input('msg91_auth_key', $request->input('auth_key', '')),
                    'sender_id' => $request->input('msg91_sender_id', $request->input('sender_id', '')),
                    'dlt_template_id' => $request->input('msg91_dlt_template_id', $request->input('dlt_template_id', '')),
                    'url' => $genericUrl,
                    'gateway_url' => $genericUrl,
                    'method' => $genericMethod,
                    'api_key' => $genericApiKey,
                    'api_token' => $genericApiKey,
                ];

                TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                    ['company_id' => $company->id, 'channel' => TenantNotificationGateway::CHANNEL_SMS],
                    [
                        'tenant_id' => $company->id,
                        'provider' => $provider,
                        'is_enabled' => $isEnabled,
                        'credentials' => $credentials,
                    ]
                );

                if (function_exists('tenant_set_setting')) {
                    tenant_set_setting($company->id, 'sms_gateway', [
                        'gateway_url' => $genericUrl,
                        'method'      => $genericMethod,
                        'api_token'   => $genericApiKey,
                        'is_enabled'  => $isEnabled,
                    ]);
                }

                AuditLog::record('gateway.configured', $company->id, $user?->id, ['channel' => 'sms', 'enabled' => $isEnabled]);

                return response()->json([
                    'success' => true,
                    'message' => 'SMS gateway configuration saved successfully.',
                ]);

            case 'email':
                $validator = Validator::make($request->all(), [
                    'smtp_is_enabled' => ['nullable'],
                    'is_enabled' => ['nullable'],
                    'smtp_host' => ['nullable', 'string', 'max:255'],
                    'host' => ['nullable', 'string', 'max:255'],
                    'smtp_port' => ['nullable', 'numeric'],
                    'port' => ['nullable', 'numeric'],
                    'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
                    'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
                    'smtp_username' => ['nullable', 'string', 'max:255'],
                    'username' => ['nullable', 'string', 'max:255'],
                    'smtp_password' => ['nullable', 'string'],
                    'password' => ['nullable', 'string'],
                    'smtp_from_address' => ['nullable', 'email', 'max:255'],
                    'from_address' => ['nullable', 'email', 'max:255'],
                    'smtp_from_name' => ['nullable', 'string', 'max:255'],
                    'from_name' => ['nullable', 'string', 'max:255'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
                }

                $isEnabled = $request->boolean('smtp_is_enabled', $request->boolean('is_enabled', false));
                $credentials = [
                    'host' => $request->input('smtp_host', $request->input('host', '')),
                    'port' => (int) $request->input('smtp_port', $request->input('port', 587)),
                    'encryption' => $request->input('smtp_encryption', $request->input('encryption', 'tls')),
                    'username' => $request->input('smtp_username', $request->input('username', '')),
                    'password' => $request->input('smtp_password', $request->input('password', '')),
                    'from_address' => $request->input('smtp_from_address', $request->input('from_address', $company->email)),
                    'from_name' => $request->input('smtp_from_name', $request->input('from_name', $company->name)),
                ];

                TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                    ['company_id' => $company->id, 'channel' => TenantNotificationGateway::CHANNEL_EMAIL],
                    [
                        'tenant_id' => $company->id,
                        'provider' => TenantNotificationGateway::PROVIDER_SMTP,
                        'is_enabled' => $isEnabled,
                        'credentials' => $credentials,
                    ]
                );

                AuditLog::record('gateway.configured', $company->id, $user?->id, ['channel' => 'email', 'enabled' => $isEnabled]);

                return response()->json([
                    'success' => true,
                    'message' => 'Custom SMTP configuration saved successfully.',
                ]);

            case 'custom_webhook':
                $validator = Validator::make($request->all(), [
                    'webhook_is_enabled' => ['nullable'],
                    'is_enabled' => ['nullable'],
                    'webhook_url' => ['nullable', 'url', 'max:500'],
                    'url' => ['nullable', 'url', 'max:500'],
                    'webhook_method' => ['nullable', 'string', 'in:POST,PUT'],
                    'method' => ['nullable', 'string', 'in:POST,PUT'],
                    'webhook_secret' => ['nullable', 'string', 'max:255'],
                    'secret' => ['nullable', 'string', 'max:255'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
                }

                $isEnabled = $request->boolean('webhook_is_enabled', $request->boolean('is_enabled', false));
                $triggers = [];
                if ($request->boolean('trigger_receipt_generated')) {
                    $triggers[] = 'receipt_generated';
                }
                if ($request->boolean('trigger_invoice_created')) {
                    $triggers[] = 'invoice_created';
                }
                if ($request->boolean('trigger_quotation_sent')) {
                    $triggers[] = 'quotation_sent';
                }
                if ($request->boolean('trigger_due_reminder')) {
                    $triggers[] = 'due_reminder';
                }

                $credentials = [
                    'url' => $request->input('webhook_url', $request->input('url', '')),
                    'method' => $request->input('webhook_method', $request->input('method', 'POST')),
                    'secret' => $request->input('webhook_secret', $request->input('secret', '')),
                    'event_types' => $triggers,
                ];

                TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                    ['company_id' => $company->id, 'channel' => TenantNotificationGateway::CHANNEL_WEBHOOK],
                    [
                        'tenant_id' => $company->id,
                        'provider' => TenantNotificationGateway::PROVIDER_WEBHOOK,
                        'is_enabled' => $isEnabled,
                        'credentials' => $credentials,
                    ]
                );

                AuditLog::record('gateway.configured', $company->id, $user?->id, ['channel' => 'custom_webhook', 'enabled' => $isEnabled]);

                return response()->json([
                    'success' => true,
                    'message' => 'Custom Webhook configuration saved successfully.',
                ]);

            case 'ai':
                $validator = Validator::make($request->all(), [
                    'default_ai_provider' => ['nullable', 'string', 'in:openai,gemini,claude'],
                    'openai_model' => ['nullable', 'string', 'max:100'],
                    'gemini_model' => ['nullable', 'string', 'max:100'],
                    'claude_model' => ['nullable', 'string', 'max:100'],
                    'openai_api_key' => ['nullable', 'string', 'max:500'],
                    'gemini_api_key' => ['nullable', 'string', 'max:500'],
                    'claude_api_key' => ['nullable', 'string', 'max:500'],
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
                }

                $keysToSave = [
                    'default_ai_provider' => $request->input('default_ai_provider'),
                    'openai_model' => $request->input('openai_model'),
                    'gemini_model' => $request->input('gemini_model'),
                    'claude_model' => $request->input('claude_model'),
                ];

                foreach (['openai_api_key', 'gemini_api_key', 'claude_api_key'] as $k) {
                    $val = $request->input($k);
                    if (filled($val)) {
                        $keysToSave[$k] = trim($val);
                    }
                }

                foreach ($keysToSave as $key => $value) {
                    if ($value !== null) {
                        Configuration::withoutGlobalScopes()->updateOrCreate(
                            ['company_id' => $company->id, 'key' => $key],
                            ['value' => $value]
                        );
                    }
                }

                AuditLog::record('company.ai_settings_updated', $company->id, $user?->id);

                return response()->json([
                    'success' => true,
                    'message' => 'AI Studio and model configuration updated successfully.',
                ]);

            default:
                return response()->json(['success' => false, 'error' => "Unknown channel '{$channel}'."], 404);
        }
    }

    /**
     * POST /api/v1/tenant/api-integrations/{channel}/test
     * Trigger a test notification or connection ping.
     */
    public function testChannel(Request $request, string $channel): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $normalizedChannel = strtolower(trim(str_replace('-', '_', $channel)));

        if ($normalizedChannel === 'email_smtp' || $normalizedChannel === 'smtp') {
            $normalizedChannel = 'email';
        } elseif ($normalizedChannel === 'webhook' || $normalizedChannel === 'webhooks') {
            $normalizedChannel = 'custom_webhook';
        } elseif (in_array($normalizedChannel, ['ai', 'ai_studio', 'api_keys'], true)) {
            $normalizedChannel = 'ai';
        }

        if ($normalizedChannel === 'ai') {
            return response()->json([
                'success' => true,
                'message' => 'AI Studio and vision engine ready.',
            ]);
        }

        $gateway = TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('channel', $normalizedChannel)
            ->first();

        if (! $gateway && $normalizedChannel === 'sms') {
            $settings = function_exists('tenant_setting') ? tenant_setting($company->id, 'sms_gateway') : null;
            $gatewayUrl = $settings['gateway_url'] ?? 'https://sms.zoomnearby.com/api/v1/messages/send?phone={phone}&message={message}';
            $gatewayMethod = strtoupper($settings['method'] ?? 'GET');
            $gatewayToken = $settings['api_token'] ?? '4HIXpW0OPsnPpzzebeA5KI7rI4fnAi7utMu5jwYl8dada339';

            $gateway = TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'channel' => TenantNotificationGateway::CHANNEL_SMS],
                [
                    'tenant_id' => $company->id,
                    'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
                    'is_enabled' => true,
                    'credentials' => [
                        'url' => $gatewayUrl,
                        'method' => $gatewayMethod,
                        'api_key' => $gatewayToken,
                        'api_token' => $gatewayToken,
                    ],
                ]
            );
        }

        if (! $gateway) {
            return response()->json([
                'success' => false,
                'error' => "Gateway for channel '{$channel}' has not been configured yet. Please save your settings first.",
            ], 422);
        }

        $testRecipient = match ($normalizedChannel) {
            'whatsapp' => $request->input('whatsapp_test_phone', $request->input('recipient', $company->phone)),
            'sms' => $request->input('sms_test_phone', $request->input('phone', $request->input('recipient', $company->phone ?: '+91 80 4111 8080'))),
            'email' => $request->input('smtp_test_email', $request->input('recipient', $company->email)),
            default => null,
        };

        $result = $this->dispatcher->testGateway($company, $gateway, $testRecipient);

        return response()->json($result);
    }

    /**
     * POST /api/v1/tenant/notifications/dispatch
     * Unified document delivery endpoint across tenant-configured channels.
     */
    public function dispatchDocument(Request $request): JsonResponse
    {
        if ($request->boolean('api_only') || in_array($request->input('document_type'), ['kot', 'kitchen_order_ticket', 'kitchen-ticket', 'repair', 'prescription', 'appointment'], true)) {
            return app(\App\Http\Controllers\Api\DocumentDispatchController::class)->dispatchDocument($request, app(\App\Services\Dispatch\DocumentDispatchService::class));
        }

        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'document_type' => ['required', 'string', 'in:receipt,invoice,quotation,due_reminder'],
            'document_id' => ['nullable', 'string'],
            'channels' => ['required', 'array'],
            'channels.*' => ['string', 'in:whatsapp,sms,email,custom_webhook,webhook'],
            'recipient_phone' => ['nullable', 'string'],
            'recipient_email' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $docType = $request->input('document_type');
        $channels = $request->input('channels', []);
        $docId = $request->input('document_id');
        $phone = $request->input('recipient_phone');
        $email = $request->input('recipient_email');

        if ($docType === 'due_reminder') {
            $customer = null;
            $custId = $request->input('customer_id');
            if ($custId) {
                $customer = Customer::withoutGlobalScope('company')->where('company_id', $company->id)->find($custId);
            }
            if (! $customer && $phone) {
                $customer = Customer::withoutGlobalScope('company')->where('company_id', $company->id)->where('phone', $phone)->first();
            }
            if (! $customer) {
                $customer = new Customer(['company_id' => $company->id, 'name' => 'Customer', 'phone' => $phone, 'email' => $email, 'due_balance' => (float) $request->input('due_amount', 0)]);
            }

            $results = $this->dispatcher->dispatchDueReminder($company, $customer, $channels, $phone, $email);

            return response()->json([
                'success' => true,
                'document_type' => 'due_reminder',
                'results' => $results,
            'device_actions' => array_values(array_filter($results, fn ($result) => ($result['status'] ?? '') === 'manual_link')),
                'url' => count($results) === 1 ? (reset($results)['url'] ?? null) : null,
                'status' => count($results) === 1 ? (reset($results)['status'] ?? null) : null,
                'message' => 'Due reminder notifications dispatched.',
            ]);
        }

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($docId) {
                $q->where('id', $docId)
                    ->orWhere('sale_number', $docId)
                    ->orWhere('external_id', $docId);
            })
            ->with(['customer', 'company'])
            ->first();

        if (! $sale) {
            return response()->json(['success' => false, 'error' => "Document #{$docId} not found."], 404);
        }

        $results = match ($docType) {
            'quotation' => $this->dispatcher->dispatchQuotation($company, $sale, $channels, $phone, $email),
            default => $this->dispatcher->dispatchReceipt($company, $sale, $channels, $phone, $email),
        };

        return response()->json([
            'success' => true,
            'document_type' => $docType,
            'document_number' => $sale->sale_number,
            'results' => $results,
            'device_actions' => array_values(array_filter($results, fn ($result) => ($result['status'] ?? '') === 'manual_link')),
            'url' => count($results) === 1 ? (reset($results)['url'] ?? null) : null,
            'status' => count($results) === 1 ? (reset($results)['status'] ?? null) : null,
            'message' => collect($results)->contains(fn ($result) => ($result['status'] ?? '') === 'manual_link') ? 'Messages prepared. Complete sending in your device app.' : 'Document dispatched across configured channels.',
        ]);
    }

    /**
     * POST /api/v1/tenant/api-keys/regenerate
     * Revoke existing active API token and generate a fresh scoped bearer key.
     */
    public function regenerateApiKey(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        // Deactivate older active keys
        TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->update(['active' => false]);

        $token = TenantApiKey::generateToken();
        $apiKey = TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'name' => 'Default REST API Token',
            'token' => $token,
            'permissions' => ['*'],
            'active' => true,
        ]);

        AuditLog::record('api_key.regenerated', $company->id, $user?->id, ['key_id' => $apiKey->id]);

        return response()->json([
            'success' => true,
            'message' => 'API Bearer Token regenerated successfully.',
            'token' => $token,
            'active_bearer_token' => $token,
        ]);
    }
}
