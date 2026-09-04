<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Configuration;
use App\Services\Auth\PermissionChecker;
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
            case 'branding':
                return $settingsController->updateProfile($request);

            case 'receipts':
                return $settingsController->updateReceipts($request);

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
                    'ai_catalog_enrichment' => ['nullable', 'boolean'],
                    'ai_receipt_ocr' => ['nullable', 'boolean'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
                }
                foreach ($validator->validated() as $key => $value) {
                    Configuration::withoutGlobalScopes()->updateOrCreate(
                        ['company_id' => $company->id, 'key' => $key],
                        ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
                    );
                }
                AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'api_integrations']);

                return response()->json(['success' => true, 'message' => 'API and integration settings updated successfully.']);

            case 'mode':
                return response()->json([
                    'success' => false,
                    'error' => 'Store operating mode is locked. Only SuperAdmin can modify operating modes.',
                ], 403);

            default:
                return response()->json(['success' => false, 'error' => 'Settings section not found.'], 404);
        }
    }
}
