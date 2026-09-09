<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\CustomNotificationChannel;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Navigation\MenuService;
use App\Services\Notifications\CustomChannelDispatcherService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile REST surface for the tenant-owned Profile, Receipts, Financial and
 * Payment Methods sections. Push and delivery gateway credentials are
 * deliberately excluded: they are managed only by SuperAdmin.
 */
class SettingsApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'success' => true,
            'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
            'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
            'profile' => $this->presentProfile($company),
            'branding' => $company->getThemeTokens(),
            'receipts' => $this->presentReceipts($company),
            'financial' => $this->presentFinancial($company),
            'payment_methods' => $this->paymentMethods($company),
            // Read-only mirror of AppBootstrapController::bootstrap()'s `nav`
            // block, for Settings > Navigation Menu to render its current
            // state without a second call — saved back through
            // AppBootstrapController::updateNav().
            'nav' => $company->normalizedNavConfig(),
            // Full IANA identifier list for the Timezone & Regional Settings
            // manual-override dropdown — served from the backend so the app
            // doesn't bundle/maintain its own copy of the tzdata identifier
            // list (only the identifiers themselves; DST/offset math is
            // still done on-device by the `timezone` package).
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        // An empty string means "clear the manual override, go back to the
        // country default" — not "invalid timezone".
        if ($request->input('timezone') === '') {
            $request->merge(['timezone' => null]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
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
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'language' => ['nullable', 'string', 'max:10'],
            'default_locale' => ['nullable', 'string', 'max:10'],
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
        if (isset($data['default_locale'])) {
            $data['default_locale'] = strtolower(trim($data['default_locale']));
            if (! isset($data['language'])) {
                $data['language'] = $data['default_locale'];
            }
        } elseif (isset($data['language'])) {
            $data['language'] = strtolower(trim($data['language']));
            $data['default_locale'] = $data['language'];
        }

        if ($user && (! empty($data['default_locale']) || ! empty($data['language']))) {
            $user->update(['locale' => $data['default_locale'] ?? $data['language']]);
        }

        $company->update($data);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'profile']);

        return response()->json(['success' => true, 'message' => 'Profile saved.', 'profile' => $this->presentProfile($company->fresh())]);
    }

    public function updateBranding(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $validator = Validator::make($request->all(), [
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'drawer_bg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'drawer_gradient_enabled' => ['required', 'boolean'],
            'drawer_gradient_start' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'drawer_gradient_end' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'drawer_gradient_direction' => ['required', 'string', 'in:top_to_bottom,diagonal,radial'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $company->update($validator->validated());
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'branding']);
        $theme = $company->fresh()->getThemeTokens();

        return response()->json([
            'success' => true,
            'message' => 'Branding and colors updated successfully.',
            'theme' => $theme,
        ]);
    }

    /**
     * POST /api/v1/pos/settings/profile/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $path = $request->file('logo')->store('tenant-logos', 'public');
        $company->update(['logo' => Storage::url($path)]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'logo']);

        return response()->json(['success' => true, 'message' => 'Logo uploaded.', 'logo_url' => $company->fresh()->getLogoUrl()]);
    }

    /**
     * DELETE /api/v1/pos/settings/profile/logo
     */
    public function removeLogo(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($company->logo && ! filter_var($company->logo, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $company->logo);
            Storage::disk('public')->delete($cleanPath);
        }

        $company->update(['logo' => null]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'logo_removed']);

        return response()->json(['success' => true, 'message' => 'Logo removed.']);
    }

    /**
     * POST /api/v1/pos/settings/profile/favicon
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'favicon' => ['required', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:1024'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $path = $request->file('favicon')->store('tenant-favicons', 'public');
        $company->update(['favicon' => Storage::url($path)]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'favicon']);

        return response()->json(['success' => true, 'message' => 'Favicon uploaded.', 'favicon_url' => $company->fresh()->getFaviconUrl()]);
    }

    /**
     * DELETE /api/v1/pos/settings/profile/favicon
     */
    public function removeFavicon(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($company->favicon && ! filter_var($company->favicon, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $company->favicon);
            Storage::disk('public')->delete($cleanPath);
        }

        $company->update(['favicon' => null]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'favicon_removed']);

        return response()->json(['success' => true, 'message' => 'Favicon removed.']);
    }

    /**
     * POST /api/v1/pos/settings/profile/drawer-cover
     */
    public function uploadDrawerCover(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'drawer_cover' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $path = $request->file('drawer_cover')->store('tenant-drawer-covers', 'public');
        $company->update(['drawer_cover' => Storage::url($path)]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'drawer_cover']);

        return response()->json(['success' => true, 'message' => 'Drawer cover uploaded.', 'drawer_cover_url' => $company->fresh()->getDrawerCoverUrl()]);
    }

    /**
     * DELETE /api/v1/pos/settings/profile/drawer-cover
     */
    public function removeDrawerCover(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($company->drawer_cover && ! filter_var($company->drawer_cover, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $company->drawer_cover);
            Storage::disk('public')->delete($cleanPath);
        }

        $company->update(['drawer_cover' => null]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'drawer_cover_removed']);

        return response()->json(['success' => true, 'message' => 'Drawer cover removed.']);
    }

    public function updateReceipts(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quotation_prefix' => ['nullable', 'string', 'max:20'],
            'repair_prefix' => ['nullable', 'string', 'max:20'],
            'prescription_prefix' => ['nullable', 'string', 'max:20'],
            'salon_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_terms' => ['nullable', 'string', 'max:4000'],
            'quote_terms' => ['nullable', 'string', 'max:4000'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
            'dispensing_disclaimer' => ['nullable', 'string', 'max:4000'],
            'repair_warranty_terms' => ['nullable', 'string', 'max:4000'],
            'salon_policy_terms' => ['nullable', 'string', 'max:4000'],
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

    /**
     * Save the tenant's custom repair intake checklist.
     * POST /api/tenant/settings/repair-checklist
     *
     * Accepts either a structured `checklist_schema` [{key,label,default}, ...]
     * or a newline `checklist_labels` blob ("Label | default" per line), or
     * `reset: true` to fall back to the built-in defaults.
     */
    public function updateRepairChecklist(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($request->boolean('reset')) {
            $company->update(['repair_checklist_schema' => null]);
            AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'repair_checklist', 'reset' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Checklist reset to defaults.',
                'checklist' => $company->fresh()->repairChecklistSchema(),
            ]);
        }

        $items = [];

        if ($request->filled('checklist_schema') && is_array($request->input('checklist_schema'))) {
            $items = $request->input('checklist_schema');
        } elseif ($request->filled('checklist_labels')) {
            foreach (preg_split('/\R/', (string) $request->input('checklist_labels')) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line, 2));
                $items[] = ['label' => $parts[0], 'default' => $parts[1] ?? 'pass'];
            }
        }

        if ($items === []) {
            return response()->json([
                'success' => false,
                'error' => 'Add at least one checkpoint, or use Reset to Defaults.',
            ], 422);
        }
        if (count($items) > 40) {
            return response()->json(['success' => false, 'error' => 'A checklist may have at most 40 checkpoints.'], 422);
        }

        // Normalise through the model helper (fills keys, clamps defaults).
        $company->repair_checklist_schema = $items;
        $normalized = $company->repairChecklistSchema();
        $company->repair_checklist_schema = $normalized;
        $company->save();

        AuditLog::record('company.settings_updated', $company->id, $user?->id, [
            'section' => 'repair_checklist', 'count' => count($normalized),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Repair checklist saved.',
            'checklist' => $normalized,
        ]);
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
        if (empty($data['currency_symbol'])) {
            $currDetails = PlatformRegionalService::getCurrencyDetails($data['currency']);
            $data['currency_symbol'] = $currDetails['symbol'];
            if (! isset($data['currency_decimals'])) {
                $data['currency_decimals'] = $currDetails['decimals'];
            }
            if (! isset($data['currency_symbol_position'])) {
                $data['currency_symbol_position'] = $currDetails['position'];
            }
        }
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
            'restaurant_alert_interval_minutes' => ['nullable', 'integer', 'in:2,3,5'],
            'restaurant_alert_sound_preset' => ['nullable', 'string', 'in:chime,bell,alert'],
            'restaurant_alert_sound_url' => ['nullable', 'string', 'max:2000', 'url'],
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
            'restaurant_alert_interval_minutes', 'restaurant_alert_sound_preset', 'restaurant_alert_sound_url',
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

    /**
     * SDUI bottom-sheet schema for editing one payment method, opened by the
     * "Edit" button on SchemaResponse::paymentMethodsView. Submits back to the
     * same savePaymentMethod() path as create.
     */
    public function paymentMethodsEditSheet(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $pm = PaymentMethod::where('company_id', $company->id)->find($id);

        if (! $pm) {
            return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
        }

        $meta = $pm->metadata ?? [];

        $sheet = SchemaResponse::screen("Edit {$pm->name}", [
            SchemaResponse::card([
                SchemaResponse::text('Edit Payment Method', 'title_medium', ['bold' => true]),
                SchemaResponse::text('Changes apply immediately at every module checkout.', 'body_small', ['color' => '#64748b']),
                SchemaResponse::divider(),
                SchemaResponse::textInput('name', 'Display Name', $pm->name),
                SchemaResponse::textInput('code', 'Short Code', (string) ($pm->code ?? '')),
                SchemaResponse::textInput('description', 'Description', (string) ($pm->description ?? ''), ['max_lines' => 2]),
                SchemaResponse::textInput('order_index', 'Display Order', (string) $pm->order_index, ['keyboard_type' => 'number']),
                SchemaResponse::toggleSwitch('is_active', 'Active (show at checkout)', (bool) $pm->is_active),
                SchemaResponse::divider(),
                SchemaResponse::textInput('metadata[bank_name]', 'Bank Name', (string) ($meta['bank_name'] ?? '')),
                SchemaResponse::textInput('metadata[account_no]', 'Account Number', (string) ($meta['account_no'] ?? '')),
                SchemaResponse::textInput('metadata[ifsc_code]', 'IFSC / SWIFT Code', (string) ($meta['ifsc_code'] ?? '')),
                SchemaResponse::textInput('metadata[upi_id]', 'UPI ID / VPA', (string) ($meta['upi_id'] ?? '')),
                SchemaResponse::textInput('metadata[holder_name]', 'Account Holder Name', (string) ($meta['holder_name'] ?? '')),
                SchemaResponse::buttonPrimary('Save Changes', SchemaResponse::formSubmitAction(
                    "/api/tenant/settings/payment-methods/{$pm->id}",
                    'POST',
                    'Payment method updated.',
                    navigateBack: true,
                    reload: true
                ), 'save'),
            ]),
        ]);

        return response()->json($sheet);
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
            'metadata' => ['nullable', 'array'],
            'metadata.bank_name' => ['nullable', 'string', 'max:150'],
            'metadata.account_no' => ['nullable', 'string', 'max:60'],
            'metadata.ifsc_code' => ['nullable', 'string', 'max:20'],
            'metadata.upi_id' => ['nullable', 'string', 'max:100'],
            'metadata.holder_name' => ['nullable', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['code'] = ($data['code'] ?? null) ?: Str::slug($data['name'], '_');

        // The SDUI form always submits every metadata[...] field, so drop the
        // blank ones instead of persisting a bag of empty strings. An entirely
        // empty bag becomes null.
        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = array_filter(
                (array) $data['metadata'],
                static fn ($v) => $v !== null && $v !== ''
            ) ?: null;
        }

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
            'language' => $company->language ?? 'en',
            'default_locale' => $company->default_locale ?: ($company->language ?: 'en'),
            // 'timezone' is the raw manual override (empty = none set, i.e.
            // following the country default); 'resolved_timezone' is what
            // order times/prep timers/KOT logs should actually be shown in
            // — always a valid IANA id, never empty. See
            // Company::resolveTimezone().
            'timezone' => $company->timezone ?? '',
            'resolved_timezone' => $company->resolveTimezone(),
            'default_timezone_for_country' => Company::defaultTimezoneForCountry($company->country),
            'logo_url' => $company->getLogoUrl(),
            'favicon_url' => $company->getFaviconUrl(),
            'drawer_cover_url' => $company->getDrawerCoverUrl(),
            'default_commission_rate' => (float) ($company->default_commission_rate ?? 0),
            'default_commission_type' => $company->default_commission_type ?: 'percentage',
        ];
    }

    private function presentReceipts(Company $company): array
    {
        return [
            'invoice_prefix' => $company->invoice_prefix ?? '',
            'quotation_prefix' => $company->quotation_prefix ?? '',
            'repair_prefix' => $company->repair_prefix ?: 'REP-',
            'prescription_prefix' => $company->prescription_prefix ?: 'RX-',
            'salon_prefix' => $company->salon_prefix ?: 'SAL-',
            'invoice_terms' => $company->invoice_terms ?? '',
            'quote_terms' => $company->quote_terms ?? '',
            'bank_details' => $company->bank_details ?? '',
            'dispensing_disclaimer' => $company->dispensing_disclaimer ?? '',
            'repair_warranty_terms' => $company->repair_warranty_terms ?? '',
            'salon_policy_terms' => $company->salon_policy_terms ?? '',
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
            'restaurant_alerts' => [
                'interval_minutes' => ! empty($configs['restaurant_alert_interval_minutes']) ? (int) $configs['restaurant_alert_interval_minutes'] : 3,
                'sound_preset' => $configs['restaurant_alert_sound_preset'] ?? 'chime',
                'sound_url' => $configs['restaurant_alert_sound_url'] ?? '',
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
            'metadata' => $pm->metadata ?: (object) [],
        ];
    }

    /**
     * Per-method transaction history (Settings > Payment Methods > [method]
     * > ledger). Matched by the free-string order_payments.payment_method
     * against this method's code/name — order_payments has no FK to
     * payment_methods (see PaymentMethod model docs).
     */
    public function paymentMethodTransactions(Request $request): JsonResponse
    {
        [$company, $pm, $rows] = $this->paymentMethodLedgerRows($request);
        if (! $pm) {
            return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'payment_method' => $this->presentPaymentMethod($pm),
            'transactions' => $rows->map(fn ($row) => $this->presentLedgerRow($row))->values(),
        ]);
    }

    public function paymentMethodTransactionsExport(Request $request)
    {
        [$company, $pm, $rows] = $this->paymentMethodLedgerRows($request);
        if (! $pm) {
            return response()->json(['success' => false, 'error' => 'Payment method not found.'], 404);
        }

        $lines = [['Date', 'Time', 'Order ID', 'Payment Method', 'Customer', 'Amount', 'Reference No', 'Status']];
        foreach ($rows as $row) {
            $data = $this->presentLedgerRow($row);
            $lines[] = [
                $data['date'], $data['time'], $data['order_id'], $data['payment_method'],
                $data['customer'], $data['amount'], $data['reference_no'], $data['status'],
            ];
        }

        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $line))."\r\n";
        }

        $filename = 'payment-method-'.($pm->code ?: $pm->id).'-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function paymentMethodLedgerRows(Request $request): array
    {
        $company = $this->resolveCompany($request);
        $pm = PaymentMethod::where('company_id', $company->id)->find($request->route('id'));
        if (! $pm) {
            return [$company, null, collect()];
        }

        $matches = array_values(array_unique(array_filter([$pm->code, $pm->name])));

        $query = OrderPayment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('sale.customer')
            ->whereIn('payment_method', $matches);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        return [$company, $pm, $query->orderByDesc('created_at')->get()];
    }

    private function presentLedgerRow(OrderPayment $payment): array
    {
        $sale = $payment->sale;

        return [
            'date' => $payment->created_at?->format('Y-m-d'),
            'time' => $payment->created_at?->format('H:i:s'),
            'order_id' => $sale?->sale_number ?? (string) $payment->sale_id,
            'payment_method' => $payment->payment_method,
            'customer' => $sale?->customer?->name ?? $sale?->customer_name ?? '',
            'amount' => (float) $payment->amount,
            'reference_no' => $payment->reference_number ?? '',
            'status' => $sale?->payment_status ?? '',
        ];
    }

    // ---- Custom Notification Channels ----

    public function notificationChannelsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $channels = CustomNotificationChannel::where('company_id', $company->id)->orderBy('name')->get();

        return response()->json(['success' => true, 'channels' => $channels->map(fn ($c) => $this->presentChannel($c))->all()]);
    }

    public function notificationChannelsStore(Request $request): JsonResponse
    {
        return $this->saveNotificationChannel($request);
    }

    public function notificationChannelsUpdate(Request $request, string $id): JsonResponse
    {
        return $this->saveNotificationChannel($request, $id);
    }

    public function notificationChannelsDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $channel = CustomNotificationChannel::where('company_id', $company->id)->find($id);
        if (! $channel) {
            return response()->json(['success' => false, 'error' => 'Notification channel not found.'], 404);
        }
        $channel->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    private function saveNotificationChannel(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500', 'url'],
            'method' => ['nullable', 'string', 'in:POST,GET'],
            'payload_format' => ['nullable', 'string', 'in:json,form_data,query_params'],
            'headers' => ['nullable', 'array'],
            'auth_type' => ['nullable', 'string', 'in:none,bearer,api_key'],
            'auth_value' => ['nullable', 'string', 'max:1000'],
            'payload_template' => ['nullable', 'string', 'max:5000'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['string', 'in:invoice,quotation,due_reminder,due_invoice_reminder,delayed_order_alert'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['icon'] = $data['icon'] ?? 'webhook';
        $data['method'] = $data['method'] ?? 'POST';
        $data['payload_format'] = $data['payload_format'] ?? 'json';
        $data['auth_type'] = $data['auth_type'] ?? 'none';
        $data['is_active'] = $data['is_active'] ?? true;

        if ($id !== null) {
            $channel = CustomNotificationChannel::where('company_id', $company->id)->find($id);
            if (! $channel) {
                return response()->json(['success' => false, 'error' => 'Notification channel not found.'], 404);
            }
            $channel->update($data);
        } else {
            $channel = CustomNotificationChannel::create(array_merge($data, ['company_id' => $company->id]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'channel' => $this->presentChannel($channel->fresh()),
        ], $id === null ? 201 : 200);
    }

    public function testNotificationChannel(
        Request $request,
        CustomChannelDispatcherService $dispatcher
    ): JsonResponse {
        $company = $this->resolveCompany($request);

        $channelId = $request->input('channel_id') ?? $request->input('id');
        $channel = null;

        if (! empty($channelId)) {
            $channel = CustomNotificationChannel::where('company_id', $company->id)->find($channelId);
            if (! $channel) {
                return response()->json(['success' => false, 'error' => 'Notification channel not found.'], 404);
            }
        } else {
            $validator = Validator::make($request->all(), [
                'url' => ['required', 'string', 'url'],
                'method' => ['nullable', 'string', 'in:POST,GET'],
                'payload_format' => ['nullable', 'string', 'in:json,form_data,query_params'],
                'headers' => ['nullable', 'array'],
                'auth_type' => ['nullable', 'string', 'in:none,bearer,api_key'],
                'auth_value' => ['nullable', 'string', 'max:1000'],
                'payload_template' => ['nullable', 'string', 'max:5000'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation error.',
                    'details' => $validator->errors(),
                ], 422);
            }

            $channel = new CustomNotificationChannel([
                'company_id' => $company->id,
                'name' => 'Test Channel',
                'url' => $request->input('url'),
                'method' => $request->input('method', 'POST'),
                'payload_format' => $request->input('payload_format', 'json'),
                'headers' => $request->input('headers'),
                'auth_type' => $request->input('auth_type', 'none'),
                'auth_value' => $request->input('auth_value'),
                'payload_template' => $request->input('payload_template'),
                'is_active' => true,
            ]);
        }

        $variables = [
            'phone' => $request->input('phone', $company->phone ?: '+1234567890'),
            'customer_name' => $request->input('customer_name', 'Test Customer'),
            'invoice_id' => $request->input('invoice_id', 'INV-TEST-'.rand(100, 999)),
            'amount' => $request->input('amount', '99.99'),
            'order_link' => $request->input('order_link', url('/invoices/test')),
            'date' => date('Y-m-d'),
        ];

        $result = $dispatcher->dispatch($channel, $variables);

        return response()->json([
            'success' => $result['success'],
            'status_code' => $result['status_code'],
            'response' => $result['response'],
            'error' => $result['error'],
            'message' => $result['success'] ? 'Test message sent successfully.' : 'Test message dispatch failed: '.$result['error'],
        ], $result['success'] ? 200 : 422);
    }

    private function presentChannel(CustomNotificationChannel $channel): array
    {
        return [
            'id' => (string) $channel->id,
            'name' => $channel->name,
            'icon' => $channel->icon ?: 'webhook',
            'icon_display' => $channel->iconDisplay(),
            'icon_is_url' => $channel->isIconUrl(),
            'url' => $channel->url,
            'method' => $channel->method,
            'payload_format' => $channel->payload_format ?: 'json',
            'headers' => $channel->headers ?: (object) [],
            'auth_type' => $channel->auth_type,
            'has_auth_value' => filled($channel->auth_value),
            'payload_template' => $channel->payload_template ?? '',
            'event_types' => $channel->event_types ?: [],
            'is_active' => (bool) $channel->is_active,
        ];
    }

    public function getNavigationLabels(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'success' => true,
            'navigation_labels' => $company->navigation_labels ?? (object) [],
        ]);
    }

    public function updateNavigationLabels(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $labels = $request->input('navigation_labels') ?? $request->input('labels') ?? $request->all();
        if (! is_array($labels)) {
            $labels = [];
        }

        $sanitized = [];
        foreach ($labels as $k => $v) {
            if (is_string($k) && ! in_array($k, ['_token', 'api_key', 'token'], true)) {
                $sanitized[trim($k)] = is_string($v) ? trim($v) : (string) $v;
            }
        }

        $company->update(['navigation_labels' => $sanitized]);

        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'navigation_labels']);

        return response()->json([
            'success' => true,
            'message' => 'Navigation labels updated successfully.',
            'navigation_labels' => $company->fresh()->navigation_labels ?? (object) [],
        ]);
    }

    public function getDrawerNavigation(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $sections = MenuService::getDrawerTree($company, $user);

        return response()->json([
            'success' => true,
            'sections' => $sections,
            'navigation' => $sections,
        ]);
    }

    public function getFormLabels(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $form = $request->query('form') ?: $request->query('form_key');

        if ($form) {
            return response()->json([
                'success' => true,
                'form' => $form,
                'labels' => $company->getFormFieldLabels($form),
                'form_field_customizations' => $company->form_field_customizations ?? (object) [],
            ]);
        }

        return response()->json([
            'success' => true,
            'form_field_customizations' => $company->form_field_customizations ?? (object) [],
        ]);
    }

    public function updateFormLabels(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $form = $request->input('form') ?: $request->input('form_key') ?: 'service_booking';
        $incomingLabels = $request->input('labels');
        $currentCustomizations = $company->form_field_customizations ?? [];
        if (! is_array($currentCustomizations)) {
            $currentCustomizations = [];
        }

        if (is_array($incomingLabels)) {
            $currentCustomizations[$form] = array_merge(
                (array) ($currentCustomizations[$form] ?? []),
                $incomingLabels
            );
        } elseif ($request->has('form_field_customizations') && is_array($request->input('form_field_customizations'))) {
            $currentCustomizations = array_merge($currentCustomizations, $request->input('form_field_customizations'));
        } else {
            $hasNested = false;
            foreach ($request->except(['_token', 'api_key', 'token']) as $k => $v) {
                if (is_array($v) && in_array($k, ['service_booking', 'repair_intake', 'customer', 'invoice', 'checkout'], true)) {
                    $currentCustomizations[$k] = array_merge((array) ($currentCustomizations[$k] ?? []), $v);
                    $hasNested = true;
                }
            }
            if (! $hasNested) {
                $sanitized = [];
                foreach ($request->except(['_token', 'api_key', 'token', 'form', 'form_key']) as $k => $v) {
                    if (is_string($k)) {
                        $sanitized[trim($k)] = $v;
                    }
                }
                $currentCustomizations[$form] = array_merge(
                    (array) ($currentCustomizations[$form] ?? []),
                    $sanitized
                );
            }
        }

        $company->update(['form_field_customizations' => $currentCustomizations]);
        AuditLog::record('company.settings_updated', $company->id, $user?->id, ['section' => 'form_labels', 'form' => $form]);

        return response()->json([
            'success' => true,
            'message' => 'Form labels updated successfully.',
            'form' => $form,
            'labels' => $company->fresh()->getFormFieldLabels($form),
            'form_field_customizations' => $company->fresh()->form_field_customizations ?? (object) [],
        ]);
    }
}
