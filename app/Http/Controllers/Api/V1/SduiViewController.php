<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Services\Auth\PermissionChecker;
use App\Services\Notifications\CustomChannelDispatcherService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller serving Server-Driven UI (SDUI) schema views and handling
 * declarative form submissions from the universal schema renderer.
 */
class SduiViewController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/tenant/views/{view} or GET /api/app/views/{view}
     */
    public function show(Request $request, string $view, PermissionChecker $permissions): JsonResponse
    {
        $norm = strtolower(trim(str_replace(['_', ' '], '-', $view)));
        if (in_array($norm, ['verify-otp', 'otp-verify', 'verify-email', 'login', 'auth-login', 'register-store', 'register-tenant', 'auth-register', 'signup'], true)) {
            $company = null;
            try {
                $company = $this->resolveCompany($request);
            } catch (\Throwable $e) {
                $company = Company::query()->first() ?? new Company([
                    'name' => config('app.name', 'ZoomNearby POS'),
                    'currency_symbol' => '$',
                ]);
            }

            return SchemaResponse::renderView($view, $company);
        }

        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $storedScreen = SchemaResponse::storedScreen($view);
        $requiredPermission = SchemaResponse::requiredPermission($view, $storedScreen);

        if ($user !== null && $requiredPermission !== null) {
            [$module, $action] = array_pad(explode('.', $requiredPermission, 2), 2, 'view');
            if (! $permissions->allows($user, $module, $action)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => "Your role cannot {$action} {$module}.",
                ], 403);
            }
        }

        return SchemaResponse::renderView($view, $company);
    }

    /**
     * POST /api/tenant/settings/{section} or PUT /api/tenant/settings/{section}
     * Generic endpoint for SDUI form_submit actions.
     */
    public function submitSettings(
        Request $request,
        string $section,
        SettingsApiController $settingsController
    ): JsonResponse {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $normSection = strtolower(trim(str_replace(['_', 'settings-'], ['-', ''], $section)));

        switch ($normSection) {
            case 'profile':
            case 'localization':
                return $this->maybeWizardAdvance($settingsController->updateProfile($request), $request, $company, $user);

            case 'branding':
                return $this->maybeWizardAdvance($settingsController->updateBranding($request), $request, $company, $user);

            case 'receipts':
                return $this->maybeWizardAdvance($settingsController->updateReceipts($request), $request, $company, $user);

            case 'notification-sounds':
                return $this->maybeWizardAdvance($settingsController->updateNotificationSounds($request), $request, $company, $user);

            case 'repair-checklist':
            case 'repair-checklist-settings':
                return $settingsController->updateRepairChecklist($request);

            case 'financial':
                return $settingsController->updateFinancial($request);

            case 'taxes':
                $validator = Validator::make($request->all(), [
                    'tax_id' => ['nullable', 'string', 'max:60'],
                    'tax_label' => ['nullable', 'string', 'max:50'],
                    'tax_inclusive' => ['nullable', 'boolean'],
                    'show_tax_summary' => ['nullable', 'boolean'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
                }
                $data = $validator->validated();
                $taxSettings = $company->tax_settings ?? [];
                if (isset($data['tax_inclusive'])) {
                    $taxSettings['inclusive'] = (bool) $data['tax_inclusive'];
                }
                if (isset($data['show_tax_summary'])) {
                    $taxSettings['show_tax_summary'] = (bool) $data['show_tax_summary'];
                }
                $company->update([
                    'tax_id' => $data['tax_id'] ?? $company->tax_id,
                    'tax_id_label' => $data['tax_label'] ?? $company->tax_id_label,
                    'tax_settings' => $taxSettings,
                ]);
                AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'taxes']);

                return response()->json(['success' => true, 'message' => 'Tax settings updated successfully.']);

            case 'api':
            case 'api-integrations':
                $validator = Validator::make($request->all(), [
                    'webhook_url' => ['nullable', 'url', 'max:255'],
                    'outbound_webhook_url' => ['nullable', 'url', 'max:255'],
                    'webhook_platform' => ['nullable', 'string', 'in:shopify,woocommerce,generic'],
                    'webhook_hmac_secret' => ['nullable', 'string', 'max:500'],
                    'outbound_webhook_secret' => ['nullable', 'string', 'max:500'],
                    'outbound_events' => ['nullable', 'array'],
                    'event_order_created' => ['nullable', 'boolean'],
                    'event_order_settled' => ['nullable', 'boolean'],
                    'event_order_cancelled' => ['nullable', 'boolean'],
                    'event_stock_low_alert' => ['nullable', 'boolean'],
                    'ai_catalog_enrichment' => ['nullable', 'boolean'],
                    'ai_receipt_ocr' => ['nullable', 'boolean'],
                    'integration_default_locale' => ['nullable', 'string', 'max:10'],
                    'integration_multilingual_payloads' => ['nullable', 'boolean'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
                }
                $validated = $validator->validated();

                $outboundEvents = [];
                if ($request->boolean('event_order_created')) {
                    $outboundEvents[] = 'order.created';
                }
                if ($request->boolean('event_order_settled')) {
                    $outboundEvents[] = 'order.settled';
                }
                if ($request->boolean('event_order_cancelled')) {
                    $outboundEvents[] = 'order.cancelled';
                }
                if ($request->boolean('event_stock_low_alert')) {
                    $outboundEvents[] = 'stock.low_alert';
                }
                if ($request->has('outbound_events') && is_array($request->input('outbound_events'))) {
                    $outboundEvents = array_values(array_unique(array_merge($outboundEvents, $request->input('outbound_events'))));
                }
                if ($request->has('event_order_created') || $request->has('outbound_events')) {
                    $validated['outbound_events'] = json_encode($outboundEvents);
                }

                // If outbound_webhook_url was passed, ensure webhook_url stays in sync
                if (isset($validated['outbound_webhook_url']) && empty($validated['webhook_url'])) {
                    $validated['webhook_url'] = $validated['outbound_webhook_url'];
                }

                foreach ($validated as $key => $value) {
                    if (str_starts_with($key, 'event_')) {
                        continue;
                    }
                    Configuration::withoutGlobalScopes()->updateOrCreate(
                        ['company_id' => $company->id, 'key' => $key],
                        ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
                    );
                }
                AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'api_integrations']);

                return response()->json(['success' => true, 'message' => 'API and integration settings updated successfully.']);

            case 'notifications':
            case 'custom-notifications':
                return $settingsController->testNotificationChannel($request, app(CustomChannelDispatcherService::class));

            case 'mode':
                return response()->json([
                    'success' => false,
                    'error' => 'Store operating mode is locked. Only SuperAdmin can modify operating modes.',
                ], 403);

            default:
                return response()->json(['success' => false, 'error' => 'Settings section not found.'], 404);
        }
    }

    /**
     * When a Store Profile setup-wizard tab submits (it POSTs a
     * `wizard_tab_index`), append the SDUI `next_action` directive the client
     * uses to auto-advance to the next tab. On the final tab it also flags the
     * company profile complete and hands back a dashboard redirect. A plain
     * (non-wizard) settings save from the standalone screens is returned
     * untouched, and a validation failure never advances.
     */
    private function maybeWizardAdvance(
        JsonResponse $response,
        Request $request,
        ?Company $company,
        $user
    ): JsonResponse {
        if (! $request->filled('wizard_tab_index')) {
            return $response;
        }

        $payload = $response->getData(true);
        if (($payload['success'] ?? false) !== true) {
            return $response;
        }

        $index = max(0, (int) $request->input('wizard_tab_index'));
        $total = max(1, (int) $request->input('wizard_total_tabs', 4));
        $isFinal = $index >= $total - 1;

        if ($isFinal && $company instanceof Company) {
            if (! $company->is_profile_completed) {
                $company->forceFill(['is_profile_completed' => true])->save();
                AuditLog::record('company.profile_completed', $company->id, $user?->id, ['via' => 'store_profile_wizard']);
            }
            $payload['message'] = 'Store setup completed successfully!';
            $payload['is_profile_completed'] = true;
        }

        $payload['next_action'] = [
            'type' => 'ADVANCE_TAB',
            'target_index' => $isFinal ? $index : $index + 1,
            'is_final' => $isFinal,
            'redirect_url' => $isFinal ? '/dashboard' : null,
        ];

        return response()->json($payload, $response->getStatusCode());
    }
}
