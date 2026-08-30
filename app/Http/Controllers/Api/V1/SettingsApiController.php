<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\PaymentMethod;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile REST surface for the "Profile", "Receipts", "Financial",
 * "Notifications" and Payment Methods sections of the web tenant Settings
 * page (app/Livewire/Tenant/Settings/Index.php). Split into one endpoint
 * per section (unlike the web page's single monolithic save()) so a mobile
 * tab only ever touches its own fields. Secrets (SMTP password, WhatsApp API
 * token) are stored in the same `configurations` key/value table the web
 * page uses, and are never echoed back — only a `has_*` boolean, matching
 * the web page's own masking convention.
 */
class SettingsApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $configs = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck('value', 'key')
            ->all();

        return response()->json([
            'success' => true,
            'profile' => $this->presentProfile($company),
            'receipts' => $this->presentReceipts($company),
            'financial' => $this->presentFinancial($company),
            'notifications' => $this->presentNotifications($company, $configs),
            'payment_methods' => $this->paymentMethods($company),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:2'],
            'primary_color' => ['nullable', 'string', 'max:16'],
            'default_commission_rate' => ['nullable', 'numeric', 'min:0'],
            'default_commission_type' => ['nullable', 'string', 'in:percentage,fixed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        $company->update($data);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'profile']);

        return response()->json(['success' => true, 'message' => 'Profile saved.', 'profile' => $this->presentProfile($company->fresh())]);
    }

    public function updateReceipts(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quotation_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_terms' => ['nullable', 'string', 'max:4000'],
            'quote_terms' => ['nullable', 'string', 'max:4000'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $company->update($validator->validated());
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'receipts']);

        return response()->json(['success' => true, 'message' => 'Receipt settings saved.', 'receipts' => $this->presentReceipts($company->fresh())]);
    }

    public function updateFinancial(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'currency' => ['required', 'string', 'max:3'],
            'currency_symbol' => ['nullable', 'string', 'max:8'],
            'currency_decimals' => ['nullable', 'integer', 'min:0', 'max:4'],
            'currency_symbol_position' => ['nullable', 'string', 'in:prefix,suffix'],
            'other_currencies' => ['nullable', 'array'],
            'other_currencies.*.code' => ['required_with:other_currencies', 'string', 'max:3'],
            'other_currencies.*.name' => ['nullable', 'string', 'max:50'],
            'other_currencies.*.symbol' => ['nullable', 'string', 'max:8'],
            'other_currencies.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['currency'] = strtoupper($data['currency']);
        if (isset($data['other_currencies'])) {
            $data['other_currencies'] = collect($data['other_currencies'])
                ->map(fn ($c) => [
                    'code' => strtoupper($c['code']),
                    'name' => $c['name'] ?? '',
                    'symbol' => $c['symbol'] ?? '',
                    'exchange_rate' => (float) ($c['exchange_rate'] ?? 1),
                ])
                ->values()
                ->all();
        }

        $company->update($data);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'financial']);

        return response()->json(['success' => true, 'message' => 'Financial settings saved.', 'financial' => $this->presentFinancial($company->fresh())]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'smtp_from_address' => ['nullable', 'email'],
            'smtp_from_name' => ['nullable', 'string', 'max:150'],
            'whatsapp_phone_prefix' => ['nullable', 'string', 'max:10'],
            'whatsapp_custom_note' => ['nullable', 'string', 'max:500'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_api_token' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $configKeys = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
            'whatsapp_phone_prefix', 'whatsapp_custom_note', 'whatsapp_phone_number_id',
        ];
        foreach ($configKeys as $key) {
            if (array_key_exists($key, $data)) {
                $this->putConfig($company, $key, (string) $data[$key]);
            }
        }

        // Secrets only overwrite when a new non-empty value is submitted —
        // an empty field means "leave the stored one as-is" (same as web).
        if (! empty($data['smtp_password'])) {
            $this->putConfig($company, 'smtp_password', $data['smtp_password']);
        }
        if (! empty($data['whatsapp_api_token'])) {
            $this->putConfig($company, 'whatsapp_api_token', trim($data['whatsapp_api_token']));
        }

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'notifications']);

        $configs = Configuration::withoutGlobalScopes()->where('company_id', $company->id)->pluck('value', 'key')->all();

        return response()->json([
            'success' => true,
            'message' => 'Notification settings saved.',
            'notifications' => $this->presentNotifications($company, $configs),
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'recipient' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            app(InvoiceDeliveryService::class)->sendTestEmail($company, $request->input('recipient'));

            return response()->json(['success' => true, 'message' => 'Test email sent to '.$request->input('recipient').'.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'SMTP test failed: '.$e->getMessage()], 422);
        }
    }

    // ---- Payment methods ----

    public function paymentMethodsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json(['success' => true, 'payment_methods' => $this->paymentMethods($company, includeInactive: true)]);
    }

    public function paymentMethodsStore(Request $request): JsonResponse
    {
        return $this->savePaymentMethod($request);
    }

    public function paymentMethodsUpdate(Request $request, string $id): JsonResponse
    {
        return $this->savePaymentMethod($request, $id);
    }

    public function paymentMethodsToggle(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $pm = PaymentMethod::where('company_id', $company->id)->find($id);

        if (! $pm) {
            return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
        }

        $pm->update(['is_active' => ! $pm->is_active]);
        AuditLog::record('company.payment_method_toggled', $company->id, $user?->id, ['id' => $pm->id]);

        return response()->json(['success' => true, 'message' => 'Updated.', 'payment_method' => $this->presentPaymentMethod($pm)]);
    }

    public function paymentMethodsDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $pm = PaymentMethod::where('company_id', $company->id)->find($id);

        if (! $pm) {
            return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
        }

        $pm->delete();
        AuditLog::record('company.payment_method_deleted', $company->id, $user?->id, ['id' => $id]);

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    private function savePaymentMethod(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['code'] = ($data['code'] ?? null) ?: \Illuminate\Support\Str::slug($data['name'], '_');

        if ($id !== null) {
            $pm = PaymentMethod::where('company_id', $company->id)->find($id);
            if (! $pm) {
                return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
            }
            $pm->update($data);
        } else {
            $data['order_index'] = $data['order_index'] ?? (PaymentMethod::where('company_id', $company->id)->count() + 1);
            $data['is_active'] = $data['is_active'] ?? true;
            $pm = PaymentMethod::create(array_merge($data, ['company_id' => $company->id]))->fresh();
        }

        AuditLog::record('company.payment_method_saved', $company->id, $user?->id, ['id' => $pm->id]);

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'payment_method' => $this->presentPaymentMethod($pm),
        ], $id === null ? 201 : 200);
    }

    // ---- Presenters ----

    private function putConfig(Company $company, string $key, string $value): void
    {
        Configuration::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'key' => $key],
            ['value' => $value],
        );
    }

    private function presentProfile(Company $company): array
    {
        return [
            'name' => $company->name,
            'trade_name' => $company->trade_name ?? '',
            'tax_id' => $company->tax_id ?? '',
            'email' => $company->email ?? '',
            'phone' => $company->phone ?? '',
            'website' => $company->website ?? '',
            'address' => $company->address ?? '',
            'city' => $company->city ?? '',
            'state' => $company->state ?? '',
            'postal_code' => $company->postal_code ?? '',
            'country' => $company->country ?? 'US',
            'primary_color' => $company->primary_color ?: '#2563eb',
            'default_commission_rate' => (float) ($company->default_commission_rate ?? 0),
            'default_commission_type' => $company->default_commission_type ?: 'percentage',
        ];
    }

    private function presentReceipts(Company $company): array
    {
        return [
            'invoice_prefix' => $company->invoice_prefix ?? '',
            'quotation_prefix' => $company->quotation_prefix ?? '',
            'invoice_terms' => $company->invoice_terms ?? '',
            'quote_terms' => $company->quote_terms ?? '',
            'bank_details' => $company->bank_details ?? '',
        ];
    }

    private function presentFinancial(Company $company): array
    {
        return [
            'currency' => $company->currency ?? 'USD',
            'currency_symbol' => $company->currency_symbol ?: '$',
            'currency_decimals' => (int) ($company->currency_decimals ?? 2),
            'currency_symbol_position' => $company->currency_symbol_position ?: 'prefix',
            'other_currencies' => $company->other_currencies ?: [],
        ];
    }

    private function presentNotifications(Company $company, array $configs): array
    {
        return [
            'smtp' => [
                'host' => $configs['smtp_host'] ?? '',
                'port' => ! empty($configs['smtp_port']) ? (int) $configs['smtp_port'] : 587,
                'username' => $configs['smtp_username'] ?? '',
                'encryption' => $configs['smtp_encryption'] ?? 'tls',
                'from_address' => $configs['smtp_from_address'] ?? ($company->email ?? ''),
                'from_name' => $configs['smtp_from_name'] ?? $company->name,
                'has_password' => ! empty($configs['smtp_password']),
            ],
            'whatsapp' => [
                'phone_prefix' => $configs['whatsapp_phone_prefix'] ?? '',
                'custom_note' => $configs['whatsapp_custom_note'] ?? '',
                'phone_number_id' => $configs['whatsapp_phone_number_id'] ?? '',
                'has_api_token' => ! empty($configs['whatsapp_api_token']),
            ],
        ];
    }

    private function paymentMethods(Company $company, bool $includeInactive = false): array
    {
        $query = PaymentMethod::where('company_id', $company->id)->orderBy('order_index');
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->get()->map(fn (PaymentMethod $pm) => $this->presentPaymentMethod($pm))->all();
    }

    private function presentPaymentMethod(PaymentMethod $pm): array
    {
        return [
            'id' => (string) $pm->id,
            'name' => $pm->name,
            'code' => $pm->code ?? '',
            'description' => $pm->description ?? '',
            'is_active' => (bool) $pm->is_active,
            'order_index' => (int) $pm->order_index,
        ];
    }
}
